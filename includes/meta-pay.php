<?php
/**
 * Meta pay handlers.
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
use AmeliaBooking\Application\Controller\Payment\GetPaymentsController;
use AmeliaBooking\Application\Controller\Payment\UpdatePaymentController;

/**
 * Payment ids attached to a customer booking, when cheap to resolve.
 *
 * @return int[]
 */
function pay_candidate_ids_for_booking( int $booking_id ): array {
	if ( $booking_id <= 0 ) {
		return array();
	}
	try {
		$container = Helpers::container();
		if ( is_wp_error( $container ) ) {
			return array();
		}
		$repo = $container->get( 'domain.payment.repository' );
		$col  = $repo->getByEntityId( $booking_id, 'customerBookingId' );
		$rows = ( $col && method_exists( $col, 'toArray' ) ) ? $col->toArray() : array();
		$ids  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$pid = is_array( $row ) ? (int) ( $row['id'] ?? 0 ) : ( is_object( $row ) && method_exists( $row, 'getId' ) && $row->getId() ? (int) $row->getId()->getValue() : 0 );
			if ( $pid > 0 ) {
				$ids[] = $pid;
			}
		}
		return array_values( array_unique( $ids ) );
	} catch ( \Throwable $e ) {
		return array();
	}
}

/**
 * Parse a payment id, or explain the booking-id mix-up when that was supplied instead.
 *
 * @param array<string,mixed> $input
 * @return int|\WP_Error
 */
function pay_id_or_wrong_type( array $input ) {
	$id = Helpers::parse_id( $input['id'] ?? $input['payment_id'] ?? 0, 'id' );
	if ( ! is_wp_error( $id ) ) {
		return $id;
	}
	$booking_id = (int) ( $input['customerBookingId'] ?? $input['booking_id'] ?? $input['fields']['customerBookingId'] ?? $input['fields']['booking_id'] ?? 0 );
	if ( $booking_id <= 0 ) {
		return $id;
	}
	$candidates = pay_candidate_ids_for_booking( $booking_id );
	$message    = __( 'This action needs a payment id, not a booking id. The id field must be a payment id.', 'harudigi-booking-abilities-for-amelia' );
	if ( $candidates ) {
		$message .= ' ' . sprintf(
			/* translators: 1: booking id 2: payment ids */
			__( 'Payment ids for booking %1$d: %2$s. Retry with id set to one of those payment ids.', 'harudigi-booking-abilities-for-amelia' ),
			$booking_id,
			implode( ', ', $candidates )
		);
	} else {
		$message .= ' ' . __( 'Suggested call: amelia/pay action=list to find the payment id for this booking.', 'harudigi-booking-abilities-for-amelia' );
	}
	return new \WP_Error(
		'wrong_id_type',
		$message,
		array(
			'customerBookingId'   => $booking_id,
			'candidatePaymentIds' => $candidates,
			'suggestedCall'       => 'amelia/pay action=list',
		)
	);
}

function meta_pay( array $input = array() ) {
	$action = strtolower( (string) ( $input['action'] ?? '' ) );

	if ( 'list' === $action ) {
		return Helpers::invoke( GetPaymentsController::class, Helpers::list_params( $input ), array(), 'GET' );
	}
	if ( 'get' === $action ) {
		$id = pay_id_or_wrong_type( $input );
		return is_wp_error( $id ) ? $id : Helpers::invoke( GetPaymentController::class, array(), array( 'id' => $id ), 'GET' );
	}
	if ( 'add' === $action ) {
		$fields = Helpers::body_from_input( $input, array( 'action', 'id', 'confirm' ) );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$body = build_add_payment_body( $fields );
		return is_wp_error( $body ) ? $body : Helpers::invoke( AddPaymentController::class, $body );
	}
	if ( 'update' === $action ) {
		$id = pay_id_or_wrong_type( $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$existing_res = Helpers::invoke( GetPaymentController::class, array(), array( 'id' => $id ), 'GET' );
		$existing     = payment_row_from_result( $existing_res );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		$patch = Helpers::body_from_input( $input, array( 'action', 'id', 'payment_id', 'confirm' ) );
		if ( is_wp_error( $patch ) ) {
			$patch = array();
			foreach ( array( 'gateway', 'status', 'amount', 'transactionId' ) as $k ) {
				if ( isset( $input[ $k ] ) ) {
					$patch[ $k ] = $input[ $k ];
				}
			}
		}
		$body = merge_payment_update( $existing, $patch );
		return is_wp_error( $body ) ? $body : Helpers::invoke( UpdatePaymentController::class, $body, array( 'id' => $id ) );
	}
	if ( 'delete' === $action ) {
		$id = pay_id_or_wrong_type( $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$ok = Helpers::require_confirm( $input );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return Helpers::invoke( DeletePaymentController::class, array(), array( 'id' => $id ) );
	}
	if ( 'link' === $action ) {
		$id = pay_id_or_wrong_type( $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$gateway = sanitize_text_field( (string) ( $input['gateway'] ?? 'stripe' ) );
		$amount  = $input['amount'] ?? ( isset( $input['fields']['amount'] ) ? $input['fields']['amount'] : null );
		return generate_payment_link( $id, $gateway, $amount );
	}

	return new \WP_Error( 'invalid_action', __( 'pay action must be list|get|add|update|delete|link.', 'harudigi-booking-abilities-for-amelia' ) );
}

/**
 * @param mixed $res
 * @return array<string,mixed>|\WP_Error
 */
function payment_row_from_result( $res ) {
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	if ( ! is_array( $res ) ) {
		return new \WP_Error( 'payment_missing', __( 'Payment not found.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	if ( isset( $res['data']['payment'] ) && is_array( $res['data']['payment'] ) ) {
		return $res['data']['payment'];
	}
	if ( isset( $res['payment'] ) && is_array( $res['payment'] ) ) {
		return $res['payment'];
	}
	return new \WP_Error( 'payment_missing', __( 'Payment not found.', 'harudigi-booking-abilities-for-amelia' ) );
}
