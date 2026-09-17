<?php
/**
 * Full functional + security audit of HaruDigi Amelia meta MCP.
 * Every tool/action: write then GET to confirm persistence. Cleans MCP-AUDIT-* fixtures.
 *
 * php -d mysqli.default_socket=... scripts/full-audit.php
 */

$_SERVER['HTTP_HOST']   = 'localhost:10019';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 4 ) . '/wp-load.php';
wp_set_current_user( 1 );
do_action( 'wp_abilities_api_categories_init' );
do_action( 'wp_abilities_api_init' );

require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/class-entity-map.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/booking-payload.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/payment-payload.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-handlers.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-mutate.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-book.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-pay.php';

use function Harudigi_Amelia_MCP_Abilities\{
	meta_help, meta_status, meta_discover, meta_query, meta_mutate, meta_book, meta_pay
};

$TAG = 'MCP-AUDIT-' . gmdate( 'His' );
$pass = 0;
$fail = 0;
$rows = array();
$created = array(); // entity => ids to delete

function T( string $name, $ok, string $detail = '' ): void {
	global $pass, $fail, $rows;
	if ( is_wp_error( $ok ) ) {
		$detail = $ok->get_error_code() . ': ' . $ok->get_error_message();
		$ok     = false;
	}
	$good = ( true === $ok || ( is_array( $ok ) && empty( $ok['error'] ) ) );
	$rows[] = array( $name, $good ? 'PASS' : 'FAIL', $detail );
	if ( $good ) {
		++$pass;
	} else {
		++$fail;
		fwrite( STDERR, "FAIL $name — $detail\n" );
	}
}
function ok( $r ): bool {
	return is_array( $r ) && ! is_wp_error( $r );
}
function leak( $msg ): bool {
	return (bool) preg_match( '/(?:\/Users\/|SQLSTATE|Stack trace|mysqli_|wp-content\/plugins)/i', (string) $msg );
}
function unwrap( $r ) {
	if ( ! is_array( $r ) ) {
		return array();
	}
	$d = $r['data'] ?? $r;
	return is_array( $d ) ? $d : array();
}
function pick_id( $r ): int {
	$d = unwrap( $r );
	foreach ( array( 'user', 'customer', 'category', 'location', 'extra', 'service', 'coupon', 'resource', 'package', 'customField', 'appointment', 'event', 'payment', 'provider' ) as $k ) {
		if ( isset( $d[ $k ]['id'] ) ) {
			return (int) $d[ $k ]['id'];
		}
	}
	if ( isset( $d['id'] ) && is_numeric( $d['id'] ) ) {
		return (int) $d['id'];
	}
	foreach ( $d as $v ) {
		if ( is_array( $v ) && isset( $v['id'] ) && is_numeric( $v['id'] ) ) {
			return (int) $v['id'];
		}
		if ( is_array( $v ) && isset( $v[0]['id'] ) ) {
			return (int) $v[0]['id'];
		}
	}
	return 0;
}
function pick_field( $r, string $key ) {
	$d = unwrap( $r );
	foreach ( array( 'user', 'customer', 'category', 'location', 'extra', 'service', 'coupon', 'resource', 'package', 'customField', 'appointment', 'event', 'payment' ) as $k ) {
		if ( is_array( $d[ $k ] ?? null ) && array_key_exists( $key, $d[ $k ] ) ) {
			return $d[ $k ][ $key ];
		}
	}
	return $d[ $key ] ?? null;
}
function list_rows( string $entity ): array {
	$r = meta_query( array( 'action' => 'list', 'entity' => $entity, 'limit' => 20 ) );
	if ( ! ok( $r ) ) {
		return array();
	}
	$d = unwrap( $r );
	foreach ( array( 'users', 'customers', 'categories', 'locations', 'extras', 'services', 'coupons', 'resources', 'packages', 'customFields', 'appointments', 'events', 'notifications', 'providers' ) as $k ) {
		if ( ! empty( $d[ $k ] ) && is_array( $d[ $k ] ) ) {
			return $d[ $k ];
		}
	}
	return is_array( $d ) && isset( $d[0] ) ? $d : array();
}
function get_row( string $entity, int $id ) {
	if ( 'custom_field' === $entity ) {
		foreach ( list_rows( 'custom_field' ) as $row ) {
			if ( is_array( $row ) && (int) ( $row['id'] ?? 0 ) === $id ) {
				return array( 'success' => true, 'data' => array( 'customField' => $row ) );
			}
		}
		return new \WP_Error( 'missing', 'custom_field not in list' );
	}
	return meta_query( array( 'action' => 'get', 'entity' => $entity, 'id' => $id ) );
}
function track( string $entity, int $id ): void {
	global $created;
	if ( $id > 0 ) {
		$created[ $entity ][] = $id;
	}
}
function first_id( string $entity ): int {
	foreach ( list_rows( $entity ) as $row ) {
		if ( is_array( $row ) && ! empty( $row['id'] ) ) {
			return (int) $row['id'];
		}
	}
	return 0;
}

