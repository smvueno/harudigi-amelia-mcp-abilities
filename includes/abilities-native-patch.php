<?php
/**
 * Patches Amelia Pro native abilities for Easy MCP compatibility / admin parity.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;

use AmeliaBooking\Application\Controller\Booking\Appointment\AddAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\AddBookingController;
use AmeliaBooking\Application\Controller\Booking\Appointment\GetTimeSlotsController;
use AmeliaBooking\Application\Controller\User\Customer\AddCustomerController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/booking-payload.php';

/**
 * Patch native Amelia abilities that break Easy MCP or diverge from admin UI.
 */
function patch_native_abilities(): void {
	if ( ! function_exists( 'wp_unregister_ability' ) || ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	patch_check_availability_ability();
	patch_add_customer_ability();
	patch_create_appointment_ability();
	patch_book_event_ability();
}

/**
 * Re-register check-availability with loose output schema + empty-object normalize.
 * Amelia returns [] for occupied/slots/busyness; MCP schema requires object.
 */
function patch_check_availability_ability(): void {
	if ( ! wp_get_ability( 'amelia/check-availability' ) ) {
		return;
	}

	wp_unregister_ability( 'amelia/check-availability' );
	wp_register_ability(
		'amelia/check-availability',
		array(
			'label'        => __( 'Check Availability', 'harudigi-amelia-mcp-abilities' ),
			'description'  => __(
				'Free slots for a service. Requires serviceId. For duration pricing (1h/2h/3h), pass serviceDuration in seconds (3600/7200/10800). Optional extras: [{id, quantity}]. persons defaults to 1. Use amelia/get-service-booking-options first.',
				'harudigi-amelia-mcp-abilities'
			),
			'category'     => 'amelia-read',
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'serviceId' ),
				'properties'           => array(
					'serviceId'            => array( 'type' => 'integer', 'description' => 'From list-services.' ),
					'persons'              => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1, 'description' => 'Optional; default 1.' ),
					'startDateTime'        => array( 'type' => 'string', 'description' => 'YYYY-MM-DD HH:mm' ),
					'endDateTime'          => array( 'type' => 'string', 'description' => 'YYYY-MM-DD HH:mm' ),
					'providerIds'          => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'locationId'           => array( 'type' => 'integer' ),
					'serviceDuration'      => array(
						'type'        => array( 'integer', 'string' ),
						'description' => 'Duration in seconds (3600=1h) or "1h"/"90m". Required for duration-priced services when checking that length.',
					),
					'excludeAppointmentId' => array( 'type' => 'integer' ),
					'extras'               => array(
						'type'        => 'array',
						'description' => 'Extras affecting slot length: [{id, quantity}].',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'id'       => array( 'type' => 'integer' ),
								'extraId'  => array( 'type' => 'integer' ),
								'quantity' => array( 'type' => 'integer', 'minimum' => 1 ),
							),
						),
					),
				),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'execute_callback'    => __NAMESPACE__ . '\\ability_check_availability_patched',
			'permission_callback' => array( Helpers::class, 'can_manage' ),
			'meta'                => Helpers::mcp_meta( true, false, true ),
		)
	);
}

/**
 * Align add-customer with Amelia admin: firstName required; lastName/email/phone optional.
 */
function patch_add_customer_ability(): void {
	if ( ! wp_get_ability( 'amelia/add-customer' ) ) {
		return;
	}

	wp_unregister_ability( 'amelia/add-customer' );
	wp_register_ability(
		'amelia/add-customer',
		array(
			'label'       => __( 'Add Customer', 'harudigi-amelia-mcp-abilities' ),
			'description' => __(
				'Create a customer. Matches Amelia admin: firstName required; lastName, email, phone, note optional. Do not invent fake emails — omit email or pass empty string when unknown.',
				'harudigi-amelia-mcp-abilities'
			),
			'category'    => 'amelia-write',
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'firstName' ),
				'properties'           => array(
					'firstName' => array( 'type' => 'string', 'description' => 'Customer first name (required).' ),
					'lastName'  => array( 'type' => 'string', 'description' => 'Optional. Empty string allowed.' ),
					'email'     => array( 'type' => 'string', 'description' => 'Optional. Omit/empty when unknown.' ),
					'phone'     => array( 'type' => 'string' ),
					'note'      => array( 'type' => 'string' ),
				),
			),
			'output_schema'       => array( 'type' => 'object', 'additionalProperties' => true ),
			'execute_callback'    => __NAMESPACE__ . '\\ability_add_customer_patched',
			'permission_callback' => array( Helpers::class, 'can_manage' ),
			'meta'                => Helpers::mcp_meta( false, false, false ),
		)
	);
}

