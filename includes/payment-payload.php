<?php
/**
 * Payment gateway / status / write-body helpers for Amelia MCP.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AmeliaBooking\Application\Controller\Payment\AddPaymentController;
use AmeliaBooking\Application\Controller\Payment\DeletePaymentController;
use AmeliaBooking\Application\Controller\Payment\GetPaymentController;
use AmeliaBooking\Application\Controller\Payment\UpdatePaymentController;

/**
 * Amelia payment gateway names (manual bookkeeping labels — does not charge cards).
 *
 * @return string[]
 */
function payment_gateway_allowlist(): array {
	return array( 'onSite', 'stripe', 'payPal', 'wc', 'mollie', 'razorpay', 'square', 'barion' );
}

/**
 * @return string[]
 */
function payment_status_allowlist(): array {
	return array( 'paid', 'pending', 'partiallyPaid', 'refunded' );
}

/**
 * Normalize gateway aliases (paypal → payPal, onsite → onSite).
 *
 * @param mixed $raw Gateway string.
 * @return string|\WP_Error Canonical gateway or error.
 */
function normalize_payment_gateway( $raw ) {
	$g = trim( (string) $raw );
	if ( '' === $g ) {
		return new \WP_Error( 'invalid_gateway', __( 'gateway is required.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$allowed = payment_gateway_allowlist();
	if ( in_array( $g, $allowed, true ) ) {
		return $g;
	}

	$key = strtolower( (string) preg_replace( '/[^a-z0-9]/i', '', $g ) );
	$map = array(
		'onsite'       => 'onSite',
		'stripe'       => 'stripe',
		'paypal'       => 'payPal',
		'wc'           => 'wc',
		'woocommerce'  => 'wc',
		'mollie'       => 'mollie',
		'razorpay'     => 'razorpay',
		'square'       => 'square',
		'barion'       => 'barion',
	);

	if ( isset( $map[ $key ] ) ) {
		return $map[ $key ];
	}

	return new \WP_Error(
		'invalid_gateway',
		sprintf(
			/* translators: %s: comma-separated gateway names */
			__( 'Unknown gateway. Use one of: %s (aliases: paypal, onsite, woocommerce). Setting gateway only records how payment was taken — it does not charge a card or send a payment link.', 'harudigi-booking-abilities-for-amelia' ),
			implode( ', ', $allowed )
		)
	);
}

/**
 * @param mixed $raw Status string.
 * @return string|\WP_Error
 */
function normalize_payment_status( $raw ) {
	$s = trim( (string) $raw );
	if ( '' === $s ) {
		return new \WP_Error( 'invalid_status', __( 'status is required.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$allowed = payment_status_allowlist();
	if ( in_array( $s, $allowed, true ) ) {
		return $s;
	}

	$key = strtolower( (string) preg_replace( '/[^a-z0-9]/i', '', $s ) );
	$map = array(
		'paid'          => 'paid',
		'pending'       => 'pending',
		'partiallypaid' => 'partiallyPaid',
		'partial'       => 'partiallyPaid',
		'half'          => 'partiallyPaid',
		'refunded'      => 'refunded',
		'refund'        => 'refunded',
	);

	if ( isset( $map[ $key ] ) ) {
		return $map[ $key ];
	}

	return new \WP_Error(
		'invalid_status',
		sprintf(
			/* translators: %s: comma-separated statuses */
			__( 'Unknown payment status. Use one of: %s.', 'harudigi-booking-abilities-for-amelia' ),
			implode( ', ', $allowed )
		)
	);
}

/**
 * Payment fragment for create-appointment / book-event.
 *
 * @param array<string,mixed> $input Ability input.
 * @return array<string,mixed>|\WP_Error Amelia payment array (gateway, optional amount/status).
 */
function payment_from_booking_input( array $input ) {
	$payment_in = array();
	if ( isset( $input['payment'] ) && is_array( $input['payment'] ) ) {
		$payment_in = $input['payment'];
	}

	$gateway_raw = $payment_in['gateway'] ?? $input['paymentGateway'] ?? 'onSite';
	$gateway     = normalize_payment_gateway( $gateway_raw );
	if ( is_wp_error( $gateway ) ) {
		return $gateway;
	}

	$out = array(
		'gateway'          => $gateway,
		'isBackendBooking' => true,
	);

	if ( isset( $payment_in['amount'] ) || isset( $input['paymentAmount'] ) ) {
		$out['amount'] = (float) ( $payment_in['amount'] ?? $input['paymentAmount'] );
	}
	if ( isset( $payment_in['status'] ) || isset( $input['paymentStatus'] ) ) {
		$status = normalize_payment_status( $payment_in['status'] ?? $input['paymentStatus'] );
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		$out['status'] = $status;
	}
	$txn = $payment_in['transactionId'] ?? $input['transactionId'] ?? null;
	if ( null !== $txn && '' !== (string) $txn ) {
		$out['transactionId'] = sanitize_text_field( (string) $txn );
	}

	return $out;
}

/**
 * Strip list-enrichment keys; keep Amelia PaymentFactory fields.
 *
 * @param array<string,mixed> $existing From get-payment / repository.
 * @return array<string,mixed>
 */
function payment_entity_base( array $existing ): array {
	$entity = $existing['entity'] ?? $existing['type'] ?? 'appointment';
	$data   = $existing['data'] ?? '';
	if ( is_array( $data ) ) {
		$data = wp_json_encode( $data );
	}

	return array(
		'customerBookingId' => ! empty( $existing['customerBookingId'] ) ? (int) $existing['customerBookingId'] : null,
		'packageCustomerId' => ! empty( $existing['packageCustomerId'] ) ? (int) $existing['packageCustomerId'] : null,
		'parentId'          => ! empty( $existing['parentId'] ) ? (int) $existing['parentId'] : null,
		'amount'            => isset( $existing['amount'] ) ? (float) $existing['amount'] : 0.0,
		'dateTime'          => ! empty( $existing['dateTime'] ) ? (string) $existing['dateTime'] : gmdate( 'Y-m-d H:i:s' ),
		'status'            => ! empty( $existing['status'] ) ? (string) $existing['status'] : 'pending',
		'gateway'           => ! empty( $existing['gateway'] ) ? (string) $existing['gateway'] : 'onSite',
		'gatewayTitle'      => isset( $existing['gatewayTitle'] ) ? (string) $existing['gatewayTitle'] : '',
		'data'              => is_string( $data ) ? $data : '',
		'entity'            => (string) $entity,
		'transactionId'     => $existing['transactionId'] ?? null,
		'wcOrderId'         => ! empty( $existing['wcOrderId'] ) ? (int) $existing['wcOrderId'] : null,
	);
}

/**
 * Merge patch onto existing payment for UpdatePaymentController.
 *
 * @param array<string,mixed> $existing Existing payment row (enriched OK).
 * @param array<string,mixed> $patch    Fields to change.
 * @return array<string,mixed>|\WP_Error
 */
function merge_payment_update( array $existing, array $patch ) {
	$body = payment_entity_base( $existing );

	if ( isset( $patch['gateway'] ) || isset( $patch['paymentGateway'] ) ) {
		$gateway = normalize_payment_gateway( $patch['gateway'] ?? $patch['paymentGateway'] );
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}
		$body['gateway'] = $gateway;
	}

	if ( isset( $patch['status'] ) || isset( $patch['paymentStatus'] ) ) {
		$status = normalize_payment_status( $patch['status'] ?? $patch['paymentStatus'] );
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		$body['status'] = $status;
	}

	if ( isset( $patch['amount'] ) || isset( $patch['paymentAmount'] ) ) {
		$body['amount'] = (float) ( $patch['amount'] ?? $patch['paymentAmount'] );
	}

	if ( array_key_exists( 'transactionId', $patch ) ) {
		$txn = $patch['transactionId'];
		$body['transactionId'] = ( null === $txn || '' === (string) $txn )
			? null
			: sanitize_text_field( (string) $txn );
	}

	if ( isset( $patch['dateTime'] ) && '' !== (string) $patch['dateTime'] ) {
		$body['dateTime'] = sanitize_text_field( (string) $patch['dateTime'] );
	}

	if ( isset( $patch['gatewayTitle'] ) ) {
		$body['gatewayTitle'] = sanitize_text_field( (string) $patch['gatewayTitle'] );
	}

	if ( isset( $patch['customerBookingId'] ) ) {
		$body['customerBookingId'] = (int) $patch['customerBookingId'] ?: null;
	}
	if ( isset( $patch['packageCustomerId'] ) ) {
		$body['packageCustomerId'] = (int) $patch['packageCustomerId'] ?: null;
	}
	if ( isset( $patch['parentId'] ) ) {
		$body['parentId'] = (int) $patch['parentId'] ?: null;
	}
	if ( isset( $patch['entity'] ) ) {
		$body['entity'] = sanitize_key( (string) $patch['entity'] );
	}

	// Keep existing data blob; never overwrite with MCP input (may contain secrets).
	return $body;
}

/**
 * Build body for AddPaymentController.
 *
 * @param array<string,mixed> $input Ability input (flat or fields).
 * @return array<string,mixed>|\WP_Error
 */
function build_add_payment_body( array $input ) {
	$booking_id  = (int) ( $input['customerBookingId'] ?? $input['booking_id'] ?? 0 );
	$package_id  = (int) ( $input['packageCustomerId'] ?? 0 );
	if ( $booking_id <= 0 && $package_id <= 0 ) {
		return new \WP_Error(
			'invalid_fields',
			__( 'customerBookingId (or booking_id) or packageCustomerId is required.', 'harudigi-booking-abilities-for-amelia' )
		);
	}

	$gateway = normalize_payment_gateway( $input['gateway'] ?? $input['paymentGateway'] ?? 'onSite' );
	if ( is_wp_error( $gateway ) ) {
		return $gateway;
	}

	$status = normalize_payment_status( $input['status'] ?? $input['paymentStatus'] ?? 'pending' );
	if ( is_wp_error( $status ) ) {
		return $status;
	}

	$date = ! empty( $input['dateTime'] )
		? sanitize_text_field( (string) $input['dateTime'] )
		: current_time( 'mysql' );

	$entity = sanitize_key( (string) ( $input['entity'] ?? 'appointment' ) );
	if ( ! in_array( $entity, array( 'appointment', 'event', 'package' ), true ) ) {
		$entity = 'appointment';
	}

	$body = array(
		'customerBookingId' => $booking_id > 0 ? $booking_id : null,
		'packageCustomerId' => $package_id > 0 ? $package_id : null,
		'amount'            => isset( $input['amount'] ) ? (float) $input['amount'] : 0.0,
		'dateTime'          => $date,
		'status'            => $status,
		'gateway'           => $gateway,
		'gatewayTitle'      => isset( $input['gatewayTitle'] ) ? sanitize_text_field( (string) $input['gatewayTitle'] ) : '',
		'data'              => '',
		'entity'            => $entity,
		'actionsCompleted'  => 1,
	);

	if ( isset( $input['transactionId'] ) && '' !== (string) $input['transactionId'] ) {
		$body['transactionId'] = sanitize_text_field( (string) $input['transactionId'] );
	}

	return $body;
}

/**
 * Build Amelia payment-link payload from a Payment entity (same shape as GetPaymentLink).
 *
 * @param object $payment Amelia Payment entity.
 * @return array<string,mixed>|\WP_Error
 */
function payment_link_reservation_data( $payment ) {
	$container = Helpers::container();
	if ( is_wp_error( $container ) ) {
		return $container;
	}

	$entity = $payment->getEntity() ? $payment->getEntity()->getValue() : 'appointment';
	/** @var \AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface $reservation_service */
	$reservation_service = $container->get( 'application.reservation.service' )->get( $entity );
	$reservation_result  = $reservation_service->getReservationByPayment( $payment );
	$reservation         = is_object( $reservation_result ) && method_exists( $reservation_result, 'getData' )
		? $reservation_result->getData()
		: ( is_array( $reservation_result ) ? $reservation_result : null );

	if ( ! is_array( $reservation ) ) {
		return new \WP_Error( 'reservation_missing', __( 'Could not load booking for this payment.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$data = array(
		'type'      => $entity,
		'paymentId' => $payment->getId() ? $payment->getId()->getValue() : null,
		'customer'  => $reservation['customer'] ?? null,
		'booking'   => $reservation['booking'] ?? null,
		'bookable'  => $reservation['bookable'] ?? null,
	);

	if ( 'appointment' === $entity ) {
		$data['appointment'] = $reservation['appointment'] ?? null;
	} elseif ( 'event' === $entity ) {
		$data['event'] = $reservation['event'] ?? null;
	} elseif ( 'package' === $entity ) {
		$data['package']            = $reservation['bookable'] ?? null;
		$data['packageCustomerId']  = $payment->getPackageCustomerId() ? $payment->getPackageCustomerId()->getValue() : null;
		$data['packageCustomer']    = $reservation['packageCustomer'] ?? null;
		$data['packageReservations'] = empty( $reservation['booking'] )
			? array()
			: array_merge(
				array( $reservation['appointment'] ?? null ),
				array_column( $reservation['recurring'] ?? array(), 'appointment' )
			);
	}

	return $data;
}

/**
 * Sum non-pending, non-refunded payment amounts for a customer booking.
 *
 * @param array<int,array<string,mixed>> $payments Payment rows.
 * @return float
 */
function payment_paid_so_far( array $payments ): float {
	$sum = 0.0;
	foreach ( $payments as $payment ) {
		if ( ! is_array( $payment ) ) {
			continue;
		}
		$status = (string) ( $payment['status'] ?? '' );
		if ( 'refunded' === $status || 'pending' === $status ) {
			continue;
		}
		$sum += (float) ( $payment['amount'] ?? 0 );
	}
	return round( $sum, 2 );
}

/**
 * Booking total used by Amelia payment links.
 *
 * @param object                                                                              $payment Amelia Payment entity.
 * @param array<string,mixed>                                                                 $data    From payment_link_reservation_data().
 * @param \AmeliaBooking\Application\Services\Payment\PaymentApplicationService $payment_as Payment AS.
 * @return float|\WP_Error
 */
function payment_link_booking_total( $payment, array $data, $payment_as ) {
	$type = $data['type'] ?? 'appointment';
	$booking = $data['booking'] ?? null;
	$reservation = $data[ $type ] ?? null;
	if ( ! is_array( $booking ) || ! is_array( $reservation ) ) {
		return new \WP_Error( 'reservation_missing', __( 'Could not load booking for this payment.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	try {
		return (float) $payment_as->calculateAppointmentPrice( $booking, $type, $reservation );
	} catch ( \Throwable $e ) {
		return new \WP_Error( 'price_failed', $e->getMessage() );
	}
}

/**
 * Resolve charge amount for a payment link.
 *
 * @param mixed $amount_arg null|number|"half" Requested charge.
 * @param float $remaining  Remaining balance.
 * @return float|\WP_Error
 */
function resolve_payment_link_amount( $amount_arg, float $remaining ) {
	if ( null === $amount_arg || '' === $amount_arg ) {
		return $remaining;
	}
	if ( is_string( $amount_arg ) && 'half' === strtolower( trim( $amount_arg ) ) ) {
		if ( $remaining <= 0 ) {
			return new \WP_Error( 'payment_done', __( 'No balance due on this booking (already covered).', 'harudigi-booking-abilities-for-amelia' ) );
		}
		return (float) round( $remaining / 2 );
	}
	if ( ! is_numeric( $amount_arg ) ) {
		return new \WP_Error(
			'invalid_amount',
			__( 'amount must be a number (JPY) or "half".', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	$want = round( (float) $amount_arg, 2 );
	if ( $want <= 0 ) {
		return new \WP_Error( 'invalid_amount', __( 'amount must be greater than 0.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	if ( $want > $remaining + 0.009 ) {
		return new \WP_Error(
			'invalid_amount',
			sprintf(
				/* translators: 1: requested amount 2: remaining balance */
				__( 'amount %1$s exceeds remaining balance %2$s.', 'harudigi-booking-abilities-for-amelia' ),
				(string) $want,
				(string) $remaining
			)
		);
	}
	return $want;
}

/**
 * Generate a Stripe (or gateway) Checkout URL for a charge amount.
 *
 * Uses Amelia direct payment links (buy.stripe.com). Optional amount / "half"
 * for partial charges: temporarily seeds a paid row so Amelia's remaining-balance
 * math (and callback chargedAmount) match the Checkout total, then removes the seed.
 *
 * @param int         $payment_id Payment id (pending row to attach the link to).
 * @param string      $gateway    stripe|payPal|…
 * @param mixed|null  $amount_arg null = remaining; number = JPY; "half" = half remaining.
 * @return array<string,mixed>|\WP_Error
 */
function generate_payment_link( int $payment_id, string $gateway, $amount_arg = null ) {
	$gateway = normalize_payment_gateway( $gateway );
	if ( is_wp_error( $gateway ) ) {
		return $gateway;
	}
	if ( 'onSite' === $gateway ) {
		return new \WP_Error(
			'invalid_gateway',
			__( 'Payment links require an online gateway (stripe, payPal, …), not onSite.', 'harudigi-booking-abilities-for-amelia' )
		);
	}

	$container = Helpers::container();
	if ( is_wp_error( $container ) ) {
		return $container;
	}

	/** @var \AmeliaBooking\Infrastructure\Repository\Payment\PaymentRepository $payment_repo */
	$payment_repo = $container->get( 'domain.payment.repository' );
	$payment      = $payment_repo->getById( $payment_id );
	if ( ! $payment ) {
		return new \WP_Error( 'payment_missing', __( 'Payment not found.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$status = $payment->getStatus() ? $payment->getStatus()->getValue() : '';
	if ( 'paid' === $status ) {
		return new \WP_Error(
			'payment_done',
			__( 'This payment is already fully paid — no payment link.', 'harudigi-booking-abilities-for-amelia' )
		);
	}

	$settings = new \AmeliaBooking\Domain\Services\Settings\SettingsService(
		new \AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage()
	);
	$plink    = (array) ( $settings->getSetting( 'payments', 'paymentLinks' ) ?: array() );
	if ( empty( $plink['enabled'] ) ) {
		return new \WP_Error(
			'payment_links_disabled',
			__( 'Enable Amelia → Settings → Payments → Pay with payment link first.', 'harudigi-booking-abilities-for-amelia' )
		);
	}

	$data = payment_link_reservation_data( $payment );
	if ( is_wp_error( $data ) ) {
		return $data;
	}

	/** @var \AmeliaBooking\Application\Services\Payment\PaymentApplicationService $payment_as */
	$payment_as = $container->get( 'application.payment.service' );
	$total      = payment_link_booking_total( $payment, $data, $payment_as );
	if ( is_wp_error( $total ) ) {
		return $total;
	}

	$cb_id  = $payment->getCustomerBookingId() ? (int) $payment->getCustomerBookingId()->getValue() : 0;
	$pkg_id = $payment->getPackageCustomerId() ? (int) $payment->getPackageCustomerId()->getValue() : 0;
	if ( $pkg_id > 0 ) {
		$payments_col = $payment_repo->getByEntityId( $pkg_id, 'packageCustomerId' );
	} elseif ( $cb_id > 0 ) {
		$payments_col = $payment_repo->getByEntityId( $cb_id, 'customerBookingId' );
	} else {
		$payments_col = null;
	}
	$payment_rows = ( $payments_col && method_exists( $payments_col, 'toArray' ) ) ? $payments_col->toArray() : array();
	$paid_so_far  = payment_paid_so_far( is_array( $payment_rows ) ? $payment_rows : array() );
	$remaining    = round( max( 0, $total - $paid_so_far ), 2 );

	$charge = resolve_payment_link_amount( $amount_arg, $remaining );
	if ( is_wp_error( $charge ) ) {
		return $charge;
	}
	if ( $remaining <= 0 || $charge <= 0 ) {
		return new \WP_Error(
			'payment_done',
			__( 'No balance due on this booking (already covered).', 'harudigi-booking-abilities-for-amelia' )
		);
	}

	// Align link row amount with Checkout / callback chargedAmount.
	try {
		$payment->setAmount( new \AmeliaBooking\Domain\ValueObjects\Number\Float\Price( $charge ) );
		$payment_repo->update( $payment_id, $payment );
	} catch ( \Throwable $e ) {
		// Non-fatal.
	}

	// Amelia bakes chargedAmount into the callback URL BEFORE any amount filter.
	// Seed a temporary paid row so remaining math = $charge (callback + Checkout agree).
	$seed_id     = 0;
	$seed_needed = round( $remaining - $charge, 2 );
	if ( $seed_needed > 0.009 && $cb_id > 0 ) {
		$seed_body = build_add_payment_body(
			array(
				'customerBookingId' => $cb_id,
				'packageCustomerId' => $pkg_id > 0 ? $pkg_id : null,
				'entity'            => $payment->getEntity() ? $payment->getEntity()->getValue() : 'appointment',
				'amount'            => $seed_needed,
				'gateway'           => 'onSite',
				'status'            => 'paid',
				'gatewayTitle'      => 'MCP amount seed (temporary)',
			)
		);
		if ( is_wp_error( $seed_body ) ) {
			return $seed_body;
		}
		$seed_res = Helpers::invoke( AddPaymentController::class, $seed_body );
		if ( is_wp_error( $seed_res ) ) {
			return $seed_res;
		}
		$seed_row = isset( $seed_res['data']['payment'] ) ? $seed_res['data']['payment'] : ( $seed_res['payment'] ?? null );
		$seed_id  = (int) ( is_array( $seed_row ) ? ( $seed_row['id'] ?? 0 ) : 0 );
		// Amelia may ignore status on add — force paid.
		if ( $seed_id > 0 && ( ! is_array( $seed_row ) || ( $seed_row['status'] ?? '' ) !== 'paid' ) ) {
			$seed_get = Helpers::invoke( GetPaymentController::class, array(), array( 'id' => $seed_id ), 'GET' );
			$existing = is_array( $seed_get ) ? ( $seed_get['data']['payment'] ?? $seed_get['payment'] ?? null ) : null;
			if ( is_array( $existing ) ) {
				$patch = merge_payment_update( $existing, array( 'status' => 'paid', 'amount' => $seed_needed ) );
				if ( ! is_wp_error( $patch ) ) {
					Helpers::invoke( UpdatePaymentController::class, $patch, array( 'id' => $seed_id ) );
				}
			}
		}
		if ( $seed_id <= 0 ) {
			return new \WP_Error( 'seed_failed', __( 'Could not create temporary paid seed for partial payment link.', 'harudigi-booking-abilities-for-amelia' ) );
		}
		$payment = $payment_repo->getById( $payment_id );
		$data    = payment_link_reservation_data( $payment );
		if ( is_wp_error( $data ) ) {
			Helpers::invoke( DeletePaymentController::class, array(), array( 'id' => $seed_id ) );
			return $data;
		}
	}

	$payment_links = $payment_as->createPaymentLink(
		$data,
		0,
		null,
		array( $gateway => true ),
		null,
		true // direct gateway Checkout URL (buy.stripe.com)
	);

	if ( $seed_id > 0 ) {
		// Prefer refund (excluded from remaining math) — delete of paid rows can fail in Amelia.
		$seed_get = Helpers::invoke( GetPaymentController::class, array(), array( 'id' => $seed_id ), 'GET' );
		$existing = is_array( $seed_get ) ? ( $seed_get['data']['payment'] ?? $seed_get['payment'] ?? null ) : null;
		if ( is_array( $existing ) ) {
			$patch = merge_payment_update( $existing, array( 'status' => 'refunded', 'amount' => $seed_needed ) );
			if ( ! is_wp_error( $patch ) ) {
				Helpers::invoke( UpdatePaymentController::class, $patch, array( 'id' => $seed_id ) );
			}
		}
		$del = Helpers::invoke( DeletePaymentController::class, array(), array( 'id' => $seed_id ) );
		if ( is_wp_error( $del ) ) {
			// Refunded seed already ignored by Amelia remaining-balance math.
		}
	}

	if ( null === $payment_links ) {
		return new \WP_Error(
			'payment_done',
			__( 'No balance due on this booking (already covered).', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	if ( ! is_array( $payment_links ) || array() === $payment_links ) {
		return new \WP_Error(
			'payment_link_failed',
			__( 'Could not create payment link. Check Payment Links are enabled and the booking is not canceled.', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	if ( ! empty( $payment_links['payment_link_error_message'] ) ) {
		return new \WP_Error( 'payment_link_failed', (string) $payment_links['payment_link_error_message'] );
	}

	$url = '';
	$prefer_key = 'payment_link_' . ( 'payPal' === $gateway ? 'paypal' : $gateway );
	if ( ! empty( $payment_links[ $prefer_key ] ) && is_string( $payment_links[ $prefer_key ] ) ) {
		$url = $payment_links[ $prefer_key ];
	} else {
		foreach ( $payment_links as $key => $value ) {
			if ( is_string( $key ) && 0 === strpos( $key, 'payment_link_' ) && is_string( $value ) && '' !== $value ) {
				$url = $value;
				break;
			}
		}
	}
	if ( '' === $url ) {
		$first = array_values( $payment_links );
		$url   = isset( $first[0] ) && is_string( $first[0] ) ? $first[0] : '';
	}
	if ( '' === $url ) {
		return new \WP_Error( 'payment_link_failed', __( 'Payment link was empty.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	if ( false === strpos( $url, 'buy.stripe.com' ) && false === strpos( $url, 'checkout.stripe.com' ) ) {
		$resolved = resolve_checkout_redirect( $url );
		if ( is_string( $resolved ) && '' !== $resolved ) {
			$url = $resolved;
		}
	}

	$payments_cfg = (array) $settings->getCategorySettings( 'payments' );
	$gw_key       = 'payPal' === $gateway ? 'payPal' : $gateway;
	$gw_enabled   = ! empty( $payments_cfg[ $gw_key ]['enabled'] );
	$remaining_after = round( max( 0, $remaining - $charge ), 2 );

	$note = $gw_enabled
		? __( 'Send paymentLink (Stripe Checkout URL) to the client. After they pay: Amelia marks this payment paid, auto-approves when Payment Links → change status is on, and sends the approved customer email. chargedAmount is what Checkout collects.', 'harudigi-booking-abilities-for-amelia' )
		: sprintf(
			/* translators: %s: gateway name */
			__( 'Link generated, but %s is not enabled in Amelia Payments — enable and configure it before the client can pay.', 'harudigi-booking-abilities-for-amelia' ),
			$gateway
		);

	return array(
		'payment_id'       => $payment_id,
		'gateway'          => $gateway,
		'paymentLink'      => $url,
		'chargedAmount'    => $charge,
		'bookingTotal'     => $total,
		'paidSoFar'        => $paid_so_far,
		'remainingBefore'  => $remaining,
		'remainingAfter'   => $remaining_after,
		'links'            => $payment_links,
		'auto_approve'     => ! empty( $plink['changeBookingStatus'] ),
		'gateway_enabled'  => $gw_enabled,
		'note'             => $note,
	);
}

/**
 * Follow a single redirect to capture buy.stripe.com / checkout.stripe.com.
 *
 * @param string $url Amelia or gateway URL.
 * @return string|null
 */
function resolve_checkout_redirect( string $url ) {
	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 20,
			'redirection' => 0,
			'sslverify'   => false,
		)
	);
	if ( is_wp_error( $response ) ) {
		return null;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	$loc  = wp_remote_retrieve_header( $response, 'location' );
	if ( $code >= 300 && $code < 400 && is_string( $loc ) && '' !== $loc ) {
		if ( false !== strpos( $loc, 'stripe.com' ) ) {
			return $loc;
		}
		// One more hop.
		$response2 = wp_remote_get(
			$loc,
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'sslverify'   => false,
			)
		);
		if ( ! is_wp_error( $response2 ) ) {
			$loc2 = wp_remote_retrieve_header( $response2, 'location' );
			if ( is_string( $loc2 ) && false !== strpos( $loc2, 'stripe.com' ) ) {
				return $loc2;
			}
		}
	}
	return null;
}
