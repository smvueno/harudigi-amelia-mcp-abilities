<?php
/**
 * Entity → Amelia controller map for meta tools.
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
use AmeliaBooking\Application\Controller\Bookable\Service\AddServiceController;
use AmeliaBooking\Application\Controller\Bookable\Service\DeleteServiceController;
use AmeliaBooking\Application\Controller\Bookable\Service\GetServiceController;
use AmeliaBooking\Application\Controller\Bookable\Service\GetServicesController;
use AmeliaBooking\Application\Controller\Bookable\Service\UpdateServiceController;
use AmeliaBooking\Application\Controller\Bookable\Service\UpdateServiceStatusController;
use AmeliaBooking\Application\Controller\Booking\Appointment\GetAppointmentController;
use AmeliaBooking\Application\Controller\Booking\Appointment\GetAppointmentsController;
use AmeliaBooking\Application\Controller\Booking\Event\GetEventController;
use AmeliaBooking\Application\Controller\Booking\Event\GetEventsController;
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
use AmeliaBooking\Application\Controller\Notification\GetNotificationsController;
use AmeliaBooking\Application\Controller\User\Customer\AddCustomerController;
use AmeliaBooking\Application\Controller\User\Customer\GetCustomerController;
use AmeliaBooking\Application\Controller\User\Customer\GetCustomersController;
use AmeliaBooking\Application\Controller\User\Customer\UpdateCustomerController;
use AmeliaBooking\Application\Controller\User\Customer\UpdateCustomerStatusController;
use AmeliaBooking\Application\Controller\User\DeleteUserController;
use AmeliaBooking\Application\Controller\User\Provider\AddProviderController;
use AmeliaBooking\Application\Controller\User\Provider\GetProviderController;
use AmeliaBooking\Application\Controller\User\Provider\GetProvidersController;
use AmeliaBooking\Application\Controller\User\Provider\UpdateProviderController;
use AmeliaBooking\Application\Controller\User\Provider\UpdateProviderStatusController;

final class Entity_Map {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return array(
			'service'       => array(
				'label'    => 'Service',
				'id_param' => 'id',
				'list'     => array( GetServicesController::class, 'GET' ),
				'get'      => array( GetServiceController::class, 'GET' ),
				'create'   => array( AddServiceController::class, 'POST' ),
				'update'   => array( UpdateServiceController::class, 'POST' ),
				'delete'   => array( DeleteServiceController::class, 'POST', 'delete' ),
				'status'   => array( UpdateServiceStatusController::class, 'POST' ),
				'mutate'   => true,
				'example'  => array( 'name' => 'Classic Location Photo Shoot', 'categoryId' => 1, 'duration' => 3600, 'price' => 10000, 'minCapacity' => 1, 'maxCapacity' => 1, 'providers' => array( 1 ) ),
			),
			'category'      => array(
				'label'    => 'Category',
				'id_param' => 'id',
				'list'     => array( GetCategoriesController::class, 'GET' ),
				'get'      => array( GetCategoryController::class, 'GET' ),
				'create'   => array( AddCategoryController::class, 'POST' ),
				'update'   => array( UpdateCategoryController::class, 'POST' ),
				'delete'   => array( DeleteCategoryController::class, 'POST', 'delete' ),
				'mutate'   => true,
				'example'  => array( 'name' => 'Photo Shoots', 'status' => 'visible' ),
			),
			'location'      => array(
				'label'    => 'Location',
				'id_param' => 'id',
				'list'     => array( GetLocationsController::class, 'GET' ),
				'get'      => array( GetLocationController::class, 'GET' ),
				'create'   => array( AddLocationController::class, 'POST' ),
				'update'   => array( UpdateLocationController::class, 'POST' ),
				'delete'   => array( DeleteLocationController::class, 'POST', 'delete' ),
				'status'   => array( UpdateLocationStatusController::class, 'POST' ),
				'mutate'   => true,
				'example'  => array( 'name' => 'Studio', 'address' => 'Kyoto', 'status' => 'visible' ),
			),
			'employee'      => array(
				'label'    => 'Employee / provider',
				'id_param' => 'id',
				'list'     => array( GetProvidersController::class, 'GET' ),
				'get'      => array( GetProviderController::class, 'GET' ),
				'create'   => array( AddProviderController::class, 'POST' ),
				'update'   => array( UpdateProviderController::class, 'POST' ),
				'delete'   => array( DeleteUserController::class, 'POST', 'delete' ),
				'status'   => array( UpdateProviderStatusController::class, 'POST' ),
				'mutate'   => true,
				'example'  => array( 'firstName' => 'Ada', 'lastName' => 'Photographer', 'email' => 'ada@example.com', 'type' => 'provider', 'status' => 'visible', 'externalId' => -1 ),
			),
			'customer'      => array(
				'label'    => 'Customer',
				'id_param' => 'id',
				'list'     => array( GetCustomersController::class, 'GET' ),
				'get'      => array( GetCustomerController::class, 'GET' ),
				'create'   => array( AddCustomerController::class, 'POST' ),
				'update'   => array( UpdateCustomerController::class, 'POST' ),
				'delete'   => array( DeleteUserController::class, 'POST', 'delete' ),
				'status'   => array( UpdateCustomerStatusController::class, 'POST' ),
				'mutate'   => true,
				'example'  => array( 'firstName' => 'MCP', 'lastName' => 'TestCustomer', 'email' => 'mcp-test@example.invalid', 'phone' => '0900000000', 'note' => 'Internal customer note', 'type' => 'customer', 'status' => 'visible' ),
			),
			'package'       => array(
				'label'    => 'Package',
				'id_param' => 'id',
				'list'     => array( GetPackagesController::class, 'GET' ),
				'get'      => array( GetPackageController::class, 'GET' ),
				'create'   => array( AddPackageController::class, 'POST' ),
				'update'   => array( UpdatePackageController::class, 'POST' ),
				'delete'   => array( DeletePackageController::class, 'POST', 'delete' ),
				'status'   => array( UpdatePackageStatusController::class, 'POST' ),
				'mutate'   => true,
			),
			'extra'         => array(
				'label'    => 'Extra (add-on)',
				'id_param' => 'id',
				'list'     => array( GetExtrasController::class, 'GET' ),
				'get'      => array( GetExtraController::class, 'GET' ),
				'create'   => array( AddExtraController::class, 'POST' ),
				'update'   => array( UpdateExtraController::class, 'POST' ),
				'delete'   => array( DeleteExtraController::class, 'POST', 'delete' ),
				'mutate'   => true,
				'example'  => array( 'name' => 'Kimono Rental', 'price' => 8000, 'duration' => 0, 'maxQuantity' => 10, 'serviceId' => 1 ),
			),
			'resource'      => array(
				'label'    => 'Resource',
				'id_param' => 'id',
				'list'     => array( GetResourcesController::class, 'GET' ),
				'get'      => array( GetResourceController::class, 'GET' ),
				'create'   => array( AddResourceController::class, 'POST' ),
				'update'   => array( UpdateResourceController::class, 'POST' ),
				'delete'   => array( DeleteResourceController::class, 'POST', 'delete' ),
				'status'   => array( UpdateResourceStatusController::class, 'POST' ),
				'mutate'   => true,
			),
			'coupon'        => array(
				'label'    => 'Coupon',
				'id_param' => 'id',
				'list'     => array( GetCouponsController::class, 'GET' ),
				'get'      => array( GetCouponController::class, 'GET' ),
				'create'   => array( AddCouponController::class, 'POST' ),
				'update'   => array( UpdateCouponController::class, 'POST' ),
				'delete'   => array( DeleteCouponController::class, 'POST', 'delete' ),
				'mutate'   => true,
			),
			'custom_field'  => array(
				'label'    => 'Custom field',
				'id_param' => 'id',
				'list'     => array( GetCustomFieldsController::class, 'GET' ),
				'get'      => null,
				'create'   => array( AddCustomFieldController::class, 'POST' ),
				'update'   => array( UpdateCustomFieldController::class, 'POST' ),
				'delete'   => array( DeleteCustomFieldController::class, 'POST', 'delete' ),
				'mutate'   => true,
				'example'  => array( 'label' => 'Preferred locations', 'type' => 'text-area', 'required' => false, 'allServices' => true ),
			),
			'appointment'   => array(
				'label'    => 'Appointment (use amelia/book to create/update/cancel)',
				'id_param' => 'id',
				'list'     => array( GetAppointmentsController::class, 'GET' ),
				'get'      => array( GetAppointmentController::class, 'GET' ),
				'mutate'   => false,
			),
			'event'         => array(
				'label'    => 'Event',
				'id_param' => 'id',
				'list'     => array( GetEventsController::class, 'GET' ),
				'get'      => array( GetEventController::class, 'GET' ),
				'mutate'   => false,
				'note'     => 'Create/update/delete events via amelia/book action=create_event|update_event|delete_event',
			),
			'notification'  => array(
				'label'    => 'Notification templates (read-only via query)',
				'id_param' => 'id',
				'list'     => array( GetNotificationsController::class, 'GET' ),
				'get'      => null,
				'mutate'   => false,
			),
		);
	}

	/** @return array<string, mixed>|\WP_Error */
	public static function get( string $entity ) {
		$map = self::all();
		$key = strtolower( str_replace( array( '-', ' ' ), '_', $entity ) );
		$aliases = array(
			'provider'       => 'employee',
			'providers'      => 'employee',
			'employees'      => 'employee',
			'services'       => 'service',
			'categories'     => 'category',
			'locations'      => 'location',
			'customers'      => 'customer',
			'packages'       => 'package',
			'extras'         => 'extra',
			'resources'      => 'resource',
			'coupons'        => 'coupon',
			'customfield'    => 'custom_field',
			'customfields'   => 'custom_field',
			'custom-field'   => 'custom_field',
			'appointments'   => 'appointment',
			'events'         => 'event',
			'notifications'  => 'notification',
		);
		if ( isset( $aliases[ $key ] ) ) {
			$key = $aliases[ $key ];
		}
		if ( ! isset( $map[ $key ] ) ) {
			return new \WP_Error(
				'unknown_entity',
				sprintf(
					/* translators: %s: entity list */
					__( 'Unknown entity. Use: %s', 'harudigi-booking-abilities-for-amelia' ),
					implode( ', ', array_keys( $map ) )
				)
			);
		}
		$def       = $map[ $key ];
		$def['key'] = $key;
		return $def;
	}

	/** @return string[] */
	public static function keys(): array {
		return array_keys( self::all() );
	}
}