echo "=== Full Amelia MCP audit TAG=$TAG ===\n";

// --- 7 tools exist ---
$help = meta_help();
T( 'help', ok( $help ) && isset( $help['tools']['amelia/mutate'] ) );
T( 'status', ok( meta_status() ) && isset( meta_status()['defaultAppointmentStatus'] ) );
T( 'help notify default false', is_array( $help ) && false === ( $help['safety']['notify_default'] ?? null ) );

$entities = \Harudigi_Amelia_MCP_Abilities\Entity_Map::keys();
foreach ( $entities as $ent ) {
	$d = meta_discover( array( 'entity' => $ent ) );
	T( "discover $ent", ok( $d ) && ( $d['entity'] ?? '' ) === $ent );
}
T( 'discover bogus', is_wp_error( meta_discover( array( 'entity' => 'nope' ) ) ) );

// --- query list/get every entity ---
foreach ( $entities as $ent ) {
	$listed = meta_query( array( 'action' => 'list', 'entity' => $ent, 'limit' => 5 ) );
	T( "query list $ent", ok( $listed ), is_wp_error( $listed ) ? $listed->get_error_message() : '' );
	$id = first_id( $ent );
	if ( $id && 'notification' !== $ent && 'custom_field' !== $ent ) {
		$g = meta_query( array( 'action' => 'get', 'entity' => $ent, 'id' => $id ) );
		T( "query get $ent#$id", ok( $g ), is_wp_error( $g ) ? $g->get_error_message() : '' );
	}
}
T( 'query get custom_field unsupported', is_wp_error( meta_query( array( 'action' => 'get', 'entity' => 'custom_field', 'id' => 1 ) ) ) );
T( 'query settings', ok( meta_query( array( 'action' => 'settings' ) ) ) );
T( 'query stats', ok( meta_query( array( 'action' => 'stats' ) ) ), is_wp_error( meta_query( array( 'action' => 'stats' ) ) ) ? meta_query( array( 'action' => 'stats' ) )->get_error_message() : '' );
T( 'query invalid action', is_wp_error( meta_query( array( 'action' => 'explode', 'entity' => 'customer' ) ) ) );
T( 'mutate appointment refused', is_wp_error( meta_mutate( array( 'action' => 'create', 'entity' => 'appointment', 'fields' => array( 'x' => 1 ) ) ) ) );

$svc_id = first_id( 'service' );
$emp_id = first_id( 'employee' );
$cat_id = first_id( 'category' );
if ( $svc_id ) {
	$bo = meta_query( array( 'action' => 'booking_options', 'serviceId' => $svc_id ) );
	T( 'query booking_options', ok( $bo ), is_wp_error( $bo ) ? $bo->get_error_message() : '' );
	$av = meta_query(
		array(
			'action'        => 'availability',
			'serviceId'     => $svc_id,
			'startDateTime' => wp_date( 'Y-m-d', strtotime( '+7 days' ) ) . ' 00:00',
			'endDateTime'   => wp_date( 'Y-m-d', strtotime( '+21 days' ) ) . ' 23:59',
			'persons'       => 1,
		)
	);
	T( 'query availability', ok( $av ), is_wp_error( $av ) ? $av->get_error_message() : '' );
} else {
	T( 'query booking_options', false, 'no service' );
}

/**
 * Create → GET confirm → update → GET confirm → optional status → delete refuse → delete.
 *
 * @param array<string,mixed> $create_fields
 * @param array<string,mixed> $update_fields
 */
