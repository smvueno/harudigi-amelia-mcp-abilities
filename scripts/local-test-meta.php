<?php
/**
 * Local regression harness for HaruDigi Amelia meta MCP (CLI).
 * Usage: php -d mysqli.default_socket=... scripts/local-test-meta.php
 */

$_SERVER['HTTP_HOST']   = 'localhost:10019';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

$wp_root = dirname( __DIR__, 4 ); // .../app/public
require $wp_root . '/wp-load.php';

if ( ! current_user_can( 'manage_options' ) ) {
	wp_set_current_user( 1 );
}

// Abilities files load on wp_abilities_api_init — pull them in for CLI.
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/class-entity-map.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/booking-payload.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/payment-payload.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-handlers.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-mutate.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-book.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-pay.php';

use function Harudigi_Amelia_MCP_Abilities\{
	meta_help,
	meta_status,
	meta_discover,
	meta_query,
	meta_mutate,
	meta_book,
	meta_pay,
	get_default_appointment_status
};

delete_option( 'harudigi_amelia_mcp_synced_version' );
harudigi_amelia_mcp_enable_in_easy_mcp();

$results = array();
$pass    = 0;
$fail    = 0;

function t( string $name, $ok, string $detail = '' ): void {
	global $results, $pass, $fail;
	$good = true === $ok || ( is_array( $ok ) && empty( $ok['error'] ) );
	if ( is_wp_error( $ok ) ) {
		$good   = false;
		$detail = $ok->get_error_code() . ': ' . $ok->get_error_message();
	} elseif ( true === $ok ) {
		$good = true;
	} elseif ( false === $ok ) {
		$good = false;
	}
	$results[] = array( $name, $good ? 'PASS' : 'FAIL', $detail );
	if ( $good ) {
		++$pass;
	} else {
		++$fail;
		fwrite( STDERR, "FAIL $name — $detail\n" );
	}
}

function ok_arr( $r ): bool {
	return is_array( $r ) && ! is_wp_error( $r );
}

echo "=== Amelia meta MCP tests ===\n";
echo 'defaultAppointmentStatus=' . get_default_appointment_status() . "\n";

$enabled = (array) get_option( 'easy_mcp_ai_enabled_abilities', array() );
$meta    = harudigi_amelia_mcp_meta_ability_slugs();
$amelia  = array_filter( $enabled, static function ( $s ) {
	return 0 === strpos( (string) $s, 'amelia/' );
} );
t( 'enable-list has exactly 7 amelia meta', count( $amelia ) === 7 && count( array_diff( $meta, $amelia ) ) === 0, 'count=' . count( $amelia ) );

$h = meta_help();
t( 'help', ok_arr( $h ) && isset( $h['tools']['amelia/book'] ) );

$st = meta_status();
t( 'status', ok_arr( $st ) && isset( $st['defaultAppointmentStatus'] ) );

$d = meta_discover( array( 'entity' => 'extra' ) );
t( 'discover extra', ok_arr( $d ) && in_array( 'create', $d['actions'], true ) );

$svc = meta_query( array( 'action' => 'list', 'entity' => 'service', 'limit' => 5 ) );
t( 'query list services', ok_arr( $svc ) );

$emp = meta_query( array( 'action' => 'list', 'entity' => 'employee', 'limit' => 5 ) );
t( 'query list employees', ok_arr( $emp ) );

$cf = meta_query( array( 'action' => 'list', 'entity' => 'custom_field' ) );
t( 'query list custom_fields', ok_arr( $cf ) );

$ex = meta_query( array( 'action' => 'list', 'entity' => 'extra', 'limit' => 20 ) );
t( 'query list extras', ok_arr( $ex ) );

// Resolve first service + provider for booking.
$service_id  = 0;
$provider_id = 0;
if ( ok_arr( $svc ) ) {
	$data = $svc['data'] ?? $svc;
	$list = $data['services'] ?? ( is_array( $data ) ? $data : array() );
	if ( is_array( $list ) ) {
		foreach ( $list as $row ) {
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				$service_id = (int) $row['id'];
				break;
			}
		}
	}
}
if ( ok_arr( $emp ) ) {
	$data = $emp['data'] ?? $emp;
	$list = $data['users'] ?? $data['providers'] ?? ( is_array( $data ) ? $data : array() );
	if ( is_array( $list ) ) {
		foreach ( $list as $row ) {
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				$provider_id = (int) $row['id'];
				break;
			}
		}
	}
}

