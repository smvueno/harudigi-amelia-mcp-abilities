<?php
/**
 * Registers ≤7 meta abilities for Easy MCP.
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
		// After Amelia Pro registers its natives (default priority 10).
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'suppress_natives' ), 100 );
	}

	public static function suppress_natives(): void {
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/native-suppress.php';
		unregister_amelia_native_abilities();
	}

	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			'amelia-ops',
			array(
				'label'       => __( 'Amelia Ops', 'harudigi-booking-abilities-for-amelia' ),
				'description' => __( 'Compact Amelia admin MCP tools (query, mutate, book, pay).', 'harudigi-booking-abilities-for-amelia' ),
			)
		);
	}

	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/class-entity-map.php';
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/booking-payload.php';
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/payment-payload.php';
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-handlers.php';
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-mutate.php';
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-book.php';
		require_once HARUDIGI_AMELIA_MCP_DIR . 'includes/meta-pay.php';
		register_meta_abilities();
	}
}