/**
 * Patch create-appointment to support duration pricing, extras, and custom fields.
 */
function patch_create_appointment_ability(): void {
	if ( ! wp_get_ability( 'amelia/create-appointment' ) ) {
		return;
	}

	wp_unregister_ability( 'amelia/create-appointment' );
	wp_register_ability(
		'amelia/create-appointment',
		array(
			'label'       => __( 'Create Appointment', 'harudigi-amelia-mcp-abilities' ),
			'description' => __(
				'Book an appointment. Confirm service, employee, customer, start time, and (when applicable) duration tier, extras, and custom fields. Optional paymentGateway (onSite|stripe|payPal|…) and paymentStatus/paymentAmount for the created payment row (manual label — does not charge). Use amelia/get-service-booking-options for duration_tiers. Pass duration in seconds. Pass extras as [{extraId, quantity}].',
				'harudigi-amelia-mcp-abilities'
			),
			'category'    => 'amelia-write',
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'serviceId', 'providerId', 'customerId', 'bookingStart' ),
				'properties'           => array(
					'serviceId'      => array( 'type' => 'integer' ),
					'providerId'     => array( 'type' => 'integer' ),
					'customerId'     => array( 'type' => 'integer' ),
					'bookingStart'   => array(
						'type'        => 'string',
						'description' => 'YYYY-MM-DD HH:mm',
					),
					'locationId'     => array( 'type' => 'integer' ),
					'persons'        => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
					'duration'       => array(
						'type'        => array( 'integer', 'string' ),
						'description' => 'Selected length in seconds (3600=1h, 7200=2h) or "1h"/"90m". Use a duration_tiers value from get-service-booking-options.',
					),
					'extras'         => array(
						'type'        => 'array',
						'description' => '[{extraId, quantity}] (id also accepted).',
						'items'       => array( 'type' => 'object' ),
					),
					'customFields'   => array(
						'type'                 => array( 'object', 'array' ),
						'description'          => 'Amelia custom field values keyed by field id.',
						'additionalProperties' => true,
					),
					'internalNotes'  => array( 'type' => 'string' ),
					'status'         => array(
						'type'    => 'string',
						'enum'    => array( 'approved', 'pending' ),
						'default' => 'approved',
					),
					'paymentGateway' => array(
						'type'        => 'string',
						'description' => 'Payment method label on the booking: onSite|stripe|payPal|wc|… Default onSite.',
					),
					'paymentStatus'  => array(
						'type' => 'string',
						'enum' => array( 'paid', 'pending', 'partiallyPaid', 'refunded' ),
					),
					'paymentAmount'  => array( 'type' => 'number' ),
					'transactionId'  => array( 'type' => 'string' ),
					'payment'        => array(
						'type'                 => 'object',
						'description'          => 'Optional nested {gateway,status,amount,transactionId}.',
						'additionalProperties' => true,
					),
				),
			),
			'output_schema'       => array( 'type' => 'object', 'additionalProperties' => true ),
			'execute_callback'    => __NAMESPACE__ . '\\ability_create_appointment_patched',
			'permission_callback' => array( Helpers::class, 'can_manage' ),
			'meta'                => Helpers::mcp_meta( false, false, false ),
		)
	);
}

/**
 * Patch book-event to accept custom fields (and keep persons).
 */
