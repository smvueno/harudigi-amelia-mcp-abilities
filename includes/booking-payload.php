<?php
/**
 * Normalize booking payloads: duration tiers, extras, custom fields.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use AmeliaBooking\Application\Controller\Bookable\Service\GetServiceController;
use AmeliaBooking\Application\Controller\CustomField\GetCustomFieldsController;

/**
 * @param mixed $raw Extras list from MCP.
 * @param string $id_key 'extraId' for appointments, 'id' for availability slots.
 * @return array<int, array<string, mixed>>
 */
function normalize_booking_extras( $raw, string $id_key = 'extraId' ): array {
	if ( ! is_array( $raw ) || ! $raw ) {
		return array();
	}
	$out = array();
	foreach ( $raw as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$booking_extra_id = 0;
		if ( ! empty( $item['_bookingExtraId'] ) ) {
			$booking_extra_id = (int) $item['_bookingExtraId'];
		} elseif ( isset( $item['extraId'], $item['id'] ) && (int) $item['extraId'] > 0 && (int) $item['id'] !== (int) $item['extraId'] ) {
			$booking_extra_id = (int) $item['id'];
		}

		$catalog_id = isset( $item['extraId'] ) ? (int) $item['extraId'] : 0;
		if ( $catalog_id <= 0 ) {
			$catalog_id = (int) ( $item['id'] ?? 0 );
		}
		if ( $catalog_id <= 0 ) {
			continue;
		}

		$row = array(
			$id_key    => $catalog_id,
			'quantity' => max( 1, (int) ( $item['quantity'] ?? 1 ) ),
		);
		if ( $booking_extra_id > 0 ) {
			$row['id'] = $booking_extra_id;
		}
		if ( isset( $item['price'] ) && is_numeric( $item['price'] ) ) {
			$row['price'] = (float) $item['price'];
		}
		if ( array_key_exists( 'aggregatedPrice', $item ) ) {
			$row['aggregatedPrice'] = (bool) $item['aggregatedPrice'];
		}
		$out[] = $row;
	}
	return $out;
}

/**
 * Accept object map or list; pass through Amelia-shaped custom field values.
 *
 * @param mixed $raw Custom fields from MCP.
 * @return array<string, mixed>|string
 */
function normalize_booking_custom_fields( $raw ) {
	if ( null === $raw || '' === $raw ) {
		return array();
	}
	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}
	return is_array( $raw ) ? $raw : array();
}

/**
 * Duration in seconds. Accepts seconds, or friendly strings like "1h", "90m", "2h".
 *
 * @param mixed $raw Duration input.
 * @return int 0 if empty/invalid.
 */
function parse_duration_seconds( $raw ): int {
	if ( null === $raw || '' === $raw ) {
		return 0;
	}
	if ( is_numeric( $raw ) ) {
		return max( 0, (int) $raw );
	}
	if ( ! is_string( $raw ) ) {
		return 0;
	}
	$s = strtolower( trim( $raw ) );
	if ( preg_match( '/^(\d+(?:\.\d+)?)\s*h(?:ours?)?$/i', $s, $m ) ) {
		return (int) round( (float) $m[1] * 3600 );
	}
	if ( preg_match( '/^(\d+(?:\.\d+)?)\s*m(?:in(?:utes?)?)?$/i', $s, $m ) ) {
		return (int) round( (float) $m[1] * 60 );
	}
	if ( preg_match( '/^(\d+)\s*s(?:ec(?:onds?)?)?$/i', $s, $m ) ) {
		return (int) $m[1];
	}
	return 0;
}

/**
 * Decode service customPricing JSON (string or array).
 *
 * @param mixed $raw Service customPricing field.
 * @return array<string, mixed>
 */
