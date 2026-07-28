<?php
/**
 * Registers amelia-ops category and gap-filler abilities.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Registrar {

	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ), 20 );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'patch_native' ), 30 );
	}

	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			'amelia-ops',
			array(
				'label'       => __( 'Amelia Ops', 'mcp-abilities-for-amelia' ),
				'description' => __( 'Admin control beyond Amelia Pro MCP (catalog, payments, settings).', 'mcp-abilities-for-amelia' ),
			)
		);
	}

	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/abilities-discover.php';
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/abilities-catalog.php';
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/abilities-booking.php';
		register_discover_abilities();
		register_catalog_abilities();
		register_booking_abilities();
	}

	public static function patch_native(): void {
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/abilities-native-patch.php';
		patch_native_abilities();
	}
}