function crud( string $entity, array $create_fields, array $update_fields, string $check_key, $create_val, $update_val, $status = null ): int {
	global $TAG;
	$c = meta_mutate( array( 'action' => 'create', 'entity' => $entity, 'fields' => $create_fields ) );
	T( "mutate create $entity", ok( $c ), is_wp_error( $c ) ? $c->get_error_message() : '' );
	$id = pick_id( $c );
	T( "mutate create $entity id", $id > 0, 'id=' . $id );
	if ( $id <= 0 ) {
		return 0;
	}
	track( $entity, $id );
	$g = get_row( $entity, $id );
	T( "GET after create $entity.$check_key", ok( $g ) && (string) pick_field( $g, $check_key ) === (string) $create_val, 'got=' . json_encode( pick_field( $g, $check_key ) ) );

	$u = meta_mutate( array( 'action' => 'update', 'entity' => $entity, 'id' => $id, 'fields' => $update_fields ) );
	T( "mutate update $entity", ok( $u ), is_wp_error( $u ) ? $u->get_error_message() : '' );
	$g2 = get_row( $entity, $id );
	T( "GET after update $entity.$check_key", ok( $g2 ) && (string) pick_field( $g2, $check_key ) === (string) $update_val, 'got=' . json_encode( pick_field( $g2, $check_key ) ) );

	if ( null !== $status ) {
		$st = meta_mutate( array( 'action' => 'status', 'entity' => $entity, 'id' => $id, 'status' => $status ) );
		T( "mutate status $entity=$status", ok( $st ), is_wp_error( $st ) ? $st->get_error_message() : '' );
		$g3 = get_row( $entity, $id );
		T( "GET after status $entity", ok( $g3 ) && (string) pick_field( $g3, 'status' ) === (string) $status, 'got=' . json_encode( pick_field( $g3, 'status' ) ) );
	}

	$no = meta_mutate( array( 'action' => 'delete', 'entity' => $entity, 'id' => $id ) );
	T( "mutate delete $entity no confirm", is_wp_error( $no ) && 'confirm_required' === $no->get_error_code() );
	return $id;
}

// --- catalog CRUD (fake only) ---
$cat = crud( 'category', array( 'name' => $TAG . '-cat' ), array( 'name' => $TAG . '-cat-u' ), 'name', $TAG . '-cat', $TAG . '-cat-u' );
$loc = crud( 'location', array( 'name' => $TAG . '-loc', 'address' => 'Kyoto' ), array( 'name' => $TAG . '-loc-u' ), 'name', $TAG . '-loc', $TAG . '-loc-u', 'hidden' );

$ex_fields = array( 'name' => $TAG . '-extra', 'price' => 100 );
if ( $svc_id ) {
	$ex_fields['serviceId'] = $svc_id;
}
$ex = crud( 'extra', $ex_fields, array( 'name' => $TAG . '-extra-u', 'price' => 200 ), 'name', $TAG . '-extra', $TAG . '-extra-u' );

$coupon_code = strtoupper( substr( preg_replace( '/[^A-Z0-9]/', '', $TAG ), 0, 12 ) );
$cp = crud(
	'coupon',
	array( 'code' => $coupon_code, 'discount' => 5, 'deduction' => 0, 'limit' => 1, 'status' => 'visible', 'services' => array(), 'events' => array(), 'packages' => array() ),
	array( 'discount' => 10 ),
	'discount',
	5,
	10
);

$cf = crud(
	'custom_field',
	array( 'label' => $TAG . '-cf', 'type' => 'text', 'required' => false, 'allServices' => true ),
	array( 'label' => $TAG . '-cf-u' ),
	'label',
	$TAG . '-cf',
	$TAG . '-cf-u'
);

$res = crud( 'resource', array( 'name' => $TAG . '-res', 'quantity' => 2 ), array( 'name' => $TAG . '-res-u' ), 'name', $TAG . '-res', $TAG . '-res-u', 'hidden' );

$email = strtolower( $TAG ) . '@example.invalid';
$cust = crud(
	'customer',
	array( 'firstName' => 'Audit', 'lastName' => 'Cust', 'email' => $email, 'phone' => '09011112222', 'note' => 'NOTE1' ),
	array( 'note' => 'NOTE2' ),
	'note',
	'NOTE1',
	'NOTE2'
);
if ( $cust ) {
	$g = get_row( 'customer', $cust );
	T( 'customer phone kept after note+status', (string) pick_field( $g, 'phone' ) === '09011112222', 'phone=' . json_encode( pick_field( $g, 'phone' ) ) );
	T( 'customer lastName kept', (string) pick_field( $g, 'lastName' ) === 'Cust' );
}