function decode_custom_pricing( $raw ): array {
	if ( is_array( $raw ) ) {
		return $raw;
	}
	if ( ! is_string( $raw ) || '' === $raw ) {
		return array();
	}
	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Human-readable duration tier list from customPricing.durations.
 *
 * @param array<string, mixed> $custom_pricing Decoded customPricing.
 * @param int                  $fallback_duration Service default duration seconds.
 * @param float                $fallback_price    Service base price.
 * @return array<int, array<string, mixed>>
 */
function duration_tiers_from_pricing( array $custom_pricing, int $fallback_duration, float $fallback_price ): array {
	$enabled = $custom_pricing['enabled'] ?? false;
	$is_duration = ( true === $enabled || 'duration' === $enabled );
	$tiers       = array();

	if ( $is_duration && ! empty( $custom_pricing['durations'] ) && is_array( $custom_pricing['durations'] ) ) {
		foreach ( $custom_pricing['durations'] as $seconds => $row ) {
			$sec = (int) $seconds;
			if ( $sec <= 0 ) {
				continue;
			}
			$price = is_array( $row ) && isset( $row['price'] ) ? (float) $row['price'] : (float) $row;
			$tiers[] = array(
				'duration'         => $sec,
				'duration_label'   => format_duration_label( $sec ),
				'price'            => $price,
			);
		}
		usort(
			$tiers,
			static function ( $a, $b ) {
				return $a['duration'] <=> $b['duration'];
			}
		);
		return $tiers;
	}

	if ( $fallback_duration > 0 ) {
		$tiers[] = array(
			'duration'       => $fallback_duration,
			'duration_label' => format_duration_label( $fallback_duration ),
			'price'          => $fallback_price,
			'is_default'     => true,
		);
	}
	return $tiers;
}

function format_duration_label( int $seconds ): string {
	if ( $seconds % 3600 === 0 ) {
		$h = $seconds / 3600;
		return $h . 'h';
	}
	if ( $seconds % 60 === 0 ) {
		return ( $seconds / 60 ) . 'm';
	}
	return $seconds . 's';
}

/**
 * Compact extras list for MCP agents.
 *
 * @param mixed $extras Service extras collection/array.
 * @return array<int, array<string, mixed>>
 */
function summarize_service_extras( $extras ): array {
	if ( ! is_array( $extras ) ) {
		return array();
	}
	$list = array();
	foreach ( $extras as $extra ) {
		if ( ! is_array( $extra ) ) {
			continue;
		}
		$id = (int) ( $extra['id'] ?? 0 );
		if ( $id <= 0 ) {
			continue;
		}
		$list[] = array(
			'id'               => $id,
			'name'             => (string) ( $extra['name'] ?? '' ),
			'price'            => isset( $extra['price'] ) ? (float) $extra['price'] : null,
			'duration'         => isset( $extra['duration'] ) ? (int) $extra['duration'] : null,
			'maxQuantity'      => isset( $extra['maxQuantity'] ) ? (int) $extra['maxQuantity'] : null,
			'aggregatedPrice'  => $extra['aggregatedPrice'] ?? null,
			'description'      => isset( $extra['description'] ) ? (string) $extra['description'] : '',
		);
	}
	return $list;
}

/**
 * Custom fields applicable to a service (and optionally events).
 *
 * @param int  $service_id Service ID (0 = all / skip service filter).
 * @param bool $for_events Include event-linked fields.
 * @return array<int, array<string, mixed>>|\WP_Error
 */
function list_custom_fields_for_entity( int $service_id = 0, bool $for_events = false ) {
	$result = Helpers::invoke( GetCustomFieldsController::class, array(), array(), 'GET' );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$data   = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : $result;
	$fields = $data['customFields'] ?? $data;
	if ( ! is_array( $fields ) ) {
		return array();
	}

	$out = array();
	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}
		$id = (int) ( $field['id'] ?? 0 );
		if ( $id <= 0 ) {
			continue;
		}

		$all_services = ! empty( $field['allServices'] );
		$all_events   = ! empty( $field['allEvents'] );
		$services     = isset( $field['services'] ) && is_array( $field['services'] ) ? $field['services'] : array();
		$events       = isset( $field['events'] ) && is_array( $field['events'] ) ? $field['events'] : array();

		$applies = false;
		if ( $for_events ) {
			$applies = $all_events;
			if ( ! $applies ) {
				foreach ( $events as $ev ) {
					$eid = is_array( $ev ) ? (int) ( $ev['id'] ?? 0 ) : (int) $ev;
					if ( $eid > 0 ) {
						$applies = true;
						break;
					}
				}
			}
		} elseif ( $service_id > 0 ) {
			if ( $all_services ) {
				$applies = true;
			} else {
				foreach ( $services as $svc ) {
					$sid = is_array( $svc ) ? (int) ( $svc['id'] ?? 0 ) : (int) $svc;
					if ( $sid === $service_id ) {
						$applies = true;
						break;
					}
				}
			}
		} else {
			$applies = true;
		}

		if ( ! $applies ) {
			continue;
		}

		$options = array();
		if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			foreach ( $field['options'] as $opt ) {
				if ( is_array( $opt ) ) {
					$options[] = array(
						'id'    => $opt['id'] ?? null,
						'label' => $opt['label'] ?? ( $opt['value'] ?? null ),
					);
				}
			}
		}

		$out[] = array(
			'id'       => $id,
			'label'    => (string) ( $field['label'] ?? '' ),
			'type'     => (string) ( $field['type'] ?? 'text' ),
			'required' => ! empty( $field['required'] ),
			'saveType' => (string) ( $field['saveType'] ?? 'bookings' ),
			'options'  => $options,
		);
	}

	return $out;
}

