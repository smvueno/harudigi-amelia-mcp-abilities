<?php
/**
 * Catalog read/write abilities (services, categories, locations, employees, packages, extras, resources, coupons).
 * Does not re-register Amelia native list-services / add-service / list-employees.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use AmeliaBooking\Application\Controller\Bookable\Category\AddCategoryController;
use AmeliaBooking\Application\Controller\Bookable\Category\DeleteCategoryController;
use AmeliaBooking\Application\Controller\Bookable\Category\GetCategoriesController;
use AmeliaBooking\Application\Controller\Bookable\Category\GetCategoryController;
use AmeliaBooking\Application\Controller\Bookable\Category\UpdateCategoryController;
use AmeliaBooking\Application\Controller\Bookable\Extra\AddExtraController;
use AmeliaBooking\Application\Controller\Bookable\Extra\DeleteExtraController;
use AmeliaBooking\Application\Controller\Bookable\Extra\GetExtraController;
use AmeliaBooking\Application\Controller\Bookable\Extra\GetExtrasController;
use AmeliaBooking\Application\Controller\Bookable\Extra\UpdateExtraController;
use AmeliaBooking\Application\Controller\Bookable\Package\AddPackageController;
use AmeliaBooking\Application\Controller\Bookable\Package\DeletePackageController;
use AmeliaBooking\Application\Controller\Bookable\Package\GetPackageController;
use AmeliaBooking\Application\Controller\Bookable\Package\GetPackagesController;
use AmeliaBooking\Application\Controller\Bookable\Package\UpdatePackageController;
use AmeliaBooking\Application\Controller\Bookable\Package\UpdatePackageStatusController;
use AmeliaBooking\Application\Controller\Bookable\Resource\AddResourceController;
use AmeliaBooking\Application\Controller\Bookable\Resource\DeleteResourceController;
use AmeliaBooking\Application\Controller\Bookable\Resource\GetResourceController;
use AmeliaBooking\Application\Controller\Bookable\Resource\GetResourcesController;
use AmeliaBooking\Application\Controller\Bookable\Resource\UpdateResourceController;
use AmeliaBooking\Application\Controller\Bookable\Resource\UpdateResourceStatusController;
use AmeliaBooking\Application\Controller\Bookable\Service\DeleteServiceController;
use AmeliaBooking\Application\Controller\Bookable\Service\GetServiceController;
use AmeliaBooking\Application\Controller\Bookable\Service\UpdateServiceController;
use AmeliaBooking\Application\Controller\Bookable\Service\UpdateServiceStatusController;
use AmeliaBooking\Application\Controller\Coupon\AddCouponController;
use AmeliaBooking\Application\Controller\Coupon\DeleteCouponController;
use AmeliaBooking\Application\Controller\Coupon\GetCouponController;
use AmeliaBooking\Application\Controller\Coupon\GetCouponsController;
use AmeliaBooking\Application\Controller\Coupon\UpdateCouponController;
use AmeliaBooking\Application\Controller\CustomField\AddCustomFieldController;
use AmeliaBooking\Application\Controller\CustomField\DeleteCustomFieldController;
use AmeliaBooking\Application\Controller\CustomField\GetCustomFieldsController;
use AmeliaBooking\Application\Controller\CustomField\UpdateCustomFieldController;
use AmeliaBooking\Application\Controller\Location\AddLocationController;
use AmeliaBooking\Application\Controller\Location\DeleteLocationController;
use AmeliaBooking\Application\Controller\Location\GetLocationController;
use AmeliaBooking\Application\Controller\Location\GetLocationsController;
use AmeliaBooking\Application\Controller\Location\UpdateLocationController;
use AmeliaBooking\Application\Controller\Location\UpdateLocationStatusController;
use AmeliaBooking\Application\Controller\User\DeleteUserController;
use AmeliaBooking\Application\Controller\User\Provider\AddProviderController;
use AmeliaBooking\Application\Controller\User\Provider\GetProviderController;
use AmeliaBooking\Application\Controller\User\Provider\UpdateProviderController;
use AmeliaBooking\Application\Controller\User\Provider\UpdateProviderStatusController;

function register_catalog_abilities(): void {
	// --- reads ---
	r( 'amelia/get-service', __( 'Get Service', 'harudigi-booking-abilities-for-amelia' ), __( 'One service by service_id (includes customPricing, extras). For booking-ready summary (duration_tiers 1h/2h/3h, extras, custom_fields) use amelia/get-service-booking-options.', 'harudigi-booking-abilities-for-amelia' ), 'ability_get_service', true, Helpers::id_schema( 'service_id' ) );
	r(
		'amelia/get-service-booking-options',
		__( 'Get Service Booking Options', 'harudigi-booking-abilities-for-amelia' ),
		__( 'Duration pricing tiers (1h/2h/3h as seconds + price), linked extras, and custom fields for a service. Call before create-appointment / check-availability.', 'harudigi-booking-abilities-for-amelia' ),
		'ability_get_service_booking_options',
		true,
		Helpers::id_schema( 'service_id' )
	);
	r( 'amelia/list-categories', __( 'List Categories', 'harudigi-booking-abilities-for-amelia' ), __( 'Service categories.', 'harudigi-booking-abilities-for-amelia' ), 'ability_list_categories', true );
	r( 'amelia/get-category', __( 'Get Category', 'harudigi-booking-abilities-for-amelia' ), __( 'One category by category_id.', 'harudigi-booking-abilities-for-amelia' ), 'ability_get_category', true, Helpers::id_schema( 'category_id' ) );
	r( 'amelia/list-locations', __( 'List Locations', 'harudigi-booking-abilities-for-amelia' ), __( 'Paginated locations.', 'harudigi-booking-abilities-for-amelia' ), 'ability_list_locations', true, Helpers::list_schema() );
	r( 'amelia/get-location', __( 'Get Location', 'harudigi-booking-abilities-for-amelia' ), __( 'One location by location_id.', 'harudigi-booking-abilities-for-amelia' ), 'ability_get_location', true, Helpers::id_schema( 'location_id' ) );
	r( 'amelia/get-employee', __( 'Get Employee', 'harudigi-booking-abilities-for-amelia' ), __( 'One provider/employee by employee_id. Lists: amelia/list-employees.', 'harudigi-booking-abilities-for-amelia' ), 'ability_get_employee', true, Helpers::id_schema( 'employee_id' ) );
	r( 'amelia/list-packages', __( 'List Packages', 'harudigi-booking-abilities-for-amelia' ), __( 'Paginated packages.', 'harudigi-booking-abilities-for-amelia' ), 'ability_list_packages', true, Helpers::list_schema() );
	r( 'amelia/get-package', __( 'Get Package', 'harudigi-booking-abilities-for-amelia' ), __( 'One package by package_id.', 'harudigi-booking-abilities-for-amelia' ), 'ability_get_package', true, Helpers::id_schema( 'package_id' ) );
	r( 'amelia/list-extras', __( 'List Extras', 'harudigi-booking-abilities-for-amelia' ), __( 'All service extras (id, name, price, duration). For extras linked to one service, prefer get-service-booking-options.', 'harudigi-booking-abilities-for-amelia' ), 'ability_list_extras', true, Helpers::list_schema() );
	r( 'amelia/get-extra', __( 'Get Extra', 'harudigi-booking-abilities-for-amelia' ), __( 'One extra by extra_id.', 'harudigi-booking-abilities-for-amelia' ), 'ability_get_extra', true, Helpers::id_schema( 'extra_id' ) );
	r( 'amelia/list-resources', __( 'List Resources', 'harudigi-booking-abilities-for-amelia' ), __( 'Resources (rooms/equipment).', 'harudigi-booking-abilities-for-amelia' ), 'ability_list_resources', true, Helpers::list_schema() );
	r( 'amelia/get-resource', __( 'Get Resource', 'harudigi-booking-abilities-for-amelia' ), __( 'One resource by resource_id.', 'harudigi-booking-abilities-for-amelia' ), 'ability_get_resource', true, Helpers::id_schema( 'resource_id' ) );
	r( 'amelia/list-coupons', __( 'List Coupons', 'harudigi-booking-abilities-for-amelia' ), __( 'Discount coupons.', 'harudigi-booking-abilities-for-amelia' ), 'ability_list_coupons', true, Helpers::list_schema() );
	r( 'amelia/get-coupon', __( 'Get Coupon', 'harudigi-booking-abilities-for-amelia' ), __( 'One coupon by coupon_id.', 'harudigi-booking-abilities-for-amelia' ), 'ability_get_coupon', true, Helpers::id_schema( 'coupon_id' ) );
	r(
		'amelia/list-custom-fields',
		__( 'List Custom Fields', 'harudigi-booking-abilities-for-amelia' ),
		__( 'Amelia custom fields. Optional service_id filters to that service; for_events=true lists event fields. Prefer get-service-booking-options when booking a known service.', 'harudigi-booking-abilities-for-amelia' ),
		'ability_list_custom_fields',
		true,
		Helpers::list_schema(
			array(
				'service_id' => array( 'type' => 'integer', 'description' => 'Filter fields linked to this service.' ),
				'for_events' => array( 'type' => 'boolean', 'description' => 'If true, return event-linked fields.' ),
			)
		)
	);

	// --- service writes (add-service is native) ---
	r(
		'amelia/update-service',
		__( 'Update Service', 'harudigi-booking-abilities-for-amelia' ),
		__( 'Update service. service_id + partial fields (merged onto existing). Supports customPricing for duration tiers: {enabled:"duration", durations:[{duration:"1h",price:70},{duration:7200,price:120}]} or Amelia map. Also name/price/duration/providers. applyGlobally defaults false.', 'harudigi-booking-abilities-for-amelia' ),
		'ability_update_service',
		false,
		fields_id_schema( 'service_id' ),
		false,
		true
	);
	r( 'amelia/update-service-status', __( 'Update Service Status', 'harudigi-booking-abilities-for-amelia' ), __( 'Set service status (visible/hidden/disabled). Prefer hidden/disabled over delete.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_service_status', false, status_id_schema( 'service_id' ) );
	r( 'amelia/delete-service', __( 'Delete Service', 'harudigi-booking-abilities-for-amelia' ), __( 'PERMANENT delete. Prefer update-service-status (hidden/disabled). Requires confirm=true after user approval.', 'harudigi-booking-abilities-for-amelia' ), 'ability_delete_service', false, Helpers::id_confirm_schema( 'service_id' ), true, false );

	r( 'amelia/add-category', __( 'Add Category', 'harudigi-booking-abilities-for-amelia' ), __( 'Create category. Requires name (flat or fields).', 'harudigi-booking-abilities-for-amelia' ), 'ability_add_category', false, Helpers::create_schema( array( 'name' ), array( 'name' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string', 'description' => 'visible|hidden' ), 'color' => array( 'type' => 'string' ), 'position' => array( 'type' => 'integer' ), 'fields' => array( 'type' => 'object' ) ) ), false, false );
	r( 'amelia/update-category', __( 'Update Category', 'harudigi-booking-abilities-for-amelia' ), __( 'Update category. category_id + fields.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_category', false, fields_id_schema( 'category_id' ), false, true );
	r( 'amelia/delete-category', __( 'Delete Category', 'harudigi-booking-abilities-for-amelia' ), __( 'PERMANENT delete. Prefer hiding unused categories. Requires confirm=true after user approval.', 'harudigi-booking-abilities-for-amelia' ), 'ability_delete_category', false, Helpers::id_confirm_schema( 'category_id' ), true, false );

	r( 'amelia/add-location', __( 'Add Location', 'harudigi-booking-abilities-for-amelia' ), __( 'Create location. Requires name. address/lat/lng optional (sensible defaults applied when omitted, same as common admin flows).', 'harudigi-booking-abilities-for-amelia' ), 'ability_add_location', false, fields_body_schema( array( 'name' ) ), false, false );
	r( 'amelia/update-location', __( 'Update Location', 'harudigi-booking-abilities-for-amelia' ), __( 'Update location. location_id + fields.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_location', false, fields_id_schema( 'location_id' ), false, true );
	r( 'amelia/update-location-status', __( 'Update Location Status', 'harudigi-booking-abilities-for-amelia' ), __( 'Set location status. Prefer hidden/disabled over delete.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_location_status', false, status_id_schema( 'location_id' ) );
	r( 'amelia/delete-location', __( 'Delete Location', 'harudigi-booking-abilities-for-amelia' ), __( 'PERMANENT delete. Prefer update-location-status. Requires confirm=true after user approval.', 'harudigi-booking-abilities-for-amelia' ), 'ability_delete_location', false, Helpers::id_confirm_schema( 'location_id' ), true, false );

	r( 'amelia/add-employee', __( 'Add Employee', 'harudigi-booking-abilities-for-amelia' ), __( 'Create provider. firstName required; lastName/email optional (empty allowed, same as Amelia admin — do not invent fake emails). Optional serviceList, weekDayList. Password/WP-user link blocked.', 'harudigi-booking-abilities-for-amelia' ), 'ability_add_employee', false, fields_body_schema( array( 'firstName' ) ), false, false );
	r( 'amelia/update-employee', __( 'Update Employee', 'harudigi-booking-abilities-for-amelia' ), __( 'Update provider. employee_id + fields. Password/externalId blocked.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_employee', false, fields_id_schema( 'employee_id' ), false, true );
	r( 'amelia/update-employee-status', __( 'Update Employee Status', 'harudigi-booking-abilities-for-amelia' ), __( 'Set employee status. Prefer hidden/disabled over delete.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_employee_status', false, status_id_schema( 'employee_id' ) );
	r( 'amelia/delete-employee', __( 'Delete Employee', 'harudigi-booking-abilities-for-amelia' ), __( 'PERMANENT delete (refused if future appointments). Prefer status change. Requires confirm=true after user approval.', 'harudigi-booking-abilities-for-amelia' ), 'ability_delete_employee', false, Helpers::id_confirm_schema( 'employee_id' ), true, false );

	r( 'amelia/add-package', __( 'Add Package', 'harudigi-booking-abilities-for-amelia' ), __( 'Create package. Amelia requires name, price, calculatedPrice, and bookable (services). Pass via fields.', 'harudigi-booking-abilities-for-amelia' ), 'ability_add_package', false, fields_body_schema( array( 'name', 'price' ) ), false, false );
	r( 'amelia/update-package', __( 'Update Package', 'harudigi-booking-abilities-for-amelia' ), __( 'Update package. package_id + fields.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_package', false, fields_id_schema( 'package_id' ), false, true );
	r( 'amelia/update-package-status', __( 'Update Package Status', 'harudigi-booking-abilities-for-amelia' ), __( 'Set package status. Prefer hidden/disabled over delete.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_package_status', false, status_id_schema( 'package_id' ) );
	r( 'amelia/delete-package', __( 'Delete Package', 'harudigi-booking-abilities-for-amelia' ), __( 'PERMANENT delete. Prefer update-package-status. Requires confirm=true after user approval.', 'harudigi-booking-abilities-for-amelia' ), 'ability_delete_package', false, Helpers::id_confirm_schema( 'package_id' ), true, false );

	r( 'amelia/add-extra', __( 'Add Extra', 'harudigi-booking-abilities-for-amelia' ), __( 'Create service extra. Requires name, price, and serviceId (DB requires serviceId).', 'harudigi-booking-abilities-for-amelia' ), 'ability_add_extra', false, fields_body_schema( array( 'name', 'price', 'serviceId' ) ), false, false );
	r( 'amelia/update-extra', __( 'Update Extra', 'harudigi-booking-abilities-for-amelia' ), __( 'Update extra. extra_id + fields.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_extra', false, fields_id_schema( 'extra_id' ), false, true );
	r( 'amelia/delete-extra', __( 'Delete Extra', 'harudigi-booking-abilities-for-amelia' ), __( 'PERMANENT delete. Requires confirm=true after user approval.', 'harudigi-booking-abilities-for-amelia' ), 'ability_delete_extra', false, Helpers::id_confirm_schema( 'extra_id' ), true, false );

	r( 'amelia/add-resource', __( 'Add Resource', 'harudigi-booking-abilities-for-amelia' ), __( 'Create resource. fields: name, quantity, …', 'harudigi-booking-abilities-for-amelia' ), 'ability_add_resource', false, fields_body_schema( array( 'name' ) ), false, false );
	r( 'amelia/update-resource', __( 'Update Resource', 'harudigi-booking-abilities-for-amelia' ), __( 'Update resource. resource_id + fields.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_resource', false, fields_id_schema( 'resource_id' ), false, true );
	r( 'amelia/update-resource-status', __( 'Update Resource Status', 'harudigi-booking-abilities-for-amelia' ), __( 'Set resource status. Prefer status change over delete.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_resource_status', false, status_id_schema( 'resource_id' ) );
	r( 'amelia/delete-resource', __( 'Delete Resource', 'harudigi-booking-abilities-for-amelia' ), __( 'PERMANENT delete. Prefer update-resource-status. Requires confirm=true after user approval.', 'harudigi-booking-abilities-for-amelia' ), 'ability_delete_resource', false, Helpers::id_confirm_schema( 'resource_id' ), true, false );

	r( 'amelia/add-coupon', __( 'Add Coupon', 'harudigi-booking-abilities-for-amelia' ), __( 'Create coupon. Amelia requires code plus discount, deduction, limit, status, and service/event/package lists (can be empty arrays).', 'harudigi-booking-abilities-for-amelia' ), 'ability_add_coupon', false, fields_body_schema( array( 'code' ) ), false, false );
	r( 'amelia/update-coupon', __( 'Update Coupon', 'harudigi-booking-abilities-for-amelia' ), __( 'Update coupon. coupon_id + fields.', 'harudigi-booking-abilities-for-amelia' ), 'ability_update_coupon', false, fields_id_schema( 'coupon_id' ), false, true );
	r( 'amelia/delete-coupon', __( 'Delete Coupon', 'harudigi-booking-abilities-for-amelia' ), __( 'PERMANENT delete. Requires confirm=true after user approval.', 'harudigi-booking-abilities-for-amelia' ), 'ability_delete_coupon', false, Helpers::id_confirm_schema( 'coupon_id' ), true, false );

	r(
		'amelia/add-custom-field',
		__( 'Add Custom Field', 'harudigi-booking-abilities-for-amelia' ),
		__( 'Create a booking/event custom field. Required: label, type (text|textarea|select|checkbox|radio|…). Optional: required, service_ids / all_services, options for select/radio.', 'harudigi-booking-abilities-for-amelia' ),
		'ability_add_custom_field',
		false,
		Helpers::create_schema(
			array( 'label', 'type' ),
			array(
				'label'        => array( 'type' => 'string' ),
				'type'         => array( 'type' => 'string', 'description' => 'text|textarea|select|checkbox|radio|datepicker|file|…' ),
				'required'     => array( 'type' => 'boolean' ),
				'all_services' => array( 'type' => 'boolean' ),
				'service_ids'  => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'all_events'   => array( 'type' => 'boolean' ),
				'options'      => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'fields'       => array( 'type' => 'object', 'description' => 'Or pass full Amelia customField object via fields.customField / fields.' ),
			)
		),
		false,
		false
	);
	r(
		'amelia/update-custom-field',
		__( 'Update Custom Field', 'harudigi-booking-abilities-for-amelia' ),
		__( 'Update custom field. custom_field_id + fields (label, type, required, services, options, …). Partial fields are merged onto the existing field.', 'harudigi-booking-abilities-for-amelia' ),
		'ability_update_custom_field',
		false,
		fields_id_schema( 'custom_field_id' ),
		false,
		true
	);
	r(
		'amelia/delete-custom-field',
		__( 'Delete Custom Field', 'harudigi-booking-abilities-for-amelia' ),
		__( 'PERMANENT delete. Requires confirm=true after user approval.', 'harudigi-booking-abilities-for-amelia' ),
		'ability_delete_custom_field',
		false,
		Helpers::id_confirm_schema( 'custom_field_id' ),
		true,
		false
	);
}

/** @param array<string,mixed>|null $schema */
function r( string $name, string $label, string $desc, string $cb, bool $readonly, ?array $schema = null, bool $destructive = false, bool $idempotent = true ): void {
	$args = array(
		'label'       => $label,
		'description' => $desc,
		'callback'    => __NAMESPACE__ . '\\' . $cb,
		'readonly'    => $readonly,
		'destructive' => $destructive,
		'idempotent'  => $idempotent,
	);
	if ( null !== $schema ) {
		$args['input_schema'] = $schema;
	}
	Helpers::register( $name, $args );
}

