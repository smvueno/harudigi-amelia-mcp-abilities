<?php
/**
 * Plugin Name:       Haru Digi Amelia MCP Abilities
 * Plugin URI:        https://smvueno.github.io/harudigi-amelia-mcp-abilities/
 * Description:       Extends Amelia Booking MCP abilities with full admin control for Easy MCP AI (catalog, bookings, payments, settings). Built by Haru Digi for SMBs that want AI-ready WordPress operations.
 * Version:           1.5.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  ameliabooking
 * Author:            Jens Madsen · Haru Digi
 * Author URI:        https://harudigi.com
 * Update URI:        https://github.com/smvueno/harudigi-amelia-mcp-abilities
 * Text Domain:       harudigi-amelia-mcp-abilities
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Haru Digi (“Haru” = new beginning / spring, “Digi” = digital) helps SMBs get a proper
 * website, stronger reach, automations, and trust with clients and AI engines.
 *
 * Amelia Pro already registers: list-services/employees/customers/events/appointments,
 * check-availability, add-service/customer, create-appointment/event, book-event, cancel-booking.
 * This plugin fills the gaps. Secrets stay redacted. Destructive deletes need confirm=true.
 * Password/externalId writes are blocked.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HARUDIGI_AMELIA_MCP_VERSION', '1.5.0' );
define( 'HARUDIGI_AMELIA_MCP_FILE', __FILE__ );
define( 'HARUDIGI_AMELIA_MCP_DIR', plugin_dir_path( __FILE__ ) );

require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/class-helpers.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/class-registrar.php';
require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/class-github-updater.php';

/**
 * Abilities shipped by Amelia core (do not re-register; enable for Easy MCP).
 *
 * @return string[]
 */
function amelia_native_ability_slugs(): array {
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
 * Gap-filler abilities registered by this plugin.
 *
 * @return string[]
 */
function amelia_mcp_abilities_slugs(): array {
	return array(
		// Discover / ops.
		'amelia/get-status',
		'amelia/list-api-surface',
		'amelia/get-stats',
		'amelia/get-entities',
		'amelia/get-settings-summary',
		// Catalog reads.
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
		// Booking / people reads.
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
		// Catalog writes.
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
		// Booking / people writes.
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
	);
}

/**
 * @return string[]
 */
function amelia_all_easy_mcp_slugs(): array {
	return array_values( array_unique( array_merge( amelia_native_ability_slugs(), amelia_mcp_abilities_slugs() ) ) );
}

/**
 * Merge abilities into Easy MCP enable list (additive only — never removes admin disables).
 */
function amelia_mcp_enable_in_easy_mcp(): void {
	$enabled = (array) get_option( 'easy_mcp_ai_enabled_abilities', array() );
	$merged  = array_values( array_unique( array_merge( $enabled, amelia_all_easy_mcp_slugs() ) ) );
	update_option( 'easy_mcp_ai_enabled_abilities', $merged, false );
	update_option( 'harudigi_amelia_mcp_synced_version', HARUDIGI_AMELIA_MCP_VERSION, false );
	delete_option( 'amelia_mcp_abilities_synced_version' );
}

/**
 * Sync enable-list only on activation or when plugin version bumps (new slugs).
 */
function amelia_mcp_maybe_sync_easy_mcp(): void {
	$synced = (string) get_option( 'harudigi_amelia_mcp_synced_version', '' );
	if ( '' === $synced ) {
		$synced = (string) get_option( 'amelia_mcp_abilities_synced_version', '' );
	}
	if ( $synced === HARUDIGI_AMELIA_MCP_VERSION ) {
		return;
	}
	amelia_mcp_enable_in_easy_mcp();
}

register_activation_hook( __FILE__, 'amelia_mcp_enable_in_easy_mcp' );

add_action(
	'plugins_loaded',
	static function (): void {
		Harudigi_Amelia_MCP_Abilities\GitHub_Updater::init();

		if ( ! defined( 'AMELIA_PATH' ) || ! defined( 'AMELIA_VERSION' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-warning"><p>';
					echo esc_html__( 'Haru Digi Amelia MCP Abilities requires Amelia Booking to be active.', 'harudigi-amelia-mcp-abilities' );
					echo '</p></div>';
				}
			);
			return;
		}

		amelia_mcp_maybe_sync_easy_mcp();
		Harudigi_Amelia_MCP_Abilities\Registrar::init();
	},
	20
);