/**
 * Full booking options for a service: duration tiers, extras, custom fields.
 *
 * @return array<string, mixed>|\WP_Error
 */
function get_service_booking_options( int $service_id ) {
	if ( $service_id <= 0 ) {
		return new \WP_Error( 'invalid_service_id', __( 'Invalid service_id.', 'mcp-abilities-for-amelia' ) );
	}

	$result = Helpers::invoke( GetServiceController::class, array(), array( 'id' => $service_id ), 'GET' );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$data    = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : $result;
	$service = $data['service'] ?? $data;
	if ( ! is_array( $service ) ) {
		return new \WP_Error( 'amelia_not_found', __( 'Service not found.', 'mcp-abilities-for-amelia' ) );
	}

	$custom_pricing = decode_custom_pricing( $service['customPricing'] ?? null );
	$duration       = (int) ( $service['duration'] ?? 0 );
	$price          = isset( $service['price'] ) ? (float) $service['price'] : 0.0;
	$enabled        = $custom_pricing['enabled'] ?? false;
	$pricing_mode   = false === $enabled || null === $enabled || '' === $enabled
		? 'standard'
		: ( true === $enabled ? 'duration' : (string) $enabled );

	$fields = list_custom_fields_for_entity( $service_id, false );
	if ( is_wp_error( $fields ) ) {
		$fields = array();
	}

	$extras = summarize_service_extras( $service['extras'] ?? array() );
	if ( ! $extras ) {
		// Fallback: list all extras and filter (get-service sometimes omits extras after partial updates).
		$listed = Helpers::invoke(
			\AmeliaBooking\Application\Controller\Bookable\Extra\GetExtrasController::class,
			array(),
			array(),
			'GET'
		);
		if ( ! is_wp_error( $listed ) ) {
			$data   = isset( $listed['data'] ) && is_array( $listed['data'] ) ? $listed['data'] : $listed;
			$all    = $data['extras'] ?? $data;
			$linked = array();
			if ( is_array( $all ) ) {
				foreach ( $all as $ex ) {
					if ( is_array( $ex ) && (int) ( $ex['serviceId'] ?? 0 ) === $service_id ) {
						$linked[] = $ex;
					}
				}
			}
			$extras = summarize_service_extras( $linked );
		}
	}

	return array(
		'service_id'             => $service_id,
		'name'                   => (string) ( $service['name'] ?? '' ),
		'default_duration'       => $duration,
		'default_duration_label' => format_duration_label( $duration ),
		'default_price'          => $price,
		'pricing_mode'           => $pricing_mode,
		'duration_tiers'         => duration_tiers_from_pricing( $custom_pricing, $duration, $price ),
		'custom_pricing_raw'     => $custom_pricing ?: null,
		'extras'                 => $extras,
		'minSelectedExtras'      => $service['minSelectedExtras'] ?? null,
		'mandatoryExtra'         => $service['mandatoryExtra'] ?? null,
		'custom_fields'          => $fields,
		'how_to_book'            => array(
			'duration'      => 'Pass duration in seconds on create-appointment (e.g. 3600=1h, 7200=2h). Use duration_tiers. Also pass the same value as serviceDuration to check-availability.',
			'extras'        => 'Pass extras: [{extraId, quantity}] on create-appointment. For check-availability use [{id, quantity}].',
			'custom_fields' => 'Pass customFields as an object keyed by field id: {"12":{"label":"...","type":"text","value":"..."}}.',
			'update'        => 'On update-appointment, put duration/extras/customFields on fields.bookings[].',
		),
	);
}