function patch_book_event_ability(): void {
	if ( ! wp_get_ability( 'amelia/book-event' ) ) {
		return;
	}

	wp_unregister_ability( 'amelia/book-event' );
	wp_register_ability(
		'amelia/book-event',
		array(
			'label'       => __( 'Book Event', 'harudigi-amelia-mcp-abilities' ),
			'description' => __(
				'Register a customer on an event. Optional persons, customFields, and paymentGateway/paymentStatus (manual payment label).',
				'harudigi-amelia-mcp-abilities'
			),
			'category'    => 'amelia-write',
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'eventId', 'customerId' ),
				'properties'           => array(
					'eventId'        => array( 'type' => 'integer' ),
					'customerId'     => array( 'type' => 'integer' ),
					'persons'        => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
					'customFields'   => array(
						'type'                 => array( 'object', 'array' ),
						'additionalProperties' => true,
					),
					'paymentGateway' => array( 'type' => 'string' ),
					'paymentStatus'  => array( 'type' => 'string' ),
					'paymentAmount'  => array( 'type' => 'number' ),
					'payment'        => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			'output_schema'       => array( 'type' => 'object', 'additionalProperties' => true ),
			'execute_callback'    => __NAMESPACE__ . '\\ability_book_event_patched',
			'permission_callback' => array( Helpers::class, 'can_manage' ),
			'meta'                => Helpers::mcp_meta( false, false, false ),
		)
	);
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function ability_check_availability_patched( $input = array() ) {
	$input  = is_array( $input ) ? $input : array();
	$params = array(
		'serviceId' => (int) ( $input['serviceId'] ?? 0 ),
		'persons'   => isset( $input['persons'] ) ? max( 1, (int) $input['persons'] ) : 1,
	);
	if ( $params['serviceId'] <= 0 ) {
		return new \WP_Error( 'invalid_serviceId', __( 'serviceId is required.', 'harudigi-amelia-mcp-abilities' ) );
	}
	foreach ( array( 'startDateTime', 'endDateTime' ) as $key ) {
		if ( ! empty( $input[ $key ] ) ) {
			$params[ $key ] = sanitize_text_field( (string) $input[ $key ] );
		}
	}
	if ( ! empty( $input['providerIds'] ) ) {
		$params['providerIds'] = array_map( 'intval', (array) $input['providerIds'] );
	}
	if ( ! empty( $input['locationId'] ) ) {
		$params['locationId'] = (int) $input['locationId'];
	}
	$duration = parse_duration_seconds( $input['serviceDuration'] ?? 0 );
	if ( $duration > 0 ) {
		$params['serviceDuration'] = $duration;
	}
	if ( ! empty( $input['excludeAppointmentId'] ) ) {
		$params['excludeAppointmentId'] = (int) $input['excludeAppointmentId'];
	}
	$extras = normalize_booking_extras( $input['extras'] ?? array(), 'id' );
	if ( $extras ) {
		$params['extras'] = wp_json_encode( $extras );
	}

	$result = Helpers::invoke( GetTimeSlotsController::class, $params, array(), 'GET' );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : $result;
	foreach ( array( 'slots', 'occupied', 'busyness' ) as $key ) {
		if ( ! isset( $data[ $key ] ) || ( is_array( $data[ $key ] ) && array() === $data[ $key ] ) ) {
			$data[ $key ] = new \stdClass();
		}
	}
	return $data;
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function ability_add_customer_patched( $input = array() ) {
	$input      = is_array( $input ) ? $input : array();
	$first_name = sanitize_text_field( (string) ( $input['firstName'] ?? '' ) );
	if ( '' === $first_name ) {
		return new \WP_Error( 'invalid_firstName', __( 'firstName is required.', 'harudigi-amelia-mcp-abilities' ) );
	}

	$last_name = sanitize_text_field( (string) ( $input['lastName'] ?? '' ) );
	$email_raw = isset( $input['email'] ) ? trim( (string) $input['email'] ) : '';
	$email     = '';
	if ( '' !== $email_raw ) {
		$email = sanitize_email( $email_raw );
		if ( '' === $email || ! is_email( $email ) ) {
			return new \WP_Error(
				'invalid_email',
				__( 'email is invalid. Omit email or pass an empty string when the customer has no email.', 'harudigi-amelia-mcp-abilities' )
			);
		}
	}

	$body = array(
		'firstName'  => $first_name,
		'lastName'   => $last_name,
		'email'      => $email,
		'type'       => 'customer',
		'status'     => 'visible',
		'externalId' => -1,
	);
	if ( ! empty( $input['phone'] ) ) {
		$body['phone'] = sanitize_text_field( (string) $input['phone'] );
	}
	if ( isset( $input['note'] ) && '' !== (string) $input['note'] ) {
		$body['note'] = sanitize_textarea_field( (string) $input['note'] );
	}

	return Helpers::invoke( AddCustomerController::class, $body );
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function ability_create_appointment_patched( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$service_id  = (int) ( $input['serviceId'] ?? 0 );
	$provider_id = (int) ( $input['providerId'] ?? 0 );
	$customer_id = (int) ( $input['customerId'] ?? 0 );
	$start       = sanitize_text_field( (string) ( $input['bookingStart'] ?? '' ) );
	if ( $service_id <= 0 || $provider_id <= 0 || $customer_id <= 0 || '' === $start ) {
		return new \WP_Error(
			'invalid_fields',
			__( 'serviceId, providerId, customerId, and bookingStart are required.', 'harudigi-amelia-mcp-abilities' )
		);
	}

	$status  = sanitize_key( (string) ( $input['status'] ?? 'approved' ) );
	if ( ! in_array( $status, array( 'approved', 'pending' ), true ) ) {
		$status = 'approved';
	}
	$persons = max( 1, (int) ( $input['persons'] ?? 1 ) );

	$booking = array(
		'customerId' => $customer_id,
		'persons'    => $persons,
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

	$custom_fields = normalize_booking_custom_fields( $input['customFields'] ?? null );
	if ( $custom_fields ) {
		$booking['customFields'] = $custom_fields;
	}

	$body = array(
		'serviceId'          => $service_id,
		'providerId'         => $provider_id,
		'bookingStart'       => $start,
		'notifyParticipants' => 1,
		'internalNotes'      => ! empty( $input['internalNotes'] ) ? sanitize_textarea_field( (string) $input['internalNotes'] ) : '',
		'locationId'         => ! empty( $input['locationId'] ) ? (int) $input['locationId'] : null,
		'recurring'          => array(),
		'bookings'           => array( $booking ),
	);

	require_once __DIR__ . '/payment-payload.php';
	$payment = payment_from_booking_input( $input );
	if ( is_wp_error( $payment ) ) {
		return $payment;
	}
	$body['payment'] = $payment;

	return Helpers::invoke( AddAppointmentController::class, $body );
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function ability_book_event_patched( $input = array() ) {
	$input       = is_array( $input ) ? $input : array();
	$event_id    = (int) ( $input['eventId'] ?? 0 );
	$customer_id = (int) ( $input['customerId'] ?? 0 );
	if ( $event_id <= 0 || $customer_id <= 0 ) {
		return new \WP_Error( 'invalid_fields', __( 'eventId and customerId are required.', 'harudigi-amelia-mcp-abilities' ) );
	}

	$booking = array(
		'eventId'    => $event_id,
		'customerId' => $customer_id,
		'persons'    => max( 1, (int) ( $input['persons'] ?? 1 ) ),
		'status'     => 'approved',
		'customer'   => array( 'id' => $customer_id ),
	);
	$custom_fields = normalize_booking_custom_fields( $input['customFields'] ?? null );
	if ( $custom_fields ) {
		$booking['customFields'] = $custom_fields;
	}

	require_once __DIR__ . '/payment-payload.php';
	$payment = payment_from_booking_input( $input );
	if ( is_wp_error( $payment ) ) {
		return $payment;
	}

	return Helpers::invoke(
		AddBookingController::class,
		array(
			'type'                        => 'event',
			'eventId'                     => $event_id,
			'notifyParticipants'          => 1,
			'runInstantPostBookingActions' => true,
			'isBackendOrCabinet'          => true,
			'payment'                     => $payment,
			'bookings'                    => array( $booking ),
		)
	);
}
