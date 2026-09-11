<?php
/**
 * Meta book handlers.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AmeliaBooking\Application\Controller\Booking\Appointment\AddAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\AddBookingController;
use AmeliaBooking\Application\Controller\Booking\Appointment\DeleteAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\GetAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateAppointmentStatusController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateBookingStatusController;
use AmeliaBooking\Application\Controller\Booking\Event\AddEventController;
use AmeliaBooking\Application\Controller\Booking\Event\DeleteEventController;
use AmeliaBooking\Application\Controller\Booking\Event\GetEventController;
use AmeliaBooking\Application\Controller\Booking\Event\UpdateEventController;

function meta_book( array $input = array() ) {
	$action = strtolower( (string) ( $input['action'] ?? '' ) );

	if ( 'create' === $action ) {
		return book_create_appointment( $input );
	}
	if ( 'update' === $action ) {
		return book_update_appointment( $input );
	}
	if ( 'cancel' === $action || 'set_status' === $action ) {
		return book_set_status( $input, 'cancel' === $action ? 'canceled' : null );
	}
	if ( 'delete' === $action ) {
		$ok = Helpers::require_confirm( $input );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$id = Helpers::parse_id( $input['id'] ?? $input['appointment_id'] ?? 0, 'id' );
		return is_wp_error( $id ) ? $id : Helpers::invoke( DeleteAppointmentController::class, array(), array( 'id' => $id ) );
	}
	if ( 'create_event' === $action ) {
		$fields = Helpers::body_from_input( $input, array( 'action', 'id', 'confirm', 'notify' ) );
		return is_wp_error( $fields ) ? $fields : Helpers::invoke( AddEventController::class, $fields );
	}
	if ( 'update_event' === $action ) {
		$id = Helpers::parse_id( $input['id'] ?? $input['eventId'] ?? 0, 'id' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$existing_res = Helpers::invoke( GetEventController::class, array(), array( 'id' => $id ), 'GET' );
		if ( is_wp_error( $existing_res ) ) {
			return $existing_res;
		}
		$data     = isset( $existing_res['data'] ) && is_array( $existing_res['data'] ) ? $existing_res['data'] : $existing_res;
		$existing = $data['event'] ?? $data;
		if ( ! is_array( $existing ) ) {
			return new \WP_Error( 'amelia_not_found', __( 'Event not found.', 'harudigi-booking-abilities-for-amelia' ) );
		}
		$fields = Helpers::body_from_input( $input, array( 'action', 'id', 'eventId', 'confirm', 'notify' ) );
		if ( is_wp_error( $fields ) ) {
			$fields = array();
		}
		$body       = array_merge( $existing, $fields );
		$body['id'] = $id;
		return Helpers::invoke( UpdateEventController::class, $body, array( 'id' => $id ) );
	}
	if ( 'delete_event' === $action ) {
		$ok = Helpers::require_confirm( $input );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$id = Helpers::parse_id( $input['id'] ?? $input['eventId'] ?? 0, 'id' );
		return is_wp_error( $id ) ? $id : Helpers::invoke( DeleteEventController::class, array(), array( 'id' => $id ) );
	}
	if ( 'book_event' === $action ) {
		return book_event( $input );
	}

	return new \WP_Error(
		'invalid_action',
		__( 'book action must be create|update|cancel|delete|set_status|create_event|update_event|delete_event|book_event.', 'harudigi-booking-abilities-for-amelia' )
	);
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function book_create_appointment( array $input ) {
	$service_id  = (int) ( $input['serviceId'] ?? 0 );
	$provider_id = (int) ( $input['providerId'] ?? 0 );
	$customer_id = (int) ( $input['customerId'] ?? 0 );
	$start       = sanitize_text_field( (string) ( $input['bookingStart'] ?? '' ) );
	if ( $service_id <= 0 || $provider_id <= 0 || $customer_id <= 0 || '' === $start ) {
		return new \WP_Error(
			'invalid_fields',
			__( 'serviceId, providerId, customerId, and bookingStart are required.', 'harudigi-booking-abilities-for-amelia' )
		);
	}

	if ( array_key_exists( 'status', $input ) && '' !== (string) $input['status'] ) {
		$status = sanitize_key( (string) $input['status'] );
		$check  = Helpers::assert_booking_status( $status );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
	} else {
		$status = get_default_appointment_status( $service_id );
	}

	$booking = array(
		'customerId' => $customer_id,
		'persons'    => max( 1, (int) ( $input['persons'] ?? 1 ) ),
		'status'     => $status,
	);

	$duration = parse_duration_seconds( $input['duration'] ?? 0 );
	if ( $duration > 0 ) {
		$booking['duration'] = $duration;
	}

	$extras = normalize_booking_extras( $input['extras'] ?? array(), 'extraId' );
	if ( $extras ) {
		$booking['extras'] = $extras;
	}

	$cf = expand_custom_fields_for_booking( $input['customFields'] ?? null, $service_id );
	if ( is_wp_error( $cf ) ) {
		return $cf;
	}
	if ( $cf ) {
		$booking['customFields'] = $cf;
	}

	if ( ! empty( $input['couponCode'] ) ) {
		$booking['couponCode'] = sanitize_text_field( (string) $input['couponCode'] );
	}

	$body = array(
		'serviceId'          => $service_id,
		'providerId'         => $provider_id,
		'bookingStart'       => $start,
		'notifyParticipants' => notify_from_input( $input ),
		'internalNotes'      => ! empty( $input['internalNotes'] ) ? sanitize_textarea_field( (string) $input['internalNotes'] ) : '',
		'locationId'         => ! empty( $input['locationId'] ) ? (int) $input['locationId'] : null,
		'recurring'          => array(),
		'bookings'           => array( $booking ),
	);

	$payment = payment_from_booking_input( $input );
	if ( is_wp_error( $payment ) ) {
		return $payment;
	}
	$body['payment'] = $payment;

	$result = Helpers::invoke( AddAppointmentController::class, $body );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( is_array( $result ) ) {
		$result['applied_status'] = $status;
		$result['notify']         = (bool) $body['notifyParticipants'];
		$result['hint']           = __( 'Status came from input or Amelia defaultAppointmentStatus. notify defaulted false unless set.', 'harudigi-booking-abilities-for-amelia' );
	}
	return $result;
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function book_update_appointment( array $input ) {
	$id = Helpers::parse_id( $input['id'] ?? $input['appointment_id'] ?? 0, 'id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	$existing_res = Helpers::invoke( GetAppointmentController::class, array(), array( 'id' => $id ), 'GET' );
	if ( is_wp_error( $existing_res ) ) {
		return $existing_res;
	}
	$data     = isset( $existing_res['data'] ) && is_array( $existing_res['data'] ) ? $existing_res['data'] : $existing_res;
	$existing = $data['appointment'] ?? $data;
	if ( ! is_array( $existing ) ) {
		return new \WP_Error( 'amelia_not_found', __( 'Appointment not found.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$patch = array();
	if ( isset( $input['fields'] ) && is_array( $input['fields'] ) ) {
		$patch = $input['fields'];
	}
	foreach ( array( 'bookingStart', 'providerId', 'serviceId', 'locationId', 'internalNotes' ) as $k ) {
		if ( array_key_exists( $k, $input ) ) {
			$patch[ $k ] = $input[ $k ];
		}
	}

	// Build booking-level patch on first booking if extras/CF/persons/status/duration provided.
	$booking_patch = array();
	foreach ( array( 'persons', 'status', 'duration', 'extras', 'customFields', 'couponCode' ) as $k ) {
		if ( array_key_exists( $k, $input ) ) {
			$booking_patch[ $k ] = $input[ $k ];
		}
	}
	if ( $booking_patch ) {
		$bookings = $existing['bookings'] ?? array();
		$first    = ( is_array( $bookings ) && isset( $bookings[0] ) && is_array( $bookings[0] ) ) ? $bookings[0] : array();
		$bid      = (int) ( $first['id'] ?? 0 );
		$row      = array_merge( array( 'id' => $bid, 'customerId' => (int) ( $first['customerId'] ?? 0 ) ), $booking_patch );
		if ( isset( $row['customFields'] ) ) {
			$service_id = (int) ( $existing['serviceId'] ?? $input['serviceId'] ?? 0 );
			$expanded   = expand_custom_fields_for_booking( $row['customFields'], $service_id );
			if ( is_wp_error( $expanded ) ) {
				return $expanded;
			}
			if ( empty( $input['replaceCustomFields'] ) && ! empty( $first['customFields'] ) ) {
				$existing_cf = normalize_booking_custom_fields( $first['customFields'] );
				if ( is_string( $existing_cf ) ) {
					$decoded     = json_decode( $existing_cf, true );
					$existing_cf = is_array( $decoded ) ? $decoded : array();
				}
				$row['customFields'] = array_replace( is_array( $existing_cf ) ? $existing_cf : array(), $expanded );
			} else {
				$row['customFields'] = $expanded;
			}
		}
		$patch['bookings'] = array( $row );
	}

	if ( array_key_exists( 'notify', $input ) || array_key_exists( 'notifyParticipants', $input ) ) {
		$patch['notifyParticipants'] = notify_from_input( $input );
	} else {
		$patch['notifyParticipants'] = 0;
	}

	$body = build_appointment_update_fields( $existing, $patch );
	return Helpers::invoke( UpdateAppointmentController::class, $body, array( 'id' => $id ) );
}

/**
 * @param array<string,mixed> $input
 * @param string|null         $force_status
 * @return array<string,mixed>|\WP_Error
 */