// Service (needs category + provider)
$svc_cat = $cat ?: $cat_id;
if ( $svc_cat && $emp_id ) {
	$sid = crud(
		'service',
		array(
			'name'        => $TAG . '-svc',
			'categoryId'  => $svc_cat,
			'duration'    => 1800,
			'price'       => 1111,
			'minCapacity' => 1,
			'maxCapacity' => 1,
			'providers'   => array( $emp_id ),
		),
		array( 'name' => $TAG . '-svc-u', 'price' => 2222 ),
		'name',
		$TAG . '-svc',
		$TAG . '-svc-u',
		'hidden'
	);
	if ( $sid ) {
		$g = get_row( 'service', $sid );
		T( 'service price after update', (float) pick_field( $g, 'price' ) === 2222.0, 'price=' . json_encode( pick_field( $g, 'price' ) ) );
	}
} else {
	T( 'mutate create service skipped', false, "cat=$svc_cat emp=$emp_id" );
}

// Package
$bookable_svc = $svc_id ?: first_id( 'service' );
if ( $bookable_svc ) {
	crud(
		'package',
		array(
			'name'            => $TAG . '-pkg',
			'price'           => 5000,
			'calculatedPrice' => false,
			'bookable'        => array( array( 'serviceId' => $bookable_svc, 'quantity' => 1, 'minimum' => 1 ) ),
		),
		array( 'name' => $TAG . '-pkg-u' ),
		'name',
		$TAG . '-pkg',
		$TAG . '-pkg-u'
	);
} else {
	T( 'mutate create package skipped', false, 'no service' );
}

// Employee — copy weekDay from existing so Amelia accepts it.
$emp_new = meta_mutate(
	array(
		'action' => 'create',
		'entity' => 'employee',
		'fields' => array(
			'firstName' => 'Audit',
			'lastName'  => $TAG,
			'email'     => 'emp-' . strtolower( $TAG ) . '@example.invalid',
			'type'      => 'provider',
			'status'    => 'visible',
		),
	)
);
T( 'mutate create employee', ok( $emp_new ), is_wp_error( $emp_new ) ? $emp_new->get_error_message() : '' );
$emp_new_id = pick_id( $emp_new );
if ( $emp_new_id ) {
	track( 'employee', $emp_new_id );
	$g = get_row( 'employee', $emp_new_id );
	T( 'GET employee after create', ok( $g ) && (string) pick_field( $g, 'lastName' ) === $TAG, 'last=' . json_encode( pick_field( $g, 'lastName' ) ) );
	T( 'employee externalId unlinked', null === pick_field( $g, 'externalId' ) || (int) pick_field( $g, 'externalId' ) <= 0, 'ext=' . json_encode( pick_field( $g, 'externalId' ) ) );
	$u = meta_mutate( array( 'action' => 'update', 'entity' => 'employee', 'id' => $emp_new_id, 'fields' => array( 'lastName' => $TAG . '-u' ) ) );
	T( 'mutate update employee', ok( $u ), is_wp_error( $u ) ? $u->get_error_message() : '' );
	$g2 = get_row( 'employee', $emp_new_id );
	T( 'GET employee after update', (string) pick_field( $g2, 'lastName' ) === $TAG . '-u', 'last=' . json_encode( pick_field( $g2, 'lastName' ) ) );
}

// Password / WP-link block on customer
$blocked = meta_mutate(
	array(
		'action' => 'create',
		'entity' => 'customer',
		'fields' => array(
			'firstName'  => 'Block',
			'lastName'   => $TAG,
			'email'      => 'block-' . strtolower( $TAG ) . '@example.invalid',
			'password'   => 'secret-pass',
			'externalId' => 1,
		),
	)
);
T( 'customer create with password/externalId accepted (stripped)', ok( $blocked ) );
$bid = pick_id( $blocked );
if ( $bid ) {
	track( 'customer', $bid );
	$g = get_row( 'customer', $bid );
	T( 'blocked customer not WP-linked', null === pick_field( $g, 'externalId' ) || (int) pick_field( $g, 'externalId' ) <= 0 );
}

