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
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/book-safety.php';
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
	book_slim_appointment_row,
	resolve_payment_link_amount,
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

/** dry_run then confirm for create/update. */
function book_commit( array $args ) {
	$dry = meta_book( array_merge( $args, array( 'dry_run' => true ) ) );
	if ( is_wp_error( $dry ) ) {
		return $dry;
	}
	if ( empty( $dry['previewId'] ) ) {
		return new \WP_Error( 'no_preview', 'dry_run missing previewId' );
	}
	return meta_book(
		array_merge(
			$args,
			array(
				'confirm'   => true,
				'previewId' => $dry['previewId'],
			)
		)
	);
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
t( 'help documents manual notify', ok_arr( $h ) && ! empty( $h['safety']['manual_send'] ) );

$st = meta_status();
t( 'status', ok_arr( $st ) && isset( $st['defaultAppointmentStatus'] ) );
t( 'status slim no settings_summary', ok_arr( $st ) && ! isset( $st['settings_summary'] ) && ( $st['settings_via'] ?? '' ) === 'amelia/query action=settings' );

$d = meta_discover( array( 'entity' => 'extra' ) );
t( 'discover extra', ok_arr( $d ) && in_array( 'create', $d['actions'], true ) );

$d_appt = meta_discover( array( 'entity' => 'appointment' ) );
t( 'discover appointment notify', ok_arr( $d_appt ) && in_array( 'notify', $d_appt['book_actions'] ?? array(), true ) );

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
	t( 'booking_options notes_map', ok_arr( $opts ) && isset( $opts['notes_map']['privateNote'], $opts['notes_map']['customerNote'] ) && ( $opts['notes_map']['sharedNoteLabel'] ?? '' ) === 'Notes' && ( null === ( $opts['notes_map']['sharedNoteFieldId'] ?? null ) || (int) $opts['notes_map']['sharedNoteFieldId'] > 0 ) );
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

// Duplicate extra guard: same name + serviceId must be refused, never created.
$dup_row = null;
if ( ok_arr( $ex ) ) {
	$ex_data = $ex['data'] ?? $ex;
	foreach ( (array) ( $ex_data['extras'] ?? $ex_data ) as $row ) {
		if ( is_array( $row ) && ! empty( $row['id'] ) && '' !== trim( (string) ( $row['name'] ?? '' ) ) ) {
			$dup_row = $row;
			break;
		}
	}
}
if ( $dup_row ) {
	$dup_try = meta_mutate( array( 'action' => 'create', 'entity' => 'extra', 'fields' => array( 'name' => (string) $dup_row['name'], 'serviceId' => (int) ( $dup_row['serviceId'] ?? 0 ), 'price' => isset( $dup_row['price'] ) ? (float) $dup_row['price'] : 1 ) ) );
	t( 'mutate create duplicate extra refused', is_wp_error( $dup_try ) && 'duplicate_extra' === $dup_try->get_error_code(), is_wp_error( $dup_try ) ? $dup_try->get_error_message() : '' );
} else {
	t( 'mutate create duplicate extra refused', false, 'no existing extra to clone' );
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
$customer_label  = 'MCP Test';

// Slim list defaults.
$slim = meta_query( array( 'action' => 'list', 'entity' => 'appointment' ) );
t( 'appointment list slim default', ok_arr( $slim ) && ( $slim['view'] ?? '' ) === 'slim', is_wp_error( $slim ) ? $slim->get_error_message() : '' );
t( 'appointment list has meta.window', ok_arr( $slim ) && ! empty( $slim['meta']['window'][0] ) && ! empty( $slim['meta']['window'][1] ) );
t( 'appointment list defaultWindowApplied', ok_arr( $slim ) && ! empty( $slim['meta']['defaultWindowApplied'] ) );
t( 'help documents dry_run', ok_arr( $h ) && ! empty( $h['safety']['create_update_dry_run'] ) );

$bogus = meta_query( array( 'action' => 'list', 'entity' => 'appointment', 'bogus_xyz' => 1 ) );
t( 'query list unknown param refused', is_wp_error( $bogus ) && 'unknown_parameter' === $bogus->get_error_code(), is_wp_error( $bogus ) ? $bogus->get_error_message() : '' );

$tomorrow_str = ( new \DateTimeImmutable( 'tomorrow', wp_timezone() ) )->format( 'Y-m-d' );
$preset_tomorrow = meta_query( array( 'action' => 'list', 'entity' => 'appointment', 'date_preset' => 'tomorrow' ) );
t( 'query date_preset tomorrow window', ok_arr( $preset_tomorrow ) && ( $preset_tomorrow['meta']['window'] ?? null ) === array( $tomorrow_str, $tomorrow_str ) && ( $preset_tomorrow['meta']['appliedFilters']['date_preset'] ?? '' ) === 'tomorrow', is_wp_error( $preset_tomorrow ) ? $preset_tomorrow->get_error_message() : wp_json_encode( $preset_tomorrow['meta'] ?? null ) );
$bad_date_from = meta_query( array( 'action' => 'list', 'entity' => 'appointment', 'date_from' => 'tomorrow' ) );
t( 'query date_from word refused invalid_date', is_wp_error( $bad_date_from ) && 'invalid_date' === $bad_date_from->get_error_code(), is_wp_error( $bad_date_from ) ? $bad_date_from->get_error_message() : '' );
$preset_upcoming = meta_query( array( 'action' => 'list', 'entity' => 'appointment', 'date_preset' => 'upcoming' ) );
t( 'query date_preset upcoming works', ok_arr( $preset_upcoming ) && ( $preset_upcoming['meta']['appliedFilters']['date_preset'] ?? '' ) === 'upcoming', is_wp_error( $preset_upcoming ) ? $preset_upcoming->get_error_message() : wp_json_encode( $preset_upcoming['meta'] ?? null ) );
$bad_preset = meta_query( array( 'action' => 'list', 'entity' => 'appointment', 'date_preset' => 'next_week' ) );
t( 'query bad date_preset refused', is_wp_error( $bad_preset ) && 'invalid_date_preset' === $bad_preset->get_error_code(), is_wp_error( $bad_preset ) ? $bad_preset->get_error_message() : '' );
$day10_expected = (int) wp_date( 'j' ) <= 10 ? wp_date( 'Y-m-10' ) : wp_date( 'Y-m-10', strtotime( 'first day of next month' ) );
$preset_day10 = meta_query( array( 'action' => 'list', 'entity' => 'appointment', 'date_preset' => 'day_10' ) );
t( 'query date_preset day_10 next 10th', ok_arr( $preset_day10 ) && ( $preset_day10['meta']['window'] ?? null ) === array( $day10_expected, $day10_expected ), is_wp_error( $preset_day10 ) ? $preset_day10->get_error_message() : wp_json_encode( $preset_day10['meta'] ?? null ) . ' expected=' . $day10_expected );
$bad_day = meta_query( array( 'action' => 'list', 'entity' => 'appointment', 'date_preset' => 'day_32' ) );
t( 'query date_preset day_32 refused', is_wp_error( $bad_day ) && 'invalid_date_preset' === $bad_day->get_error_code(), is_wp_error( $bad_day ) ? $bad_day->get_error_message() : '' );

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

	$iso_expected = ( new \DateTimeImmutable( '2026-12-20T01:00:00+00:00' ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i' );
	$iso_dry = meta_book( array_merge( $book, array( 'dry_run' => true, 'bookingStart' => '2026-12-20T01:00:00+00:00' ) ) );
	t( 'book create ISO offset canonical when', ok_arr( $iso_dry ) && ( $iso_dry['summary']['when'] ?? '' ) === $iso_expected, is_wp_error( $iso_dry ) ? $iso_dry->get_error_message() : 'expected=' . $iso_expected . ' got=' . ( $iso_dry['summary']['when'] ?? '' ) );
	foreach ( array( '20/12/2026 10:00', '2026-12-20', 'next friday 10:00', '2026-02-30 10:00' ) as $bad_start ) {
		$bad_dry = meta_book( array_merge( $book, array( 'dry_run' => true, 'bookingStart' => $bad_start ) ) );
		t( 'book create rejects bookingStart ' . $bad_start, is_wp_error( $bad_dry ) && 'invalid_booking_start' === $bad_dry->get_error_code(), is_wp_error( $bad_dry ) ? $bad_dry->get_error_message() : '' );
	}

	// Extra from another service (or bogus id) must fail at dry_run, not at confirm.
	$own_extra_ids = array();
	foreach ( (array) ( $opts['extras'] ?? array() ) as $row ) {
		if ( is_array( $row ) && ! empty( $row['id'] ) ) {
			$own_extra_ids[] = (int) $row['id'];
		}
	}
	$bad_extra_id = 999999;
	$all_svc      = meta_query( array( 'action' => 'list', 'entity' => 'service', 'limit' => 20 ) );
	if ( ok_arr( $all_svc ) ) {
		$svc_data = $all_svc['data'] ?? $all_svc;
		foreach ( (array) ( $svc_data['services'] ?? $svc_data ) as $srow ) {
			if ( ! is_array( $srow ) || empty( $srow['id'] ) || (int) $srow['id'] === $service_id ) {
				continue;
			}
			$other_opts = meta_query( array( 'action' => 'booking_options', 'serviceId' => (int) $srow['id'] ) );
			foreach ( (array) ( $other_opts['extras'] ?? array() ) as $orow ) {
				if ( is_array( $orow ) && ! empty( $orow['id'] ) && ! in_array( (int) $orow['id'], $own_extra_ids, true ) ) {
					$bad_extra_id = (int) $orow['id'];
					break 2;
				}
			}
		}
	}
	$bad_extra_dry = meta_book( array_merge( $book, array( 'dry_run' => true, 'extras' => array( array( 'extraId' => $bad_extra_id, 'quantity' => 1 ) ) ) ) );
	t( 'book create dry_run extra not for service refused', is_wp_error( $bad_extra_dry ) && 'extra_not_for_service' === $bad_extra_dry->get_error_code(), is_wp_error( $bad_extra_dry ) ? $bad_extra_dry->get_error_message() : '' );

	$bare = meta_book( $book );
	t( 'book create without dry_run refused', is_wp_error( $bare ) && 'dry_run_required' === $bare->get_error_code() );

	$emoji_dry = meta_book( array_merge( $book, array( 'dry_run' => true, 'internalNotes' => "Emoji \u{1F600} test" ) ) );
	t( 'book create emoji notes dry_run refused', is_wp_error( $emoji_dry ) && 'invalid_characters' === $emoji_dry->get_error_code(), is_wp_error( $emoji_dry ) ? $emoji_dry->get_error_message() : '' );
	$banana_dry = meta_book( array_merge( $book, array( 'dry_run' => true, 'status' => 'banana' ) ) );
	t( 'book create status banana dry_run refused', is_wp_error( $banana_dry ) && 'invalid_status' === $banana_dry->get_error_code(), is_wp_error( $banana_dry ) ? $banana_dry->get_error_message() : '' );
	$ghost_dry = meta_book( array_merge( $book, array( 'dry_run' => true, 'customerId' => 999999 ) ) );
	t( 'book create ghost customer dry_run refused', is_wp_error( $ghost_dry ) && 'customer_not_found' === $ghost_dry->get_error_code(), is_wp_error( $ghost_dry ) ? $ghost_dry->get_error_message() : '' );
	$coupon_dry = meta_book( array_merge( $book, array( 'dry_run' => true, 'couponCode' => 'NOSUCHCODE' ) ) );
	t( 'book create unknown coupon dry_run refused', is_wp_error( $coupon_dry ) && 'unknown_coupon' === $coupon_dry->get_error_code(), is_wp_error( $coupon_dry ) ? $coupon_dry->get_error_message() : '' );

	$dry_c = meta_book( array_merge( $book, array( 'dry_run' => true ) ) );
	t( 'book create dry_run', ok_arr( $dry_c ) && ! empty( $dry_c['previewId'] ), is_wp_error( $dry_c ) ? $dry_c->get_error_message() : '' );
	t( 'book create dry_run no appointment id yet', ok_arr( $dry_c ) && empty( $dry_c['data']['appointment']['id'] ?? null ) );
	$sum = ok_arr( $dry_c ) ? ( $dry_c['summary'] ?? array() ) : array();
	t( 'create summary service name', is_array( $sum ) && is_string( $sum['service'] ?? null ) && '' !== $sum['service'], wp_json_encode( $sum ) );
	// Exact tier+extras depends on site pricing fixtures, so assert a positive number.
	t( 'create summary estimatedTotal positive', is_array( $sum ) && is_numeric( $sum['estimatedTotal'] ?? null ) && (float) $sum['estimatedTotal'] > 0, wp_json_encode( $sum['estimatedTotal'] ?? null ) );
	t( 'create summary emailToClient false', is_array( $sum ) && false === ( $sum['emailToClient'] ?? null ) );
	$sum_line_has_service = false;
	foreach ( (array) ( $sum['lines'] ?? array() ) as $line ) {
		if ( is_string( $line ) && '' !== ( $sum['service'] ?? '' ) && false !== strpos( $line, (string) $sum['service'] ) ) {
			$sum_line_has_service = true;
		}
	}
	t( 'create summary line mentions service', $sum_line_has_service, wp_json_encode( $sum['lines'] ?? null ) );
	if ( ok_arr( $dry_c ) ) {
		fwrite( STDERR, 'EXAMPLE_SUMMARY ' . wp_json_encode( $sum ) . "\n" );
	}

	$created = book_commit( $book );
	t( 'book create confirm', ok_arr( $created ), is_wp_error( $created ) ? $created->get_error_message() : '' );

	$applied = is_array( $created ) ? ( $created['applied_status'] ?? null ) : null;
	t( 'book status matches site default', $applied === $default_status, "applied=$applied default=$default_status" );
	t( 'book notify false', is_array( $created ) && empty( $created['notify'] ) );

	if ( ok_arr( $created ) ) {
		$data = $created['data'] ?? $created;
		$appt = $data['appointment'] ?? $data;
		$booking_id_appt = (int) ( is_array( $appt ) ? ( $appt['id'] ?? 0 ) : 0 );
		if ( is_array( $appt ) && ! empty( $appt['bookings'][0]['customer'] ) ) {
			$c = $appt['bookings'][0]['customer'];
			$customer_label = trim( ( $c['firstName'] ?? '' ) . ' ' . ( $c['lastName'] ?? '' ) );
			if ( '' === $customer_label && ! empty( $c['email'] ) ) {
				$customer_label = (string) $c['email'];
			}
		}
	}

	if ( $booking_id_appt ) {
		$got = meta_query( array( 'action' => 'get', 'entity' => 'appointment', 'id' => $booking_id_appt ) );
		t( 'query get appointment', ok_arr( $got ) );
		$got_data = ok_arr( $got ) ? ( $got['data'] ?? $got ) : array();
		$got_apt  = is_array( $got_data ) ? ( $got_data['appointment'] ?? $got_data ) : array();
		$slim_row = is_array( $got_apt ) ? book_slim_appointment_row( $got_apt ) : array();
		$money    = is_array( $slim_row ) ? ( $slim_row['money'] ?? array() ) : array();
		t( 'slim row money totals', isset( $money['total'], $money['paid'], $money['owed'] ) && (float) $money['total'] >= (float) $money['paid'] && abs( (float) $money['owed'] - max( 0.0, (float) $money['total'] - (float) $money['paid'] ) ) < 0.001, wp_json_encode( $money ) );

		$upd_args = array(
			'action'           => 'update',
			'id'               => $booking_id_appt,
			'expectedCustomer' => $customer_label,
			'internalNotes'    => 'MCP harness updated',
			'extras'           => $extra_id ? array( array( 'extraId' => $extra_id, 'quantity' => 1 ) ) : array(),
			'customFields'     => $cf_map ?: array( '3' => 'updated locations' ),
		);

		$no_mode = meta_book( $upd_args );
		t( 'book update without dry_run refused', is_wp_error( $no_mode ) && 'dry_run_required' === $no_mode->get_error_code() );

		$wrong_cust = meta_book(
			array_merge(
				$upd_args,
				array(
					'dry_run'          => true,
					'expectedCustomer' => 'Definitely Not This Person XYZ',
				)
			)
		);
		t( 'book update wrong expectedCustomer refused', is_wp_error( $wrong_cust ) && 'expected_customer_mismatch' === $wrong_cust->get_error_code() );

		$mixed = meta_book(
			array(
				'action'           => 'update',
				'id'               => $booking_id_appt,
				'expectedCustomer' => $customer_label,
				'dry_run'          => true,
				'internalNotes'    => 'x',
				'bookingStart'     => $start,
			)
		);
		t( 'book update notes+reschedule refused', is_wp_error( $mixed ) && 'mixed_update' === $mixed->get_error_code() );

		$dry_u = meta_book( array_merge( $upd_args, array( 'dry_run' => true ) ) );
		t( 'book update dry_run', ok_arr( $dry_u ) && ! empty( $dry_u['previewId'] ), is_wp_error( $dry_u ) ? $dry_u->get_error_message() : '' );
		t( 'book update dry_run shows target apt', ok_arr( $dry_u ) && (int) ( $dry_u['target']['aptId'] ?? 0 ) === $booking_id_appt );

		$has_extras_change = static function ( $preview ) {
			foreach ( (array) ( $preview['changes'] ?? array() ) as $c ) {
				if ( is_array( $c ) && ( $c['field'] ?? '' ) === 'extras' && isset( $c['before'], $c['after'] ) ) {
					return true;
				}
			}
			return false;
		};
		if ( $extra_id ) {
			// Target quantity differs from whatever a previous run left on this shared slot.
			$cur_qty   = 0;
			$cur_get   = meta_query( array( 'action' => 'get', 'entity' => 'appointment', 'id' => $booking_id_appt ) );
			$cur_data  = ok_arr( $cur_get ) ? ( $cur_get['data'] ?? $cur_get ) : array();
			$cur_apt   = is_array( $cur_data ) ? ( $cur_data['appointment'] ?? $cur_data ) : array();
			if ( is_array( $cur_apt ) && ! empty( $cur_apt['bookings'][0]['extras'] ) && is_array( $cur_apt['bookings'][0]['extras'] ) ) {
				foreach ( $cur_apt['bookings'][0]['extras'] as $ex_row ) {
					if ( is_array( $ex_row ) && (int) ( $ex_row['extraId'] ?? 0 ) === $extra_id ) {
						$cur_qty = (int) ( $ex_row['quantity'] ?? 0 );
					}
				}
			}
			$target_qty = $cur_qty + 1;
			$dry_top = meta_book( array( 'action' => 'update', 'id' => $booking_id_appt, 'expectedCustomer' => $customer_label, 'dry_run' => true, 'extras' => array( array( 'extraId' => $extra_id, 'quantity' => $target_qty ) ) ) );
			t( 'top-level extras preview includes extras change', ok_arr( $dry_top ) && $has_extras_change( $dry_top ), is_wp_error( $dry_top ) ? $dry_top->get_error_message() : wp_json_encode( $dry_top['changes'] ?? null ) );
			$dry_fields = meta_book( array( 'action' => 'update', 'id' => $booking_id_appt, 'expectedCustomer' => $customer_label, 'dry_run' => true, 'fields' => array( 'extras' => array( array( 'extraId' => $extra_id, 'quantity' => $target_qty ) ) ) ) );
			t( 'fields.extras preview includes extras change', ok_arr( $dry_fields ) && $has_extras_change( $dry_fields ), is_wp_error( $dry_fields ) ? $dry_fields->get_error_message() : wp_json_encode( $dry_fields['changes'] ?? null ) );
		}

		$filtered = meta_query( array( 'action' => 'list', 'entity' => 'appointment', 'customerId' => $customer_id, 'date_from' => substr( $start, 0, 10 ), 'date_to' => substr( $start, 0, 10 ) ) );
		// Slim rows show the first booking, which may be another customer on a shared slot.
		// Verify via full view: every returned appointment must contain this customer.
		$filtered_full = meta_query( array( 'action' => 'list', 'entity' => 'appointment', 'customerId' => $customer_id, 'date_from' => substr( $start, 0, 10 ), 'date_to' => substr( $start, 0, 10 ), 'view' => 'full' ) );
		$only_customer = ok_arr( $filtered_full );
		$seen_customer = false;
		if ( $only_customer ) {
			$buckets = $filtered_full['data']['appointments'] ?? array();
			foreach ( (array) $buckets as $bucket ) {
				foreach ( (array) ( $bucket['appointments'] ?? array() ) as $apt_row ) {
					$has = false;
					foreach ( (array) ( $apt_row['bookings'] ?? array() ) as $bk ) {
						if ( (int) ( $bk['customerId'] ?? 0 ) === $customer_id ) {
							$has           = true;
							$seen_customer = true;
						}
					}
					if ( ! $has ) {
						$only_customer = false;
					}
				}
			}
		}
		t( 'query list appointment customerId filters', $only_customer && $seen_customer && (int) ( $filtered['meta']['appliedFilters']['customerId'] ?? 0 ) === $customer_id, is_wp_error( $filtered ) ? $filtered->get_error_message() : wp_json_encode( $filtered['meta']['appliedFilters'] ?? null ) );

		$bad_prv = meta_book( array_merge( $upd_args, array( 'confirm' => true, 'previewId' => 'prv_deadbeefdeadbeefdeadbeef' ) ) );
		t( 'book update bad previewId refused', is_wp_error( $bad_prv ) && in_array( $bad_prv->get_error_code(), array( 'preview_expired', 'preview_mismatch', 'preview_required' ), true ) );

		$upd = book_commit( $upd_args );
		t( 'book update extras/CF', ok_arr( $upd ), is_wp_error( $upd ) ? $upd->get_error_message() : '' );
		t( 'book update confirm persisted', ok_arr( $upd ) && ! empty( $upd['persisted'] ) && ( $upd['changed']['internalNotes']['after'] ?? '' ) === 'MCP harness updated', is_array( $upd ) ? wp_json_encode( $upd['changed'] ?? null ) : '' );

		$no_notify = meta_book( array( 'action' => 'notify', 'id' => $booking_id_appt ) );
		t( 'book notify without confirm refused', is_wp_error( $no_notify ) && 'confirm_required' === $no_notify->get_error_code() );

		$do_notify = meta_book( array( 'action' => 'notify', 'id' => $booking_id_appt, 'confirm' => true ) );
		t( 'book notify with confirm', ok_arr( $do_notify ) && ! empty( $do_notify['ok'] ), is_wp_error( $do_notify ) ? $do_notify->get_error_message() : '' );
		t( 'book notify email channel only', ok_arr( $do_notify ) && array( 'email' ) === ( $do_notify['channels'] ?? null ) );

		$resend = meta_book( array( 'action' => 'resend', 'id' => $booking_id_appt, 'confirm' => true ) );
		t( 'book resend alias', ok_arr( $resend ) && ! empty( $resend['ok'] ), is_wp_error( $resend ) ? $resend->get_error_message() : '' );

		$bad_bid = meta_book( array( 'action' => 'notify', 'id' => $booking_id_appt, 'booking_id' => 999999999, 'confirm' => true ) );
		t( 'book notify bad booking_id refused', is_wp_error( $bad_bid ) && 'no_bookings' === $bad_bid->get_error_code() );

		// Persist notify=1, then note-only update must restore flag (not leave 0).
		$with_notify = book_commit(
			array(
				'action'           => 'update',
				'id'               => $booking_id_appt,
				'expectedCustomer' => $customer_label,
				'notify'           => true,
			)
		);
		t( 'book update set notify true', ok_arr( $with_notify ), is_wp_error( $with_notify ) ? $with_notify->get_error_message() : '' );
		global $wpdb;
		$flag_before = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT notifyParticipants FROM ' . $wpdb->prefix . 'amelia_appointments WHERE id=%d', $booking_id_appt )
		);
		t( 'notifyParticipants stored 1', 1 === $flag_before );

		$note_upd = book_commit(
			array(
				'action'           => 'update',
				'id'               => $booking_id_appt,
				'expectedCustomer' => $customer_label,
				'internalNotes'    => 'MCP harness note after notify flag',
			)
		);
		t( 'book update notes omit notify', ok_arr( $note_upd ), is_wp_error( $note_upd ) ? $note_upd->get_error_message() : '' );
		$flag_after = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT notifyParticipants FROM ' . $wpdb->prefix . 'amelia_appointments WHERE id=%d', $booking_id_appt )
		);
		t( 'notifyParticipants restored after mute update', 1 === $flag_after, "flag=$flag_after" );

		if ( $extra_id ) {
			$fields_extras = book_commit(
				array(
					'action'           => 'update',
					'id'               => $booking_id_appt,
					'expectedCustomer' => $customer_label,
					'fields'           => array( 'extras' => array( array( 'extraId' => $extra_id, 'quantity' => 2 ) ) ),
				)
			);
			$after_fields    = meta_query( array( 'action' => 'get', 'entity' => 'appointment', 'id' => $booking_id_appt ) );
			$after_data      = ok_arr( $after_fields ) ? ( $after_fields['data'] ?? $after_fields ) : array();
			$after_apt       = is_array( $after_data ) ? ( $after_data['appointment'] ?? $after_data ) : array();
			$qty_after       = 0;
			$real_booking_id = 0;
			if ( is_array( $after_apt ) && ! empty( $after_apt['bookings'][0] ) && is_array( $after_apt['bookings'][0] ) ) {
				$real_booking_id = (int) ( $after_apt['bookings'][0]['id'] ?? 0 );
				foreach ( (array) ( $after_apt['bookings'][0]['extras'] ?? array() ) as $ex_row ) {
					if ( is_array( $ex_row ) && (int) ( $ex_row['extraId'] ?? $ex_row['id'] ?? 0 ) === $extra_id ) {
						$qty_after = (int) ( $ex_row['quantity'] ?? 0 );
					}
				}
			}
			t( 'fields.extras confirm persists', ok_arr( $fields_extras ) && 2 === $qty_after, "qty=$qty_after " . ( is_wp_error( $fields_extras ) ? $fields_extras->get_error_message() : '' ) );

			if ( $real_booking_id ) {
				$wrong = meta_pay( array( 'action' => 'link', 'customerBookingId' => $real_booking_id ) );
				t( 'pay link booking id gives wrong_id_type', is_wp_error( $wrong ) && 'wrong_id_type' === $wrong->get_error_code(), is_wp_error( $wrong ) ? $wrong->get_error_message() : '' );
			}
		}

		// Custom fields by label: shared note (Notes field) stays distinct from private internalNotes.
		// Unique per run: this slot is shared, so a fixed value would already match on re-runs.
		$cf_value      = 'CF-BY-LABEL-TEST-' . time();
		$cf_label_args = array(
			'action'           => 'update',
			'id'               => $booking_id_appt,
			'expectedCustomer' => $customer_label,
			'fields'           => array( 'customFields' => array( 'Notes' => $cf_value ) ),
		);
		$dry_cf = meta_book( array_merge( $cf_label_args, array( 'dry_run' => true ) ) );
		$cf_preview_has = false;
		if ( ok_arr( $dry_cf ) ) {
			foreach ( (array) ( $dry_cf['changes'] ?? array() ) as $c ) {
				if ( is_array( $c ) && ( $c['field'] ?? '' ) === 'customFields' ) {
					$cf_preview_has = true;
				}
			}
		}
		t( 'customFields by label preview includes change', $cf_preview_has, is_wp_error( $dry_cf ) ? $dry_cf->get_error_message() : wp_json_encode( $dry_cf['changes'] ?? null ) );

		$cf_upd = book_commit( $cf_label_args );
		t( 'book update customFields by label', ok_arr( $cf_upd ), is_wp_error( $cf_upd ) ? $cf_upd->get_error_message() : '' );
		t( 'book update customFields changed by id', ok_arr( $cf_upd ) && ! empty( $cf_upd['persisted'] ) && in_array( $cf_value, (array) ( $cf_upd['changed']['customFields']['after'] ?? array() ), true ), is_array( $cf_upd ) ? wp_json_encode( $cf_upd['changed'] ?? null ) : '' );

		$got_cf   = meta_query( array( 'action' => 'get', 'entity' => 'appointment', 'id' => $booking_id_appt ) );
		$naf      = ok_arr( $got_cf ) ? ( $got_cf['data']['notesAndFields'] ?? array() ) : array();
		$has_notes_label = false;
		foreach ( (array) ( $naf['fields'] ?? array() ) as $frow ) {
			if ( is_array( $frow ) && 'Notes' === ( $frow['label'] ?? '' ) ) {
				$has_notes_label = true;
			}
		}
		t( 'notesAndFields sharedNote by label', ( $naf['sharedNote'] ?? null ) === $cf_value && $has_notes_label, wp_json_encode( $naf ) );
		t( 'notesAndFields privateNote distinct from shared', ( $naf['privateNote'] ?? null ) === 'MCP harness note after notify flag' && ( $naf['sharedNote'] ?? null ) !== ( $naf['privateNote'] ?? null ), wp_json_encode( $naf ) );
		t( 'notesAndFields customerNote present', array_key_exists( 'customerNote', (array) $naf ) );

		$unknown_cf = meta_book(
			array(
				'action'           => 'update',
				'id'               => $booking_id_appt,
				'expectedCustomer' => $customer_label,
				'dry_run'          => true,
				'fields'           => array( 'customFields' => array( 'No Such Field' => 'x' ) ),
			)
		);
		t( 'unknown custom field label refused', is_wp_error( $unknown_cf ) && 'unknown_custom_field' === $unknown_cf->get_error_code(), is_wp_error( $unknown_cf ) ? $unknown_cf->get_error_message() : '' );

		$partial_cf = meta_book(
			array(
				'action'           => 'update',
				'id'               => $booking_id_appt,
				'expectedCustomer' => $customer_label,
				'dry_run'          => true,
				'fields'           => array( 'customFields' => array( 'preferred locations' => 'CF-PARTIAL-LABEL' ) ),
			)
		);
		t( 'partial custom field label resolves', ok_arr( $partial_cf ) && ! empty( $partial_cf['previewId'] ), is_wp_error( $partial_cf ) ? $partial_cf->get_error_message() : '' );

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
$pay_rows = ok_arr( $plist ) ? ( $plist['data']['payments'] ?? array() ) : array();
$pay_first = is_array( $pay_rows ) && ! empty( $pay_rows[0] ) && is_array( $pay_rows[0] ) ? $pay_rows[0] : array();
$pay_id0 = (int) ( $pay_first['id'] ?? 0 );
$pay_amt0 = isset( $pay_first['amount'] ) ? (float) $pay_first['amount'] : null;
$pay_xyz = $pay_id0 ? meta_pay( array( 'action' => 'update', 'id' => $pay_id0, 'fields' => array( 'amount' => 'xyz' ) ) ) : new \WP_Error( 'skip', 'no payment' );
t( 'pay update amount xyz refused', is_wp_error( $pay_xyz ) && 'invalid_amount' === $pay_xyz->get_error_code(), is_wp_error( $pay_xyz ) ? $pay_xyz->get_error_message() : '' );
$pay_after = $pay_id0 ? meta_pay( array( 'action' => 'get', 'id' => $pay_id0 ) ) : null;
$pay_after_row = ok_arr( $pay_after ) ? ( $pay_after['data']['payment'] ?? array() ) : array();
t( 'pay amount unchanged after xyz', null !== $pay_amt0 && isset( $pay_after_row['amount'] ) && (float) $pay_after_row['amount'] === $pay_amt0, wp_json_encode( $pay_after_row['amount'] ?? null ) );
$pay_add_xyz = ! empty( $pay_first['customerBookingId'] ) ? meta_pay( array( 'action' => 'add', 'fields' => array( 'customerBookingId' => (int) $pay_first['customerBookingId'], 'amount' => 'xyz', 'gateway' => 'onSite', 'status' => 'pending' ) ) ) : new \WP_Error( 'skip', 'no booking id' );
t( 'pay add amount xyz refused', is_wp_error( $pay_add_xyz ) && 'invalid_amount' === $pay_add_xyz->get_error_code(), is_wp_error( $pay_add_xyz ) ? $pay_add_xyz->get_error_message() : '' );
$pdel = meta_pay( array( 'action' => 'delete', 'id' => 1 ) );
t( 'pay delete without confirm refused', is_wp_error( $pdel ) && 'confirm_required' === $pdel->get_error_code() );
t( 'pay half with nothing paid is half of total', 5000.0 === (float) resolve_payment_link_amount( 'half', 10000.0, 0.0 ) );
t( 'pay half after partial payment is all remaining', 6000.0 === (float) resolve_payment_link_amount( 'half', 6000.0, 4000.0 ) );

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

	$av_ex = meta_query(
		array(
			'action'        => 'availability',
			'serviceId'     => $service_id,
			'startDateTime' => wp_date( 'Y-m-d', strtotime( '+7 days' ) ) . ' 00:00',
			'endDateTime'   => wp_date( 'Y-m-d', strtotime( '+14 days' ) ) . ' 23:59',
			'persons'       => 1,
			'serviceDuration' => 3600,
			'extras'        => array( array( 'id' => $extra_id ?: 1, 'quantity' => 2 ) ),
		)
	);
	t( 'query availability with extras', ok_arr( $av_ex ), is_wp_error( $av_ex ) ? $av_ex->get_error_code() . ': ' . $av_ex->get_error_message() : '' );
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