/** @return array<string,mixed> */
function fields_id_schema( string $id_field ): array {
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( $id_field, 'fields' ),
		'properties'           => array(
			$id_field => array( 'type' => 'integer' ),
			'fields'  => array( 'type' => 'object', 'description' => 'Amelia entity fields (camelCase as in Amelia admin API).' ),
		),
	);
}

/**
 * Create schema: flat props OR nested `fields` (both accepted).
 *
 * @param string[] $required Required field names (flat or inside fields).
 * @return array<string,mixed>
 */
function fields_body_schema( array $required ): array {
	$props = array(
		'fields' => array(
			'type'                 => 'object',
			'description'          => 'Optional nested body (same keys as flat).',
			'additionalProperties' => true,
		),
	);
	foreach ( $required as $key ) {
		$props[ $key ] = array( 'type' => array( 'string', 'number', 'integer', 'boolean', 'array', 'object' ) );
	}
	return array(
		'type'                 => 'object',
		'additionalProperties' => true,
		'properties'           => $props,
	);
}

/** @return array<string,mixed> */
function status_id_schema( string $id_field ): array {
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( $id_field, 'status' ),
		'properties'           => array(
			$id_field => array( 'type' => 'integer' ),
			'status'  => array(
				'type'        => 'string',
				'description' => 'visible|hidden|disabled (shared for resources)',
				'enum'        => array( 'visible', 'hidden', 'disabled', 'shared' ),
			),
		),
	);
}