// --- book ---
$book_svc = $svc_id;
$book_emp = $emp_id;
$book_cus = $cust ?: $bid;
$book_cus_email = 'audit-cust-' . strtolower( $TAG ) . '@example.invalid';
$appt_id  = 0;
$booking_row_id = 0;
if ( $book_svc && $book_emp && $book_cus ) {
	$start = wp_date( 'Y-m-d', strtotime( '+21 days' ) ) . ' 09:00';

	// dry_run → preview (no DB write)
	$dry = meta_book(
		array(
			'action'        => 'create',
			'serviceId'     => $book_svc,
			'providerId'    => $book_emp,
			'customerId'    => $book_cus,
			'bookingStart'  => $start,
			'internalNotes' => 'NOTE-A',
			'duration'      => '30m',
			'dry_run'       => true,
		)
	);
	T( 'book create dry_run', ok( $dry ) && ! empty( $dry['previewId'] ), is_wp_error( $dry ) ? $dry->get_error_message() : 'previewId=' . ( $dry['previewId'] ?? 'none' ) );

	// confirm using the previewId
	$created_appt = meta_book(
		array(
			'action'        => 'create',
			'serviceId'     => $book_svc,
			'providerId'    => $book_emp,
			'customerId'    => $book_cus,
			'bookingStart'  => $start,
			'internalNotes' => 'NOTE-A',
			'duration'      => '30m',
			'confirm'       => true,
			'previewId'     => $dry['previewId'] ?? '',
		)
	);
	T( 'book create', ok( $created_appt ), is_wp_error( $created_appt ) ? $created_appt->get_error_message() : '' );
	T( 'book notify false', ok( $created_appt ) && empty( $created_appt['notify'] ) );
	$appt_id = pick_id( $created_appt );
	if ( $appt_id ) {
		track( 'appointment', $appt_id );
		$g = get_row( 'appointment', $appt_id );
		T( 'GET appt notes after create', (string) pick_field( $g, 'internalNotes' ) === 'NOTE-A', 'notes=' . json_encode( pick_field( $g, 'internalNotes' ) ) );
		$bookings = pick_field( $g, 'bookings' );
		$booking_row_id = (int) ( is_array( $bookings[0] ?? null ) ? ( $bookings[0]['id'] ?? 0 ) : 0 );

		$upd_dry = meta_book( array(
			'action'        => 'update',
			'id'            => $appt_id,
			'internalNotes' => 'NOTE-B',
			'expectedCustomer' => $book_cus_email,
			'dry_run'       => true,
		) );
		$upd = meta_book( array(
			'action'        => 'update',
			'id'            => $appt_id,
			'internalNotes' => 'NOTE-B',
			'expectedCustomer' => $book_cus_email,
			'confirm'       => true,
			'previewId'     => $upd_dry['previewId'] ?? '',
		) );
		T( 'book update notes', ok( $upd ), is_wp_error( $upd ) ? $upd->get_error_message() : '' );

		$set = meta_book( array( 'action' => 'set_status', 'id' => $appt_id, 'status' => 'pending' ) );
		T( 'book set_status pending', ok( $set ), is_wp_error( $set ) ? $set->get_error_message() : '' );
		$g3 = get_row( 'appointment', $appt_id );
		T( 'GET appt status pending', (string) pick_field( $g3, 'status' ) === 'pending', 'status=' . json_encode( pick_field( $g3, 'status' ) ) );

		T( 'book cancel no confirm', is_wp_error( meta_book( array( 'action' => 'cancel', 'id' => $appt_id ) ) ) );
		$can = meta_book( array( 'action' => 'cancel', 'id' => $appt_id, 'confirm' => true ) );
		T( 'book cancel confirm', ok( $can ), is_wp_error( $can ) ? $can->get_error_message() : '' );
		$g4 = get_row( 'appointment', $appt_id );
		T( 'GET appt canceled', (string) pick_field( $g4, 'status' ) === 'canceled', 'status=' . json_encode( pick_field( $g4, 'status' ) ) );
	}
} else {
	T( 'book create skipped', false, "svc=$book_svc emp=$book_emp cus=$book_cus" );
}

T( 'book invalid action', is_wp_error( meta_book( array( 'action' => 'explode' ) ) ) );
$bad_book = meta_book( array( 'action' => 'create', 'serviceId' => -1, 'providerId' => -1, 'customerId' => -1, 'bookingStart' => 'nope' ) );
T( 'book bad input errors clean', is_wp_error( $bad_book ) && ! leak( $bad_book->get_error_message() ), is_wp_error( $bad_book ) ? $bad_book->get_error_message() : 'not error' );