function book_set_status( array $input, $force_status = null ) {
	if ( null !== $force_status ) {
		$ok = Helpers::require_confirm( $input );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
	}
	$id = Helpers::parse_id( $input['id'] ?? $input['appointment_id'] ?? 0, 'id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$status = $force_status ?? sanitize_key( (string) ( $input['status'] ?? '' ) );
	$check  = Helpers::assert_booking_status( $status );
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	// Prefer booking-level status when booking_id given.
	if ( ! empty( $input['booking_id'] ) ) {
		$bid = Helpers::parse_id( $input['booking_id'], 'booking_id' );
		if ( is_wp_error( $bid ) ) {
			return $bid;
		}
		return Helpers::invoke(
			UpdateBookingStatusController::class,
			array(
				'status'             => $status,
				'notifyParticipants' => notify_from_input( $input ),
			),
			array( 'id' => $bid )
		);
	}

	return Helpers::invoke(
		UpdateAppointmentStatusController::class,
		array(
			'status'             => $status,
			'notifyParticipants' => notify_from_input( $input ),
		),
		array( 'id' => $id )
	);
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function book_event( array $input ) {
	$event_id    = (int) ( $input['eventId'] ?? $input['id'] ?? 0 );
	$customer_id = (int) ( $input['customerId'] ?? 0 );
	if ( $event_id <= 0 || $customer_id <= 0 ) {
		return new \WP_Error( 'invalid_fields', __( 'eventId and customerId are required.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	$status = array_key_exists( 'status', $input ) && '' !== (string) $input['status']
		? sanitize_key( (string) $input['status'] )
		: get_default_appointment_status( 0 );

	$booking = array(
		'eventId'    => $event_id,
		'customerId' => $customer_id,
		'persons'    => max( 1, (int) ( $input['persons'] ?? 1 ) ),
		'status'     => $status,
		'customer'   => array( 'id' => $customer_id ),
	);
	$cf = expand_custom_fields_for_booking( $input['customFields'] ?? null, 0 );
	if ( is_wp_error( $cf ) ) {
		return $cf;
	}
	if ( $cf ) {
		$booking['customFields'] = $cf;
	}
	$payment = payment_from_booking_input( $input );
	if ( is_wp_error( $payment ) ) {
		return $payment;
	}
	return Helpers::invoke(
		AddBookingController::class,
		array(
			'type'                         => 'event',
			'eventId'                      => $event_id,
			'notifyParticipants'           => notify_from_input( $input ),
			'runInstantPostBookingActions' => true,
			'isBackendOrCabinet'           => true,
			'payment'                      => $payment,
			'bookings'                     => array( $booking ),
		)
	);
}

/**
 * Expand simple {"3":"value"} maps into Amelia customFields objects.
 *
 * @param mixed $raw
 * @return array<string,mixed>|\WP_Error
 */
function expand_custom_fields_for_booking( $raw, int $service_id ) {
	$cf = normalize_booking_custom_fields( $raw );
	if ( ! $cf || ! is_array( $cf ) ) {
		return array();
	}

	$needs_expand = false;
	foreach ( $cf as $v ) {
		if ( ! is_array( $v ) ) {
			$needs_expand = true;
			break;
		}
	}
	if ( ! $needs_expand ) {
		return $cf;
	}

	$defs = list_custom_fields_for_entity( $service_id, false );
	if ( is_wp_error( $defs ) ) {
		$defs = array();
	}
	$by_id = array();
	foreach ( $defs as $d ) {
		$by_id[ (string) $d['id'] ] = $d;
	}

	$out = array();
	foreach ( $cf as $key => $value ) {
		$kid = (string) $key;
		if ( is_array( $value ) ) {
			$out[ $kid ] = $value;
			continue;
		}
		$def         = $by_id[ $kid ] ?? array( 'label' => '', 'type' => 'text' );
		$out[ $kid ] = array(
			'label' => (string) ( $def['label'] ?? '' ),
			'type'  => (string) ( $def['type'] ?? 'text' ),
			'value' => $value,
		);
	}
	return $out;
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