/** @return array<string,mixed>|\WP_Error */
function get_by_id( string $controller, array $input, string $field ) {
	$id = Helpers::parse_id( $input[ $field ] ?? 0, $field );
	return is_wp_error( $id ) ? $id : Helpers::invoke( $controller, array(), array( 'id' => $id ), 'GET' );
}

/** @return array<string,mixed>|\WP_Error */
function post_status( string $controller, array $input, string $field ) {
	$id = Helpers::parse_id( $input[ $field ] ?? 0, $field );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$status = sanitize_text_field( (string) ( $input['status'] ?? '' ) );
	$ok     = Helpers::assert_entity_status( $status );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	return Helpers::invoke( $controller, array( 'status' => $status ), array( 'id' => $id ) );
}

/** @return array<string,mixed>|\WP_Error */
function post_fields( string $controller, array $input, string $field ) {
	$id = Helpers::parse_id( $input[ $field ] ?? 0, $field );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$fields = Helpers::body_from_input( $input, array( $field ) );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	return Helpers::invoke( $controller, $fields, array( 'id' => $id ) );
}

/** @return array<string,mixed>|\WP_Error */
function post_delete( string $controller, array $input, string $field ) {
	$ok = Helpers::require_confirm( $input );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$id = Helpers::parse_id( $input[ $field ] ?? 0, $field );
	return is_wp_error( $id ) ? $id : Helpers::invoke( $controller, array(), array( 'id' => $id ) );
}

