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
use AmeliaBooking\Application\Controller\Bookable\Service\GetServiceController;
use AmeliaBooking\Application\Controller\Coupon\GetCouponsController;
use AmeliaBooking\Application\Controller\User\Customer\GetCustomerController;
use AmeliaBooking\Application\Controller\User\Provider\GetProviderController;
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

/**
 * Create-time existence checks, run before any preview is stored.
 * Provider assignment is only enforced when the service lists providers.
 *
 * @return true|\WP_Error
 */
function book_assert_create_parties( int $service_id, int $provider_id, int $customer_id ) {
	$customer = Helpers::invoke( GetCustomerController::class, array(), array( 'id' => $customer_id ), 'GET' );
	if ( is_wp_error( $customer ) ) {
		return new \WP_Error(
			'customer_not_found',
			sprintf( /* translators: %d: customer id */ __( 'Customer %d not found. Create the customer first or use an existing customerId.', 'harudigi-booking-abilities-for-amelia' ), $customer_id )
		);
	}
	$service_res = Helpers::invoke( GetServiceController::class, array(), array( 'id' => $service_id ), 'GET' );
	if ( is_wp_error( $service_res ) ) {
		return new \WP_Error(
			'service_not_found',
			sprintf( /* translators: %d: service id */ __( 'Service %d not found. Use an existing serviceId.', 'harudigi-booking-abilities-for-amelia' ), $service_id )
		);
	}
	$provider = Helpers::invoke( GetProviderController::class, array(), array( 'id' => $provider_id ), 'GET' );
	if ( is_wp_error( $provider ) ) {
		return new \WP_Error(
			'provider_not_for_service',
			sprintf( /* translators: 1: provider id 2: service id */ __( 'Provider %1$d not found for service %2$d. Use a provider assigned to this service.', 'harudigi-booking-abilities-for-amelia' ), $provider_id, $service_id )
		);
	}
	$data    = isset( $service_res['data'] ) && is_array( $service_res['data'] ) ? $service_res['data'] : $service_res;
	$service = $data['service'] ?? $data;
	$assigned = array();
	if ( is_array( $service ) ) {
		foreach ( (array) ( $service['providers'] ?? $service['employees'] ?? array() ) as $row ) {
			$pid = is_array( $row ) ? (int) ( $row['id'] ?? $row['providerId'] ?? $row['userId'] ?? 0 ) : (int) $row;
			if ( $pid > 0 ) {
				$assigned[] = $pid;
			}
		}
	}
	if ( $assigned && ! in_array( $provider_id, $assigned, true ) ) {
		return new \WP_Error(
			'provider_not_for_service',
			sprintf( /* translators: 1: provider id 2: service id */ __( 'Provider %1$d is not assigned to service %2$d. Assigned provider ids: %3$s.', 'harudigi-booking-abilities-for-amelia' ), $provider_id, $service_id, implode( ', ', $assigned ) )
		);
	}
	return true;
}

/**
 * Coupon code must exist (case-insensitive). Fail open when the lookup itself fails.
 *
 * @return true|\WP_Error
 */
function book_assert_coupon_code( string $code ) {
	$code = trim( $code );
	if ( '' === $code ) {
		return true;
	}
	$listed = Helpers::invoke( GetCouponsController::class, array(), array(), 'GET' );
	if ( is_wp_error( $listed ) ) {
		return true;
	}
	$data = isset( $listed['data'] ) && is_array( $listed['data'] ) ? $listed['data'] : $listed;
	$rows = $data['coupons'] ?? null;
	if ( ! is_array( $rows ) ) {
		return true;
	}
	$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $code ) : strtolower( $code );
	foreach ( $rows as $row ) {
		if ( is_array( $row ) && '' !== (string) ( $row['code'] ?? '' ) ) {
			$known = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $row['code'] ) : strtolower( (string) $row['code'] );
			if ( $known === $needle ) {
				return true;
			}
		}
	}
	return new \WP_Error(
		'unknown_coupon',
		sprintf( /* translators: %s: coupon code */ __( 'Unknown coupon code "%s". Check the code or omit couponCode.', 'harudigi-booking-abilities-for-amelia' ), $code )
	);
}