$opts = null;
if ( $service_id ) {
	$opts = meta_query( array( 'action' => 'booking_options', 'serviceId' => $service_id ) );
	t( 'booking_options', ok_arr( $opts ) );
}

// Fake customer.
$cust = meta_mutate(
	array(
		'action' => 'create',
		'entity' => 'customer',
		'fields' => array(
			'firstName' => 'MCP',
			'lastName'  => 'TestCustomer',
			'email'     => 'mcp-test-' . time() . '@example.invalid',
			'phone'     => '0900000000',
			'note'      => 'HaruDigi MCP fake test customer — safe to delete',
		),
	)
);
t( 'mutate create fake customer', ok_arr( $cust ) );
$customer_id = 0;
$customer_user = array();
if ( ok_arr( $cust ) ) {
	$data = $cust['data'] ?? $cust;
	$customer_user = $data['user'] ?? $data['customer'] ?? $data;
	$customer_id = (int) ( is_array( $customer_user ) ? ( $customer_user['id'] ?? 0 ) : 0 );
}
t( 'fake customer id', $customer_id > 0, 'id=' . $customer_id );
t( 'create persisted customer note', is_array( $customer_user ) && ( $customer_user['note'] ?? '' ) === 'HaruDigi MCP fake test customer — safe to delete' );

$note_upd = meta_mutate(
	array(
		'action' => 'update',
		'entity' => 'customer',
		'id'     => $customer_id,
		'fields' => array( 'note' => 'Updated customer note' ),
	)
);
t( 'mutate update customer note', ok_arr( $note_upd ) );
$got_cust = $customer_id ? meta_query( array( 'action' => 'get', 'entity' => 'customer', 'id' => $customer_id ) ) : new \WP_Error( 'skip', 'no id' );
$got_user = array();
if ( ok_arr( $got_cust ) ) {
	$gd       = $got_cust['data'] ?? $got_cust;
	$got_user = $gd['user'] ?? $gd['customer'] ?? $gd;
}
t( 'customer note after update', is_array( $got_user ) && ( $got_user['note'] ?? '' ) === 'Updated customer note' );
t( 'customer phone preserved on note update', is_array( $got_user ) && ( $got_user['phone'] ?? '' ) === '0900000000' );

// Misuse: delete without confirm.
$mis = meta_mutate( array( 'action' => 'delete', 'entity' => 'customer', 'id' => $customer_id ?: 999999 ) );
t( 'mutate delete without confirm refused', is_wp_error( $mis ) && 'confirm_required' === $mis->get_error_code() );

$extra_id = 0;
if ( ok_arr( $opts ) && ! empty( $opts['extras'][0]['id'] ) ) {
	$extra_id = (int) $opts['extras'][0]['id'];
} elseif ( ok_arr( $ex ) ) {
	$data = $ex['data'] ?? $ex;
	$list = $data['extras'] ?? array();
	if ( is_array( $list ) ) {
		foreach ( $list as $row ) {
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				$extra_id = (int) $row['id'];
				break;
			}
		}
	}
}

$cf_map = array();
if ( ok_arr( $opts ) && ! empty( $opts['custom_fields'] ) && is_array( $opts['custom_fields'] ) ) {
	foreach ( $opts['custom_fields'] as $f ) {
		if ( ! is_array( $f ) || empty( $f['id'] ) ) {
			continue;
		}
		$cf_map[ (string) $f['id'] ] = ( 'select-box' === ( $f['type'] ?? '' ) || 'select' === ( $f['type'] ?? '' ) )
			? ( $f['options'][0]['label'] ?? 'option' )
			: 'MCP test value';
		if ( count( $cf_map ) >= 2 ) {
			break;
		}
	}
}

$default_status = get_default_appointment_status( $service_id );
$booking_id_appt = 0;