/** @return array<string,mixed>|\WP_Error */
function post_create( string $controller, array $input ) {
	$fields = Helpers::body_from_input( $input );
	return is_wp_error( $fields ) ? $fields : Helpers::invoke( $controller, $fields );
}

function ability_get_service( array $i = array() ) { return get_by_id( GetServiceController::class, $i, 'service_id' ); }
function ability_get_service_booking_options( array $i = array() ) {
	require_once __DIR__ . '/booking-payload.php';
	$id = Helpers::parse_id( $i['service_id'] ?? 0, 'service_id' );
	return is_wp_error( $id ) ? $id : get_service_booking_options( $id );
}
function ability_list_categories( array $i = array() ) { unset( $i ); return Helpers::invoke( GetCategoriesController::class, array(), array(), 'GET' ); }
function ability_get_category( array $i = array() ) { return get_by_id( GetCategoryController::class, $i, 'category_id' ); }
function ability_list_locations( array $i = array() ) { return Helpers::invoke( GetLocationsController::class, Helpers::list_params( $i ), array(), 'GET' ); }
function ability_get_location( array $i = array() ) { return get_by_id( GetLocationController::class, $i, 'location_id' ); }
function ability_get_employee( array $i = array() ) { return get_by_id( GetProviderController::class, $i, 'employee_id' ); }
function ability_list_packages( array $i = array() ) { return Helpers::invoke( GetPackagesController::class, Helpers::list_params( $i ), array(), 'GET' ); }
function ability_get_package( array $i = array() ) { return get_by_id( GetPackageController::class, $i, 'package_id' ); }
function ability_list_extras( array $i = array() ) { return Helpers::invoke( GetExtrasController::class, Helpers::list_params( $i ), array(), 'GET' ); }
function ability_get_extra( array $i = array() ) { return get_by_id( GetExtraController::class, $i, 'extra_id' ); }
function ability_list_resources( array $i = array() ) { return Helpers::invoke( GetResourcesController::class, Helpers::list_params( $i ), array(), 'GET' ); }
function ability_get_resource( array $i = array() ) { return get_by_id( GetResourceController::class, $i, 'resource_id' ); }
function ability_list_coupons( array $i = array() ) { return Helpers::invoke( GetCouponsController::class, Helpers::list_params( $i ), array(), 'GET' ); }
function ability_get_coupon( array $i = array() ) { return get_by_id( GetCouponController::class, $i, 'coupon_id' ); }
function ability_list_custom_fields( array $i = array() ) {
	require_once __DIR__ . '/booking-payload.php';
	$service_id = isset( $i['service_id'] ) ? (int) $i['service_id'] : 0;
	$for_events = ! empty( $i['for_events'] );
	if ( $service_id > 0 || $for_events ) {
		$fields = list_custom_fields_for_entity( $service_id, $for_events );
		return is_wp_error( $fields ) ? $fields : array( 'customFields' => $fields );
	}
	return Helpers::invoke( GetCustomFieldsController::class, array(), array(), 'GET' );
}