/**
 * Normalize one booking row for create/update (duration, extras, customFields).
 *
 * @param array<string, mixed> $booking Booking row.
 * @return array<string, mixed>
 */
function normalize_booking_row( array $booking ): array {
	if ( array_key_exists( 'duration', $booking ) ) {
		$sec = parse_duration_seconds( $booking['duration'] );
		if ( $sec > 0 ) {
			$booking['duration'] = $sec;
		} else {
			unset( $booking['duration'] );
		}
	}
	if ( array_key_exists( 'extras', $booking ) ) {
		$extras = normalize_booking_extras( $booking['extras'], 'extraId' );
		if ( $extras ) {
			$booking['extras'] = $extras;
		} else {
			$booking['extras'] = array();
		}
	}
	if ( array_key_exists( 'customFields', $booking ) ) {
		$cf = normalize_booking_custom_fields( $booking['customFields'] );
		if ( $cf ) {
			$booking['customFields'] = $cf;
		} else {
			unset( $booking['customFields'] );
		}
	}
	return $booking;
}

/**
 * When updating, Amelia updates extras by booking-extra row id; missing id → INSERT (duplicate key).
 * Copy existing row ids onto incoming extras matched by extraId.
 *
 * @param array<string, mixed> $incoming Incoming booking row.
 * @param array<string, mixed> $existing Existing booking row from get-appointment.
 * @return array<string, mixed>
 */
function preserve_booking_extra_row_ids( array $incoming, array $existing ): array {
	if ( empty( $incoming['extras'] ) || ! is_array( $incoming['extras'] ) ) {
		return $incoming;
	}
	$by_extra = array();
	if ( ! empty( $existing['extras'] ) && is_array( $existing['extras'] ) ) {
		foreach ( $existing['extras'] as $ex ) {
			if ( ! is_array( $ex ) ) {
				continue;
			}
			$eid = (int) ( $ex['extraId'] ?? 0 );
			$rid = (int) ( $ex['id'] ?? 0 );
			if ( $eid > 0 && $rid > 0 ) {
				$by_extra[ $eid ] = $rid;
			}
		}
	}
	foreach ( $incoming['extras'] as $i => $ex ) {
		if ( ! is_array( $ex ) ) {
			continue;
		}
		$eid = (int) ( $ex['extraId'] ?? 0 );
		if ( $eid <= 0 ) {
			$eid = (int) ( $ex['id'] ?? 0 );
		}
		if ( $eid > 0 && isset( $by_extra[ $eid ] ) ) {
			$incoming['extras'][ $i ]['extraId']         = $eid;
			$incoming['extras'][ $i ]['_bookingExtraId'] = $by_extra[ $eid ];
			$incoming['extras'][ $i ]['id']              = $by_extra[ $eid ];
		}
	}
	return $incoming;
}

/**
 * Strip nested read-only blobs that break UpdateAppointment.
 *
 * @param array<string, mixed> $fields Appointment body.
 * @return array<string, mixed>
 */
function sanitize_appointment_update_fields( array $fields ): array {
	unset(
		$fields['provider'],
		$fields['service'],
		$fields['location'],
		$fields['type'],
		$fields['resources'],
		$fields['zoomMeeting'],
		$fields['lessonSpace'],
		$fields['googleCalendarEventId'],
		$fields['googleMeetUrl'],
		$fields['outlookCalendarEventId'],
		$fields['microsoftTeamsUrl'],
		$fields['appleCalendarEventId'],
		$fields['isRescheduled'],
		$fields['isChangedStatus'],
		$fields['isFull'],
		$fields['initialAppointmentDateTime'],
		$fields['createPaymentLinks'],
		$fields['parentId']
	);
	if ( ! empty( $fields['bookings'] ) && is_array( $fields['bookings'] ) ) {
		foreach ( $fields['bookings'] as $i => $booking ) {
			if ( ! is_array( $booking ) ) {
				continue;
			}
			unset(
				$fields['bookings'][ $i ]['customer'],
				$fields['bookings'][ $i ]['token'],
				$fields['bookings'][ $i ]['packageCustomerService'],
				$fields['bookings'][ $i ]['ticketsData'],
				$fields['bookings'][ $i ]['qrCodes'],
				$fields['bookings'][ $i ]['icsFiles'],
				$fields['bookings'][ $i ]['isChangedStatus'],
				$fields['bookings'][ $i ]['isLastBooking'],
				$fields['bookings'][ $i ]['isNew'],
				$fields['bookings'][ $i ]['isUpdated']
			);
		}
	}
	return $fields;
}

