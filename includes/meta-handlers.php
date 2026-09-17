<?php
/**
 * Meta ability registration + dispatch handlers.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AmeliaBooking\Application\Controller\Booking\Appointment\AddAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\DeleteAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\GetAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\GetTimeSlotsController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateAppointmentStatusController;
use AmeliaBooking\Application\Controller\Booking\Appointment\UpdateBookingStatusController;
use AmeliaBooking\Application\Controller\Booking\Event\AddEventController;
use AmeliaBooking\Application\Controller\Booking\Event\DeleteEventController;
use AmeliaBooking\Application\Controller\Booking\Event\GetEventController;
use AmeliaBooking\Application\Controller\Booking\Event\UpdateEventController;
use AmeliaBooking\Application\Controller\Booking\Appointment\AddBookingController;
use AmeliaBooking\Application\Controller\Payment\AddPaymentController;
use AmeliaBooking\Application\Controller\Payment\DeletePaymentController;
use AmeliaBooking\Application\Controller\Payment\GetPaymentController;
use AmeliaBooking\Application\Controller\Payment\GetPaymentsController;
use AmeliaBooking\Application\Controller\Payment\UpdatePaymentController;
use AmeliaBooking\Application\Controller\Stats\GetStatsController;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;

function register_meta_abilities(): void {
	Helpers::register(
		'amelia/help',
		array(
			'label'       => __( 'Amelia MCP Help', 'harudigi-booking-abilities-for-amelia' ),
			'description' => __(
				'START HERE. Explains the 7 Amelia tools, entities, booking workflow, extras/customFields shapes, notify/status safety. Call before other Amelia tools if unsure.',
				'harudigi-booking-abilities-for-amelia'
			),
			'callback'    => __NAMESPACE__ . '\\meta_help',
			'readonly'    => true,
		)
	);

	Helpers::register(
		'amelia/status',
		array(
			'label'       => __( 'Amelia Status', 'harudigi-booking-abilities-for-amelia' ),
			'description' => __(
				'Amelia/plugin versions, entity counts, and defaultAppointmentStatus (used when amelia/book omits status). Read-only.',
				'harudigi-booking-abilities-for-amelia'
			),
			'callback'    => __NAMESPACE__ . '\\meta_status',
			'readonly'    => true,
		)
	);

	Helpers::register(
		'amelia/discover',
		array(
			'label'        => __( 'Discover Entity', 'harudigi-booking-abilities-for-amelia' ),
			'description'  => __(
				'Show allowed actions and example payloads for one entity (service, extra, custom_field, customer, …). Pass entity name. Use before mutate/book if schema is unclear.',
				'harudigi-booking-abilities-for-amelia'
			),
			'callback'     => __NAMESPACE__ . '\\meta_discover',
			'readonly'     => true,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'entity' ),
				'properties'           => array(
					'entity' => array(
						'type'        => 'string',
						'description' => 'Entity key: service, category, location, employee, customer, package, extra, resource, coupon, custom_field, appointment, event, notification, payment.',
					),
				),
			),
		)
	);

	Helpers::register(
		'amelia/query',
		array(
			'label'        => __( 'Query Amelia', 'harudigi-booking-abilities-for-amelia' ),
			'description'  => __(
				'Read data. Required: action + entity (for list/get). Actions: list, get, stats, availability, booking_options, settings. Appointment list defaults: date window today→+14d, view=slim (compact rows with aptId+bookId). Override with date_from/date_to or dates; view=full for raw Amelia blobs; page/limit to page inside the window. Examples: {action:"list",entity:"appointment"}, {action:"list",entity:"appointment",date_from:"2026-09-17",date_to:"2026-09-17"}, {action:"get",entity:"appointment",id:12}.',
				'harudigi-booking-abilities-for-amelia'
			),
			'callback'     => __NAMESPACE__ . '\\meta_query',
			'readonly'     => true,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'required'             => array( 'action' ),
				'properties'           => array(
					'action'        => array(
						'type'        => 'string',
						'description' => 'list|get|stats|availability|booking_options|settings',
					),
					'entity'        => array( 'type' => 'string' ),
					'id'            => array( 'type' => 'integer' ),
					'page'          => array( 'type' => 'integer' ),
					'limit'         => array( 'type' => 'integer' ),
					'search'        => array( 'type' => 'string' ),
					'status'        => array( 'type' => 'string' ),
					'date_from'     => array( 'type' => 'string', 'description' => 'YYYY-MM-DD (with date_to → dates window).' ),
					'date_to'       => array( 'type' => 'string', 'description' => 'YYYY-MM-DD.' ),
					'view'          => array( 'type' => 'string', 'description' => 'appointment list: slim (default) | full.' ),
					'serviceId'     => array( 'type' => 'integer' ),
					'providerIds'   => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'startDateTime' => array( 'type' => 'string' ),
					'endDateTime'   => array( 'type' => 'string' ),
					'persons'       => array( 'type' => 'integer' ),
					'serviceDuration' => array( 'type' => array( 'integer', 'string' ) ),
					'extras'        => array( 'type' => 'array' ),
				),
			),
		)
	);

	Helpers::register(
		'amelia/mutate',
		array(
			'label'        => __( 'Mutate Catalog', 'harudigi-booking-abilities-for-amelia' ),
			'description'  => __(
				'Create/update/delete/status for catalog + customers (NOT appointments — use amelia/book). Required: action, entity. actions: create|update|delete|status. Pass fields object (or flat fields). delete/status need id; delete needs confirm:true. Customer fields: firstName, lastName, email, phone, note (internal). Example create: {action:"create",entity:"customer",fields:{firstName:"MCP",lastName:"Test",email:"mcp-test@example.invalid",note:"VIP"}}. Example extra: {action:"create",entity:"extra",fields:{name:"Kimono",price:8000,serviceId:1}}.',
				'harudigi-booking-abilities-for-amelia'
			),
			'callback'     => __NAMESPACE__ . '\\meta_mutate',
			'readonly'     => false,
			'destructive'  => true,
			'idempotent'   => false,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'required'             => array( 'action', 'entity' ),
				'properties'           => array(
					'action'  => array( 'type' => 'string', 'description' => 'create|update|delete|status' ),
					'entity'  => array( 'type' => 'string' ),
					'id'      => array( 'type' => 'integer' ),
					'fields'  => array( 'type' => 'object' ),
					'status'  => array( 'type' => 'string' ),
					'confirm' => array( 'type' => 'boolean', 'description' => 'Required true for delete.' ),
				),
			),
		)
	);

	Helpers::register(
		'amelia/book',
		array(
			'label'        => __( 'Book / Manage Appointments', 'harudigi-booking-abilities-for-amelia' ),
			'description'  => __(
				'Create/update/cancel appointments and events. id = appointmentId ONLY (never bookingId; pay uses bookingId). WRITE PROTOCOL: (1) dry_run:true → preview + previewId, no DB write (2) show preview to human (3) confirm:true + same fields + previewId. Updates also need expectedCustomer (name or email). Never mix internalNotes + bookingStart in one update. Cancel/delete/notify need confirm:true. notify defaults FALSE. Create needs: serviceId, providerId, customerId, bookingStart. Actions: create, update, cancel, delete, set_status, notify, create_event, update_event, delete_event, book_event.',
				'harudigi-booking-abilities-for-amelia'
			),
			'callback'     => __NAMESPACE__ . '\\meta_book',
			'readonly'     => false,
			'destructive'  => true,
			'idempotent'   => false,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'required'             => array( 'action' ),
				'properties'           => array(
					'action'             => array( 'type' => 'string', 'description' => 'create|update|cancel|delete|set_status|notify|resend|create_event|update_event|delete_event|book_event' ),
					'id'                 => array( 'type' => 'integer', 'description' => 'AppointmentId for update/cancel/delete/notify — NOT bookingId.' ),
					'serviceId'          => array( 'type' => 'integer' ),
					'providerId'         => array( 'type' => 'integer' ),
					'customerId'         => array( 'type' => 'integer' ),
					'bookingStart'       => array( 'type' => 'string' ),
					'locationId'         => array( 'type' => 'integer' ),
					'persons'            => array( 'type' => 'integer' ),
					'duration'           => array( 'type' => array( 'integer', 'string' ), 'description' => 'Seconds or "1h"/"90m".' ),
					'status'             => array( 'type' => 'string', 'description' => 'approved|pending|canceled|rejected|no-show. Omit → site default.' ),
					'notify'             => array( 'type' => 'boolean', 'description' => 'Default false. Ask human before true. On create/set_status only gates mail — does not send by itself.' ),
					'booking_id'         => array( 'type' => 'integer', 'description' => 'Optional customer booking id (set_status / notify).' ),
					'internalNotes'      => array( 'type' => 'string' ),
					'extras'             => array(
						'type'        => 'array',
						'description' => '[{extraId, quantity}]',
						'items'       => array( 'type' => 'object' ),
					),
					'customFields'       => array(
						'type'                 => 'object',
						'description'          => 'Map fieldId → value string, or Amelia-shaped objects.',
						'additionalProperties' => true,
					),
					'couponCode'         => array( 'type' => 'string' ),
					'fields'             => array( 'type' => 'object', 'description' => 'Partial update body for update action.' ),
					'replaceCustomFields'=> array( 'type' => 'boolean' ),
					'dry_run'            => array( 'type' => 'boolean', 'description' => 'create/update: preview only, returns previewId. No DB write.' ),
					'previewId'          => array( 'type' => 'string', 'description' => 'From dry_run; required with confirm on create/update.' ),
					'expectedCustomer'   => array( 'type' => 'string', 'description' => 'Required on update: customer name or email must match appointment.' ),
					'confirm'            => array( 'type' => 'boolean', 'description' => 'create/update after dry_run; also cancel|delete|notify.' ),
					'eventId'            => array( 'type' => 'integer' ),
					'payment'            => array( 'type' => 'object' ),
				),
			),
		)
	);

	Helpers::register(
		'amelia/pay',
		array(
			'label'        => __( 'Amelia Payments', 'harudigi-booking-abilities-for-amelia' ),
			'description'  => __(
				'Money control: list/get/add/update/delete payments and payment links. Does NOT charge cards — records bookkeeping / generates Checkout URLs. link returns buy.stripe.com (send that to the client). Optional amount (JPY) or amount:"half" for partial. delete requires confirm:true. Actions: list, get, add, update, delete, link. Gateways: onSite, stripe, payPal, wc, mollie, razorpay, square. Statuses: paid, pending, partiallyPaid, refunded.',
				'harudigi-booking-abilities-for-amelia'
			),
			'callback'     => __NAMESPACE__ . '\\meta_pay',
			'readonly'     => false,
			'destructive'  => true,
			'idempotent'   => false,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'required'             => array( 'action' ),
				'properties'           => array(
					'action'      => array( 'type' => 'string', 'description' => 'list|get|add|update|delete|link' ),
					'id'          => array( 'type' => 'integer', 'description' => 'payment id' ),
					'fields'      => array( 'type' => 'object' ),
					'gateway'     => array( 'type' => 'string' ),
					'status'      => array( 'type' => 'string' ),
					'amount'      => array( 'type' => 'string', 'description' => 'For add/update: JPY number. For link: JPY number or "half". Omit on link = full remaining.' ),
					'confirm'     => array( 'type' => 'boolean' ),
					'page'        => array( 'type' => 'integer' ),
					'limit'       => array( 'type' => 'integer' ),
				),
			),
		)
	);
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function meta_help( array $input = array() ) {
	unset( $input );
	return array(
		'tools'       => array(
			'amelia/help'     => 'This guide.',
			'amelia/status'   => 'Versions + defaultAppointmentStatus.',
			'amelia/discover' => 'Per-entity actions + examples.',
			'amelia/query'    => 'list/get/stats/availability/booking_options/settings.',
			'amelia/mutate'   => 'CRUD catalog + customers (not appointments).',
			'amelia/book'     => 'Appointments/events create/update/cancel.',
			'amelia/pay'      => 'Payments + links.',
		),
		'workflow_book' => array(
			'1' => 'amelia/query action=list entity=appointment — slim rows today→+14d (aptId + bookId). Narrow with date_from/date_to.',
			'2' => 'Writes: dry_run:true first → show previewId/changes to human → confirm:true + previewId (+ expectedCustomer on update).',
			'3' => 'id on book = appointmentId only. bookingId is for amelia/pay. Never mix internalNotes + bookingStart.',
			'4' => 'Create: booking_options → dry_run create → confirm create. Omit status (site default); notify false unless human said yes.',
			'5' => 'Manual resend: amelia/book action=notify id=APPOINTMENT_ID confirm:true.',
		),
		'workflow_pay'  => array(
			'full_link'   => 'amelia/pay action=link id=PAYMENT_ID gateway=stripe — returns buy.stripe.com URL for full remaining. Send that URL to the client (not an Amelia site link).',
			'half_link'   => 'amelia/pay action=link id=PAYMENT_ID gateway=stripe amount=half — or amount=<JPY>. chargedAmount is what Checkout collects.',
			'after_pay'   => 'When client pays: payment → paid; if Payment Links auto-approve is on, appointment → approved and customer gets approved email (amount paid should appear in the template).',
			'cash_rest'   => 'Balance in cash: amelia/pay action=add customerBookingId=… amount=REMAINING gateway=onSite status=paid',
			'stripe_rest' => 'Balance via Stripe: amelia/pay action=link id=PENDING_PAYMENT_ID gateway=stripe (omit amount = remaining).',
			'send'        => 'Always send paymentLink from the response (Stripe Checkout). Never invent amounts.',
		),
		'safety'      => array(
			'notify_default'             => false,
			'ask_before_notify_true'     => true,
			'notify_flag_is_gate'        => 'notify:true on create/set_status only allows mail on that lifecycle event; does not send by itself.',
			'manual_send'                => 'action=notify|resend + confirm:true (email only)',
			'templates_gate'             => 'Customer emails require enabled templates in Amelia → Notifications (query entity=notification to list).',
			'provider_mail'              => 'Provider status emails ignore booking notifyParticipants when those templates are enabled.',
			'update_notify'              => 'Omit notify on update → mute this edit’s customer emails but restore prior notifyParticipants (reminders stay). Pass notify:true|false to set the stored flag.',
			'status_when_omitted'        => 'Amelia Settings → general.defaultAppointmentStatus',
			'deletes_need_confirm'       => true,
			'notify_needs_confirm'       => true,
			'create_update_dry_run'      => true,
			'previewId_binds_confirm'    => true,
			'expectedCustomer_on_update' => true,
			'notes_xor_reschedule'       => true,
			'id_is_appointmentId'        => true,
			'test_with_fake_customer'    => true,
			'appointment_list_default'   => 'slim + today→+14d',
		),
		'ids'         => array(
			'appointmentId' => 'Calendar slot — amelia/book id',
			'bookingId'     => 'Customer seat — amelia/pay customerBookingId',
			'customerId'    => 'Profile — amelia/mutate',
			'paymentId'     => 'Payment row — amelia/pay id',
		),
		'shapes'      => array(
			'extras'       => array( array( 'extraId' => 1, 'quantity' => 1 ) ),
			'customFields' => array(
				'3' => 'Preferred locations text',
				'4' => 'WhatsApp',
			),
		),
		'entities'    => Entity_Map::keys(),
	);
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function meta_status( array $input = array() ) {
	unset( $input );
	$base = Helpers::status_payload();
	if ( is_wp_error( $base ) ) {
		return $base;
	}
	$base['defaultAppointmentStatus'] = get_default_appointment_status();
	$base['settings_summary']         = Helpers::settings_summary();
	return $base;
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function meta_discover( array $input = array() ) {
	$entity = (string) ( $input['entity'] ?? '' );
	if ( 'payment' === strtolower( $entity ) ) {
		return array(
			'entity'  => 'payment',
			'tool'    => 'amelia/pay',
			'actions' => array( 'list', 'get', 'add', 'update', 'delete', 'link' ),
			'example_add' => array(
				'action' => 'add',
				'fields' => array(
					'customerId'          => 1,
					'amount'              => 1000,
					'gateway'             => 'onSite',
					'status'              => 'pending',
					'dateTime'            => wp_date( 'Y-m-d H:i' ),
					'bookingType'         => 'appointment',
				),
			),
		);
	}
	$def = Entity_Map::get( $entity );
	if ( is_wp_error( $def ) ) {
		return $def;
	}
	$actions = array( 'list', 'get' );
	if ( ! empty( $def['mutate'] ) ) {
		$actions = array_merge( $actions, array( 'create', 'update', 'delete' ) );
		if ( ! empty( $def['status'] ) ) {
			$actions[] = 'status';
		}
	}
	if ( in_array( $def['key'], array( 'appointment', 'event' ), true ) ) {
		$actions = array( 'list', 'get', 'use amelia/book for writes' );
	}
	$out = array(
		'entity'  => $def['key'],
		'label'   => $def['label'],
		'actions' => $actions,
		'tools'   => array(
			'read'  => 'amelia/query',
			'write' => ! empty( $def['mutate'] ) ? 'amelia/mutate' : 'amelia/book',
		),
		'example' => $def['example'] ?? null,
		'note'    => $def['note'] ?? null,
	);
	if ( 'appointment' === $def['key'] ) {
		$out['book_actions'] = array( 'create', 'update', 'cancel', 'delete', 'set_status', 'notify' );
		$out['write_protocol'] = array(
			'dry_run'          => true,
			'previewId'        => true,
			'confirm'          => true,
			'expectedCustomer' => 'required on update',
			'id'               => 'appointmentId only',
		);
		$out['list_defaults'] = array(
			'view'      => 'slim',
			'date_from' => 'today',
			'date_to'   => '+14 days',
		);
		$out['notify']       = array(
			'action'  => 'notify',
			'needs'   => array( 'id', 'confirm:true' ),
			'optional'=> array( 'booking_id' ),
			'effect'  => 'Sends customer status emails for current status; template must be enabled in Amelia.',
		);
	}
	if ( 'notification' === $def['key'] ) {
		$out['note'] = 'List only. Enable/disable templates in Amelia admin — MCP cannot flip them. Enabled status templates are required for customer email.';
	}
	return $out;
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function meta_query( array $input = array() ) {
	$action = strtolower( (string) ( $input['action'] ?? '' ) );

	if ( 'settings' === $action ) {
		return Helpers::settings_summary();
	}
	if ( 'stats' === $action ) {
		$params = Helpers::list_params( $input );
		if ( empty( $params['dates'] ) || ! is_array( $params['dates'] ) || 2 !== count( $params['dates'] ) ) {
			$params['dates'] = array( wp_date( 'Y-m-d', strtotime( '-30 days' ) ), wp_date( 'Y-m-d' ) );
		}
		if ( empty( $params['stats'] ) ) {
			$params['stats'] = array( 'approved', 'pending', 'canceled', 'rejected', 'no-show' );
		}
		return Helpers::invoke( GetStatsController::class, $params, array(), 'GET' );
	}
	if ( 'booking_options' === $action ) {
		$id = Helpers::parse_id( $input['serviceId'] ?? $input['id'] ?? 0, 'serviceId' );
		return is_wp_error( $id ) ? $id : get_service_booking_options( $id );
	}
	if ( 'availability' === $action ) {
		$service_id = (int) ( $input['serviceId'] ?? 0 );
		if ( $service_id <= 0 ) {
			return new \WP_Error( 'invalid_serviceId', __( 'serviceId is required for availability.', 'harudigi-booking-abilities-for-amelia' ) );
		}
		$params = array(
			'serviceId' => $service_id,
			'persons'   => max( 1, (int) ( $input['persons'] ?? 1 ) ),
		);
		foreach ( array( 'startDateTime', 'endDateTime', 'locationId', 'excludeAppointmentId' ) as $k ) {
			if ( isset( $input[ $k ] ) && '' !== $input[ $k ] ) {
				$params[ $k ] = $input[ $k ];
			}
		}
		if ( ! empty( $input['providerIds'] ) && is_array( $input['providerIds'] ) ) {
			$params['providerIds'] = array_map( 'intval', $input['providerIds'] );
		}
		$dur = parse_duration_seconds( $input['serviceDuration'] ?? 0 );
		if ( $dur > 0 ) {
			$params['serviceDuration'] = $dur;
		}
		$extras = normalize_booking_extras( $input['extras'] ?? array(), 'id' );
		if ( $extras ) {
			$params['extras'] = $extras;
		}
		$result = Helpers::invoke( GetTimeSlotsController::class, $params, array(), 'GET' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// Normalize empty arrays → objects for MCP clients that choke on [].
		if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
			foreach ( array( 'slots', 'occupied', 'busyness' ) as $k ) {
				if ( isset( $result['data'][ $k ] ) && is_array( $result['data'][ $k ] ) && array() === $result['data'][ $k ] ) {
					$result['data'][ $k ] = (object) array();
				}
			}
		}
		return $result;
	}

	$def = Entity_Map::get( (string) ( $input['entity'] ?? '' ) );
	if ( is_wp_error( $def ) ) {
		return $def;
	}

	if ( 'list' === $action ) {
		if ( empty( $def['list'] ) ) {
			return new \WP_Error( 'unsupported', __( 'List not supported for this entity.', 'harudigi-booking-abilities-for-amelia' ) );
		}
		$params = Helpers::list_params( $input );
		if ( 'custom_field' === $def['key'] ) {
			$service_id = (int) ( $input['serviceId'] ?? $input['service_id'] ?? 0 );
			$for_events = ! empty( $input['for_events'] );
			$fields     = list_custom_fields_for_entity( $service_id, $for_events );
			return is_wp_error( $fields ) ? $fields : array( 'success' => true, 'data' => array( 'customFields' => $fields ) );
		}

		// Appointments: default date window + slim rows for agent context diet.
		if ( 'appointment' === $def['key'] ) {
			$view = strtolower( (string) ( $input['view'] ?? 'slim' ) );
			if ( '' === $view ) {
				$view = 'slim';
			}
			$had_dates = ! empty( $params['dates'] );
			if ( ! $had_dates ) {
				$from = wp_date( 'Y-m-d' );
				$to   = wp_date( 'Y-m-d', strtotime( '+14 days' ) );
				$params['dates'] = array( $from, $to );
			}
			$window = is_array( $params['dates'] ) && 2 === count( $params['dates'] )
				? array( (string) $params['dates'][0], (string) $params['dates'][1] )
				: array( wp_date( 'Y-m-d' ), wp_date( 'Y-m-d', strtotime( '+14 days' ) ) );
			$page  = (int) ( $params['page'] ?? 1 );
			$limit = (int) ( $params['limit'] ?? 25 );
			if ( 'slim' === $view && ! isset( $input['limit'] ) && ! isset( $input['per_page'] ) ) {
				$params['limit'] = min( 50, Helpers::LIST_MAX_LIMIT );
				$limit           = (int) $params['limit'];
			}
			$result = Helpers::invoke( $def['list'][0], $params, array(), $def['list'][1] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( 'full' === $view ) {
				if ( is_array( $result ) ) {
					$result['meta'] = array(
						'window'  => $window,
						'page'    => $page,
						'limit'   => $limit,
						'view'    => 'full',
						'defaultWindowApplied' => ! $had_dates,
					);
				}
				return $result;
			}
			if ( ! is_array( $result ) ) {
				return $result;
			}
			$slim = book_slim_appointments_result( $result, $window, $page, $limit );
			$slim['meta']['defaultWindowApplied'] = ! $had_dates;
			return $slim;
		}

		return Helpers::invoke( $def['list'][0], $params, array(), $def['list'][1] );
	}

	if ( 'get' === $action ) {
		if ( empty( $def['get'] ) ) {
			return new \WP_Error( 'unsupported', __( 'Get-by-id not supported; use list or booking_options.', 'harudigi-booking-abilities-for-amelia' ) );
		}
		$id = Helpers::parse_id( $input['id'] ?? 0, 'id' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return Helpers::invoke( $def['get'][0], array(), array( 'id' => $id ), $def['get'][1] );
	}

	return new \WP_Error(
		'invalid_action',
		__( 'query action must be list|get|stats|availability|booking_options|settings.', 'harudigi-booking-abilities-for-amelia' )
	);
}
