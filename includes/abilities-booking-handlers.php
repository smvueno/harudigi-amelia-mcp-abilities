<?php
/**
 * Booking ability handlers + shared schemas.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use AmeliaBooking\Application\Controller\Booking\Appointment\DeleteAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\GetAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateAppointmentNoteController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateAppointmentStatusController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateAppointmentTimeController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateBookingStatusController;
use AmeliaBooking\Application\Controller\Booking\Event\DeleteEventController;
use AmeliaBooking\Application\Controller\Booking\Event\GetEventBookingController;
use AmeliaBooking\Application\Controller\Booking\Event\GetEventBookingsController;
use AmeliaBooking\Application\Controller\Booking\Event\GetEventController;
use AmeliaBooking\Application\Controller\Booking\Event\UpdateEventController;
use AmeliaBooking\Application\Controller\Booking\Event\UpdateEventStatusController;
use AmeliaBooking\Application\Controller\Booking\Event\UpdateEventVisibilityController;
use AmeliaBooking\Application\Controller\Notification\GetNotificationsController;
use AmeliaBooking\Application\Controller\Payment\AddPaymentController;
use AmeliaBooking\Application\Controller\Payment\DeletePaymentController;
use AmeliaBooking\Application\Controller\Payment\GetPaymentController;
use AmeliaBooking\Application\Controller\Payment\GetPaymentsController;
use AmeliaBooking\Application\Controller\Payment\UpdatePaymentController;
use AmeliaBooking\Application\Controller\User\Customer\GetCustomerController;
use AmeliaBooking\Application\Controller\User\Customer\UpdateCustomerController;
use AmeliaBooking\Application\Controller\User\Customer\UpdateCustomerNoteController;
use AmeliaBooking\Application\Controller\User\Customer\UpdateCustomerStatusController;
use AmeliaBooking\Application\Controller\User\DeleteUserController;

/** @return array<string,mixed> */
function catalog_fields_id( string $id_field ): array {
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( $id_field, 'fields' ),
		'properties'           => array(
			$id_field => array( 'type' => 'integer' ),
			'fields'  => array( 'type' => 'object' ),
		),
	);
}

/** @return array<string,mixed> */
function catalog_status_id( string $id_field ): array {
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( $id_field, 'status' ),
		'properties'           => array(
			$id_field => array( 'type' => 'integer' ),
			'status'  => array( 'type' => 'string' ),
		),
	);
}

/** @return array<string,mixed> */
function booking_status_id( string $id_field ): array {
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( $id_field, 'status' ),
		'properties'           => array(
			$id_field => array( 'type' => 'integer' ),
			'status'  => array(
				'type'        => 'string',
				'description' => 'approved|pending|canceled|rejected|no-show',
				'enum'        => array( 'approved', 'pending', 'canceled', 'rejected', 'no-show' ),
			),
		),
	);
}

function ability_get_appointment( array $i = array() ) {
	$id = Helpers::parse_id( $i['appointment_id'] ?? 0, 'appointment_id' );
	return is_wp_error( $id ) ? $id : Helpers::invoke( GetAppointmentController::class, array(), array( 'id' => $id ), 'GET' );
}
function ability_get_event( array $i = array() ) {
	$id = Helpers::parse_id( $i['event_id'] ?? 0, 'event_id' );
	return is_wp_error( $id ) ? $id : Helpers::invoke( GetEventController::class, array(), array( 'id' => $id ), 'GET' );
}
function ability_get_customer( array $i = array() ) {
	$id = Helpers::parse_id( $i['customer_id'] ?? 0, 'customer_id' );
	return is_wp_error( $id ) ? $id : Helpers::invoke( GetCustomerController::class, array(), array( 'id' => $id ), 'GET' );
}
function ability_list_event_bookings( array $i = array() ) {
	$params = Helpers::list_params( $i );
	if ( ! empty( $i['event_id'] ) ) {
		$params['events'] = array( (int) $i['event_id'] );
	}
	return Helpers::invoke( GetEventBookingsController::class, $params, array(), 'GET' );
}
function ability_get_event_booking( array $i = array() ) {
	$id = Helpers::parse_id( $i['booking_id'] ?? 0, 'booking_id' );
	return is_wp_error( $id ) ? $id : Helpers::invoke( GetEventBookingController::class, array(), array( 'id' => $id ), 'GET' );
}
function ability_list_payments( array $i = array() ) {
	return Helpers::invoke( GetPaymentsController::class, Helpers::list_params( $i ), array(), 'GET' );
}
function ability_get_payment( array $i = array() ) {
	$id = Helpers::parse_id( $i['payment_id'] ?? 0, 'payment_id' );
	return is_wp_error( $id ) ? $id : Helpers::invoke( GetPaymentController::class, array(), array( 'id' => $id ), 'GET' );
}

