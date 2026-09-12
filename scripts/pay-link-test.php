<?php
/**
 * Payment-link regression: full / half / custom + cash remainder (CLI).
 * Usage: php -d mysqli.default_socket=… scripts/pay-link-test.php
 */

$_SERVER['HTTP_HOST']   = 'localhost:10019';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

$wp_root = dirname( __DIR__, 4 );
require $wp_root . '/wp-load.php';
wp_set_current_user( 1 );

require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/class-entity-map.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/booking-payload.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/payment-payload.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-handlers.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-mutate.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-book.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-pay.php';

use function Harudigi_Amelia_MCP_Abilities\{
	meta_help,
	meta_query,
	meta_mutate,
	meta_book,
	meta_pay
};

$pass = 0;
$fail = 0;
$results = array();

function t( string $name, $ok, string $detail = '' ): void {
	global $results, $pass, $fail;
	$good = true === $ok;
	if ( is_wp_error( $ok ) ) {
		$good   = false;
		$detail = $ok->get_error_code() . ': ' . $ok->get_error_message();
	}
	$results[] = array( $name, $good ? 'PASS' : 'FAIL', $detail );
	$good ? ++$pass : ++$fail;
	if ( ! $good ) {
		fwrite( STDERR, "FAIL $name — $detail\n" );
	}
}

function ok_arr( $r ): bool {
	return is_array( $r ) && ! is_wp_error( $r );
}

function pick( $r, string $path ) {
	$c = $r;
	foreach ( explode( '.', $path ) as $p ) {
		if ( ! is_array( $c ) || ! array_key_exists( $p, $c ) ) {
			return null;
		}
		$c = $c[ $p ];
	}
	return $c;
}

function pay_row( $res ) {
	if ( is_wp_error( $res ) ) {
		return null;
	}
	return $res['data']['payment'] ?? $res['payment'] ?? ( is_array( $res ) ? $res : null );
}

function is_stripe_url( string $url ): bool {
	return false !== strpos( $url, 'buy.stripe.com' ) || false !== strpos( $url, 'checkout.stripe.com' );
}

echo "=== Pay link tests ===\n";

$help = meta_help();
t( 'help has workflow_pay', ok_arr( $help ) && isset( $help['workflow_pay']['half_link'] ) );

$svc = meta_query( array( 'action' => 'list', 'entity' => 'service', 'limit' => 5 ) );
$service_id = (int) ( pick( $svc, 'data.services.0.id' ) ?: 0 );
t( 'service id', $service_id > 0, "id=$service_id" );

$cust = meta_mutate(
	array(
		'action' => 'create',
		'entity' => 'customer',
		'fields' => array(
			'firstName' => 'PayLink',
			'lastName'  => 'Harness',
			'email'     => 'paylink-' . time() . '@example.invalid',
			'phone'     => '0903333444',
			'note'      => 'PAY-LINK-HARNESS',
		),
	)
);
$cid = (int) ( pick( $cust, 'data.user.id' ) ?: 0 );
t( 'fake customer', $cid > 0, "id=$cid" );

$start = wp_date( 'Y-m-d H:i', strtotime( '+50 days +' . wp_rand( 1, 200 ) . ' minutes' ) );
// Snap to :00 or :30 for Amelia slots.
$ts    = strtotime( $start );
$start = wp_date( 'Y-m-d H:i', $ts - ( $ts % 1800 ) );
$book  = meta_book(
	array(
		'action'       => 'create',
		'serviceId'    => $service_id,
		'providerId'   => 1,
		'customerId'   => $cid,
		'bookingStart' => $start,
		'notify'       => false,
	)
);
t( 'book create', ok_arr( $book ), is_wp_error( $book ) ? $book->get_error_message() : '' );
$appt  = pick( $book, 'data.appointment' ) ?: array();
$aid   = (int) ( $appt['id'] ?? 0 );
$cb    = (int) ( $appt['bookings'][0]['id'] ?? 0 );
$pid   = (int) ( $appt['bookings'][0]['payments'][0]['id'] ?? 0 );
$price = (float) ( $appt['bookings'][0]['price'] ?? 0 );
t( 'booking ids', $aid > 0 && $cb > 0 && $pid > 0, "aid=$aid cb=$cb pid=$pid price=$price" );

