<?php
/**
 * Plugin Name:       HaruDigi Booking Abilities for Amelia and Easy MCP AI
 * Plugin URI:        https://smvueno.github.io/harudigi-amelia-mcp-abilities/
 * Description:       Compact Amelia admin MCP tools for Easy MCP AI (≤7 meta abilities). Independent plugin by HaruDigi — not affiliated with or endorsed by TMS Software.
 * Version:           2.0.4
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  easy-mcp-ai
 * Author:            Jens Madsen · HaruDigi
 * Author URI:        https://harudigi.com
 * Update URI:        https://github.com/smvueno/harudigi-amelia-mcp-abilities
 * Text Domain:       harudigi-booking-abilities-for-amelia
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HARUDIGI_AMELIA_MCP_VERSION', '2.0.4' );
define( 'HARUDIGI_AMELIA_MCP_FILE', __FILE__ );
define( 'HARUDIGI_AMELIA_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'HARUDIGI_AMELIA_MCP_MIN_AMELIA', '9.7' );

require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/class-helpers.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/class-registrar.php';

$harudigi_amelia_mcp_updater = HARUDIGI_AMELIA_MCP_DIR . 'includes/class-github-updater.php';
if ( is_readable( $harudigi_amelia_mcp_updater ) ) {
	require_once $harudigi_amelia_mcp_updater;
}

/**
 * Meta abilities registered by this plugin (and enabled in Easy MCP).
 *
 * @return string[]
 */
function harudigi_amelia_mcp_meta_ability_slugs(): array {
	return array(
		'amelia/help',
		'amelia/status',
		'amelia/discover',
		'amelia/query',
		'amelia/mutate',
		'amelia/book',
		'amelia/pay',
	);
}

/**
 * Amelia Pro native ability slugs (suppressed while this plugin is active).
 *
 * @return string[]
 */
function harudigi_amelia_mcp_amelia_native_slugs(): array {
	return array(
		'amelia/list-services',
		'amelia/list-employees',
		'amelia/list-customers',
		'amelia/list-events',
		'amelia/list-appointments',
		'amelia/check-availability',
		'amelia/add-service',
		'amelia/add-customer',
		'amelia/create-appointment',
		'amelia/create-event',
		'amelia/book-event',
		'amelia/cancel-booking',
	);
}

/**
 * Legacy fine-grained HaruDigi slugs + Amelia natives — stripped from Easy MCP enable-list.
 *
 * @return string[]
 */
function harudigi_amelia_mcp_legacy_ability_slugs(): array {
	return array_values(
		array_unique(
			array_merge(
				harudigi_amelia_mcp_amelia_native_slugs(),
				array(
					'amelia/get-status',
					'amelia/list-api-surface',
					'amelia/get-stats',
					'amelia/get-entities',
					'amelia/get-settings-summary',
					'amelia/get-service',
					'amelia/get-service-booking-options',
					'amelia/list-categories',
					'amelia/get-category',
					'amelia/list-locations',
					'amelia/get-location',
					'amelia/get-employee',
					'amelia/list-packages',
					'amelia/get-package',
					'amelia/list-extras',
					'amelia/get-extra',
					'amelia/list-resources',
					'amelia/get-resource',
					'amelia/list-coupons',
					'amelia/get-coupon',
					'amelia/list-custom-fields',
					'amelia/add-custom-field',
					'amelia/update-custom-field',
					'amelia/delete-custom-field',
					'amelia/get-appointment',
					'amelia/get-event',
					'amelia/get-customer',
					'amelia/list-event-bookings',
					'amelia/get-event-booking',
					'amelia/list-payments',
					'amelia/get-payment',
					'amelia/get-payment-link',
					'amelia/add-payment',
					'amelia/update-payment',
					'amelia/delete-payment',
					'amelia/list-notifications',
					'amelia/update-service',
					'amelia/update-service-status',
					'amelia/delete-service',
					'amelia/add-category',
					'amelia/update-category',
					'amelia/delete-category',
					'amelia/add-location',
					'amelia/update-location',
					'amelia/update-location-status',
					'amelia/delete-location',
					'amelia/add-employee',
					'amelia/update-employee',
					'amelia/update-employee-status',
					'amelia/delete-employee',
					'amelia/add-package',
					'amelia/update-package',
					'amelia/update-package-status',
					'amelia/delete-package',
					'amelia/add-extra',
					'amelia/update-extra',
					'amelia/delete-extra',
					'amelia/add-resource',
					'amelia/update-resource',
					'amelia/update-resource-status',
					'amelia/delete-resource',
					'amelia/add-coupon',
					'amelia/update-coupon',
					'amelia/delete-coupon',
					'amelia/update-appointment',
					'amelia/update-appointment-status',
					'amelia/update-appointment-note',
					'amelia/update-appointment-time',
					'amelia/delete-appointment',
					'amelia/update-booking-status',
					'amelia/update-event',
					'amelia/update-event-status',
					'amelia/update-event-visibility',
					'amelia/delete-event',
					'amelia/update-customer',
					'amelia/update-customer-status',
					'amelia/update-customer-note',
					'amelia/delete-customer',
				)
			)
		)
	);
}