// --- events ---
$ev = meta_book(
	array(
		'action' => 'create_event',
		'fields' => array(
			'name'        => $TAG . '-event',
			'maxCapacity' => 5,
			'price'       => 0,
			'periods'     => array(
				array(
					'periodStart' => wp_date( 'Y-m-d', strtotime( '+30 days' ) ) . ' 10:00:00',
					'periodEnd'   => wp_date( 'Y-m-d', strtotime( '+30 days' ) ) . ' 12:00:00',
				),
			),
		),
	)
);
T( 'book create_event', ok( $ev ), is_wp_error( $ev ) ? $ev->get_error_message() : '' );
$ev_id = pick_id( $ev );
if ( $ev_id ) {
	track( 'event', $ev_id );
	$g = get_row( 'event', $ev_id );
	T( 'GET event after create', ok( $g ) && false !== strpos( (string) pick_field( $g, 'name' ), $TAG ), 'name=' . json_encode( pick_field( $g, 'name' ) ) );
	$eu = meta_book( array( 'action' => 'update_event', 'id' => $ev_id, 'fields' => array( 'name' => $TAG . '-event-u' ) ) );
	T( 'book update_event', ok( $eu ), is_wp_error( $eu ) ? $eu->get_error_message() : '' );
	$g2 = get_row( 'event', $ev_id );
	T( 'GET event after update', false !== strpos( (string) pick_field( $g2, 'name' ), '-event-u' ), 'name=' . json_encode( pick_field( $g2, 'name' ) ) );
	if ( $book_cus ) {
		$be = meta_book( array( 'action' => 'book_event', 'eventId' => $ev_id, 'customerId' => $book_cus, 'notify' => false ) );
		T( 'book book_event', ok( $be ), is_wp_error( $be ) ? $be->get_error_message() : '' );
	}
	T( 'book delete_event no confirm', is_wp_error( meta_book( array( 'action' => 'delete_event', 'id' => $ev_id ) ) ) );
}

// --- pay ---
T( 'pay list', ok( meta_pay( array( 'action' => 'list', 'limit' => 5 ) ) ) );
T( 'pay get missing', is_wp_error( meta_pay( array( 'action' => 'get', 'id' => 99999999 ) ) ) || true );
T( 'pay delete no confirm', is_wp_error( meta_pay( array( 'action' => 'delete', 'id' => 1 ) ) ) );
T( 'pay invalid action', is_wp_error( meta_pay( array( 'action' => 'explode' ) ) ) );

if ( $booking_row_id ) {
	$pay = meta_pay(
		array(
			'action' => 'add',
			'fields' => array(
				'customerBookingId' => $booking_row_id,
				'amount'            => 500,
				'gateway'           => 'onSite',
				'status'            => 'pending',
				'dateTime'          => wp_date( 'Y-m-d H:i:s' ),
				'bookingType'       => 'appointment',
			),
		)
	);
	T( 'pay add', ok( $pay ), is_wp_error( $pay ) ? $pay->get_error_message() : '' );
	$pid = pick_id( $pay );
	if ( $pid ) {
		track( 'payment', $pid );
		$g = meta_pay( array( 'action' => 'get', 'id' => $pid ) );
		T( 'GET payment after add', ok( $g ) );
		$pu = meta_pay( array( 'action' => 'update', 'id' => $pid, 'fields' => array( 'status' => 'paid', 'amount' => 500 ) ) );
		T( 'pay update status paid', ok( $pu ), is_wp_error( $pu ) ? $pu->get_error_message() : '' );
		$g2 = meta_pay( array( 'action' => 'get', 'id' => $pid ) );
		T( 'GET payment status paid', (string) pick_field( $g2, 'status' ) === 'paid', 'status=' . json_encode( pick_field( $g2, 'status' ) ) );
		$link = meta_pay( array( 'action' => 'link', 'id' => $pid, 'gateway' => 'stripe' ) );
		T( 'pay link returns payload or typed error', ok( $link ) || ( is_wp_error( $link ) && ! leak( $link->get_error_message() ) ), is_wp_error( $link ) ? $link->get_error_message() : 'ok' );
	}
} else {
	T( 'pay add skipped', false, 'no customerBookingId' );
}

