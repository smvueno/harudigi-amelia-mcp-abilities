<?php
/**
 * Meta mutate handlers + shared helpers.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AmeliaBooking\Application\Controller\Bookable\Service\GetServiceController;
use AmeliaBooking\Application\Controller\Bookable\Service\UpdateServiceController;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;

/**
 * Amelia default appointment status (service override → global). Never hardcode.
 */
function get_default_appointment_status( int $service_id = 0 ): string {
	$settings = new SettingsService( new SettingsStorage() );
	if ( $service_id > 0 ) {
		$res = Helpers::invoke( GetServiceController::class, array(), array( 'id' => $service_id ), 'GET' );
		if ( ! is_wp_error( $res ) ) {
			$data    = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : $res;
			$service = $data['service'] ?? $data;
			if ( is_array( $service ) && ! empty( $service['settings'] ) ) {
				$decoded = is_string( $service['settings'] ) ? json_decode( $service['settings'], true ) : $service['settings'];
				if ( is_array( $decoded ) && ! empty( $decoded['general']['defaultAppointmentStatus'] ) ) {
					$s = sanitize_key( (string) $decoded['general']['defaultAppointmentStatus'] );
					if ( in_array( $s, array( 'approved', 'pending', 'canceled', 'rejected', 'no-show', 'waiting' ), true ) ) {
						return $s;
					}
				}
			}
		}
	}
	$s = sanitize_key( (string) $settings->getSetting( 'general', 'defaultAppointmentStatus' ) );
	return in_array( $s, array( 'approved', 'pending', 'canceled', 'rejected', 'no-show', 'waiting' ), true ) ? $s : 'approved';
}

