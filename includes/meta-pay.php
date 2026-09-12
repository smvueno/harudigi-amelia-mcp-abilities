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

function meta_pay( array $input = array() ) {
	$action = strtolower( (string) ( $input['action'] ?? '' ) );

	if ( 'list' === $action ) {
		return Helpers::invoke( GetPaymentsController::class, Helpers::list_params( $input ), array(), 'GET' );
	}
	if ( 'get' === $action ) {
		$id = Helpers::parse_id( $input['id'] ?? $input['payment_id'] ?? 0, 'id' );
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
		$id = Helpers::parse_id( $input['id'] ?? $input['payment_id'] ?? 0, 'id' );
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
		$ok = Helpers::require_confirm( $input );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$id = Helpers::parse_id( $input['id'] ?? $input['payment_id'] ?? 0, 'id' );
		return is_wp_error( $id ) ? $id : Helpers::invoke( DeletePaymentController::class, array(), array( 'id' => $id ) );
	}
	if ( 'link' === $action ) {
		$id = Helpers::parse_id( $input['id'] ?? $input['payment_id'] ?? 0, 'id' );
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