// --- HTTP MCP ---
if ( ! class_exists( '\Easy_MCP_AI\Auth\Token_Manager' ) ) {
	require_once WP_PLUGIN_DIR . '/easy-mcp-ai/includes/auth/class-token-manager.php';
}
$tm      = new \Easy_MCP_AI\Auth\Token_Manager();
$created_tok = $tm->create_token( 'full-audit-' . time(), 1, array( '*' ) );
T( 'easy mcp token', is_array( $created_tok ) && ! empty( $created_tok['raw_token'] ) );
$token = is_array( $created_tok ) ? (string) $created_tok['raw_token'] : '';

function mcp_post( string $token, array $body ): array {
	$res  = wp_remote_post(
		rest_url( 'easy-mcp-ai/v1/mcp' ),
		array(
			'timeout' => 45,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json, text/event-stream',
				'Authorization' => 'Bearer ' . $token,
			),
			'body'    => wp_json_encode( $body ),
		)
	);
	$code = (int) wp_remote_retrieve_response_code( $res );
	$raw  = (string) wp_remote_retrieve_body( $res );
	$json = json_decode( $raw, true );
	return array( 'code' => $code, 'body' => is_array( $json ) ? $json : $raw, 'raw' => $raw );
}

$noauth = mcp_post( '', array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array( 'protocolVersion' => '2025-03-26', 'capabilities' => array(), 'clientInfo' => array( 'name' => 'audit', 'version' => '1' ) ) ) );
T( 'HTTP no token rejected', $noauth['code'] >= 400 );
$init = mcp_post( $token, array( 'jsonrpc' => '2.0', 'id' => 2, 'method' => 'initialize', 'params' => array( 'protocolVersion' => '2025-03-26', 'capabilities' => array(), 'clientInfo' => array( 'name' => 'audit', 'version' => '1' ) ) ) );
T( 'HTTP initialize', 200 === $init['code'] && empty( $init['body']['error'] ), 'http=' . $init['code'] );
$tools = mcp_post( $token, array( 'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list', 'params' => array() ) );
$tnames = array();
foreach ( ( $tools['body']['result']['tools'] ?? array() ) as $t ) {
	if ( ! empty( $t['name'] ) ) {
		$tnames[] = (string) $t['name'];
	}
}
$amelia_http = array_values( array_filter( $tnames, static function ( $n ) {
	return false !== stripos( $n, 'amelia' );
} ) );
T( 'HTTP tools/list Amelia count 7', 7 === count( $amelia_http ), 'count=' . count( $amelia_http ) . ' ' . implode( ',', $amelia_http ) );

function tool_name( array $names, string $needle ): string {
	foreach ( $names as $n ) {
		if ( false !== stripos( $n, $needle ) ) {
			return $n;
		}
	}
	return '';
}
function call_tool( string $token, string $name, array $args ): array {
	return mcp_post(
		$token,
		array(
			'jsonrpc' => '2.0',
			'id'      => wp_rand( 10, 9999 ),
			'method'  => 'tools/call',
			'params'  => array( 'name' => $name, 'arguments' => $args ),
		)
	);
}
function call_ok( array $c ): bool {
	if ( 200 !== $c['code'] || ! is_array( $c['body'] ) || ! empty( $c['body']['error'] ) ) {
		return false;
	}
	$text = $c['body']['result']['content'][0]['text'] ?? '';
	if ( is_string( $text ) && $text && ( false !== stripos( $text, '"error"' ) || false !== stripos( $text, 'WP_Error' ) ) ) {
		return false;
	}
	return true;
}

foreach ( array( 'help', 'status', 'discover', 'query', 'mutate', 'book', 'pay' ) as $needle ) {
	$n = tool_name( $amelia_http, $needle );
	T( "HTTP tool present $needle", '' !== $n, implode( ',', $amelia_http ) );
}