function ability_get_payment_link( array $i = array() ) {
	require_once __DIR__ . '/payment-payload.php';
	$id = Helpers::parse_id( $i['payment_id'] ?? 0, 'payment_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$gateway = sanitize_text_field( (string) ( $i['gateway'] ?? 'stripe' ) );
	return generate_payment_link( $id, $gateway );
}

/**
 * Extract payment array from get-payment invoke result.
 *
 * @param mixed $res Invoke result.
 * @return array<string,mixed>|\WP_Error
 */
function payment_row_from_get_result( $res ) {
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

function ability_add_payment( array $i = array() ) {
	require_once __DIR__ . '/payment-payload.php';
	$fields = Helpers::body_from_input( $i, array( 'payment_id' ) );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	$body = build_add_payment_body( $fields );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	return Helpers::invoke( AddPaymentController::class, $body );
}

function ability_update_payment( array $i = array() ) {
	require_once __DIR__ . '/payment-payload.php';
	$id = Helpers::parse_id( $i['payment_id'] ?? 0, 'payment_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	$existing_res = Helpers::invoke( GetPaymentController::class, array(), array( 'id' => $id ), 'GET' );
	$existing     = payment_row_from_get_result( $existing_res );
	if ( is_wp_error( $existing ) ) {
		return $existing;
	}

	$patch = Helpers::body_from_input( $i, array( 'payment_id' ) );
	if ( is_wp_error( $patch ) ) {
		$patch = $i;
		unset( $patch['payment_id'], $patch['confirm'], $patch['fields'] );
	}
	if ( ! $patch ) {
		return new \WP_Error( 'invalid_fields', __( 'Provide gateway, status, amount, and/or transactionId to update.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$body = merge_payment_update( $existing, $patch );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	return Helpers::invoke( UpdatePaymentController::class, $body, array( 'id' => $id ) );
}

function ability_delete_payment( array $i = array() ) {
	$ok = Helpers::require_confirm( $i );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$id = Helpers::parse_id( $i['payment_id'] ?? 0, 'payment_id' );
	return is_wp_error( $id ) ? $id : Helpers::invoke( DeletePaymentController::class, array(), array( 'id' => $id ) );
}

function ability_list_notifications( array $i = array() ) {
	unset( $i );
	return Helpers::invoke( GetNotificationsController::class, array(), array(), 'GET' );
}

function ability_update_appointment( array $i = array() ) {
	$id = Helpers::parse_id( $i['appointment_id'] ?? 0, 'appointment_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$patch = Helpers::body_from_input( $i, array( 'appointment_id' ) );
	if ( is_wp_error( $patch ) ) {
		return $patch;
	}
	require_once __DIR__ . '/booking-payload.php';

	$existing_res = Helpers::invoke( GetAppointmentController::class, array(), array( 'id' => $id ), 'GET' );
	if ( is_wp_error( $existing_res ) ) {
		return $existing_res;
	}
	$existing = $existing_res['data']['appointment'] ?? ( $existing_res['appointment'] ?? $existing_res );
	if ( ! is_array( $existing ) ) {
		return new \WP_Error( 'amelia_not_found', __( 'Appointment not found.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$fields                       = build_appointment_update_fields( $existing, $patch );
	$fields['id']                 = $id;
	$fields['notifyParticipants'] = array_key_exists( 'notifyParticipants', $patch )
		? $patch['notifyParticipants']
		: 0;
	if ( ! array_key_exists( 'removedBookings', $fields ) ) {
		$fields['removedBookings'] = array();
	}
	return Helpers::invoke( UpdateAppointmentController::class, $fields, array( 'id' => $id ) );
}
function ability_update_appointment_status( array $i = array() ) {
	$id = Helpers::parse_id( $i['appointment_id'] ?? 0, 'appointment_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$status = sanitize_text_field( (string) ( $i['status'] ?? '' ) );
	$ok     = Helpers::assert_booking_status( $status );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	return Helpers::invoke( UpdateAppointmentStatusController::class, array( 'status' => $status ), array( 'id' => $id ) );
}
function ability_update_appointment_note( array $i = array() ) {
	$id = Helpers::parse_id( $i['appointment_id'] ?? 0, 'appointment_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	return Helpers::invoke( UpdateAppointmentNoteController::class, array( 'internalNotes' => (string) ( $i['internal_notes'] ?? '' ) ), array( 'id' => $id ) );
}
function ability_update_appointment_time( array $i = array() ) {
	$id = Helpers::parse_id( $i['appointment_id'] ?? 0, 'appointment_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$body = array( 'bookingStart' => (string) ( $i['booking_start'] ?? '' ) );
	if ( ! empty( $i['time_zone'] ) ) {
		$body['timeZone'] = (string) $i['time_zone'];
	}
	return Helpers::invoke( UpdateAppointmentTimeController::class, $body, array( 'id' => $id ) );
}
function ability_delete_appointment( array $i = array() ) {
	$ok = Helpers::require_confirm( $i );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$id = Helpers::parse_id( $i['appointment_id'] ?? 0, 'appointment_id' );
	return is_wp_error( $id ) ? $id : Helpers::invoke( DeleteAppointmentController::class, array(), array( 'id' => $id ) );
}
function ability_update_booking_status( array $i = array() ) {
	$id = Helpers::parse_id( $i['booking_id'] ?? 0, 'booking_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$status = sanitize_text_field( (string) ( $i['status'] ?? '' ) );
	$ok     = Helpers::assert_booking_status( $status );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$type = sanitize_key( (string) ( $i['type'] ?? 'appointment' ) );
	if ( ! in_array( $type, array( 'appointment', 'event' ), true ) ) {
		return new \WP_Error( 'invalid_type', __( 'type must be appointment or event.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	return Helpers::invoke(
		UpdateBookingStatusController::class,
		array(
			'status' => $status,
			'type'   => $type,
		),
		array( 'id' => $id )
	);
}

function ability_update_event( array $i = array() ) {
	$id = Helpers::parse_id( $i['event_id'] ?? 0, 'event_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$fields = Helpers::body_from_input( $i, array( 'event_id' ) );
	return is_wp_error( $fields ) ? $fields : Helpers::invoke( UpdateEventController::class, $fields, array( 'id' => $id ) );
}
function ability_update_event_status( array $i = array() ) {
	$id = Helpers::parse_id( $i['event_id'] ?? 0, 'event_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$status = sanitize_text_field( (string) ( $i['status'] ?? '' ) );
	$ok     = Helpers::assert_booking_status( $status );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	return Helpers::invoke(
		UpdateEventStatusController::class,
		array(
			'status'        => $status,
			'applyGlobally' => ! empty( $i['apply_globally'] ),
		),
		array( 'id' => $id )
	);
}
function ability_update_event_visibility( array $i = array() ) {
	$id = Helpers::parse_id( $i['event_id'] ?? 0, 'event_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$fields = Helpers::body_from_input( $i, array( 'event_id' ) );
	return is_wp_error( $fields ) ? $fields : Helpers::invoke( UpdateEventVisibilityController::class, $fields, array( 'id' => $id ) );
}
function ability_delete_event( array $i = array() ) {
	$ok = Helpers::require_confirm( $i );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$id = Helpers::parse_id( $i['event_id'] ?? 0, 'event_id' );
	return is_wp_error( $id ) ? $id : Helpers::invoke( DeleteEventController::class, array(), array( 'id' => $id ) );
}

function ability_update_customer( array $i = array() ) {
	$id = Helpers::parse_id( $i['customer_id'] ?? 0, 'customer_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$fields = Helpers::body_from_input( $i, array( 'customer_id' ) );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	$fields['type'] = 'customer';
	// Never link/unlink WP users via MCP.
	unset( $fields['externalId'], $fields['external_id'] );
	return Helpers::invoke( UpdateCustomerController::class, $fields, array( 'id' => $id ) );
}
function ability_update_customer_status( array $i = array() ) {
	$id = Helpers::parse_id( $i['customer_id'] ?? 0, 'customer_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$status = sanitize_text_field( (string) ( $i['status'] ?? '' ) );
	$ok     = Helpers::assert_entity_status( $status );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	return Helpers::invoke( UpdateCustomerStatusController::class, array( 'status' => $status ), array( 'id' => $id ) );
}
function ability_update_customer_note( array $i = array() ) {
	$id = Helpers::parse_id( $i['customer_id'] ?? 0, 'customer_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	return Helpers::invoke( UpdateCustomerNoteController::class, array( 'note' => (string) ( $i['note'] ?? '' ) ), array( 'id' => $id ) );
}
function ability_delete_customer( array $i = array() ) {
	$ok = Helpers::require_confirm( $i );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$id = Helpers::parse_id( $i['customer_id'] ?? 0, 'customer_id' );
	return is_wp_error( $id ) ? $id : Helpers::invoke( DeleteUserController::class, array(), array( 'id' => $id ) );
}