if ( $service_id && $provider_id && $customer_id ) {
	// Find a far-future weekday morning slot to avoid collisions.
	$start = wp_date( 'Y-m-d', strtotime( '+14 days' ) ) . ' 09:00';
	$book  = array(
		'action'       => 'create',
		'serviceId'    => $service_id,
		'providerId'   => $provider_id,
		'customerId'   => $customer_id,
		'bookingStart' => $start,
		'persons'      => 1,
		'duration'     => '1h',
		'internalNotes'=> 'MCP harness booking',
	);
	if ( $extra_id ) {
		$book['extras'] = array( array( 'extraId' => $extra_id, 'quantity' => 1 ) );
	}
	if ( $cf_map ) {
		$book['customFields'] = $cf_map;
	}
	$created = meta_book( $book );
	t( 'book create', ok_arr( $created ), is_wp_error( $created ) ? $created->get_error_message() : '' );

	$applied = is_array( $created ) ? ( $created['applied_status'] ?? null ) : null;
	t( 'book status matches site default', $applied === $default_status, "applied=$applied default=$default_status" );
	t( 'book notify false', is_array( $created ) && empty( $created['notify'] ) );

	if ( ok_arr( $created ) ) {
		$data = $created['data'] ?? $created;
		$appt = $data['appointment'] ?? $data;
		$booking_id_appt = (int) ( is_array( $appt ) ? ( $appt['id'] ?? 0 ) : 0 );
	}

	if ( $booking_id_appt ) {
		$got = meta_query( array( 'action' => 'get', 'entity' => 'appointment', 'id' => $booking_id_appt ) );
		t( 'query get appointment', ok_arr( $got ) );

		$upd = meta_book(
			array(
				'action'       => 'update',
				'id'           => $booking_id_appt,
				'internalNotes'=> 'MCP harness updated',
				'extras'       => $extra_id ? array( array( 'extraId' => $extra_id, 'quantity' => 1 ) ) : array(),
				'customFields' => $cf_map ?: array( '3' => 'updated locations' ),
			)
		);
		t( 'book update extras/CF', ok_arr( $upd ), is_wp_error( $upd ) ? $upd->get_error_message() : '' );

		$no_confirm = meta_book( array( 'action' => 'cancel', 'id' => $booking_id_appt ) );
		t( 'book cancel without confirm refused', is_wp_error( $no_confirm ) && 'confirm_required' === $no_confirm->get_error_code() );

		$cancel = meta_book( array( 'action' => 'cancel', 'id' => $booking_id_appt, 'confirm' => true ) );
		t( 'book cancel with confirm', ok_arr( $cancel ), is_wp_error( $cancel ) ? $cancel->get_error_message() : '' );
	} else {
		t( 'book create returned id', false, 'could not parse appointment id' );
	}
} else {
	t( 'book create skipped — missing ids', false, "service=$service_id provider=$provider_id customer=$customer_id" );
}

// Payment smoke: list + misuse delete.
$plist = meta_pay( array( 'action' => 'list', 'limit' => 5 ) );
t( 'pay list', ok_arr( $plist ) );
$pdel = meta_pay( array( 'action' => 'delete', 'id' => 1 ) );
t( 'pay delete without confirm refused', is_wp_error( $pdel ) && 'confirm_required' === $pdel->get_error_code() );

// Availability if we have service.
if ( $service_id ) {
	$av = meta_query(
		array(
			'action'        => 'availability',
			'serviceId'     => $service_id,
			'startDateTime' => wp_date( 'Y-m-d', strtotime( '+7 days' ) ) . ' 00:00',
			'endDateTime'   => wp_date( 'Y-m-d', strtotime( '+14 days' ) ) . ' 23:59',
			'persons'       => 1,
			'serviceDuration' => 3600,
		)
	);
	t( 'query availability', ok_arr( $av ), is_wp_error( $av ) ? $av->get_error_message() : '' );
}

echo "\n=== Summary: $pass PASS / $fail FAIL ===\n";
foreach ( $results as $row ) {
	echo implode( ' | ', $row ) . "\n";
}

// Write compact log path for tasks.md update.
file_put_contents(
	dirname( __DIR__, 6 ) . '/tasks-test-last.json',
	wp_json_encode(
		array(
			'pass'         => $pass,
			'fail'         => $fail,
			'defaultStatus'=> $default_status ?? null,
			'customer_id'  => $customer_id,
			'appointment'  => $booking_id_appt,
			'results'      => $results,
		),
		JSON_PRETTY_PRINT
	)
);

exit( $fail ? 1 : 0 );
