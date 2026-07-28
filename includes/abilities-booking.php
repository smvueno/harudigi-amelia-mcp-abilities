<?php
/**
 * Booking / customer / payment / notification abilities (beyond Amelia native).
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/abilities-booking-handlers.php';

function register_booking_abilities(): void {
	Helpers::register(
		'amelia/get-appointment',
		array(
			'label'        => __( 'Get Appointment', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'One appointment by appointment_id. Includes bookings[].duration, bookings[].extras, bookings[].customFields when set. Lists: amelia/list-appointments.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_get_appointment',
			'readonly'     => true,
			'input_schema' => Helpers::id_schema( 'appointment_id' ),
		)
	);
	Helpers::register(
		'amelia/get-event',
		array(
			'label'        => __( 'Get Event', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'One event by event_id. Lists: amelia/list-events (native).', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_get_event',
			'readonly'     => true,
			'input_schema' => Helpers::id_schema( 'event_id' ),
		)
	);
	Helpers::register(
		'amelia/get-customer',
		array(
			'label'        => __( 'Get Customer', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'One customer by customer_id.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_get_customer',
			'readonly'     => true,
			'input_schema' => Helpers::id_schema( 'customer_id' ),
		)
	);
	Helpers::register(
		'amelia/list-event-bookings',
		array(
			'label'        => __( 'List Event Bookings', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Event attendee bookings. Optional filters via page/limit/search.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_list_event_bookings',
			'readonly'     => true,
			'input_schema' => Helpers::list_schema(
				array( 'event_id' => array( 'type' => 'integer' ) )
			),
		)
	);
	Helpers::register(
		'amelia/get-event-booking',
		array(
			'label'        => __( 'Get Event Booking', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'One event booking by booking_id.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_get_event_booking',
			'readonly'     => true,
			'input_schema' => Helpers::id_schema( 'booking_id' ),
		)
	);
	Helpers::register(
		'amelia/list-payments',
		array(
			'label'        => __( 'List Payments', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Paginated payments (amounts/status; no gateway secrets).', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_list_payments',
			'readonly'     => true,
			'input_schema' => Helpers::list_schema(
				array(
					'date_from' => array( 'type' => 'string' ),
					'date_to'   => array( 'type' => 'string' ),
				)
			),
		)
	);
	Helpers::register(
		'amelia/get-payment',
		array(
			'label'        => __( 'Get Payment', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'One payment by payment_id.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_get_payment',
			'readonly'     => true,
			'input_schema' => Helpers::id_schema( 'payment_id' ),
		)
	);
	Helpers::register(
		'amelia/get-payment-link',
		array(
			'label'        => __( 'Get Payment Link', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Copyable URL for the remaining balance on a payment. payment_id + gateway (stripe|payPal|…). Requires Amelia Payment Links enabled. Charges remaining due (not a custom half). Does not email the client.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_get_payment_link',
			'readonly'     => true,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'payment_id' ),
				'properties'           => array(
					'payment_id' => array( 'type' => 'integer' ),
					'gateway'    => array(
						'type'        => 'string',
						'description' => 'stripe|payPal|wc|mollie|razorpay|square|barion. Default stripe.',
						'default'     => 'stripe',
					),
				),
			),
		)
	);
	Helpers::register(
		'amelia/add-payment',
		array(
			'label'        => __( 'Add Payment', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Record a payment row (manual bookkeeping). Requires customerBookingId (booking id from get-appointment) or packageCustomerId. Set gateway (onSite|stripe|payPal|wc|mollie|razorpay|square|barion), status (paid|pending|partiallyPaid|refunded), amount. Optional transactionId. For partial payments, add another row on the same customerBookingId. Does not charge cards or send payment-link emails.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_add_payment',
			'readonly'     => false,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'customerBookingId' => array( 'type' => 'integer', 'description' => 'Booking id (bookings[].id).' ),
					'booking_id'        => array( 'type' => 'integer', 'description' => 'Alias of customerBookingId.' ),
					'packageCustomerId' => array( 'type' => 'integer' ),
					'amount'            => array( 'type' => 'number' ),
					'status'            => array(
						'type' => 'string',
						'enum' => array( 'paid', 'pending', 'partiallyPaid', 'refunded' ),
					),
					'gateway'           => array(
						'type'        => 'string',
						'description' => 'onSite|stripe|payPal|wc|mollie|razorpay|square|barion (aliases: paypal, onsite).',
					),
					'dateTime'          => array( 'type' => 'string', 'description' => 'YYYY-MM-DD HH:mm:ss' ),
					'entity'            => array( 'type' => 'string', 'enum' => array( 'appointment', 'event', 'package' ) ),
					'transactionId'     => array( 'type' => 'string' ),
					'gatewayTitle'      => array( 'type' => 'string' ),
					'fields'            => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
		)
	);
	Helpers::register(
		'amelia/update-payment',
		array(
			'label'        => __( 'Update Payment', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Change payment amount, status, and/or gateway (e.g. mark paid via stripe/payPal). payment_id + gateway|status|amount|transactionId. Manual bookkeeping only — does not charge or refund through Stripe/PayPal APIs.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_payment',
			'readonly'     => false,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'payment_id' ),
				'properties'           => array(
					'payment_id'    => array( 'type' => 'integer' ),
					'gateway'       => array( 'type' => 'string', 'description' => 'onSite|stripe|payPal|wc|…' ),
					'status'        => array(
						'type' => 'string',
						'enum' => array( 'paid', 'pending', 'partiallyPaid', 'refunded' ),
					),
					'amount'        => array( 'type' => 'number' ),
					'transactionId' => array( 'type' => 'string' ),
					'dateTime'      => array( 'type' => 'string' ),
					'gatewayTitle'  => array( 'type' => 'string' ),
					'fields'        => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
		)
	);
	Helpers::register(
		'amelia/delete-payment',
		array(
			'label'        => __( 'Delete Payment', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Permanently delete a payment row. Requires confirm=true after user approval. Prefer update-payment (status/gateway) when possible.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_delete_payment',
			'readonly'     => false,
			'destructive'  => true,
			'idempotent'   => false,
			'input_schema' => Helpers::id_confirm_schema( 'payment_id' ),
		)
	);
	Helpers::register(
		'amelia/list-notifications',
		array(
			'label'       => __( 'List Notifications', 'mcp-abilities-for-amelia' ),
			'description' => __( 'Amelia notification templates (email/SMS). No credentials.', 'mcp-abilities-for-amelia' ),
			'callback'    => __NAMESPACE__ . '\\ability_list_notifications',
			'readonly'    => true,
		)
	);

	// Appointment writes.
	Helpers::register(
		'amelia/update-appointment',
		array(
			'label'        => __( 'Update Appointment', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Full appointment update. appointment_id + fields (Amelia camelCase). To change duration tier / extras / custom fields, include fields.bookings[] with id, customerId, duration (seconds or "1h"), extras [{extraId,quantity}], customFields. Use get-service-booking-options for allowed values. Prefer get-appointment first.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_appointment',
			'readonly'     => false,
			'input_schema' => catalog_fields_id( 'appointment_id' ),
		)
	);
	Helpers::register(
		'amelia/update-appointment-status',
		array(
			'label'        => __( 'Update Appointment Status', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'approved|pending|canceled|rejected|no-show.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_appointment_status',
			'readonly'     => false,
			'input_schema' => booking_status_id( 'appointment_id' ),
		)
	);
	Helpers::register(
		'amelia/update-appointment-note',
		array(
			'label'        => __( 'Update Appointment Note', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Set internal_notes on an appointment.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_appointment_note',
			'readonly'     => false,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'appointment_id', 'internal_notes' ),
				'properties'           => array(
					'appointment_id' => array( 'type' => 'integer' ),
					'internal_notes' => array( 'type' => 'string' ),
				),
			),
		)
	);
	Helpers::register(
		'amelia/update-appointment-time',
		array(
			'label'        => __( 'Update Appointment Time', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Reschedule. booking_start as YYYY-MM-DD HH:mm.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_appointment_time',
			'readonly'     => false,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'appointment_id', 'booking_start' ),
				'properties'           => array(
					'appointment_id' => array( 'type' => 'integer' ),
					'booking_start'  => array( 'type' => 'string' ),
					'time_zone'      => array( 'type' => 'string' ),
				),
			),
		)
	);
	Helpers::register(
		'amelia/delete-appointment',
		array(
			'label'        => __( 'Delete Appointment', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'PERMANENT delete. Prefer update-appointment-status (canceled) or amelia/cancel-booking. Requires confirm=true after user approval.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_delete_appointment',
			'readonly'     => false,
			'destructive'  => true,
			'idempotent'   => false,
			'input_schema' => Helpers::id_confirm_schema( 'appointment_id' ),
		)
	);
	Helpers::register(
		'amelia/update-booking-status',
		array(
			'label'        => __( 'Update Booking Status', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Update a customer booking line (booking_id) status. type: appointment|event.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_booking_status',
			'readonly'     => false,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'booking_id', 'status' ),
				'properties'           => array(
					'booking_id' => array( 'type' => 'integer' ),
					'status'     => array(
						'type' => 'string',
						'enum' => array( 'approved', 'pending', 'canceled', 'rejected', 'no-show' ),
					),
					'type'       => array( 'type' => 'string', 'enum' => array( 'appointment', 'event' ), 'default' => 'appointment' ),
				),
			),
		)
	);

	Helpers::register(
		'amelia/update-event',
		array(
			'label'        => __( 'Update Event', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Full event update. event_id + fields.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_event',
			'readonly'     => false,
			'input_schema' => catalog_fields_id( 'event_id' ),
		)
	);
	Helpers::register(
		'amelia/update-event-status',
		array(
			'label'        => __( 'Update Event Status', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Set event status. apply_globally for recurring siblings.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_event_status',
			'readonly'     => false,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'event_id', 'status' ),
				'properties'           => array(
					'event_id'       => array( 'type' => 'integer' ),
					'status'         => array( 'type' => 'string' ),
					'apply_globally' => array( 'type' => 'boolean', 'default' => false ),
				),
			),
		)
	);
	Helpers::register(
		'amelia/update-event-visibility',
		array(
			'label'        => __( 'Update Event Visibility', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Show/hide event. Pass fields with show boolean (and applyGlobally if needed).', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_event_visibility',
			'readonly'     => false,
			'input_schema' => catalog_fields_id( 'event_id' ),
		)
	);
	Helpers::register(
		'amelia/delete-event',
		array(
			'label'        => __( 'Delete Event', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'PERMANENT delete. Prefer update-event-status (canceled) or hide via visibility. Requires confirm=true after user approval.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_delete_event',
			'readonly'     => false,
			'destructive'  => true,
			'idempotent'   => false,
			'input_schema' => Helpers::id_confirm_schema( 'event_id' ),
		)
	);

	Helpers::register(
		'amelia/update-customer',
		array(
			'label'        => __( 'Update Customer', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Update customer. customer_id + fields (firstName, lastName, email, phone, …). Password/externalId blocked via MCP.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_customer',
			'readonly'     => false,
			'input_schema' => catalog_fields_id( 'customer_id' ),
		)
	);
	Helpers::register(
		'amelia/update-customer-status',
		array(
			'label'        => __( 'Update Customer Status', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Set customer status (visible/hidden/disabled). Prefer this over delete.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_customer_status',
			'readonly'     => false,
			'input_schema' => catalog_status_id( 'customer_id' ),
		)
	);
	Helpers::register(
		'amelia/update-customer-note',
		array(
			'label'        => __( 'Update Customer Note', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Set customer note.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_update_customer_note',
			'readonly'     => false,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'customer_id', 'note' ),
				'properties'           => array(
					'customer_id' => array( 'type' => 'integer' ),
					'note'        => array( 'type' => 'string' ),
				),
			),
		)
	);
	Helpers::register(
		'amelia/delete-customer',
		array(
			'label'        => __( 'Delete Customer', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'PERMANENT delete (refused if future appointments). Prefer update-customer-status. Requires confirm=true after user approval.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_delete_customer',
			'readonly'     => false,
			'destructive'  => true,
			'idempotent'   => false,
			'input_schema' => Helpers::id_confirm_schema( 'customer_id' ),
		)
	);
}