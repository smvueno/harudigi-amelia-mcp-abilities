<?php
/**
 * Write safety: dry_run preview, previewId binding, expectedCustomer.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HARUDIGI_PREVIEW_TTL = 600;
const HARUDIGI_PREVIEW_PREFIX = 'harudigi_amelia_prv_';

/** @param mixed $v */
function book_flag_true( $v ): bool {
	return true === $v || 1 === $v || '1' === $v || 'true' === $v;
}

/**
 * @param array<string,mixed> $input
 * @return 'dry_run'|'confirm'|\WP_Error
 */
function book_require_write_mode( array $input ) {
	$dry     = book_flag_true( $input['dry_run'] ?? false );
	$confirm = book_flag_true( $input['confirm'] ?? false );
	if ( $dry && $confirm ) {
		return new \WP_Error(
			'invalid_flags',
			__( 'Pass dry_run:true OR confirm:true, not both.', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	if ( $dry ) {
		return 'dry_run';
	}
	if ( $confirm ) {
		return 'confirm';
	}
	return new \WP_Error(
		'dry_run_required',
		__( 'Write refused. First call with dry_run:true, show the preview to the human, then call again with confirm:true + previewId + expectedCustomer (updates).', 'harudigi-booking-abilities-for-amelia' )
	);
}

/**
 * Refuse notes + reschedule in one update.
 *
 * @param array<string,mixed> $patch Merged appointment-level patch keys.
 * @return true|\WP_Error
 */
function book_assert_single_concern( array $patch ) {
	$has_notes = array_key_exists( 'internalNotes', $patch );
	$has_start = array_key_exists( 'bookingStart', $patch );
	if ( $has_notes && $has_start ) {
		return new \WP_Error(
			'mixed_update',
			__( 'Refuse: internalNotes and bookingStart cannot change in the same update. Do notes-only OR reschedule-only.', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	return true;
}

/**
 * @param array<string,mixed>|null $customer
 * @return true|\WP_Error
 */
function book_assert_expected_customer( $customer, string $expected ) {
	$expected = strtolower( trim( $expected ) );
	if ( '' === $expected ) {
		return new \WP_Error(
			'expected_customer_required',
			__( 'expectedCustomer is required on update (customer full name or email). Must match the appointment.', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	if ( ! is_array( $customer ) ) {
		return new \WP_Error(
			'expected_customer_mismatch',
			__( 'Appointment has no customer to match expectedCustomer against.', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	$first = strtolower( trim( (string) ( $customer['firstName'] ?? '' ) ) );
	$last  = strtolower( trim( (string) ( $customer['lastName'] ?? '' ) ) );
	$name  = trim( $first . ' ' . $last );
	$email = strtolower( trim( (string) ( $customer['email'] ?? '' ) ) );

	if ( $email && ( $email === $expected || false !== strpos( $email, $expected ) || false !== strpos( $expected, $email ) ) ) {
		return true;
	}
	if ( $name && ( $name === $expected || false !== strpos( $name, $expected ) || false !== strpos( $expected, $name ) ) ) {
		return true;
	}
	if ( $first && ( $first === $expected || false !== strpos( $expected, $first ) ) ) {
		return true;
	}
	return new \WP_Error(
		'expected_customer_mismatch',
		sprintf(
			/* translators: 1: expected string 2: actual name 3: email */
			__( 'expectedCustomer "%1$s" does not match appointment customer "%2$s" <%3$s>. Wrong id?', 'harudigi-booking-abilities-for-amelia' ),
			$expected,
			$name !== '' ? $name : '(unknown)',
			$email !== '' ? $email : 'no-email'
		)
	);
}

/**
 * Canonical fingerprint of the intended write (stable JSON).
 *
 * @param array<string,mixed> $payload
 */
function book_preview_fingerprint( array $payload ): string {
	book_ksort_recursive( $payload );
	$json = wp_json_encode( $payload );
	return hash( 'sha256', is_string( $json ) ? $json : serialize( $payload ) );
}

/** @param array<string,mixed> $arr */
function book_ksort_recursive( array &$arr ): void {
	ksort( $arr );
	foreach ( $arr as &$v ) {
		if ( is_array( $v ) ) {
			book_ksort_recursive( $v );
		}
	}
}

/**
 * @param array<string,mixed> $fingerprint_payload
 * @param array<string,mixed> $preview_body Shown to agent/human.
 * @return array<string,mixed>
 */
function book_store_preview( string $action, array $fingerprint_payload, array $preview_body ): array {
	$fp        = book_preview_fingerprint( $fingerprint_payload );
	$preview_id = 'prv_' . substr( hash( 'sha256', $fp . wp_generate_password( 8, false ) . microtime( true ) ), 0, 24 );
	set_transient(
		HARUDIGI_PREVIEW_PREFIX . $preview_id,
		array(
			'action'      => $action,
			'fingerprint' => $fp,
			'payload'     => $fingerprint_payload,
			'created'     => time(),
		),
		HARUDIGI_PREVIEW_TTL
	);
	$preview_body['dry_run']    = true;
	$preview_body['previewId']  = $preview_id;
	$preview_body['expiresIn']  = HARUDIGI_PREVIEW_TTL;
	$preview_body['hint']       = __( 'Show this preview to the human. On yes: same fields + confirm:true + previewId (+ expectedCustomer on update).', 'harudigi-booking-abilities-for-amelia' );
	return $preview_body;
}

/**
 * @param array<string,mixed> $fingerprint_payload
 * @return true|\WP_Error
 */
function book_consume_preview( string $preview_id, string $action, array $fingerprint_payload ) {
	$preview_id = sanitize_text_field( $preview_id );
	if ( '' === $preview_id || 0 !== strpos( $preview_id, 'prv_' ) ) {
		return new \WP_Error(
			'preview_required',
			__( 'confirm requires previewId from a prior dry_run for this exact payload.', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	$key  = HARUDIGI_PREVIEW_PREFIX . $preview_id;
	$stored = get_transient( $key );
	if ( ! is_array( $stored ) || empty( $stored['fingerprint'] ) ) {
		return new \WP_Error(
			'preview_expired',
			__( 'previewId missing or expired (10 min). Run dry_run again and re-approve.', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	if ( ( $stored['action'] ?? '' ) !== $action ) {
		return new \WP_Error(
			'preview_mismatch',
			__( 'previewId action mismatch (create vs update).', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	$fp = book_preview_fingerprint( $fingerprint_payload );
	if ( ! hash_equals( (string) $stored['fingerprint'], $fp ) ) {
		return new \WP_Error(
			'preview_mismatch',
			__( 'Payload changed since dry_run. Run dry_run again with the final fields.', 'harudigi-booking-abilities-for-amelia' )
		);
	}
	delete_transient( $key );
	return true;
}

/**
 * First booking + customer from appointment array.
 *
 * @param array<string,mixed> $appointment
 * @return array{0:?array<string,mixed>,1:?array<string,mixed>}
 */
function book_first_booking_customer( array $appointment ): array {
	$booking = null;
	if ( ! empty( $appointment['bookings'][0] ) && is_array( $appointment['bookings'][0] ) ) {
		$booking = $appointment['bookings'][0];
	}
	$customer = null;
	if ( $booking && ! empty( $booking['customer'] ) && is_array( $booking['customer'] ) ) {
		$customer = $booking['customer'];
	}
	return array( $booking, $customer );
}

/**
 * Merge update patch keys from input (appointment-level only for concern check).
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>
 */
function book_collect_update_patch_keys( array $input ): array {
	$patch = array();
	if ( isset( $input['fields'] ) && is_array( $input['fields'] ) ) {
		$patch = $input['fields'];
	}
	// Appointment-level and booking-level keys behave the same at top level
	// or inside fields{} (top level wins, mirroring the appointment keys).
	foreach ( array( 'bookingStart', 'providerId', 'serviceId', 'locationId', 'internalNotes', 'persons', 'status', 'duration', 'extras', 'customFields', 'couponCode' ) as $k ) {
		if ( array_key_exists( $k, $input ) ) {
			$patch[ $k ] = $input[ $k ];
		}
	}
	// Ignore notify flags for concern check.
	unset( $patch['notify'], $patch['notifyParticipants'] );
	return $patch;
}

/**
 * Extras as a stable [{extraId,quantity}] list for preview / read-back compares.
 *
 * @param mixed $raw Extras list (incoming shape or existing booking shape).
 * @return array<int, array{extraId:int,quantity:int}>
 */
function book_extras_preview_shape( $raw ): array {
	$out = array();
	foreach ( normalize_booking_extras( $raw, 'extraId' ) as $row ) {
		$out[] = array(
			'extraId'  => (int) $row['extraId'],
			'quantity' => (int) $row['quantity'],
		);
	}
	usort(
		$out,
		static function ( $a, $b ) {
			return $a['extraId'] <=> $b['extraId'];
		}
	);
	return $out;
}

/**
 * Build fingerprint payload for update (stable subset).
 *
 * @param array<string,mixed> $input
 * @param array<string,mixed> $patch
 */
function book_update_fingerprint_payload( int $apt_id, array $input, array $patch ): array {
	$out = array(
		'action'           => 'update',
		'id'               => $apt_id,
		'expectedCustomer' => strtolower( trim( (string) ( $input['expectedCustomer'] ?? '' ) ) ),
		'booking_id'       => (int) ( $input['booking_id'] ?? 0 ),
		'fields'           => array(),
	);
	foreach ( array( 'bookingStart', 'providerId', 'serviceId', 'locationId', 'internalNotes', 'persons', 'status', 'duration', 'extras', 'customFields', 'couponCode' ) as $k ) {
		if ( array_key_exists( $k, $patch ) ) {
			$out['fields'][ $k ] = $patch[ $k ];
		}
		if ( array_key_exists( $k, $input ) && ! array_key_exists( $k, $out['fields'] ) ) {
			$out['fields'][ $k ] = $input[ $k ];
		}
	}
	return $out;
}

/**
 * @param array<string,mixed> $appointment
 * @param array<string,mixed> $patch
 * @return array<string,mixed>
 */
function book_build_update_preview( array $appointment, array $patch, string $expected_customer ): array {
	list( $booking, $customer ) = book_first_booking_customer( $appointment );
	$apt_id  = (int) ( $appointment['id'] ?? 0 );
	$book_id = (int) ( $booking['id'] ?? 0 );
	$name    = trim( (string) ( $customer['firstName'] ?? '' ) . ' ' . (string) ( $customer['lastName'] ?? '' ) );
	$email   = (string) ( $customer['email'] ?? '' );

	$changes   = array();
	$unchanged = array( 'status', 'payments', 'serviceId', 'providerId' );
	$booking_keys = array( 'persons', 'status', 'duration', 'extras', 'customFields', 'couponCode' );
	foreach ( $patch as $field => $after ) {
		if ( in_array( $field, array( 'bookings', 'notifyParticipants', 'notify' ), true ) ) {
			continue;
		}
		$before = $appointment[ $field ] ?? null;
		if ( in_array( $field, $booking_keys, true ) && is_array( $booking ) ) {
			$before = $booking[ $field ] ?? $before;
		}
		if ( 'extras' === $field ) {
			$before = book_extras_preview_shape( is_array( $booking ) ? ( $booking['extras'] ?? array() ) : array() );
			$after  = book_extras_preview_shape( $after );
		}
		if ( 'customFields' === $field ) {
			// Compare by field id after label resolution, not raw input shapes.
			// Updates merge onto existing fields, so preview the merged result.
			$before   = booking_custom_field_values_by_id( is_array( $booking ) ? ( $booking['customFields'] ?? null ) : null );
			$expanded = expand_custom_fields_for_booking( $after, (int) ( $appointment['serviceId'] ?? 0 ) );
			$incoming = is_wp_error( $expanded ) ? booking_custom_field_values_by_id( $after ) : booking_custom_field_values_by_id( $expanded );
			$after    = array_replace( $before, $incoming );
			ksort( $after );
		}
		if ( 'internalNotes' === $field ) {
			$before = (string) ( $appointment['internalNotes'] ?? '' );
			$after  = (string) $after;
		}
		if ( $before === $after ) {
			continue;
		}
		$changes[] = array(
			'field'  => $field,
			'before' => $before,
			'after'  => $after,
		);
		$unchanged = array_values( array_diff( $unchanged, array( $field ) ) );
	}
	if ( array_key_exists( 'bookingStart', $patch ) ) {
		$unchanged = array_values( array_diff( $unchanged, array( 'bookingStart' ) ) );
	} else {
		$unchanged[] = 'bookingStart';
	}
	if ( ! array_key_exists( 'internalNotes', $patch ) ) {
		$unchanged[] = 'internalNotes';
	}

	$warnings = array();
	if ( array_key_exists( 'bookingStart', $patch ) ) {
		$warnings[] = 'reschedule';
	}

	return array(
		'success' => true,
		'target'  => array(
			'aptId'    => $apt_id,
			'bookId'   => $book_id,
			'customer' => $name,
			'email'    => $email,
			'start'    => $appointment['bookingStart'] ?? null,
			'end'      => $appointment['bookingEnd'] ?? null,
			'status'   => $appointment['status'] ?? null,
		),
		'expectedCustomer' => $expected_customer,
		'changes'          => $changes,
		'unchanged'        => array_values( array_unique( $unchanged ) ),
		'warnings'         => $warnings,
	);
}

/**
 * @param array<string,mixed> $input
 * @return array<string,mixed>
 */
function book_create_fingerprint_payload( array $input ): array {
	return array(
		'action'       => 'create',
		'serviceId'    => (int) ( $input['serviceId'] ?? 0 ),
		'providerId'   => (int) ( $input['providerId'] ?? 0 ),
		'customerId'   => (int) ( $input['customerId'] ?? 0 ),
		'bookingStart' => (string) ( $input['bookingStart'] ?? '' ),
		'persons'      => (int) ( $input['persons'] ?? 1 ),
		'internalNotes'=> (string) ( $input['internalNotes'] ?? '' ),
		'status'       => (string) ( $input['status'] ?? '' ),
		'locationId'   => (int) ( $input['locationId'] ?? 0 ),
		'duration'     => $input['duration'] ?? null,
		'extras'       => $input['extras'] ?? null,
		'customFields' => $input['customFields'] ?? null,
		'couponCode'   => (string) ( $input['couponCode'] ?? '' ),
	);
}

/**
 * Slim one appointment for agent list responses.
 *
 * @param array<string,mixed> $apt
 * @return array<string,mixed>
 */
function book_slim_appointment_row( array $apt ): array {
	list( $booking, $customer ) = book_first_booking_customer( $apt );
	$name  = trim( (string) ( $customer['firstName'] ?? '' ) . ' ' . (string) ( $customer['lastName'] ?? '' ) );
	$notes = (string) ( $apt['internalNotes'] ?? '' );
	$preview = function_exists( 'mb_substr' ) ? mb_substr( $notes, 0, 120 ) : substr( $notes, 0, 120 );

	$paid = 0.0;
	$pending = 0.0;
	$pay_n = 0;
	if ( $booking && ! empty( $booking['payments'] ) && is_array( $booking['payments'] ) ) {
		foreach ( $booking['payments'] as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			++$pay_n;
			$amt = (float) ( $p['amount'] ?? 0 );
			$st  = (string) ( $p['status'] ?? '' );
			if ( 'paid' === $st ) {
				$paid += $amt;
			} elseif ( 'pending' === $st || 'partiallyPaid' === $st ) {
				$pending += $amt;
			}
		}
	}

	$total = 0.0;
	if ( $booking ) {
		$total = isset( $booking['price'] ) && is_numeric( $booking['price'] ) ? (float) $booking['price'] : 0.0;
		if ( ! empty( $booking['extras'] ) && is_array( $booking['extras'] ) ) {
			foreach ( $booking['extras'] as $ex ) {
				if ( is_array( $ex ) && isset( $ex['price'] ) && is_numeric( $ex['price'] ) ) {
					$total += (float) $ex['price'] * max( 0, (int) ( $ex['quantity'] ?? 1 ) );
				}
			}
		}
	}
	$owed = max( 0.0, $total - $paid );

	$service = $apt['service'] ?? null;
	$service_name = is_array( $service ) ? (string) ( $service['name'] ?? '' ) : '';

	return array(
		'aptId'               => (int) ( $apt['id'] ?? 0 ),
		'bookId'              => (int) ( $booking['id'] ?? 0 ),
		'start'               => $apt['bookingStart'] ?? null,
		'end'                 => $apt['bookingEnd'] ?? null,
		'status'              => $apt['status'] ?? null,
		'serviceId'           => (int) ( $apt['serviceId'] ?? 0 ),
		'serviceName'         => $service_name,
		'customerId'          => (int) ( $customer['id'] ?? $booking['customerId'] ?? 0 ),
		'customerName'        => $name,
		'customerEmail'       => (string) ( $customer['email'] ?? '' ),
		'sourceNote'          => (string) ( $customer['note'] ?? '' ),
		'internalNotesPreview'=> $preview,
		'payments'            => array(
			'count'   => $pay_n,
			'paid'    => $paid,
			'pending' => $pending,
		),
		'money'               => array(
			'total' => $total,
			'paid'  => $paid,
			'owed'  => $owed,
		),
	);
}

/**
 * Transform Amelia appointment list payload to slim + meta.
 *
 * @param array<string,mixed> $result Amelia invoke result.
 * @param array{0:string,1:string} $window
 * @return array<string,mixed>
 */
function book_slim_appointments_result( array $result, array $window, int $page, int $limit ): array {
	$rows = array();
	$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : $result;

	// Amelia shapes: data.appointments keyed by date, or nested.
	$by_date = $data['appointments'] ?? null;
	if ( is_array( $by_date ) ) {
		foreach ( $by_date as $date_key => $bucket ) {
			$appts = array();
			if ( is_array( $bucket ) && isset( $bucket['appointments'] ) && is_array( $bucket['appointments'] ) ) {
				$appts = $bucket['appointments'];
			} elseif ( is_array( $bucket ) && isset( $bucket[0] ) ) {
				$appts = $bucket;
			}
			foreach ( $appts as $apt ) {
				if ( is_array( $apt ) ) {
					$rows[] = book_slim_appointment_row( $apt );
				}
			}
		}
	}

	$count = count( $rows );
	return array(
		'success' => true,
		'view'    => 'slim',
		'meta'    => array(
			'window'  => $window,
			'page'    => $page,
			'limit'   => $limit,
			'count'   => $count,
			'hasMore' => $count >= $limit,
		),
		'data'    => array(
			'appointments' => $rows,
		),
	);
}
