<?php
/**
 * Unregister Amelia Pro native MCP abilities so only HaruDigi meta tools remain.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Amelia core still registers 12 abilities (incl. create-appointment hardcoding approved).
 * When this plugin is active we remove them from the Abilities API so Easy MCP cannot
 * accidentally expose them alongside our meta tools.
 */
function unregister_amelia_native_abilities(): void {
	if ( ! function_exists( 'wp_unregister_ability' ) ) {
		return;
	}
	foreach ( harudigi_amelia_mcp_amelia_native_slugs() as $slug ) {
		if ( function_exists( 'wp_get_ability' ) && ! wp_get_ability( $slug ) ) {
			continue;
		}
		wp_unregister_ability( $slug );
	}
	// Categories may linger empty; only unregister if API supports it.
	if ( function_exists( 'wp_unregister_ability_category' ) ) {
		foreach ( array( 'amelia-read', 'amelia-write' ) as $cat ) {
			wp_unregister_ability_category( $cat );
		}
	}
}