function ability_update_service( array $i = array() ) {
	require_once __DIR__ . '/booking-payload.php';
	$id = Helpers::parse_id( $i['service_id'] ?? 0, 'service_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$patch = Helpers::body_from_input( $i, array( 'service_id' ) );
	if ( is_wp_error( $patch ) ) {
		return $patch;
	}
	$existing_res = Helpers::invoke( GetServiceController::class, array(), array( 'id' => $id ), 'GET' );
	if ( is_wp_error( $existing_res ) ) {
		return $existing_res;
	}
	$existing = $existing_res['data']['service'] ?? ( $existing_res['service'] ?? $existing_res );
	if ( ! is_array( $existing ) ) {
		return new \WP_Error( 'amelia_not_found', __( 'Service not found.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	$fields = merge_service_update_fields( $existing, $patch );
	return Helpers::invoke( UpdateServiceController::class, $fields, array( 'id' => $id ) );
}
function ability_update_service_status( array $i = array() ) { return post_status( UpdateServiceStatusController::class, $i, 'service_id' ); }
function ability_delete_service( array $i = array() ) { return post_delete( DeleteServiceController::class, $i, 'service_id' ); }

function ability_add_category( array $i = array() ) {
	$fields = Helpers::body_from_input( $i );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	if ( empty( $fields['name'] ) ) {
		return new \WP_Error( 'invalid_fields', __( 'name is required.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	$fields['status']   = $fields['status'] ?? 'visible';
	$fields['position'] = isset( $fields['position'] ) ? (int) $fields['position'] : 1;
	$fields['color']    = $fields['color'] ?? '#1788FB';
	return Helpers::invoke( AddCategoryController::class, $fields );
}
function ability_update_category( array $i = array() ) { return post_fields( UpdateCategoryController::class, $i, 'category_id' ); }
function ability_delete_category( array $i = array() ) { return post_delete( DeleteCategoryController::class, $i, 'category_id' ); }

function ability_add_location( array $i = array() ) {
	$fields = Helpers::body_from_input( $i );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	if ( empty( $fields['name'] ) ) {
		return new \WP_Error( 'invalid_fields', __( 'name is required.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	$fields['status']    = $fields['status'] ?? 'visible';
	// Amelia GeoTag treats 0 as empty — default to a non-zero placeholder if omitted.
	$fields['latitude']  = ( isset( $fields['latitude'] ) && ! empty( (float) $fields['latitude'] ) )
		? (float) $fields['latitude'] : 35.0116;
	$fields['longitude'] = ( isset( $fields['longitude'] ) && ! empty( (float) $fields['longitude'] ) )
		? (float) $fields['longitude'] : 135.7681;
	$fields['address']   = $fields['address'] ?? '';
	$fields['phone']     = $fields['phone'] ?? '';
	$fields['description'] = $fields['description'] ?? '';
	return Helpers::invoke( AddLocationController::class, $fields );
}
function ability_update_location( array $i = array() ) { return post_fields( UpdateLocationController::class, $i, 'location_id' ); }
function ability_update_location_status( array $i = array() ) { return post_status( UpdateLocationStatusController::class, $i, 'location_id' ); }
function ability_delete_location( array $i = array() ) { return post_delete( DeleteLocationController::class, $i, 'location_id' ); }

function ability_add_employee( array $i = array() ) {
	$fields = Helpers::body_from_input( $i );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	$first = sanitize_text_field( (string) ( $fields['firstName'] ?? '' ) );
	if ( '' === $first ) {
		return new \WP_Error( 'invalid_firstName', __( 'firstName is required.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	$fields['firstName'] = $first;
	// Amelia mandatoryFields treats null as missing; empty string matches admin UI.
	$fields['lastName'] = sanitize_text_field( (string) ( $fields['lastName'] ?? '' ) );
	$email_raw          = isset( $fields['email'] ) ? trim( (string) $fields['email'] ) : '';
	if ( '' !== $email_raw ) {
		$email = sanitize_email( $email_raw );
		if ( '' === $email || ! is_email( $email ) ) {
			return new \WP_Error(
				'invalid_email',
				__( 'email is invalid. Omit email or pass an empty string when unknown.', 'harudigi-booking-abilities-for-amelia' )
			);
		}
		$fields['email'] = $email;
	} else {
		$fields['email'] = '';
	}
	$fields['type']   = 'provider';
	$fields['status'] = $fields['status'] ?? 'visible';
	// Never link a WP user or set a password via MCP. Omit externalId (null is dropped by isset).
	unset( $fields['externalId'], $fields['external_id'], $fields['password'] );
	// Default Mon–Fri 09:00–17:00 so slots/appointments work without a separate schedule step.
	if ( empty( $fields['weekDayList'] ) || ! is_array( $fields['weekDayList'] ) ) {
		$fields['weekDayList'] = array();
		foreach ( array( 1, 2, 3, 4, 5 ) as $day ) {
			$fields['weekDayList'][] = array(
				'dayIndex'    => $day,
				'startTime'   => '09:00:00',
				'endTime'     => '17:00:00',
				'timeOutList' => array(),
				'periodList'  => array(),
			);
		}
	}
	if ( ! isset( $fields['specialDayList'] ) ) {
		$fields['specialDayList'] = array();
	}
	if ( ! isset( $fields['dayOffList'] ) ) {
		$fields['dayOffList'] = array();
	}
	return Helpers::invoke( AddProviderController::class, $fields );
}
function ability_update_employee( array $i = array() ) {
	$id = Helpers::parse_id( $i['employee_id'] ?? 0, 'employee_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$fields = Helpers::body_from_input( $i, array( 'employee_id' ) );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	$fields['type'] = 'provider';
	unset( $fields['externalId'], $fields['external_id'], $fields['password'] );
	return Helpers::invoke( UpdateProviderController::class, $fields, array( 'id' => $id ) );
}
function ability_update_employee_status( array $i = array() ) { return post_status( UpdateProviderStatusController::class, $i, 'employee_id' ); }
function ability_delete_employee( array $i = array() ) { return post_delete( DeleteUserController::class, $i, 'employee_id' ); }

function ability_add_package( array $i = array() ) { return post_create( AddPackageController::class, $i ); }
function ability_update_package( array $i = array() ) { return post_fields( UpdatePackageController::class, $i, 'package_id' ); }
function ability_update_package_status( array $i = array() ) { return post_status( UpdatePackageStatusController::class, $i, 'package_id' ); }
function ability_delete_package( array $i = array() ) { return post_delete( DeletePackageController::class, $i, 'package_id' ); }

function ability_add_extra( array $i = array() ) {
	$fields = Helpers::body_from_input( $i );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	if ( empty( $fields['serviceId'] ) ) {
		return new \WP_Error( 'missing_service_id', __( 'serviceId is required for extras.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	$fields['maxQuantity']     = isset( $fields['maxQuantity'] ) ? (int) $fields['maxQuantity'] : 1;
	$fields['aggregatedPrice'] = array_key_exists( 'aggregatedPrice', $fields ) ? $fields['aggregatedPrice'] : true;
	$fields['position']        = isset( $fields['position'] ) ? (int) $fields['position'] : 1;
	$fields['duration']        = isset( $fields['duration'] ) ? (int) $fields['duration'] : null;
	$fields['description']     = $fields['description'] ?? '';
	return Helpers::invoke( AddExtraController::class, $fields );
}
function ability_update_extra( array $i = array() ) {
	$id = Helpers::parse_id( $i['extra_id'] ?? 0, 'extra_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$patch = Helpers::body_from_input( $i, array( 'extra_id' ) );
	if ( is_wp_error( $patch ) ) {
		return $patch;
	}
	$existing_res = Helpers::invoke( GetExtraController::class, array(), array( 'id' => $id ), 'GET' );
	if ( is_wp_error( $existing_res ) ) {
		return $existing_res;
	}
	$existing = $existing_res['data']['extra'] ?? ( $existing_res['extra'] ?? $existing_res );
	if ( ! is_array( $existing ) ) {
		return new \WP_Error( 'amelia_not_found', __( 'Extra not found.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	$fields = array_merge( $existing, $patch );
	$fields['id']          = $id;
	$fields['name']        = (string) ( $fields['name'] ?? '' );
	$fields['description'] = (string) ( $fields['description'] ?? '' );
	$fields['price']       = isset( $fields['price'] ) ? (float) $fields['price'] : 0.0;
	$fields['maxQuantity'] = isset( $fields['maxQuantity'] ) ? (int) $fields['maxQuantity'] : 1;
	// Amelia mandatoryFields treats null as missing; 0 means "no duration" (ExtraFactory skips empty).
	if ( ! isset( $fields['duration'] ) || '' === $fields['duration'] || false === $fields['duration'] ) {
		$fields['duration'] = 0;
	} else {
		$fields['duration'] = (int) $fields['duration'];
	}
	unset( $fields['type'] );
	return Helpers::invoke( UpdateExtraController::class, $fields, array( 'id' => $id ) );
}
function ability_delete_extra( array $i = array() ) { return post_delete( DeleteExtraController::class, $i, 'extra_id' ); }

function ability_add_resource( array $i = array() ) {
	$fields = Helpers::body_from_input( $i );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	$fields['quantity'] = isset( $fields['quantity'] ) ? (int) $fields['quantity'] : 1;
	$fields['status']   = $fields['status'] ?? 'shared';
	$fields['entities'] = is_array( $fields['entities'] ?? null ) ? $fields['entities'] : array();
	return Helpers::invoke( AddResourceController::class, $fields );
}
function ability_update_resource( array $i = array() ) { return post_fields( UpdateResourceController::class, $i, 'resource_id' ); }
function ability_update_resource_status( array $i = array() ) { return post_status( UpdateResourceStatusController::class, $i, 'resource_id' ); }
function ability_delete_resource( array $i = array() ) { return post_delete( DeleteResourceController::class, $i, 'resource_id' ); }

function ability_add_coupon( array $i = array() ) {
	$fields = Helpers::body_from_input( $i );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	if ( empty( $fields['code'] ) ) {
		return new \WP_Error( 'invalid_fields', __( 'code is required.', 'harudigi-booking-abilities-for-amelia' ) );
	}
	// Amelia mandatoryFields — empty/zero defaults match typical admin create form.
	$fields['discount']  = isset( $fields['discount'] ) ? $fields['discount'] : 0;
	$fields['deduction'] = isset( $fields['deduction'] ) ? $fields['deduction'] : 0;
	$fields['limit']     = isset( $fields['limit'] ) ? (int) $fields['limit'] : 1;
	$fields['status']    = $fields['status'] ?? 'visible';
	$fields['services']  = is_array( $fields['services'] ?? null ) ? $fields['services'] : array();
	$fields['events']    = is_array( $fields['events'] ?? null ) ? $fields['events'] : array();
	$fields['packages']  = is_array( $fields['packages'] ?? null ) ? $fields['packages'] : array();
	return Helpers::invoke( AddCouponController::class, $fields );
}
function ability_update_coupon( array $i = array() ) { return post_fields( UpdateCouponController::class, $i, 'coupon_id' ); }
function ability_delete_coupon( array $i = array() ) { return post_delete( DeleteCouponController::class, $i, 'coupon_id' ); }

/**
 * Build Amelia customField services stubs from IDs.
 *
 * @param int[] $ids Service IDs.
 * @return array<int, array{id:int}>
 */
function custom_field_service_stubs( array $ids ): array {
	$out = array();
	foreach ( $ids as $sid ) {
		$sid = (int) $sid;
		if ( $sid > 0 ) {
			$out[] = array( 'id' => $sid );
		}
	}
	return $out;
}

/**
 * Map MCP-friendly custom field types to Amelia values (hyphenated).
 * Do not use sanitize_key — it strips hyphens (text-area → textarea).
 *
 * @param mixed $type Raw type.
 * @return string
 */
function normalize_custom_field_type( $type ): string {
	$t = strtolower( trim( (string) $type ) );
	$t = str_replace( array( '_', ' ' ), '-', $t );
	$map = array(
		'text'       => 'text',
		'textarea'   => 'text-area',
		'text-area'  => 'text-area',
		'select'     => 'select',
		'checkbox'   => 'checkbox',
		'radio'      => 'radio',
		'content'    => 'content',
		'address'    => 'address',
		'datepicker' => 'datepicker',
		'file'       => 'file',
	);
	return $map[ $t ] ?? ( preg_match( '/^[a-z0-9\-]+$/', $t ) ? $t : 'text' );
}

/**
 * @param array<string,mixed> $i
 * @return array<string,mixed>|\WP_Error
 */
function ability_add_custom_field( array $i = array() ) {
	$fields = Helpers::body_from_input( $i );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	// Accept nested customField or flat MCP-friendly keys.
	$cf = isset( $fields['customField'] ) && is_array( $fields['customField'] ) ? $fields['customField'] : $fields;

	$label = sanitize_text_field( (string) ( $cf['label'] ?? '' ) );
	$type  = normalize_custom_field_type( $cf['type'] ?? 'text' );
	if ( '' === $label || '' === $type ) {
		return new \WP_Error( 'invalid_fields', __( 'label and type are required.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$all_services = ! empty( $cf['allServices'] ) || ! empty( $cf['all_services'] );
	$all_events   = ! empty( $cf['allEvents'] ) || ! empty( $cf['all_events'] );
	$service_ids  = array();
	if ( ! empty( $cf['service_ids'] ) && is_array( $cf['service_ids'] ) ) {
		$service_ids = $cf['service_ids'];
	} elseif ( ! empty( $cf['services'] ) && is_array( $cf['services'] ) ) {
		foreach ( $cf['services'] as $s ) {
			$service_ids[] = is_array( $s ) ? (int) ( $s['id'] ?? 0 ) : (int) $s;
		}
	}

	$body = array(
		'customField' => array(
			'label'       => $label,
			'type'        => $type,
			'required'    => ! empty( $cf['required'] ),
			'position'    => isset( $cf['position'] ) ? (int) $cf['position'] : 1,
			'width'       => isset( $cf['width'] ) ? (int) $cf['width'] : 50,
			'saveType'    => sanitize_key( (string) ( $cf['saveType'] ?? $cf['save_type'] ?? 'bookings' ) ),
			'allServices' => $all_services,
			'allEvents'   => $all_events,
			'services'    => $all_services ? array() : custom_field_service_stubs( $service_ids ),
			'events'      => array(),
			'options'     => is_array( $cf['options'] ?? null ) ? $cf['options'] : array(),
		),
	);

	return Helpers::invoke( AddCustomFieldController::class, $body );
}

/**
 * @param array<string,mixed> $i
 * @return array<string,mixed>|\WP_Error
 */
function ability_update_custom_field( array $i = array() ) {
	$id = Helpers::parse_id( $i['custom_field_id'] ?? 0, 'custom_field_id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$patch = Helpers::body_from_input( $i, array( 'custom_field_id' ) );
	if ( is_wp_error( $patch ) ) {
		return $patch;
	}
	if ( isset( $patch['customField'] ) && is_array( $patch['customField'] ) ) {
		$patch = $patch['customField'];
	}

	$list = Helpers::invoke( GetCustomFieldsController::class, array(), array(), 'GET' );
	if ( is_wp_error( $list ) ) {
		return $list;
	}
	$data   = isset( $list['data'] ) && is_array( $list['data'] ) ? $list['data'] : $list;
	$fields = $data['customFields'] ?? $data;
	$existing = null;
	if ( is_array( $fields ) ) {
		foreach ( $fields as $f ) {
			if ( is_array( $f ) && (int) ( $f['id'] ?? 0 ) === $id ) {
				$existing = $f;
				break;
			}
		}
	}
	if ( ! $existing ) {
		return new \WP_Error( 'amelia_not_found', __( 'Custom field not found.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$merged = array_merge( $existing, $patch );
	$merged['id'] = $id;

	if ( isset( $patch['all_services'] ) ) {
		$merged['allServices'] = (bool) $patch['all_services'];
	}
	if ( isset( $patch['all_events'] ) ) {
		$merged['allEvents'] = (bool) $patch['all_events'];
	}
	if ( isset( $patch['service_ids'] ) && is_array( $patch['service_ids'] ) ) {
		$merged['services']    = custom_field_service_stubs( $patch['service_ids'] );
		$merged['allServices'] = false;
	} elseif ( isset( $merged['services'] ) && is_array( $merged['services'] ) ) {
		$ids = array();
		foreach ( $merged['services'] as $s ) {
			$ids[] = is_array( $s ) ? (int) ( $s['id'] ?? 0 ) : (int) $s;
		}
		$merged['services'] = custom_field_service_stubs( $ids );
	} else {
		$merged['services'] = array();
	}

	if ( ! isset( $merged['events'] ) || ! is_array( $merged['events'] ) ) {
		$merged['events'] = array();
	}
	if ( ! isset( $merged['options'] ) || ! is_array( $merged['options'] ) ) {
		$merged['options'] = array();
	}
	$merged['required'] = ! empty( $merged['required'] );
	$merged['position'] = isset( $merged['position'] ) ? (int) $merged['position'] : 1;
	$merged['width']    = isset( $merged['width'] ) ? (int) $merged['width'] : 50;
	$merged['saveType'] = sanitize_key( (string) ( $merged['saveType'] ?? 'bookings' ) );
	// Prefer patch type, else existing; map aliases (textarea → text-area). Never sanitize_key.
	$type_src       = array_key_exists( 'type', $patch ) ? $patch['type'] : ( $existing['type'] ?? 'text' );
	$merged['type'] = normalize_custom_field_type( $type_src !== '' && null !== $type_src ? $type_src : 'text' );
	$merged['label'] = sanitize_text_field( (string) ( $merged['label'] ?? '' ) );

	return Helpers::invoke( UpdateCustomFieldController::class, $merged );
}

/**
 * @param array<string,mixed> $i
 * @return array<string,mixed>|\WP_Error
 */
function ability_delete_custom_field( array $i = array() ) {
	return post_delete( DeleteCustomFieldController::class, $i, 'custom_field_id' );
}
