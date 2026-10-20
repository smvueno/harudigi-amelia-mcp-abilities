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
		if ( ! empty( $item['customerBookingId'] ) ) {
			$row['customerBookingId'] = (int) $item['customerBookingId'];
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
 * Decode a booking customFields blob into a [{id,label,value}] list.
 * Handles the JSON string Amelia stores and plain arrays.
 *
 * @param mixed $raw Booking customFields (JSON string or array).
 * @return array<int, array{id:int,label:string,value:mixed}>
 */
function decode_booking_custom_fields( $raw ): array {
	$cf = normalize_booking_custom_fields( $raw );
	if ( ! is_array( $cf ) ) {
		return array();
	}
	$out = array();
	foreach ( $cf as $key => $value ) {
		if ( is_array( $value ) && isset( $value['id'] ) ) {
			$id = (int) $value['id'];
		} elseif ( is_numeric( $key ) ) {
			$id = (int) $key;
		} else {
			continue;
		}
		if ( $id <= 0 ) {
			continue;
		}
		$out[] = array(
			'id'    => $id,
			'label' => is_array( $value ) ? (string) ( $value['label'] ?? '' ) : '',
			'value' => is_array( $value ) ? ( $value['value'] ?? null ) : $value,
		);
	}
	return $out;
}

/**
 * Custom field values keyed by field id, for before/after compares.
 *
 * @param mixed $raw Booking customFields (stored shape or expanded input).
 * @return array<int, mixed>
 */
function booking_custom_field_values_by_id( $raw ): array {
	$out = array();
	foreach ( decode_booking_custom_fields( $raw ) as $row ) {
		$out[ $row['id'] ] = $row['value'];
	}
	ksort( $out );
	return $out;
}

/**
 * Resolve a custom field key (id or label) to a field id for a service.
 * Exact label match first, then a unique partial label match.
 *
 * @param array<int, array<string,mixed>> $defs Field defs from list_custom_fields_for_entity().
 * @return string|\WP_Error Field id as string.
 */
function resolve_booking_custom_field_key( string $key, array $defs ) {
	$trimmed = trim( $key );
	$available = array();
	foreach ( $defs as $d ) {
		if ( is_array( $d ) && ! empty( $d['id'] ) ) {
			$available[] = sprintf( '%d "%s"', (int) $d['id'], (string) ( $d['label'] ?? '' ) );
		}
	}
	$listing = $available ? implode( ', ', $available ) : __( 'none', 'harudigi-booking-abilities-for-amelia' );

	if ( is_numeric( $trimmed ) ) {
		$id = (string) (int) $trimmed;
		if ( ! $defs ) {
			return $id;
		}
		foreach ( $defs as $d ) {
			if ( is_array( $d ) && (int) ( $d['id'] ?? 0 ) === (int) $id ) {
				return $id;
			}
		}
		return new \WP_Error(
			'unknown_custom_field',
			sprintf(
				/* translators: 1: field key 2: available fields */
				__( 'Unknown custom field id "%1$s". Available fields: %2$s.', 'harudigi-booking-abilities-for-amelia' ),
				$trimmed,
				$listing
			)
		);
	}

	$needle = strtolower( $trimmed );
	$exact  = array();
	$partial = array();
	foreach ( $defs as $d ) {
		if ( ! is_array( $d ) || empty( $d['id'] ) ) {
			continue;
		}
		$label = strtolower( trim( (string) ( $d['label'] ?? '' ) ) );
		if ( $label === $needle ) {
			$exact[] = $d;
		} elseif ( '' !== $needle && false !== strpos( $label, $needle ) ) {
			$partial[] = $d;
		}
	}
	$matches = $exact ? $exact : $partial;
	if ( 1 === count( $matches ) ) {
		return (string) (int) $matches[0]['id'];
	}
	if ( count( $matches ) > 1 ) {
		return new \WP_Error(
			'unknown_custom_field',
			sprintf(
				/* translators: 1: field key 2: available fields */
				__( 'Ambiguous custom field label "%1$s". Available fields: %2$s. Use the exact label or the field id.', 'harudigi-booking-abilities-for-amelia' ),
				$trimmed,
				$listing
			)
		);
	}
	return new \WP_Error(
		'unknown_custom_field',
		sprintf(
			/* translators: 1: field key 2: available fields */
			__( 'Unknown custom field "%1$s". Available fields: %2$s. Use a field label or id from this list.', 'harudigi-booking-abilities-for-amelia' ),
			$trimmed,
			$listing
		)
	);
}

/**
 * The three note areas plus decoded booking fields, by name.
 *
 * @param array<string,mixed> $appointment Appointment array from get-appointment.
 * @return array<string,mixed>
 */
function appointment_notes_and_fields( array $appointment ): array {
	$booking = null;
	if ( ! empty( $appointment['bookings'][0] ) && is_array( $appointment['bookings'][0] ) ) {
		$booking = $appointment['bookings'][0];
	}
	$fields = $booking ? decode_booking_custom_fields( $booking['customFields'] ?? null ) : array();
	$shared = null;
	foreach ( $fields as $row ) {
		if ( 'notes' === strtolower( trim( $row['label'] ) ) ) {
			$shared = $row['value'];
			break;
		}
	}
	$customer = ( $booking && ! empty( $booking['customer'] ) && is_array( $booking['customer'] ) ) ? $booking['customer'] : array();
	return array(
		'privateNote'  => $appointment['internalNotes'] ?? null,
		'customerNote' => $customer['note'] ?? null,
		'sharedNote'   => $shared,
		'fields'       => $fields,
	);
}

/** @param mixed $raw */
function invalid_booking_start_error( $raw ): \WP_Error {
	return new \WP_Error(
		'invalid_booking_start',
		sprintf(
			/* translators: %s: received value */
			__( 'Invalid bookingStart "%s". Accepted: "Y-m-d H:i" or "Y-m-d H:i:s" in site time, or ISO 8601 "Y-m-dTH:i" / "Y-m-dTH:i:s" with optional Z or offset like +09:00 (converted to site time). Example: 2026-12-20 10:00. Date only, slash or dot dates, month names, and relative words like tomorrow are not accepted.', 'harudigi-booking-abilities-for-amelia' ),
			is_scalar( $raw ) ? (string) $raw : wp_json_encode( $raw )
		)
	);
}

function is_strict_ymd( string $s ): bool {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m ) ) {
		return false;
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/**
 * Canonical booking time in site timezone, or WP_Error.
 *
 * @param mixed $raw Booking start input.
 * @return string|\WP_Error Canonical Y-m-d H:i.
 */
function normalize_booking_start( $raw ) {
	if ( ! is_string( $raw ) && ! is_numeric( $raw ) ) {
		return invalid_booking_start_error( $raw );
	}
	$s = trim( (string) $raw );
	if ( '' === $s ) {
		return invalid_booking_start_error( $raw );
	}
	$tz = wp_timezone();

	// ISO 8601: Y-m-dTH:i[:s][Z|±HH:MM]
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?(Z|[+-]\d{2}:\d{2})?$/', $s, $m ) ) {
		$y = (int) $m[1]; $mo = (int) $m[2]; $d = (int) $m[3];
		$h = (int) $m[4]; $mi = (int) $m[5]; $sec = isset( $m[6] ) && '' !== $m[6] ? (int) $m[6] : 0;
		if ( ! checkdate( $mo, $d, $y ) || $h > 23 || $mi > 59 || $sec > 59 ) {
			return invalid_booking_start_error( $raw );
		}
		$offset = $m[7] ?? '';
		if ( '' !== $offset ) {
			if ( ! preg_match( '/^(Z|[+-](\d{2}):(\d{2}))$/', $offset, $om ) || ( 'Z' !== $offset && ( (int) $om[2] > 14 || (int) $om[3] > 59 ) ) ) {
				return invalid_booking_start_error( $raw );
			}
			$offset_norm = 'Z' === $offset ? '+00:00' : $offset;
			$input_norm  = sprintf( '%04d-%02d-%02dT%02d:%02d:%02d%s', $y, $mo, $d, $h, $mi, $sec, $offset_norm );
			$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:sP', $input_norm );
			$err = \DateTimeImmutable::getLastErrors();
			if ( false === $dt || ( is_array( $err ) && ( $err['warning_count'] > 0 || $err['error_count'] > 0 ) ) ) {
				return invalid_booking_start_error( $raw );
			}
			return $dt->setTimezone( $tz )->format( 'Y-m-d H:i' );
		}
		try {
			$dt = new \DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $mi, $sec ), $tz );
		} catch ( \Throwable $e ) {
			return invalid_booking_start_error( $raw );
		}
		return $dt->format( 'Y-m-d H:i' );
	}

	// Plain site time: Y-m-d H:i[:s]
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})(?::(\d{2}))?$/', $s, $m ) ) {
		$y = (int) $m[1]; $mo = (int) $m[2]; $d = (int) $m[3];
		$h = (int) $m[4]; $mi = (int) $m[5]; $sec = isset( $m[6] ) && '' !== $m[6] ? (int) $m[6] : 0;
		if ( ! checkdate( $mo, $d, $y ) || $h > 23 || $mi > 59 || $sec > 59 ) {
			return invalid_booking_start_error( $raw );
		}
		$input_norm = sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $mi, $sec );
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $input_norm, $tz );
		$err = \DateTimeImmutable::getLastErrors();
		if ( false === $dt || ( is_array( $err ) && ( $err['warning_count'] > 0 || $err['error_count'] > 0 ) ) ) {
			return invalid_booking_start_error( $raw );
		}
		return $dt->format( 'Y-m-d H:i' );
	}

	return invalid_booking_start_error( $raw );
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
		return new \WP_Error( 'invalid_service_id', __( 'Invalid service_id.', 'harudigi-booking-abilities-for-amelia' ) );
	}

	$result = Helpers::invoke( GetServiceController::class, array(), array( 'id' => $service_id ), 'GET' );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$data    = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : $result;
	$service = $data['service'] ?? $data;
	if ( ! is_array( $service ) ) {
		return new \WP_Error( 'amelia_not_found', __( 'Service not found.', 'harudigi-booking-abilities-for-amelia' ) );
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

	$notes_field_id = null;
	foreach ( $fields as $f ) {
		if ( is_array( $f ) && 'notes' === strtolower( trim( (string) ( $f['label'] ?? '' ) ) ) ) {
			$notes_field_id = (int) $f['id'];
			break;
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
		'notes_map'              => array(
			'privateNote'       => 'amelia/book internalNotes (staff only)',
			'sharedNoteFieldId' => $notes_field_id,
			'sharedNoteLabel'   => 'Notes',
			'customerNote'      => 'amelia/mutate customer fields.note',
		),
		'how_to_book'            => array(
			'duration'      => 'amelia/book action=create: pass duration as seconds or "1h"/"90m". For availability use amelia/query action=availability with serviceDuration.',
			'extras'        => 'amelia/book: extras [{extraId, quantity}]. Availability: [{id, quantity}].',
			'custom_fields' => 'Custom field keys can be field labels or ids, e.g. {"Notes":"..."} or {"3":"..."}. Labels match exactly, or by a unique partial match. Simple values and full Amelia objects both work. For select fields pass the option label, e.g. "WhatsApp".',
			'update'        => 'amelia/book action=update id=N with extras/customFields/persons/status at top level (merges onto first booking).',
			'status'        => 'Omit status → Amelia defaultAppointmentStatus. notify defaults false — ask human before true.',
		),
	);
}

/**
 * Every requested extra must belong to the booking service.
 *
 * @param mixed $raw_extras Requested extras (booking shape).
 * @return true|\WP_Error
 */
function validate_booking_extras_for_service( $raw_extras, int $service_id ) {
	$requested = normalize_booking_extras( $raw_extras, 'extraId' );
	if ( ! $requested || $service_id <= 0 ) {
		return true;
	}
	$opts = get_service_booking_options( $service_id );
	if ( is_wp_error( $opts ) ) {
		return $opts;
	}
	$valid = array();
	foreach ( (array) ( $opts['extras'] ?? array() ) as $ex ) {
		if ( is_array( $ex ) && ! empty( $ex['id'] ) ) {
			$valid[ (int) $ex['id'] ] = (string) ( $ex['name'] ?? '' );
		}
	}
	$bad = array();
	foreach ( $requested as $row ) {
		$eid = (int) $row['extraId'];
		if ( $eid > 0 && ! isset( $valid[ $eid ] ) ) {
			$bad[ $eid ] = $eid;
		}
	}
	if ( ! $bad ) {
		return true;
	}
	$pairs = array();
	foreach ( $valid as $vid => $vname ) {
		$pairs[] = sprintf( '%d "%s"', $vid, $vname );
	}
	return new \WP_Error(
		'extra_not_for_service',
		sprintf(
			/* translators: 1: bad extra ids 2: service id 3: valid extras */
			__( 'Extra %1$s does not belong to service %2$d. Valid extras for this service: %3$s.', 'harudigi-booking-abilities-for-amelia' ),
			implode( ', ', array_values( $bad ) ),
			$service_id,
			$pairs ? implode( ', ', $pairs ) : __( 'none', 'harudigi-booking-abilities-for-amelia' )
		)
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
	$booking_id = (int) ( $incoming['id'] ?? $existing['id'] ?? 0 );
	$by_extra   = array();
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
			// Amelia's update() writes customerBookingId from the entity; omit it and the row is orphaned (id kept, booking link cleared).
			if ( $booking_id > 0 ) {
				$incoming['extras'][ $i ]['customerBookingId'] = $booking_id;
			}
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
	if ( empty( $merged['color'] ) ) {
		$merged['color'] = '#1788FB';
	}

	return $merged;
}