// fields merge: top-level customerBookingId + nested amount (on a throwaway booking payment add)
$nested = meta_pay(
	array(
		'action'            => 'add',
		'customerBookingId' => $cb,
		'entity'            => 'appointment',
		'fields'            => array(
			'amount'  => 1500,
			'gateway' => 'stripe',
			'status'  => 'pending',
		),
	)
);
$row = pay_row( $nested );
t( 'add fields-merge amount', ok_arr( $nested ) && $row && (float) $row['amount'] === 1500.0, is_wp_error( $nested ) ? $nested->get_error_message() : wp_json_encode( $row ) );
t( 'add fields-merge gateway stripe', $row && ( $row['gateway'] ?? '' ) === 'stripe' );
$extra_pid = (int) ( $row['id'] ?? 0 );
if ( $extra_pid > 0 ) {
	meta_pay( array( 'action' => 'delete', 'id' => $extra_pid, 'confirm' => true ) );
}

$half = meta_pay(
	array(
		'action'  => 'link',
		'id'      => $pid,
		'gateway' => 'stripe',
		'amount'  => 'half',
	)
);
t( 'half link ok', ok_arr( $half ), is_wp_error( $half ) ? $half->get_error_message() : '' );
$expect_half = (float) round( $price / 2 );
t( 'half chargedAmount', ok_arr( $half ) && (float) ( $half['chargedAmount'] ?? 0 ) === $expect_half, 'got=' . ( is_array( $half ) ? ( $half['chargedAmount'] ?? '?' ) : '?' ) );
t( 'half remainingAfter', ok_arr( $half ) && (float) ( $half['remainingAfter'] ?? -1 ) === $expect_half );
t( 'half stripe URL', ok_arr( $half ) && is_stripe_url( (string) ( $half['paymentLink'] ?? '' ) ), is_array( $half ) ? (string) ( $half['paymentLink'] ?? '' ) : '' );
t( 'half auto_approve flag', ok_arr( $half ) && ! empty( $half['auto_approve'] ) );

// No leftover seed rows after partial link.
$after = meta_query( array( 'action' => 'get', 'entity' => 'appointment', 'id' => $aid ) );
$pays  = pick( $after, 'data.appointment.bookings.0.payments' ) ?: array();
$seedish = array_filter(
	is_array( $pays ) ? $pays : array(),
	static function ( $p ) {
		return is_array( $p ) && false !== strpos( (string) ( $p['gatewayTitle'] ?? '' ), 'MCP amount seed' );
	}
);
t( 'no leftover seed rows', 0 === count( $seedish ), 'count=' . count( $seedish ) );

$custom = meta_pay(
	array(
		'action'  => 'link',
		'id'      => $pid,
		'gateway' => 'stripe',
		'amount'  => 5000,
	)
);
t( 'custom 5000 chargedAmount', ok_arr( $custom ) && (float) ( $custom['chargedAmount'] ?? 0 ) === 5000.0, is_wp_error( $custom ) ? $custom->get_error_message() : '' );
t( 'custom stripe URL', ok_arr( $custom ) && is_stripe_url( (string) ( $custom['paymentLink'] ?? '' ) ) );

$full = meta_pay(
	array(
		'action'  => 'link',
		'id'      => $pid,
		'gateway' => 'stripe',
	)
);
t( 'full chargedAmount', ok_arr( $full ) && (float) ( $full['chargedAmount'] ?? 0 ) === $price, is_wp_error( $full ) ? $full->get_error_message() : 'got=' . ( $full['chargedAmount'] ?? '?' ) );
t( 'full stripe URL', ok_arr( $full ) && is_stripe_url( (string) ( $full['paymentLink'] ?? '' ) ) );

// Simulate half paid then cash remainder (bookkeeping only — no live Stripe).
$mark = meta_pay(
	array(
		'action' => 'update',
		'id'     => $pid,
		'status' => 'paid',
		'amount' => $expect_half,
		'gateway'=> 'stripe',
	)
);
t( 'mark half paid (sim)', ok_arr( $mark ), is_wp_error( $mark ) ? $mark->get_error_message() : '' );