function notify_from_input( array $input ): int {
	if ( ! array_key_exists( 'notify', $input ) && ! array_key_exists( 'notifyParticipants', $input ) ) {
		return 0;
	}
	$raw = $input['notify'] ?? $input['notifyParticipants'];
	return ( true === $raw || 1 === $raw || '1' === $raw || 'true' === $raw ) ? 1 : 0;
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function meta_mutate( array $input = array() ) {
	$action = strtolower( (string) ( $input['action'] ?? '' ) );
	$def    = Entity_Map::get( (string) ( $input['entity'] ?? '' ) );
	if ( is_wp_error( $def ) ) {
		return $def;
	}
	if ( empty( $def['mutate'] ) ) {
		return new \WP_Error(
			'use_book',
			__( 'This entity is not mutated via amelia/mutate. Use amelia/book for appointments/events.', 'harudigi-booking-abilities-for-amelia' )
		);
	}

	if ( 'delete' === $action ) {
		$ok = Helpers::require_confirm( $input );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$id = Helpers::parse_id( $input['id'] ?? 0, 'id' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return Helpers::invoke( $def['delete'][0], array(), array( 'id' => $id ) );
	}

	if ( 'status' === $action ) {
		if ( empty( $def['status'] ) ) {
			return new \WP_Error( 'unsupported', __( 'Status toggle not supported for this entity.', 'harudigi-booking-abilities-for-amelia' ) );
		}
		$id = Helpers::parse_id( $input['id'] ?? 0, 'id' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$status = sanitize_key( (string) ( $input['status'] ?? ( $input['fields']['status'] ?? '' ) ) );
		$check  = Helpers::assert_entity_status( $status );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		return Helpers::invoke( $def['status'][0], array( 'status' => $status ), array( 'id' => $id ) );
	}

	if ( 'create' === $action ) {
		$fields = Helpers::body_from_input( $input, array( 'action', 'entity', 'id' ) );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		if ( 'customer' === $def['key'] ) {
			$fields = prepare_customer_body( $fields );
			if ( is_wp_error( $fields ) ) {
				return $fields;
			}
		}
		if ( 'extra' === $def['key'] ) {
			$fields = prepare_extra_body( $fields );
		}
		if ( 'category' === $def['key'] ) {
			$fields = prepare_category_body( $fields );
		}
		if ( 'location' === $def['key'] ) {
			$fields = prepare_location_body( $fields );
		}
		if ( 'coupon' === $def['key'] ) {
			$fields = prepare_coupon_body( $fields );
		}
		if ( 'resource' === $def['key'] ) {
			$fields = prepare_resource_body( $fields );
		}
		if ( 'custom_field' === $def['key'] ) {
			$fields = prepare_custom_field_body( $fields, true );
			$fields = array( 'customField' => $fields );
		}
		if ( 'service' === $def['key'] ) {
			$fields = encode_service_json_fields( $fields );
			if ( isset( $fields['customPricing'] ) ) {
				$enc = normalize_custom_pricing_input( $fields['customPricing'] );
				if ( null !== $enc ) {
					$fields['customPricing'] = $enc;
				}
			}
		}
		return Helpers::invoke( $def['create'][0], $fields );
	}

	if ( 'update' === $action ) {
		$id = Helpers::parse_id( $input['id'] ?? 0, 'id' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$patch = Helpers::body_from_input( $input, array( 'action', 'entity', 'id' ) );
		if ( is_wp_error( $patch ) ) {
			// Allow merge-only updates for entities that load existing rows.
			if ( in_array( $def['key'], array( 'extra', 'custom_field', 'service', 'customer' ), true ) ) {
				$patch = array();
			} else {
				return $patch;
			}
		}
		if ( 'service' === $def['key'] && ! empty( $def['get'] ) ) {
			$existing_res = Helpers::invoke( $def['get'][0], array(), array( 'id' => $id ), 'GET' );
			if ( is_wp_error( $existing_res ) ) {
				return $existing_res;
			}
			$data     = isset( $existing_res['data'] ) && is_array( $existing_res['data'] ) ? $existing_res['data'] : $existing_res;
			$existing = $data['service'] ?? $data;
			if ( ! is_array( $existing ) ) {
				return new \WP_Error( 'amelia_not_found', __( 'Service not found.', 'harudigi-booking-abilities-for-amelia' ) );
			}
			$body = merge_service_update_fields( $existing, $patch );
			return Helpers::invoke( UpdateServiceController::class, $body, array( 'id' => $id ) );
		}
		if ( 'customer' === $def['key'] ) {
			$patch = prepare_customer_body( $patch, false );
			if ( is_wp_error( $patch ) ) {
				return $patch;
			}
		}
		if ( 'extra' === $def['key'] ) {
			$existing_res = Helpers::invoke(
				\AmeliaBooking\Application\Controller\Bookable\Extra\GetExtraController::class,
				array(),
				array( 'id' => $id ),
				'GET'
			);
			if ( is_wp_error( $existing_res ) ) {
				return $existing_res;
			}
			$data     = isset( $existing_res['data'] ) && is_array( $existing_res['data'] ) ? $existing_res['data'] : $existing_res;
			$existing = $data['extra'] ?? $data;
			if ( ! is_array( $existing ) ) {
				return new \WP_Error( 'amelia_not_found', __( 'Extra not found.', 'harudigi-booking-abilities-for-amelia' ) );
			}
			unset( $existing['type'] );
			$patch = prepare_extra_body( array_merge( $existing, $patch ) );
			// UpdateExtra checkMandatoryFields rejects null — use '' / 0.
			if ( ! isset( $patch['description'] ) || null === $patch['description'] ) {
				$patch['description'] = '';
			}
			if ( ! isset( $patch['duration'] ) || null === $patch['duration'] || '' === $patch['duration'] ) {
				$patch['duration'] = 0;
			}
			if ( ! isset( $patch['maxQuantity'] ) ) {
				$patch['maxQuantity'] = 1;
			}
		}
		if ( 'custom_field' === $def['key'] ) {
			$merged = merge_custom_field_update( $id, $patch );
			if ( is_wp_error( $merged ) ) {
				return $merged;
			}
			$patch = $merged;
		}
		return Helpers::invoke( $def['update'][0], $patch, array( 'id' => $id ) );
	}

	return new \WP_Error( 'invalid_action', __( 'mutate action must be create|update|delete|status.', 'harudigi-booking-abilities-for-amelia' ) );
}

/**
 * @param array<string,mixed> $fields
 * @return array<string,mixed>|\WP_Error
 */
function prepare_customer_body( array $fields, bool $creating = true ) {
	if ( $creating ) {
		$first = sanitize_text_field( (string) ( $fields['firstName'] ?? '' ) );
		if ( '' === $first ) {
			return new \WP_Error( 'invalid_firstName', __( 'firstName is required.', 'harudigi-booking-abilities-for-amelia' ) );
		}
		$fields['firstName'] = $first;
		$fields['lastName']  = sanitize_text_field( (string) ( $fields['lastName'] ?? '' ) );
		$fields['type']      = 'customer';
		$fields['status']    = sanitize_key( (string) ( $fields['status'] ?? 'visible' ) );
	}
	if ( array_key_exists( 'email', $fields ) ) {
		$email_raw = trim( (string) $fields['email'] );
		if ( '' === $email_raw ) {
			$fields['email'] = '';
		} else {
			$email = sanitize_email( $email_raw );
			if ( '' === $email || ! is_email( $email ) ) {
				return new \WP_Error( 'invalid_email', __( 'email is invalid.', 'harudigi-booking-abilities-for-amelia' ) );
			}
			$fields['email'] = $email;
		}
	}
	$clean = Helpers::sanitize_write_body( $fields );
	// Amelia requires externalId; MCP never links WP users — force unlinked sentinel.
	$clean['externalId'] = -1;
	return $clean;
}

/**
 * @param array<string,mixed> $fields
 * @return array<string,mixed>
 */
function prepare_extra_body( array $fields ): array {
	$fields = Helpers::sanitize_write_body( $fields );
	if ( ! isset( $fields['position'] ) || ! is_numeric( $fields['position'] ) || (int) $fields['position'] <= 0 ) {
		$fields['position'] = 1;
	} else {
		$fields['position'] = (int) $fields['position'];
	}
	if ( array_key_exists( 'duration', $fields ) && ( '' === $fields['duration'] || null === $fields['duration'] ) ) {
		unset( $fields['duration'] );
	} elseif ( isset( $fields['duration'] ) ) {
		$fields['duration'] = (int) $fields['duration'];
	}
	if ( isset( $fields['price'] ) ) {
		$fields['price'] = (float) $fields['price'];
	}
	if ( isset( $fields['maxQuantity'] ) ) {
		$fields['maxQuantity'] = max( 1, (int) $fields['maxQuantity'] );
	}
	if ( isset( $fields['serviceId'] ) ) {
		$fields['serviceId'] = (int) $fields['serviceId'];
	}
	if ( ! array_key_exists( 'aggregatedPrice', $fields ) ) {
		$fields['aggregatedPrice'] = true;
	}
	return $fields;
}

/** @param array<string,mixed> $fields @return array<string,mixed> */
function prepare_category_body( array $fields ): array {
	$fields = Helpers::sanitize_write_body( $fields );
	if ( empty( $fields['status'] ) ) {
		$fields['status'] = 'visible';
	}
	if ( ! isset( $fields['position'] ) || ! is_numeric( $fields['position'] ) || (int) $fields['position'] <= 0 ) {
		$fields['position'] = 1;
	} else {
		$fields['position'] = (int) $fields['position'];
	}
	if ( empty( $fields['color'] ) ) {
		$fields['color'] = '#1788FB';
	}
	return $fields;
}

/** @param array<string,mixed> $fields @return array<string,mixed> */
function prepare_location_body( array $fields ): array {
	$fields = Helpers::sanitize_write_body( $fields );
	if ( empty( $fields['status'] ) ) {
		$fields['status'] = 'visible';
	}
	// Amelia rejects empty/0 lat-long — default Kyoto if omitted.
	if ( ! isset( $fields['latitude'] ) || '' === $fields['latitude'] || null === $fields['latitude'] || 0 == $fields['latitude'] ) {
		$fields['latitude'] = 35.0116;
	}
	if ( ! isset( $fields['longitude'] ) || '' === $fields['longitude'] || null === $fields['longitude'] || 0 == $fields['longitude'] ) {
		$fields['longitude'] = 135.7681;
	}
	if ( ! isset( $fields['address'] ) ) {
		$fields['address'] = '';
	}
	return $fields;
}

/**
 * @param array<string,mixed> $fields
 * @return array<string,mixed>
 */
function prepare_coupon_body( array $fields ): array {
	$fields = Helpers::sanitize_write_body( $fields );
	foreach ( array( 'services', 'events', 'packages' ) as $k ) {
		if ( empty( $fields[ $k ] ) || ! is_array( $fields[ $k ] ) ) {
			$fields[ $k ] = array();
			continue;
		}
		$norm = array();
		foreach ( $fields[ $k ] as $row ) {
			$id = is_array( $row ) ? (int) ( $row['id'] ?? 0 ) : (int) $row;
			if ( $id > 0 ) {
				$norm[] = array( 'id' => $id );
			}
		}
		$fields[ $k ] = $norm;
	}
	if ( ! isset( $fields['discount'] ) ) {
		$fields['discount'] = 0;
	}
	if ( ! isset( $fields['deduction'] ) ) {
		$fields['deduction'] = 0;
	}
	if ( empty( $fields['status'] ) ) {
		$fields['status'] = 'visible';
	}
	return $fields;
}

/** @param array<string,mixed> $fields @return array<string,mixed> */
function prepare_resource_body( array $fields ): array {
	$fields = Helpers::sanitize_write_body( $fields );
	if ( ! isset( $fields['quantity'] ) ) {
		$fields['quantity'] = 1;
	}
	if ( empty( $fields['status'] ) ) {
		$fields['status'] = 'shared';
	}
	if ( ! isset( $fields['entities'] ) || ! is_array( $fields['entities'] ) ) {
		$fields['entities'] = array();
	}
	return $fields;
}

/**
 * @param array<string,mixed> $fields
 * @return array<string,mixed>
 */
function prepare_custom_field_body( array $fields, bool $creating = true ): array {
	$fields = Helpers::sanitize_write_body( $fields );
	if ( $creating ) {
		if ( empty( $fields['type'] ) ) {
			$fields['type'] = 'text';
		}
		if ( ! isset( $fields['position'] ) || ! is_numeric( $fields['position'] ) || (int) $fields['position'] <= 0 ) {
			$fields['position'] = 1;
		} else {
			$fields['position'] = (int) $fields['position'];
		}
		if ( empty( $fields['width'] ) || ! is_numeric( $fields['width'] ) ) {
			$fields['width'] = 100;
		} else {
			$fields['width'] = (int) $fields['width'];
		}
		if ( ! array_key_exists( 'required', $fields ) ) {
			$fields['required'] = false;
		}
		if ( ! array_key_exists( 'options', $fields ) ) {
			$fields['options'] = array();
		}
		if ( ! array_key_exists( 'services', $fields ) || ! is_array( $fields['services'] ) ) {
			$fields['services'] = array();
		}
		if ( ! array_key_exists( 'events', $fields ) || ! is_array( $fields['events'] ) ) {
			$fields['events'] = array();
		}
		if ( ! array_key_exists( 'allServices', $fields ) && empty( $fields['services'] ) ) {
			$fields['allServices'] = true;
		}
		if ( ! array_key_exists( 'allEvents', $fields ) ) {
			$fields['allEvents'] = false;
		}
		if ( empty( $fields['saveType'] ) ) {
			$fields['saveType'] = 'bookings';
		}
	}
	// Normalize services/events to [{id:N}]
	foreach ( array( 'services', 'events' ) as $k ) {
		if ( empty( $fields[ $k ] ) || ! is_array( $fields[ $k ] ) ) {
			continue;
		}
		$norm = array();
		foreach ( $fields[ $k ] as $row ) {
			$id = is_array( $row ) ? (int) ( $row['id'] ?? 0 ) : (int) $row;
			if ( $id > 0 ) {
				$norm[] = array( 'id' => $id );
			}
		}
		$fields[ $k ] = $norm;
	}
	return $fields;
}

/**
 * Merge patch onto existing custom field (Amelia requires full mandatory set).
 *
 * @param array<string,mixed> $patch
 * @return array<string,mixed>|\WP_Error
 */
function merge_custom_field_update( int $id, array $patch ) {
	$listed = Helpers::invoke(
		\AmeliaBooking\Application\Controller\CustomField\GetCustomFieldsController::class,
		array(),
		array(),
		'GET'
	);
	if ( is_wp_error( $listed ) ) {
		return $listed;
	}
	$data   = isset( $listed['data'] ) && is_array( $listed['data'] ) ? $listed['data'] : $listed;
	$fields = $data['customFields'] ?? $data;
	$found  = null;
	if ( is_array( $fields ) ) {
		foreach ( $fields as $f ) {
			if ( is_array( $f ) && (int) ( $f['id'] ?? 0 ) === $id ) {
				$found = $f;
				break;
			}
		}
	}
	if ( ! $found ) {
		return new \WP_Error( 'amelia_not_found', __( 'Custom field not found.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$services = $found['services'] ?? array();
	$events   = $found['events'] ?? array();
	$service_objs = array();
	if ( is_array( $services ) ) {
		foreach ( $services as $s ) {
			$sid = is_array( $s ) ? (int) ( $s['id'] ?? 0 ) : (int) $s;
			if ( $sid > 0 ) {
				$service_objs[] = array( 'id' => $sid );
			}
		}
	}
	$event_objs = array();
	if ( is_array( $events ) ) {
		foreach ( $events as $e ) {
			$eid = is_array( $e ) ? (int) ( $e['id'] ?? 0 ) : (int) $e;
			if ( $eid > 0 ) {
				$event_objs[] = array( 'id' => $eid );
			}
		}
	}

	$width = $found['width'] ?? 100;
	if ( ! is_numeric( $width ) || (int) $width <= 0 ) {
		$width = 100;
	}

	$options = array();
	if ( ! empty( $found['options'] ) && is_array( $found['options'] ) ) {
		foreach ( $found['options'] as $opt ) {
			if ( ! is_array( $opt ) ) {
				continue;
			}
			$row = array(
				'label'    => (string) ( $opt['label'] ?? '' ),
				'position' => (int) ( $opt['position'] ?? 1 ),
			);
			if ( ! empty( $opt['id'] ) ) {
				$row['id'] = (int) $opt['id'];
			}
			if ( ! empty( $opt['customFieldId'] ) ) {
				$row['customFieldId'] = (int) $opt['customFieldId'];
			}
			$options[] = $row;
		}
	}

	$base = array(
		'id'          => $id,
		'label'       => (string) ( $found['label'] ?? '' ),
		'type'        => (string) ( $found['type'] ?? 'text' ),
		'options'     => $options,
		'position'    => max( 1, (int) ( $found['position'] ?? 1 ) ),
		'width'       => (int) $width,
		'required'    => ! empty( $found['required'] ),
		'services'    => $service_objs,
		'events'      => $event_objs,
		'allServices' => ! empty( $found['allServices'] ),
		'allEvents'   => ! empty( $found['allEvents'] ),
		'saveType'    => (string) ( $found['saveType'] ?? 'bookings' ),
	);

	$merged = array_merge( $base, $patch );
	$merged['id'] = $id;
	if ( null === ( $merged['label'] ?? null ) || '' === (string) $merged['label'] ) {
		$merged['label'] = $base['label'];
	}
	if ( empty( $merged['width'] ) || ! is_numeric( $merged['width'] ) ) {
		$merged['width'] = 100;
	} else {
		$merged['width'] = (int) $merged['width'];
	}
	$merged['position'] = max( 1, (int) ( $merged['position'] ?? 1 ) );
	if ( ! isset( $merged['options'] ) || ! is_array( $merged['options'] ) ) {
		$merged['options'] = array();
	}
	// Ensure services/events are [{id:N}, ...] not bare ints.
	if ( ! empty( $merged['services'] ) && is_array( $merged['services'] ) ) {
		$norm = array();
		foreach ( $merged['services'] as $s ) {
			$sid = is_array( $s ) ? (int) ( $s['id'] ?? 0 ) : (int) $s;
			if ( $sid > 0 ) {
				$norm[] = array( 'id' => $sid );
			}
		}
		$merged['services'] = $norm;
	}
	if ( ! empty( $merged['events'] ) && is_array( $merged['events'] ) ) {
		$norm = array();
		foreach ( $merged['events'] as $e ) {
			$eid = is_array( $e ) ? (int) ( $e['id'] ?? 0 ) : (int) $e;
			if ( $eid > 0 ) {
				$norm[] = array( 'id' => $eid );
			}
		}
		$merged['events'] = $norm;
	}
	return $merged;
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