/**
 * Booking text that lands in utf8 (3-byte) columns: reject 4-byte chars early.
 *
 * @param mixed $custom_fields
 * @param mixed $internal_notes
 * @return true|\WP_Error
 */
function book_assert_plain_booking_text( $internal_notes, $custom_fields ) {
	$check = Helpers::assert_plain_text_field( 'internalNotes', $internal_notes );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	return Helpers::assert_plain_text_custom_fields( $custom_fields );
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function book_create_appointment( array $input ) {
	$mode = book_require_write_mode( $input );
	if ( is_wp_error( $mode ) ) {
		return $mode;
	}

	$service_id  = (int) ( $input['serviceId'] ?? 0 );
	$provider_id = (int) ( $input['providerId'] ?? 0 );
	$customer_id = (int) ( $input['customerId'] ?? 0 );
	$raw_start   = $input['bookingStart'] ?? ( $input['fields']['bookingStart'] ?? '' );
	if ( is_string( $raw_start ) && '' !== trim( $raw_start ) ) {
		$normalized = normalize_booking_start( $raw_start );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		$input['bookingStart'] = $normalized;
		if ( isset( $input['fields'] ) && is_array( $input['fields'] ) && array_key_exists( 'bookingStart', $input['fields'] ) ) {
			$input['fields']['bookingStart'] = $normalized;
		}
		$start = $normalized;
	} else {
		$start = '';
	}
	if ( $service_id <= 0 || $provider_id <= 0 || $customer_id <= 0 || '' === $start ) {
		return new \WP_Error(
			'invalid_fields',
			__( 'serviceId, providerId, customerId, and bookingStart are required.', 'harudigi-booking-abilities-for-amelia' )
		);
	}

	// Validate before preview storage so dry_run cannot approve a bad write.
	if ( array_key_exists( 'status', $input ) && '' !== (string) $input['status'] ) {
		$status_check = Helpers::assert_booking_status( sanitize_key( (string) $input['status'] ) );
		if ( is_wp_error( $status_check ) ) {
			return $status_check;
		}
	}
	$text_check = book_assert_plain_booking_text(
		$input['internalNotes'] ?? ( $input['fields']['internalNotes'] ?? '' ),
		$input['customFields'] ?? ( $input['fields']['customFields'] ?? null )
	);
	if ( is_wp_error( $text_check ) ) {
		return $text_check;
	}
	$parties = book_assert_create_parties( $service_id, $provider_id, $customer_id );
	if ( is_wp_error( $parties ) ) {
		return $parties;
	}
	$coupon_check = book_assert_coupon_code( (string) ( $input['couponCode'] ?? ( $input['fields']['couponCode'] ?? '' ) ) );
	if ( is_wp_error( $coupon_check ) ) {
		return $coupon_check;
	}

	if ( isset( $input['customFields'] ) ) {
		$cf_check = expand_custom_fields_for_booking( $input['customFields'], $service_id );
		if ( is_wp_error( $cf_check ) ) {
			return $cf_check;
		}
	}

	if ( isset( $input['extras'] ) ) {
		$ex_check = validate_booking_extras_for_service( $input['extras'], $service_id );
		if ( is_wp_error( $ex_check ) ) {
			return $ex_check;
		}
	}

	$fp_payload = book_create_fingerprint_payload( $input );
	if ( 'dry_run' === $mode ) {
		$preview = array(
			'success' => true,
			'target'  => array(
				'serviceId'    => $service_id,
				'providerId'   => $provider_id,
				'customerId'   => $customer_id,
				'bookingStart' => $start,
				'persons'      => max( 1, (int) ( $input['persons'] ?? 1 ) ),
				'internalNotes'=> (string) ( $input['internalNotes'] ?? '' ),
			),
			'changes'   => array(
				array(
					'field'  => 'create',
					'before' => null,
					'after'  => $fp_payload,
				),
			),
			'unchanged' => array(),
			'warnings'  => array( 'create' ),
		);
		$preview['summary'] = book_create_preview_summary( $input, $service_id, $provider_id, $customer_id, $start );
		return book_store_preview( 'create', $fp_payload, $preview );
	}

	$ok = book_consume_preview( (string) ( $input['previewId'] ?? '' ), 'create', $fp_payload );
	if ( is_wp_error( $ok ) ) {
		return $ok;
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

/**
 * Human booking card for a create dry_run preview. Never fails the preview:
 * every lookup falls back to ids or omits the field.
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>
 */
function book_create_preview_summary( array $input, int $service_id, int $provider_id, int $customer_id, string $start ): array {
	$persons       = max( 1, (int) ( $input['persons'] ?? 1 ) );
	$email_client  = (bool) notify_from_input( $input );
	$service_label = sprintf( 'Service %d', $service_id );
	$opts          = array();
	try {
		$maybe = get_service_booking_options( $service_id );
		if ( is_array( $maybe ) ) {
			$opts = $maybe;
		}
	} catch ( \Throwable $e ) {
		$opts = array();
	}
	if ( ! empty( $opts['name'] ) ) {
		$service_label = (string) $opts['name'];
	}

	$duration = parse_duration_seconds( $input['duration'] ?? 0 );
	if ( $duration <= 0 && ! empty( $opts['default_duration'] ) ) {
		$duration = (int) $opts['default_duration'];
	}
	$duration_label = $duration > 0 ? format_duration_label( $duration ) : '';
	$end            = null;
	if ( $duration > 0 && '' !== $start ) {
		try {
			$dt = new \DateTime( $start, wp_timezone() );
			$dt->modify( '+' . $duration . ' seconds' );
			$end = $dt->format( 'Y-m-d H:i' );
		} catch ( \Throwable $e ) {
			$end = null;
		}
	}

	$extras_out   = array();
	$extras_total = 0.0;
	$catalog      = array();
	foreach ( (array) ( $opts['extras'] ?? array() ) as $ex ) {
		if ( is_array( $ex ) && ! empty( $ex['id'] ) ) {
			$catalog[ (int) $ex['id'] ] = $ex;
		}
	}
	foreach ( normalize_booking_extras( $input['extras'] ?? array(), 'extraId' ) as $row ) {
		$eid = (int) $row['extraId'];
		$qty = (int) $row['quantity'];
		if ( isset( $catalog[ $eid ] ) ) {
			$price        = isset( $catalog[ $eid ]['price'] ) ? (float) $catalog[ $eid ]['price'] : null;
			$extras_out[] = array(
				'name'     => (string) ( $catalog[ $eid ]['name'] ?? '' ),
				'quantity' => $qty,
				'price'    => $price,
			);
			if ( null !== $price ) {
				$extras_total += $price * $qty;
			}
		} else {
			$extras_out[] = array(
				'extraId'  => $eid,
				'quantity' => $qty,
			);
		}
	}

	$total = null;
	if ( $opts ) {
		$base = null;
		foreach ( (array) ( $opts['duration_tiers'] ?? array() ) as $tier ) {
			if ( is_array( $tier ) && $duration > 0 && (int) ( $tier['duration'] ?? 0 ) === $duration && isset( $tier['price'] ) && is_numeric( $tier['price'] ) ) {
				$base = (float) $tier['price'];
				break;
			}
		}
		if ( null === $base && isset( $opts['default_price'] ) && is_numeric( $opts['default_price'] ) ) {
			$base = (float) $opts['default_price'];
		}
		if ( null !== $base ) {
			$total = $base + $extras_total;
		}
	}

	if ( array_key_exists( 'status', $input ) && '' !== (string) $input['status'] ) {
		$status_label = (string) $input['status'];
	} else {
		try {
			$status_label = sprintf( 'site default (%s)', get_default_appointment_status( $service_id ) );
		} catch ( \Throwable $e ) {
			$status_label = 'site default';
		}
	}

	$summary = array(
		'service'        => $service_label,
		'when'           => $start,
		'end'            => $end,
		'durationLabel'  => $duration_label,
		'persons'        => $persons,
		'extras'         => $extras_out,
		'estimatedTotal' => $total,
		'status'         => $status_label,
		'emailToClient'  => $email_client,
	);

	// Provider and customer names, only when cheap to resolve.
	try {
		$pres = Helpers::invoke( GetProviderController::class, array(), array( 'id' => $provider_id ), 'GET' );
		if ( is_array( $pres ) ) {
			$pdata = isset( $pres['data'] ) && is_array( $pres['data'] ) ? $pres['data'] : $pres;
			$puser = $pdata['user'] ?? $pdata['provider'] ?? $pdata;
			if ( is_array( $puser ) ) {
				$pname = trim( (string) ( $puser['firstName'] ?? '' ) . ' ' . (string) ( $puser['lastName'] ?? '' ) );
				if ( '' !== $pname ) {
					$summary['provider'] = $pname;
				}
			}
		}
	} catch ( \Throwable $e ) {
		// Omit provider.
	}
	try {
		$cres = Helpers::invoke( GetCustomerController::class, array(), array( 'id' => $customer_id ), 'GET' );
		if ( is_array( $cres ) ) {
			$cdata = isset( $cres['data'] ) && is_array( $cres['data'] ) ? $cres['data'] : $cres;
			$cuser = $cdata['user'] ?? $cdata['customer'] ?? $cdata;
			if ( is_array( $cuser ) ) {
				$cname = trim( (string) ( $cuser['firstName'] ?? '' ) . ' ' . (string) ( $cuser['lastName'] ?? '' ) );
				if ( '' !== $cname || ! empty( $cuser['email'] ) ) {
					$summary['customer'] = array(
						'name'  => $cname,
						'email' => (string) ( $cuser['email'] ?? '' ),
					);
				}
			}
		}
	} catch ( \Throwable $e ) {
		// Omit customer.
	}

	// Notes: private note plus booking fields rendered by label.
	$fields_out = array();
	try {
		if ( isset( $input['customFields'] ) ) {
			$expanded = expand_custom_fields_for_booking( $input['customFields'], $service_id );
			if ( ! is_wp_error( $expanded ) ) {
				$label_by_id = array();
				foreach ( (array) ( $opts['custom_fields'] ?? array() ) as $f ) {
					if ( is_array( $f ) && ! empty( $f['id'] ) ) {
						$label_by_id[ (int) $f['id'] ] = (string) ( $f['label'] ?? '' );
					}
				}
				foreach ( decode_booking_custom_fields( $expanded ) as $row ) {
					$fields_out[] = array(
						'label' => '' !== $row['label'] ? $row['label'] : ( $label_by_id[ $row['id'] ] ?? (string) $row['id'] ),
						'value' => is_scalar( $row['value'] ) || null === $row['value'] ? $row['value'] : wp_json_encode( $row['value'] ),
					);
				}
			}
		}
	} catch ( \Throwable $e ) {
		$fields_out = array();
	}
	$summary['notes'] = array(
		'privateNote'     => (string) ( $input['internalNotes'] ?? '' ),
		'sharedAndFields' => $fields_out,
	);

	$lines   = array();
	$lines[] = 'Service: ' . $service_label . ( '' !== $duration_label ? ', ' . $duration_label : '' );
	$lines[] = 'When: ' . $start;
	if ( ! empty( $summary['customer'] ) ) {
		$c       = $summary['customer'];
		$lines[] = 'Customer: ' . $c['name'] . ( '' !== $c['email'] ? ' (' . $c['email'] . ')' : '' );
	}
	if ( ! empty( $summary['provider'] ) ) {
		$lines[] = 'Provider: ' . $summary['provider'];
	}
	$lines[] = 'Persons: ' . $persons;
	if ( $extras_out ) {
		$parts = array();
		foreach ( $extras_out as $ex ) {
			$parts[] = ( $ex['name'] ?? ( 'Extra ' . ( $ex['extraId'] ?? '' ) ) ) . ' x' . $ex['quantity'];
		}
		$lines[] = 'Extras: ' . implode( ', ', $parts );
	}
	if ( null !== $total ) {
		$lines[] = 'Estimated total: ' . ( $total === floor( $total ) ? (string) (int) $total : (string) $total );
	}
	$lines[] = 'Status: ' . $status_label;
	$lines[] = 'Email to client: ' . ( $email_client ? 'yes' : 'no' );
	if ( '' !== $summary['notes']['privateNote'] ) {
		$lines[] = 'Private note: ' . $summary['notes']['privateNote'];
	}
	foreach ( $fields_out as $f ) {
		$lines[] = $f['label'] . ': ' . ( is_scalar( $f['value'] ) ? (string) $f['value'] : '' );
	}
	$summary['lines'] = $lines;

	return $summary;
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function book_update_appointment( array $input ) {
	$mode = book_require_write_mode( $input );
	if ( is_wp_error( $mode ) ) {
		return $mode;
	}

	$id = Helpers::parse_id( $input['id'] ?? $input['appointment_id'] ?? 0, 'id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	$existing_res = Helpers::invoke( GetAppointmentController::class, array(), array( 'id' => $id ), 'GET' );
	if ( is_wp_error( $existing_res ) ) {
		$msg = $existing_res->get_error_message();
		return new \WP_Error(
			$existing_res->get_error_code(),
			$msg . ' ' . __( 'Hint: amelia/book id must be appointmentId (not bookingId). Use list/get slim rows for aptId vs bookId.', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	$data     = isset( $existing_res['data'] ) && is_array( $existing_res['data'] ) ? $existing_res['data'] : $existing_res;
	$existing = $data['appointment'] ?? $data;
	if ( ! is_array( $existing ) ) {
		return new \WP_Error( 'amelia_not_found', __( 'Appointment not found. id must be appointmentId, not bookingId.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$patch = book_collect_update_patch_keys( $input );
	if ( array_key_exists( 'bookingStart', $patch ) ) {
		$normalized = normalize_booking_start( $patch['bookingStart'] );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		$patch['bookingStart'] = $normalized;
		if ( array_key_exists( 'bookingStart', $input ) ) {
			$input['bookingStart'] = $normalized;
		}
		if ( isset( $input['fields'] ) && is_array( $input['fields'] ) && array_key_exists( 'bookingStart', $input['fields'] ) ) {
			$input['fields']['bookingStart'] = $normalized;
		}
	}
	$concern = book_assert_single_concern( $patch );
	if ( is_wp_error( $concern ) ) {
		return $concern;
	}

	list( , $customer ) = book_first_booking_customer( $existing );
	$expected = (string) ( $input['expectedCustomer'] ?? '' );
	$match    = book_assert_expected_customer( $customer, $expected );
	if ( is_wp_error( $match ) ) {
		return $match;
	}

	if ( isset( $patch['customFields'] ) ) {
		$cf_check = expand_custom_fields_for_booking( $patch['customFields'], (int) ( $existing['serviceId'] ?? $input['serviceId'] ?? 0 ) );
		if ( is_wp_error( $cf_check ) ) {
			return $cf_check;
		}
	}

	if ( isset( $patch['extras'] ) ) {
		$effective_service_id = ! empty( $patch['serviceId'] ) ? (int) $patch['serviceId'] : (int) ( $existing['serviceId'] ?? $input['serviceId'] ?? 0 );
		$ex_check             = validate_booking_extras_for_service( $patch['extras'], $effective_service_id );
		if ( is_wp_error( $ex_check ) ) {
			return $ex_check;
		}
	}

	// Validate before preview storage so dry_run cannot approve a bad write.
	if ( array_key_exists( 'status', $patch ) && '' !== (string) $patch['status'] ) {
		$status_check = Helpers::assert_booking_status( sanitize_key( (string) $patch['status'] ) );
		if ( is_wp_error( $status_check ) ) {
			return $status_check;
		}
	}
	$text_check = book_assert_plain_booking_text(
		$patch['internalNotes'] ?? '',
		$patch['customFields'] ?? null
	);
	if ( is_wp_error( $text_check ) ) {
		return $text_check;
	}
	if ( ! empty( $patch['couponCode'] ) ) {
		$coupon_check = book_assert_coupon_code( (string) $patch['couponCode'] );
		if ( is_wp_error( $coupon_check ) ) {
			return $coupon_check;
		}
	}

	$fp_payload = book_update_fingerprint_payload( $id, $input, $patch );
	if ( 'dry_run' === $mode ) {
		$preview = book_build_update_preview( $existing, $patch, $expected );
		// Cheap booking card from the preview target (no extra lookups).
		$target  = is_array( $preview['target'] ?? null ) ? $preview['target'] : array();
		$cname   = trim( (string) ( $target['customer'] ?? '' ) );
		$cemail  = (string) ( $target['email'] ?? '' );
		$u_email = (bool) notify_from_input( $input );
		$u_lines = array();
		if ( '' !== $cname || '' !== $cemail ) {
			$u_lines[] = 'Customer: ' . $cname . ( '' !== $cemail ? ' (' . $cemail . ')' : '' );
		}
		if ( ! empty( $target['start'] ) ) {
			$u_lines[] = 'When: ' . $target['start'];
		}
		$u_lines[]        = 'Email to client: ' . ( $u_email ? 'yes' : 'no' );
		$preview['summary'] = array(
			'customer'      => array(
				'name'  => $cname,
				'email' => $cemail,
			),
			'when'          => $target['start'] ?? null,
			'emailToClient' => $u_email,
			'lines'         => $u_lines,
		);
		return book_store_preview( 'update', $fp_payload, $preview );
	}

	$ok = book_consume_preview( (string) ( $input['previewId'] ?? '' ), 'update', $fp_payload );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}

	// Same unified patch as the preview/fingerprint: booking-level keys from
	// fields{} behave identically to top-level keys.
	$patch = book_collect_update_patch_keys( $input );

	// Build booking-level patch. Keep all existing bookings; merge onto target only.
	$booking_patch = array();
	foreach ( array( 'persons', 'status', 'duration', 'extras', 'customFields', 'couponCode' ) as $k ) {
		if ( array_key_exists( $k, $patch ) ) {
			$booking_patch[ $k ] = $patch[ $k ];
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

	// Booking-level keys live on bookings[], not on the appointment root.
	foreach ( array( 'persons', 'status', 'duration', 'extras', 'customFields', 'couponCode' ) as $k ) {
		unset( $patch[ $k ] );
	}

	$body   = build_appointment_update_fields( $existing, $patch );
	$result = Helpers::invoke( UpdateAppointmentController::class, $body, array( 'id' => $id ) );
	if ( ! $explicit_notify && $prior_notify && ! is_wp_error( $result ) ) {
		restore_appointment_notify_participants( $id, 1 );
	}
	if ( is_wp_error( $result ) || ! is_array( $result ) ) {
		return $result;
	}

	// Read-back proof: re-fetch and compare attempted fields to the before snapshot.
	$attempted_patch = book_collect_update_patch_keys( $input );
	$target_bid      = (int) ( $input['booking_id'] ?? 0 );
	$after_res       = Helpers::invoke( GetAppointmentController::class, array(), array( 'id' => $id ), 'GET' );
	$changed         = array();
	if ( ! is_wp_error( $after_res ) && is_array( $after_res ) ) {
		$after_data = isset( $after_res['data'] ) && is_array( $after_res['data'] ) ? $after_res['data'] : $after_res;
		$after_apt  = $after_data['appointment'] ?? $after_data;
		if ( is_array( $after_apt ) ) {
			$changed = book_update_changed_map( $existing, $after_apt, $attempted_patch, $target_bid );
		}
	}
	$result['persisted'] = ! empty( $changed );
	$result['changed']   = $changed;
	return $result;
}

/**
 * Booking row targeted by an update (booking_id wins, else first booking).
 *
 * @param array<string,mixed> $appointment
 * @return array<string,mixed>|null
 */
function book_target_booking( array $appointment, int $target_bid ) {
	if ( ! empty( $appointment['bookings'] ) && is_array( $appointment['bookings'] ) ) {
		foreach ( $appointment['bookings'] as $b ) {
			if ( ! is_array( $b ) ) {
				continue;
			}
			if ( $target_bid && (int) ( $b['id'] ?? 0 ) === $target_bid ) {
				return $b;
			}
		}
		if ( ! $target_bid && ! empty( $appointment['bookings'][0] ) && is_array( $appointment['bookings'][0] ) ) {
			return $appointment['bookings'][0];
		}
	}
	return null;
}

/**
 * Map of attempted fields that actually differ between before/after snapshots.
 *
 * @param array<string,mixed> $before
 * @param array<string,mixed> $after
 * @param array<string,mixed> $patch Attempted patch (unified).
 * @return array<string, array{before:mixed,after:mixed}>
 */
function book_update_changed_map( array $before, array $after, array $patch, int $target_bid ): array {
	$before_booking = book_target_booking( $before, $target_bid );
	$after_booking  = book_target_booking( $after, $target_bid );
	$changed        = array();
	foreach ( array( 'internalNotes', 'bookingStart', 'status', 'persons', 'extras', 'customFields' ) as $field ) {
		if ( ! array_key_exists( $field, $patch ) ) {
			continue;
		}
		if ( 'customFields' === $field ) {
			$bv = booking_custom_field_values_by_id( is_array( $before_booking ) ? ( $before_booking['customFields'] ?? null ) : null );
			$av = booking_custom_field_values_by_id( is_array( $after_booking ) ? ( $after_booking['customFields'] ?? null ) : null );
		} elseif ( 'internalNotes' === $field ) {
			$bv = (string) ( $before['internalNotes'] ?? '' );
			$av = (string) ( $after['internalNotes'] ?? '' );
		} elseif ( 'bookingStart' === $field ) {
			$bv = $before['bookingStart'] ?? null;
			$av = $after['bookingStart'] ?? null;
		} elseif ( 'extras' === $field ) {
			$bv = book_extras_preview_shape( is_array( $before_booking ) ? ( $before_booking['extras'] ?? array() ) : array() );
			$av = book_extras_preview_shape( is_array( $after_booking ) ? ( $after_booking['extras'] ?? array() ) : array() );
		} elseif ( 'persons' === $field ) {
			$bv = is_array( $before_booking ) && isset( $before_booking['persons'] ) ? (int) $before_booking['persons'] : null;
			$av = is_array( $after_booking ) && isset( $after_booking['persons'] ) ? (int) $after_booking['persons'] : null;
		} else { // status.
			$bv = is_array( $before_booking ) ? ( $before_booking['status'] ?? ( $before['status'] ?? null ) ) : ( $before['status'] ?? null );
			$av = is_array( $after_booking ) ? ( $after_booking['status'] ?? ( $after['status'] ?? null ) ) : ( $after['status'] ?? null );
		}
		if ( $bv !== $av ) {
			$changed[ $field ] = array(
				'before' => $bv,
				'after'  => $av,
			);
		}
	}
	return $changed;
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
	foreach ( $cf as $key => $value ) {
		if ( ! is_array( $value ) || ! is_numeric( $key ) ) {
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
		if ( is_array( $d ) && ! empty( $d['id'] ) ) {
			$by_id[ (string) (int) $d['id'] ] = $d;
		}
	}

	$out = array();
	foreach ( $cf as $key => $value ) {
		// Keys may be field ids or field labels. Labels never silently drop.
		$resolved = resolve_booking_custom_field_key( (string) $key, $defs );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( is_array( $value ) ) {
			$out[ $resolved ] = $value;
			continue;
		}
		$def         = $by_id[ $resolved ] ?? array( 'label' => '', 'type' => 'text' );
		$out[ $resolved ] = array(
			'label' => (string) ( $def['label'] ?? '' ),
			'type'  => (string) ( $def['type'] ?? 'text' ),
			'value' => $value,
		);
	}
	return $out;
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