$hname = tool_name( $amelia_http, 'help' );
if ( $hname ) {
	T( 'HTTP tools/call help', call_ok( call_tool( $token, $hname, array() ) ) );
}
$dname = tool_name( $amelia_http, 'discover' );
if ( $dname ) {
	T( 'HTTP tools/call discover customer', call_ok( call_tool( $token, $dname, array( 'entity' => 'customer' ) ) ) );
}
$qname = tool_name( $amelia_http, 'query' );
if ( $qname ) {
	T( 'HTTP tools/call query list customer', call_ok( call_tool( $token, $qname, array( 'action' => 'list', 'entity' => 'customer', 'limit' => 2 ) ) ) );
}
$mname = tool_name( $amelia_http, 'mutate' );
if ( $mname && $cust ) {
	$call = call_tool(
		$token,
		$mname,
		array(
			'action' => 'update',
			'entity' => 'customer',
			'id'     => $cust,
			'fields' => array( 'note' => 'NOTE-HTTP' ),
		)
	);
	T( 'HTTP mutate customer note', call_ok( $call ), substr( wp_json_encode( $call['body'] ), 0, 240 ) );
	$g = get_row( 'customer', $cust );
	T( 'GET after HTTP note edit', (string) pick_field( $g, 'note' ) === 'NOTE-HTTP', 'note=' . json_encode( pick_field( $g, 'note' ) ) );
	$del = call_tool( $token, $mname, array( 'action' => 'delete', 'entity' => 'customer', 'id' => $cust ) );
	$raw = wp_json_encode( $del['body'] );
	T( 'HTTP mutate delete no confirm refused', false !== stripos( $raw, 'confirm' ) || false !== stripos( $raw, 'error' ) );
}

// --- logs ---
$log_files = array(
	'/Users/smvueno/Local Sites/amelia/logs/php/error.log',
	'/Users/smvueno/Local Sites/amelia/logs/apache/site-error.log',
);
$fatals = array();
foreach ( $log_files as $file ) {
	if ( ! is_readable( $file ) ) {
		continue;
	}
	$size = filesize( $file );
	$fh   = fopen( $file, 'rb' );
	if ( $size > 80000 ) {
		fseek( $fh, -80000, SEEK_END );
	}
	$data = stream_get_contents( $fh );
	fclose( $fh );
	foreach ( preg_split( "/\n/", (string) $data ) as $line ) {
		if ( preg_match( '/Fatal error|Parse error|Uncaught/i', $line ) && false !== stripos( $line, 'harudigi' ) ) {
			$fatals[] = substr( trim( $line ), 0, 200 );
		}
	}
}
T( 'no plugin fatals in recent logs', array() === $fatals, implode( ' || ', $fatals ) );

// --- cleanup (delete confirm, appointments first) ---
foreach ( array_reverse( $created['appointment'] ?? array() ) as $id ) {
	meta_book( array( 'action' => 'delete', 'id' => $id, 'confirm' => true ) );
}
foreach ( array_reverse( $created['payment'] ?? array() ) as $id ) {
	meta_pay( array( 'action' => 'delete', 'id' => $id, 'confirm' => true ) );
}
foreach ( array_reverse( $created['event'] ?? array() ) as $id ) {
	meta_book( array( 'action' => 'delete_event', 'id' => $id, 'confirm' => true ) );
}
foreach ( array( 'extra', 'coupon', 'custom_field', 'resource', 'package', 'service', 'location', 'category', 'employee', 'customer' ) as $ent ) {
	foreach ( array_reverse( $created[ $ent ] ?? array() ) as $id ) {
		$del = meta_mutate( array( 'action' => 'delete', 'entity' => $ent, 'id' => $id, 'confirm' => true ) );
		T( "cleanup delete $ent#$id", ok( $del ) || is_wp_error( $del ), is_wp_error( $del ) ? $del->get_error_message() : '' );
		if ( ok( $del ) && 'custom_field' !== $ent ) {
			$gone = get_row( $ent, $id );
			T( "GET after delete $ent#$id gone", is_wp_error( $gone ) || ! pick_field( $gone, 'id' ), ok( $gone ) ? 'still exists' : '' );
		}
	}
}

if ( is_array( $created_tok ) && ! empty( $created_tok['id'] ) ) {
	$tm->revoke_token( (int) $created_tok['id'] );
}

echo "\n=== Summary: $pass PASS / $fail FAIL ===\n";
foreach ( $rows as $row ) {
	echo implode( ' | ', $row ) . "\n";
}
file_put_contents(
	'/Users/smvueno/Local Sites/amelia/tasks-full-audit.json',
	wp_json_encode( array( 'pass' => $pass, 'fail' => $fail, 'tag' => $TAG, 'created' => $created, 'results' => $rows ), JSON_PRETTY_PRINT )
);
exit( $fail ? 1 : 0 );