$cash = meta_pay(
	array(
		'action'            => 'add',
		'customerBookingId' => $cb,
		'entity'            => 'appointment',
		'amount'            => $expect_half,
		'gateway'           => 'onSite',
		'status'            => 'paid',
	)
);
$cash_row = pay_row( $cash );
t( 'cash remainder paid', ok_arr( $cash ) && $cash_row && ( $cash_row['status'] ?? '' ) === 'paid' && (float) $cash_row['amount'] === $expect_half, is_wp_error( $cash ) ? $cash->get_error_message() : '' );

// Second booking: half paid sim → second stripe link for remaining.
$start2 = wp_date( 'Y-m-d H:i', strtotime( '+55 days +' . wp_rand( 1, 200 ) . ' minutes' ) );
$ts2    = strtotime( $start2 );
$start2 = wp_date( 'Y-m-d H:i', $ts2 - ( $ts2 % 1800 ) );
$book2  = meta_book(
	array(
		'action'       => 'create',
		'serviceId'    => $service_id,
		'providerId'   => 1,
		'customerId'   => $cid,
		'bookingStart' => $start2,
		'notify'       => false,
	)
);
$appt2  = pick( $book2, 'data.appointment' ) ?: array();
$aid2   = (int) ( $appt2['id'] ?? 0 );
$cb2    = (int) ( $appt2['bookings'][0]['id'] ?? 0 );
$pid2   = (int) ( $appt2['bookings'][0]['payments'][0]['id'] ?? 0 );
$price2 = (float) ( $appt2['bookings'][0]['price'] ?? 0 );
$half2  = (float) round( $price2 / 2 );
t( 'book2 ids', $aid2 > 0 && $cb2 > 0 && $pid2 > 0 && $aid2 !== $aid, "aid2=$aid2 cb2=$cb2 pid2=$pid2 price2=$price2" );

meta_pay( array( 'action' => 'update', 'id' => $pid2, 'status' => 'paid', 'amount' => $half2, 'gateway' => 'stripe' ) );
$rest_pay = meta_pay(
	array(
		'action'            => 'add',
		'customerBookingId' => $cb2,
		'entity'            => 'appointment',
		'amount'            => $half2,
		'gateway'           => 'stripe',
		'status'            => 'pending',
	)
);
$rest_row = pay_row( $rest_pay );
$rest_id  = (int) ( $rest_row['id'] ?? 0 );
t( 'rest payment row', $rest_id > 0, is_wp_error( $rest_pay ) ? $rest_pay->get_error_message() : "id=$rest_id" );
$link2    = $rest_id ? meta_pay( array( 'action' => 'link', 'id' => $rest_id, 'gateway' => 'stripe' ) ) : new \WP_Error( 'skip', 'no rest id' );
t( 'stripe remainder link', ok_arr( $link2 ) && (float) ( $link2['chargedAmount'] ?? 0 ) === $half2, is_wp_error( $link2 ) ? $link2->get_error_message() : wp_json_encode( $link2 ) );
t( 'stripe remainder URL', ok_arr( $link2 ) && is_stripe_url( (string) ( $link2['paymentLink'] ?? '' ) ) );

if ( $aid ) {
	meta_book( array( 'action' => 'cancel', 'id' => $aid, 'confirm' => true, 'notify' => false ) );
}
if ( $aid2 ) {
	meta_book( array( 'action' => 'cancel', 'id' => $aid2, 'confirm' => true, 'notify' => false ) );
}

echo "\n=== Summary: $pass PASS / $fail FAIL ===\n";
foreach ( $results as $row ) {
	echo implode( ' | ', $row ) . "\n";
}

file_put_contents(
	dirname( __DIR__, 6 ) . '/tasks-pay-link-last.json',
	wp_json_encode( array( 'pass' => $pass, 'fail' => $fail, 'results' => $results ), JSON_PRETTY_PRINT )
);

exit( $fail > 0 ? 1 : 0 );
