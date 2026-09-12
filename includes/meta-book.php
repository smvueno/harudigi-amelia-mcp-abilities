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
use AmeliaBooking\Domain\Entity\Entities;

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
	if ( 'notify' === $action || 'resend' === $action ) {
		return book_notify( $input );
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
		__( 'book action must be create|update|cancel|delete|set_status|notify|create_event|update_event|delete_event|book_event.', 'harudigi-booking-abilities-for-amelia' )
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

	// Build booking-level patch. Keep all existing bookings; merge onto target only.
	$booking_patch = array();
	foreach ( array( 'persons', 'status', 'duration', 'extras', 'customFields', 'couponCode' ) as $k ) {
		if ( array_key_exists( $k, $input ) ) {
			$booking_patch[ $k ] = $input[ $k ];
		}
	}
	if ( $booking_patch ) {
		$bookings = array();
		if ( ! empty( $existing['bookings'] ) && is_array( $existing['bookings'] ) ) {
			foreach ( $existing['bookings'] as $b ) {
				if ( is_array( $b ) ) {
					$bookings[] = $b;
				}
			}
		}
		if ( ! $bookings ) {
			return new \WP_Error( 'amelia_not_found', __( 'Appointment has no bookings to update.', 'harudigi-booking-abilities-for-amelia' ) );
		}
		$target_bid = (int) ( $input['booking_id'] ?? 0 );
		$service_id = (int) ( $existing['serviceId'] ?? $input['serviceId'] ?? 0 );
		$out        = array();
		$applied    = false;
		foreach ( $bookings as $i => $row_existing ) {
			$bid   = (int) ( $row_existing['id'] ?? 0 );
			// Explicit booking_id wins; otherwise patch first booking (legacy) and keep siblings.
			$apply = $target_bid ? ( $bid === $target_bid ) : ( 0 === $i );
			if ( ! $apply ) {
				$out[] = $row_existing;
				continue;
			}
			$applied = true;
			$row     = array_merge( $row_existing, $booking_patch );
			$row['id']         = $bid;
			$row['customerId'] = (int) ( $row['customerId'] ?? $row_existing['customerId'] ?? 0 );
			if ( isset( $row['customFields'] ) ) {
				$expanded = expand_custom_fields_for_booking( $row['customFields'], $service_id );
				if ( is_wp_error( $expanded ) ) {
					return $expanded;
				}
				if ( empty( $input['replaceCustomFields'] ) && ! empty( $row_existing['customFields'] ) ) {
					$existing_cf = normalize_booking_custom_fields( $row_existing['customFields'] );
					if ( is_string( $existing_cf ) ) {
						$decoded     = json_decode( $existing_cf, true );
						$existing_cf = is_array( $decoded ) ? $decoded : array();
					}
					$row['customFields'] = array_replace( is_array( $existing_cf ) ? $existing_cf : array(), $expanded );
				} else {
					$row['customFields'] = $expanded;
				}
			}
			$out[] = $row;
		}
		if ( $target_bid && ! $applied ) {
			return new \WP_Error( 'no_bookings', __( 'No matching booking_id on this appointment.', 'harudigi-booking-abilities-for-amelia' ) );
		}
		$patch['bookings'] = $out;
	}

	// Mute customer emails for this edit unless notify explicitly set.
	// Restore prior DB flag afterward so reminders stay intact when omitted.
	$prior_notify = (int) ! empty( $existing['notifyParticipants'] );
	$explicit_notify = array_key_exists( 'notify', $input ) || array_key_exists( 'notifyParticipants', $input );
	if ( $explicit_notify ) {
		$patch['notifyParticipants'] = notify_from_input( $input );
	} else {
		$patch['notifyParticipants'] = 0;
	}

	$body   = build_appointment_update_fields( $existing, $patch );
	$result = Helpers::invoke( UpdateAppointmentController::class, $body, array( 'id' => $id ) );
	if ( ! $explicit_notify && $prior_notify && ! is_wp_error( $result ) ) {
		restore_appointment_notify_participants( $id, 1 );
	}
	return $result;
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

/**
 * Manually send customer status emails for an appointment (no status/time change).
 * Requires confirm:true. Email channel only. Template must be enabled in Amelia.
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>|\WP_Error
 */
function book_notify( array $input ) {
	$ok = Helpers::require_confirm(
		$input,
		__( 'Set confirm=true only after the user approved sending customer emails now.', 'harudigi-booking-abilities-for-amelia' )
	);
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}

	$id = Helpers::parse_id( $input['id'] ?? $input['appointment_id'] ?? 0, 'id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	$got = Helpers::invoke( GetAppointmentController::class, array(), array( 'id' => $id ), 'GET' );
	if ( is_wp_error( $got ) ) {
		return $got;
	}

	$data = isset( $got['data'] ) && is_array( $got['data'] ) ? $got['data'] : $got;
	$appointment_array = $data['appointment'] ?? $data;
	if ( ! is_array( $appointment_array ) || empty( $appointment_array['bookings'] ) || ! is_array( $appointment_array['bookings'] ) ) {
		return new \WP_Error( 'amelia_not_found', __( 'Appointment not found.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$booking_filter = ! empty( $input['booking_id'] ) ? (int) $input['booking_id'] : 0;
	$marked         = array();
	$targets        = array();

	foreach ( $appointment_array['bookings'] as $booking ) {
		if ( ! is_array( $booking ) ) {
			continue;
		}
		$bid = (int) ( $booking['id'] ?? 0 );
		if ( $booking_filter && $bid !== $booking_filter ) {
			continue;
		}
		$targets[] = $booking;
		$marked[]  = $bid;
	}

	if ( ! $marked ) {
		return new \WP_Error(
			'no_bookings',
			__( 'No matching bookings to notify.', 'harudigi-booking-abilities-for-amelia' )
		);
	}

	$appointment_array['type']               = $appointment_array['type'] ?? Entities::APPOINTMENT;
	$appointment_array['notifyParticipants'] = true;
	$appointment_array['isBackend']          = true;

	$container = Helpers::container();
	if ( is_wp_error( $container ) ) {
		return $container;
	}

	$attempted = array();
	$skipped   = array();

	try {
		/** @var \AmeliaBooking\Application\Services\Notification\EmailNotificationService $email */
		$email = $container->get( 'application.emailNotification.service' );

		foreach ( $targets as $booking ) {
			$bid    = (int) ( $booking['id'] ?? 0 );
			$status = sanitize_key( (string) ( $booking['status'] ?? $appointment_array['status'] ?? '' ) );
			$name   = 'customer_' . $appointment_array['type'] . '_' . $status;
			if ( ! customer_email_template_enabled( $email, $name, $appointment_array ) ) {
				$skipped[] = array(
					'bookingId' => $bid,
					'status'    => $status,
					'reason'    => 'no_enabled_template',
					'template'  => $name,
				);
				continue;
			}
			$email->sendCustomerBookingNotification( $appointment_array, $booking );
			$attempted[] = array(
				'bookingId' => $bid,
				'status'    => $status,
				'template'  => $name,
				'channel'   => 'email',
			);
		}
	} catch ( \Throwable $e ) {
		return new \WP_Error(
			'notify_failed',
			sprintf(
				/* translators: %s: error message */
				__( 'Failed to send notifications: %s', 'harudigi-booking-abilities-for-amelia' ),
				$e->getMessage()
			)
		);
	}

	if ( ! $attempted && $skipped ) {
		return new \WP_Error(
			'no_enabled_template',
			__( 'No enabled customer email template for this booking status. Enable it in Amelia → Notifications.', 'harudigi-booking-abilities-for-amelia' ),
			array( 'skipped' => $skipped )
		);
	}

	return array(
		'ok'            => true,
		'appointmentId' => $id,
		'bookingIds'    => $marked,
		'status'        => $appointment_array['status'] ?? null,
		'channels'      => array( 'email' ),
		'attempted'     => $attempted,
		'skipped'       => $skipped,
		'hint'          => __( 'Queued customer status emails (email only). Delivery still depends on wp_mail / SMTP.', 'harudigi-booking-abilities-for-amelia' ),
	);
}

/**
 * Persist notifyParticipants without firing Amelia edit events (reminder flag restore).
 */
function restore_appointment_notify_participants( int $appointment_id, int $value ): void {
	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'amelia_appointments',
		array( 'notifyParticipants' => $value ? 1 : 0 ),
		array( 'id' => $appointment_id ),
		array( '%d' ),
		array( '%d' )
	);
}

/**
 * @param \AmeliaBooking\Application\Services\Notification\EmailNotificationService $email
 * @param array<string,mixed>                                                       $appointment_array
 */
function customer_email_template_enabled( $email, string $name, array $appointment_array ): bool {
	try {
		$notifications = $email->getByNameAndType( $name, 'email' );
	} catch ( \Throwable $e ) {
		return false;
	}
	if ( ! $notifications || ! method_exists( $notifications, 'getItems' ) ) {
		return false;
	}
	$send_default = $email->sendDefault( $notifications, $appointment_array );
	foreach ( $notifications->getItems() as $notification ) {
		$status = $notification->getStatus() ? $notification->getStatus()->getValue() : '';
		if ( 'enabled' !== $status ) {
			continue;
		}
		if ( $email->checkCustom( $notification, $appointment_array, $send_default ) ) {
			return true;
		}
	}
	return false;
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
