<?php
/**
 * Shared helpers — Slim controller invoke (same path as Amelia core MCP).
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;
use AmeliaVendor\Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class Helpers {

	public const LIST_MAX_LIMIT = 100;

	public const SECRET_KEYS = array(
		'password', 'access_key', 'secret_key', 'api_key', 'apikey', 'client_id', 'client_secret',
		'auth_token', 'access_token', 'refresh_token', 'token', 'purchasecode', 'purchase_code',
		'envatotokenemail', 'smtppass', 'smtppassword', 'mailgunapikey', 'sendgridapikey',
		'stripesecret', 'stripekey', 'paypalsecret', 'mollieapikey', 'razorpaykeysecret',
		'squareaccesstoken', 'squarerefreshtoken', 'webhook_url', 'zoomapikey', 'zoomapisecret',
	);

	/**
	 * Write-body keys never accepted via MCP (passwords, WP user link, credential-ish).
	 * Matched case-insensitively after stripping non-alphanumeric chars.
	 *
	 * @var string[]
	 */
	public const BLOCKED_WRITE_KEYS = array(
		'password',
		'externalid',
		'sendemployeepanelaccessemail',
		'stripeconnect',
		'confirmpassword',
		'newpassword',
		'currentpassword',
	);

	/** @var Container|null */
	private static $container = null;

	/** @return true|\WP_Error */
	public static function require_amelia() {
		if ( ! defined( 'AMELIA_PATH' ) || ! defined( 'AMELIA_VERSION' ) ) {
			return new \WP_Error( 'amelia_inactive', __( 'Amelia Booking is not active.', 'mcp-abilities-for-amelia' ) );
		}
		return true;
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/** @return Container|\WP_Error */
	public static function container() {
		$ok = self::require_amelia();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( null === self::$container ) {
			self::$container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';
		}
		return self::$container;
	}

	/**
	 * Invoke an Amelia Slim controller (fires domain events like native MCP).
	 *
	 * @param class-string             $controller_class Controller FQCN.
	 * @param array<string, mixed>     $params           Body (POST) or query (GET).
	 * @param array<string, mixed>     $args             Route args (id, …).
	 * @param string                   $method           GET|POST.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function invoke( string $controller_class, array $params = array(), array $args = array(), string $method = 'POST' ) {
		$container = self::container();
		if ( is_wp_error( $container ) ) {
			return $container;
		}
		if ( ! class_exists( $controller_class ) ) {
			return new \WP_Error(
				'amelia_controller_missing',
				__( 'Amelia controller is not available.', 'mcp-abilities-for-amelia' )
			);
		}

		try {
			$factory = new ServerRequestFactory();
			$uri     = home_url( '/' );
			if ( 'GET' === $method && $params ) {
				$uri .= '?' . http_build_query( $params );
			}
			$request = $factory->createServerRequest( $method, $uri )
				->withHeader( 'Content-Type', 'application/json' );
			if ( 'GET' !== $method ) {
				$request = $request->withParsedBody( $params );
			}

			$controller = new $controller_class( $container );
			/** @var Response $result */
			$result     = $controller( $request, ( new ResponseFactory() )->createResponse(), $args, true );
			$status     = $result->getStatusCode();
			$decoded    = json_decode( (string) $result->getBody(), true );
			$data       = is_array( $decoded ) ? $decoded : array();
			$payload    = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : $data;
			$payload    = self::redact( $payload );

			if ( $status >= 400 ) {
				$msg = isset( $data['message'] ) ? (string) $data['message'] : __( 'Amelia command failed.', 'mcp-abilities-for-amelia' );
				return new \WP_Error(
					$status === 403 ? 'amelia_access_denied' : 'amelia_command_error',
					self::safe_error_message( $msg ),
					array( 'status' => $status, 'data' => $payload )
				);
			}

			return array(
				'success' => true,
				'message' => isset( $data['message'] ) ? $data['message'] : null,
				'data'    => $payload,
			);
		} catch ( \Throwable $e ) {
			$msg = $e->getMessage();
			// Normalize Amelia "Invalid key {id}" into a clear not-found error.
			if ( preg_match( '/^Invalid key\s+(\d+)/i', $msg, $m ) ) {
				return new \WP_Error(
					'amelia_not_found',
					sprintf(
						/* translators: %s: entity id */
						__( 'Amelia entity not found (id %s).', 'mcp-abilities-for-amelia' ),
						$m[1]
					)
				);
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[mcp-abilities-for-amelia] invoke failed: ' . $msg );
			}
			// Surface short Amelia validation errors (mandatory fields, etc.).
			$safe = self::safe_error_message( $msg );
			if ( $safe !== __( 'Amelia command failed.', 'mcp-abilities-for-amelia' ) && $safe !== __( 'Amelia invoke failed. Check WordPress/Amelia logs for details.', 'mcp-abilities-for-amelia' ) ) {
				return new \WP_Error( 'amelia_invoke_failed', $safe );
			}
			return new \WP_Error(
				'amelia_invoke_failed',
				__( 'Amelia invoke failed. Check WordPress/Amelia logs for details.', 'mcp-abilities-for-amelia' )
			);
		}
	}

	/**
	 * Public-facing error text: allow short Amelia messages, strip paths/SQL noise.
	 */
	public static function safe_error_message( string $message ): string {
		$message = trim( wp_strip_all_tags( $message ) );
		if ( '' === $message ) {
			return __( 'Amelia command failed.', 'mcp-abilities-for-amelia' );
		}
		// Block path / SQL / stack-ish leakage.
		if ( preg_match( '/(?:\/(?:var|home|usr|tmp|wp-|plugins)|\\\\|SQLSTATE|Stack trace|PDOException|mysqli)/i', $message ) ) {
			return __( 'Amelia command failed.', 'mcp-abilities-for-amelia' );
		}
		if ( strlen( $message ) > 240 ) {
			$message = substr( $message, 0, 237 ) . '...';
		}
		return $message;
	}

	/** @param mixed $data @return mixed */
	public static function redact( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( $data as $key => $value ) {
			$key_l = is_string( $key ) ? strtolower( preg_replace( '/[^a-z0-9_]/i', '', $key ) ) : $key;
			if ( is_string( $key_l ) && in_array( $key_l, self::SECRET_KEYS, true ) ) {
				$out[ $key ] = ( is_string( $value ) && '' !== $value ) || ( is_array( $value ) && $value )
					? '[redacted]' : ( is_string( $value ) ? '' : null );
				continue;
			}
			$out[ $key ] = self::redact( $value );
		}
		return $out;
	}

	/**
	 * Normalize a field key for blocklist matching.
	 */
	public static function normalize_field_key( $key ): string {
		if ( ! is_string( $key ) ) {
			return '';
		}
		return strtolower( (string) preg_replace( '/[^a-z0-9]/i', '', $key ) );
	}

	/**
	 * Remove password / WP-link / credential fields from a write body (top-level only).
	 *
	 * @param array<string, mixed> $body Raw Amelia body.
	 * @return array<string, mixed>
	 */
	public static function sanitize_write_body( array $body ): array {
		$clean = array();
		foreach ( $body as $key => $value ) {
			$norm = self::normalize_field_key( $key );
			if ( '' !== $norm && in_array( $norm, self::BLOCKED_WRITE_KEYS, true ) ) {
				continue;
			}
			// Never pass MCP control flags into Amelia.
			if ( in_array( $norm, array( 'confirm', 'includebulk', 'force' ), true ) ) {
				continue;
			}
			$clean[ $key ] = $value;
		}
		return $clean;
	}

	/** @return int|\WP_Error */
	public static function parse_id( $id, string $label = 'id' ) {
		if ( ! is_numeric( $id ) || (int) $id <= 0 ) {
			/* translators: %s: field label */
			return new \WP_Error( 'invalid_' . $label, sprintf( __( 'Invalid %s.', 'mcp-abilities-for-amelia' ), $label ) );
		}
		return (int) $id;
	}

	/**
	 * Destructive ops require an explicit confirm=true boolean.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return true|\WP_Error
	 */
	public static function require_confirm( array $input ) {
		$confirm = $input['confirm'] ?? false;
		if ( true === $confirm || 1 === $confirm || '1' === $confirm || 'true' === $confirm ) {
			return true;
		}
		return new \WP_Error(
			'confirm_required',
			__( 'Destructive action refused. Set confirm=true only after the user explicitly approved permanent deletion. Prefer cancel/hide/disable when possible.', 'mcp-abilities-for-amelia' )
		);
	}

	/**
	 * Resolve create/update body: prefer nested `fields`, else flat input minus id keys.
	 * Always strips blocked write keys (password, externalId, …).
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @param string[]             $strip Keys to strip from flat input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function body_from_input( array $input, array $strip = array() ) {
		if ( isset( $input['fields'] ) && is_array( $input['fields'] ) ) {
			$body = self::sanitize_write_body( $input['fields'] );
			if ( ! $body ) {
				return new \WP_Error( 'invalid_fields', __( 'Provide fields (or a fields object).', 'mcp-abilities-for-amelia' ) );
			}
			return $body;
		}
		$body = $input;
		unset( $body['fields'] );
		foreach ( array_merge( $strip, array( 'confirm', 'include_bulk' ) ) as $key ) {
			unset( $body[ $key ] );
		}
		$body = self::sanitize_write_body( $body );
		if ( ! $body ) {
			return new \WP_Error( 'invalid_fields', __( 'Provide fields (or a fields object).', 'mcp-abilities-for-amelia' ) );
		}
		return $body;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function assert_booking_status( string $status ) {
		$allowed = array( 'approved', 'pending', 'canceled', 'rejected', 'no-show' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new \WP_Error(
				'invalid_status',
				sprintf(
					/* translators: %s: allowed statuses */
					__( 'Invalid status. Use: %s', 'mcp-abilities-for-amelia' ),
					implode( ', ', $allowed )
				)
			);
		}
		return true;
	}

	/**
	 * Catalog entity status (services, locations, employees, packages, customers).
	 *
	 * @return true|\WP_Error
	 */
	public static function assert_entity_status( string $status ) {
		$allowed = array( 'visible', 'hidden', 'disabled', 'shared' );
		if ( '' === $status || ! in_array( $status, $allowed, true ) ) {
			return new \WP_Error(
				'invalid_status',
				sprintf(
					/* translators: %s: allowed statuses */
					__( 'Invalid status. Use: %s', 'mcp-abilities-for-amelia' ),
					implode( ', ', $allowed )
				)
			);
		}
		return true;
	}

	/**
	 * Flat create schema (no nested fields wrapper).
	 *
	 * @param string[]             $required Required top-level keys.
	 * @param array<string, mixed> $props    Property schemas.
	 * @return array<string, mixed>
	 */
	public static function create_schema( array $required, array $props ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'required'             => $required,
			'properties'           => $props,
		);
	}

	/** @param array<string, mixed> $input @return array<string, mixed> */
	public static function list_params( array $input ): array {
		$page  = max( 1, (int) ( $input['page'] ?? 1 ) );
		$limit = max( 1, min( self::LIST_MAX_LIMIT, (int) ( $input['limit'] ?? ( $input['per_page'] ?? 25 ) ) ) );
		$params = array( 'page' => $page, 'limit' => $limit );
		foreach ( array( 'status', 'search', 'dates', 'services', 'providers', 'locations', 'customers' ) as $key ) {
			if ( isset( $input[ $key ] ) && '' !== $input[ $key ] && null !== $input[ $key ] ) {
				$params[ $key ] = is_string( $input[ $key ] )
					? sanitize_text_field( $input[ $key ] )
					: $input[ $key ];
			}
		}
		if ( ! empty( $input['date_from'] ) || ! empty( $input['date_to'] ) ) {
			$params['dates'] = array(
				sanitize_text_field( (string) ( $input['date_from'] ?? '' ) ),
				sanitize_text_field( (string) ( $input['date_to'] ?? '' ) ),
			);
		}
		return $params;
	}

	/** @return array<string, mixed> */
	public static function list_schema( array $extra = array() ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array_merge(
				array(
					'page'   => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'limit'  => array(
						'type'        => 'integer',
						'default'     => 25,
						'minimum'     => 1,
						'maximum'     => self::LIST_MAX_LIMIT,
						'description' => 'Page size (max ' . self::LIST_MAX_LIMIT . ').',
					),
					'status' => array( 'type' => 'string' ),
					'search' => array( 'type' => 'string' ),
				),
				$extra
			),
		);
	}

	/** @return array<string, mixed> */
	public static function id_schema( string $field ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( $field ),
			'properties'           => array( $field => array( 'type' => 'integer' ) ),
		);
	}

	/**
	 * Destructive delete schema: entity id + confirm=true.
	 *
	 * @return array<string, mixed>
	 */
	public static function id_confirm_schema( string $field ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( $field, 'confirm' ),
			'properties'           => array(
				$field    => array( 'type' => 'integer' ),
				'confirm' => array(
					'type'        => 'boolean',
					'description' => 'Must be true. Only set after the user explicitly approved permanent deletion.',
				),
			),
		);
	}

	/** @return array<string, mixed> */
	public static function mcp_meta( bool $readonly, bool $destructive = false, bool $idempotent = true ): array {
		return array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => $readonly,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			),
		);
	}

	/** @param array<string, mixed> $args */
	public static function register( string $name, array $args ): void {
		$readonly    = ! empty( $args['readonly'] );
		$destructive = ! empty( $args['destructive'] );
		$idempotent  = array_key_exists( 'idempotent', $args ) ? (bool) $args['idempotent'] : true;
		wp_register_ability(
			$name,
			array(
				'label'               => $args['label'],
				'description'         => $args['description'],
				'category'            => 'amelia-ops',
				'input_schema'        => $args['input_schema'] ?? array(
					'type'                 => 'object',
					'additionalProperties' => false,
				),
				'output_schema'       => $args['output_schema'] ?? array( 'type' => 'object' ),
				'execute_callback'    => $args['callback'],
				'permission_callback' => array( self::class, 'can_manage' ),
				'meta'                => self::mcp_meta( $readonly, $destructive, $idempotent ),
			)
		);
	}

	/** @return array<string, mixed>|\WP_Error */
	public static function status_payload() {
		$ok = self::require_amelia();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		global $wpdb;
		$prefix = $wpdb->prefix . 'amelia_';
		$counts = array();
		foreach ( array( 'appointments', 'events', 'users', 'services', 'locations', 'categories', 'packages', 'coupons', 'payments' ) as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$counts[ $table ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}{$table}" );
		}
		$settings   = new SettingsService( new SettingsStorage() );
		$activation = self::redact( (array) $settings->getCategorySettings( 'activation' ) );
		return array(
			'amelia_version'  => AMELIA_VERSION,
			'bridge_version'  => HARUDIGI_AMELIA_MCP_VERSION,
			'activation'      => array(
				'version' => $activation['version'] ?? null,
				'active'  => ! empty( $activation['active'] ),
			),
			'counts'          => $counts,
			'native_abilities'=> amelia_native_ability_slugs(),
			'helper_abilities'=> amelia_mcp_abilities_slugs(),
		);
	}

	/** @return array<string, mixed> */
	public static function api_surface(): array {
		return array(
			'bridge_version'   => HARUDIGI_AMELIA_MCP_VERSION,
			'amelia_version'   => defined( 'AMELIA_VERSION' ) ? AMELIA_VERSION : null,
			'native_amelia'    => array(
				'note'     => 'Registered by Amelia Pro (amelia-read / amelia-write). Do not duplicate.',
				'abilities'=> amelia_native_ability_slugs(),
			),
			'helper_gaps'      => array(
				'note'     => 'Registered by mcp-abilities-for-amelia (amelia-ops) for full admin control.',
				'abilities'=> amelia_mcp_abilities_slugs(),
			),
			'never_expose'     => array(
				'Payment gateway secrets', 'SMTP/SMS/WhatsApp API keys',
				'OAuth tokens', 'Purchase/license codes',
				'Passwords / externalId (WP user link) via MCP writes',
			),
			'safety'           => array(
				'deletes_require_confirm' => true,
				'list_max_limit'          => self::LIST_MAX_LIMIT,
				'get_entities_requires_confirm' => true,
				'blocked_write_keys'      => self::BLOCKED_WRITE_KEYS,
			),
		);
	}

	/**
	 * Non-secret settings summary.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function settings_summary() {
		$ok = self::require_amelia();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$settings = new SettingsService( new SettingsStorage() );
		$safe_cats = array( 'general', 'company', 'appointments', 'events', 'roles', 'labels', 'weekSchedule' );
		$out       = array();
		foreach ( $safe_cats as $cat ) {
			$out[ $cat ] = self::redact( (array) $settings->getCategorySettings( $cat ) );
		}
		$payments = (array) $settings->getCategorySettings( 'payments' );
		$out['payments'] = array(
			'currency' => $payments['currency'] ?? null,
			'priceSymbol' => $payments['priceSymbol'] ?? null,
			'defaultPaymentMethod' => $payments['defaultPaymentMethod'] ?? null,
			'paymentLinks' => array(
				'enabled'             => ! empty( $payments['paymentLinks']['enabled'] ),
				'changeBookingStatus' => ! empty( $payments['paymentLinks']['changeBookingStatus'] ),
			),
			'depositPaymentFeature' => $settings->isFeatureEnabled( 'depositPayment' ),
			'gateways_configured' => array(
				'stripe'  => ! empty( $payments['stripe']['enabled'] ),
				'paypal'  => ! empty( $payments['payPal']['enabled'] ),
				'mollie'  => ! empty( $payments['mollie']['enabled'] ),
				'square'  => ! empty( $payments['square']['enabled'] ),
				'wc'      => ! empty( $payments['wc']['enabled'] ),
				'onSite'  => ! empty( $payments['onSite']['enabled'] ),
			),
		);
		return $out;
	}
}