/**
 * Sync Easy MCP enable-list: keep only our meta slugs (plus unrelated non-amelia abilities).
 */
function harudigi_amelia_mcp_enable_in_easy_mcp(): void {
	// phpcs:ignore WordPress.FAKESECRET_s1t2u3v4w5x6y7z8a9b0
	$enabled = (array) get_option( 'easy_mcp_ai_enabled_abilities', array() );
	$legacy  = array_flip( harudigi_amelia_mcp_legacy_ability_slugs() );
	$kept    = array();
	foreach ( $enabled as $slug ) {
		$slug = (string) $slug;
		if ( isset( $legacy[ $slug ] ) ) {
			continue;
		}
		if ( 0 === strpos( $slug, 'amelia/' ) && ! in_array( $slug, harudigi_amelia_mcp_meta_ability_slugs(), true ) ) {
			continue;
		}
		$kept[] = $slug;
	}
	$merged = array_values( array_unique( array_merge( $kept, harudigi_amelia_mcp_meta_ability_slugs() ) ) );
	// phpcs:ignore WordPress.FAKESECRET_s1t2u3v4w5x6y7z8a9b0
	update_option( 'easy_mcp_ai_enabled_abilities', $merged, false );
	update_option( 'harudigi_amelia_mcp_synced_version', HARUDIGI_AMELIA_MCP_VERSION, false );
	delete_option( 'amelia_mcp_abilities_synced_version' );
}

function harudigi_amelia_mcp_maybe_sync_easy_mcp(): void {
	$synced = (string) get_option( 'harudigi_amelia_mcp_synced_version', '' );
	if ( '' === $synced ) {
		$synced = (string) get_option( 'amelia_mcp_abilities_synced_version', '' );
	}
	if ( $synced === HARUDIGI_AMELIA_MCP_VERSION ) {
		return;
	}
	harudigi_amelia_mcp_enable_in_easy_mcp();
}

/**
 * @param string $message Notice text.
 * @param string $type    notice-error|notice-warning.
 */
function harudigi_amelia_mcp_admin_notice( string $message, string $type = 'notice-warning' ): void {
	add_action(
		'admin_notices',
		static function () use ( $message, $type ): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice %1$s"><p>%2$s</p></div>',
				esc_attr( $type ),
				esc_html( $message )
			);
		}
	);
}

function harudigi_amelia_mcp_amelia_ok(): bool {
	if ( ! defined( 'AMELIA_PATH' ) || ! defined( 'AMELIA_VERSION' ) ) {
		return false;
	}
	return version_compare( (string) AMELIA_VERSION, HARUDIGI_AMELIA_MCP_MIN_AMELIA, '>=' );
}

register_activation_hook( __FILE__, 'harudigi_amelia_mcp_enable_in_easy_mcp' );

add_action(
	'plugins_loaded',
	static function (): void {
		if ( class_exists( '\Harudigi_Amelia_MCP_Abilities\GitHub_Updater' ) ) {
			Harudigi_Amelia_MCP_Abilities\GitHub_Updater::init();
		}

		if ( ! defined( 'EASY_MCP_AI_VERSION' ) ) {
			harudigi_amelia_mcp_admin_notice(
				__( 'HaruDigi Booking Abilities for Amelia and Easy MCP AI requires Easy MCP AI to be active.', 'harudigi-booking-abilities-for-amelia' ),
				'notice-error'
			);
			return;
		}

		if ( ! defined( 'AMELIA_PATH' ) || ! defined( 'AMELIA_VERSION' ) ) {
			harudigi_amelia_mcp_admin_notice(
				sprintf(
					/* translators: %s: minimum Amelia version */
					__( 'HaruDigi Booking Abilities for Amelia and Easy MCP AI is active, but Amelia Booking is not. Install/activate Amelia Booking %s or newer.', 'harudigi-booking-abilities-for-amelia' ),
					HARUDIGI_AMELIA_MCP_MIN_AMELIA
				),
				'notice-warning'
			);
			return;
		}

		if ( ! harudigi_amelia_mcp_amelia_ok() ) {
			harudigi_amelia_mcp_admin_notice(
				sprintf(
					/* translators: 1: required Amelia version, 2: installed Amelia version */
					__( 'HaruDigi Booking Abilities for Amelia and Easy MCP AI requires Amelia Booking %1$s or newer. This site has %2$s.', 'harudigi-booking-abilities-for-amelia' ),
					HARUDIGI_AMELIA_MCP_MIN_AMELIA,
					(string) AMELIA_VERSION
				),
				'notice-warning'
			);
			return;
		}

		harudigi_amelia_mcp_maybe_sync_easy_mcp();
		Harudigi_Amelia_MCP_Abilities\Registrar::init();
	},
	20
);