/**
 * Build update body: merge patch onto existing appointment, preserve extra row ids, normalize.
 *
 * @param array<string, mixed> $existing From get-appointment.
 * @param array<string, mixed> $patch    MCP fields.
 * @return array<string, mixed>
 */
function build_appointment_update_fields( array $existing, array $patch ): array {
	$existing_bookings = array();
	if ( ! empty( $existing['bookings'] ) && is_array( $existing['bookings'] ) ) {
		foreach ( $existing['bookings'] as $b ) {
			if ( is_array( $b ) && ! empty( $b['id'] ) ) {
				$existing_bookings[ (int) $b['id'] ] = $b;
			}
		}
	}

	$fields = array_merge( $existing, $patch );
	if ( ! empty( $patch['bookings'] ) && is_array( $patch['bookings'] ) ) {
		$merged_bookings = array();
		foreach ( $patch['bookings'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$bid = (int) ( $row['id'] ?? 0 );
			$base = ( $bid && isset( $existing_bookings[ $bid ] ) ) ? $existing_bookings[ $bid ] : array();
			$merged = array_merge( $base, $row );
			$merged = preserve_booking_extra_row_ids( $merged, $base );
			$merged_bookings[] = normalize_booking_row( $merged );
		}
		$fields['bookings'] = $merged_bookings;
	} else {
		$fields = normalize_appointment_fields( $fields );
	}

	$fields = sanitize_appointment_update_fields( $fields );
	return $fields;
}

/**
 * Normalize appointment update body so bookings[] carry valid duration/extras/customFields.
 *
 * @param array<string, mixed> $fields Amelia appointment body.
 * @return array<string, mixed>
 */
function normalize_appointment_fields( array $fields ): array {
	if ( ! empty( $fields['bookings'] ) && is_array( $fields['bookings'] ) ) {
		$normalized = array();
		foreach ( $fields['bookings'] as $row ) {
			$normalized[] = is_array( $row ) ? normalize_booking_row( $row ) : $row;
		}
		$fields['bookings'] = $normalized;
	}
	return $fields;
}

/**
 * Amelia Json VO expects a string. Arrays break SQL (customPricing = ,).
 *
 * @param array<string, mixed> $fields Service write body.
 * @return array<string, mixed>
 */
function encode_service_json_fields( array $fields ): array {
	foreach ( array( 'customPricing', 'limitPerCustomer', 'settings', 'translations' ) as $key ) {
		if ( ! array_key_exists( $key, $fields ) ) {
			continue;
		}
		if ( is_array( $fields[ $key ] ) ) {
			$fields[ $key ] = wp_json_encode( $fields[ $key ] );
		} elseif ( null === $fields[ $key ] ) {
			// Leave null — Amelia treats empty as unset.
		} elseif ( ! is_string( $fields[ $key ] ) ) {
			$fields[ $key ] = wp_json_encode( $fields[ $key ] );
		}
	}
	return $fields;
}

/**
 * Friendly duration-pricing payload → Amelia customPricing JSON string.
 *
 * Accepts either full Amelia shape `{enabled:"duration", durations:{...}}`
 * or MCP shorthand `{enabled:true|"duration", durations:[{duration:3600,price:70},...]}`.
 *
 * @param mixed $raw customPricing input.
 * @return string|null Encoded JSON or null if empty.
 */
function normalize_custom_pricing_input( $raw ) {
	if ( null === $raw || '' === $raw ) {
		return null;
	}
	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? wp_json_encode( $decoded ) : $raw;
	}
	if ( ! is_array( $raw ) ) {
		return null;
	}
	$enabled = $raw['enabled'] ?? false;
	if ( true === $enabled ) {
		$enabled = 'duration';
	}
	$durations_in = $raw['durations'] ?? array();
	$durations    = array();
	if ( is_array( $durations_in ) ) {
		$is_list = array_keys( $durations_in ) === range( 0, count( $durations_in ) - 1 );
		if ( $is_list ) {
			foreach ( $durations_in as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$sec = parse_duration_seconds( $row['duration'] ?? ( $row['seconds'] ?? 0 ) );
				if ( $sec <= 0 ) {
					continue;
				}
				$durations[ (string) $sec ] = array(
					'price' => isset( $row['price'] ) ? (float) $row['price'] : 0.0,
				);
			}
		} else {
			foreach ( $durations_in as $sec_key => $row ) {
				$sec = parse_duration_seconds( $sec_key );
				if ( $sec <= 0 && is_numeric( $sec_key ) ) {
					$sec = (int) $sec_key;
				}
				if ( $sec <= 0 ) {
					continue;
				}
				$price = is_array( $row ) && isset( $row['price'] ) ? (float) $row['price'] : (float) $row;
				$durations[ (string) $sec ] = array( 'price' => $price );
			}
		}
	}
	return wp_json_encode(
		array(
			'enabled'   => $enabled,
			'durations' => $durations,
		)
	);
}

/**
 * Merge partial MCP service update onto existing service (Amelia requires full mandatory set).
 *
 * @param array<string, mixed> $existing From get-service.
 * @param array<string, mixed> $patch    MCP fields.
 * @return array<string, mixed>
 */
function merge_service_update_fields( array $existing, array $patch ): array {
	$base = $existing;
	// Nested display-only blobs — not write payloads.
	unset( $base['category'], $base['coupons'], $base['gallery'], $base['priority'] );

	$providers = $patch['providers'] ?? ( $base['providers'] ?? array() );
	if ( is_array( $providers ) ) {
		$providers = array_values(
			array_filter(
				array_map(
					static function ( $p ) {
						if ( is_array( $p ) ) {
							return (int) ( $p['id'] ?? 0 );
						}
						return (int) $p;
					},
					$providers
				)
			)
		);
	} else {
		$providers = array();
	}

	// Preserve extras unless the patch explicitly replaces them.
	// Amelia manageExtrasForServiceUpdate DELETES any extra not present in the payload.
	$preserved_extras = array();
	if ( ! empty( $base['extras'] ) && is_array( $base['extras'] ) ) {
		$preserved_extras = array_values( $base['extras'] );
	}

	$merged = array_merge( $base, $patch );
	$merged['providers']     = $providers;
	$merged['applyGlobally'] = array_key_exists( 'applyGlobally', $patch )
		? (bool) $patch['applyGlobally']
		: false;

	if ( array_key_exists( 'extras', $patch ) ) {
		$merged['extras'] = is_array( $patch['extras'] ) ? array_values( $patch['extras'] ) : array();
	} else {
		$merged['extras'] = $preserved_extras;
	}

	if ( array_key_exists( 'customPricing', $patch ) ) {
		$encoded = normalize_custom_pricing_input( $patch['customPricing'] );
		if ( null === $encoded ) {
			$merged['customPricing'] = null;
		} else {
			$merged['customPricing'] = $encoded;
		}
	} else {
		$merged = encode_service_json_fields( $merged );
	}

	foreach ( array( 'limitPerCustomer', 'settings', 'translations' ) as $json_key ) {
		if ( array_key_exists( $json_key, $patch ) && is_array( $patch[ $json_key ] ) ) {
			$merged[ $json_key ] = wp_json_encode( $patch[ $json_key ] );
		}
	}

	$merged['name']        = (string) ( $merged['name'] ?? '' );
	$merged['categoryId']  = (int) ( $merged['categoryId'] ?? 0 );
	$merged['duration']    = (int) ( $merged['duration'] ?? 3600 );
	$merged['price']       = isset( $merged['price'] ) ? (float) $merged['price'] : 0.0;
	$merged['minCapacity'] = isset( $merged['minCapacity'] ) ? (int) $merged['minCapacity'] : 1;
	$merged['maxCapacity'] = isset( $merged['maxCapacity'] ) ? (int) $merged['maxCapacity'] : 1;
	$merged['id']          = (int) ( $merged['id'] ?? 0 );

	return $merged;
}
