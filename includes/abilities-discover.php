<?php
/**
 * Discover / ops abilities (status, surface, stats, entities, settings).
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use AmeliaBooking\Application\Controller\Entities\GetEntitiesController;
use AmeliaBooking\Application\Controller\Stats\GetStatsController;

function register_discover_abilities(): void {
	Helpers::register(
		'amelia/get-status',
		array(
			'label'       => __( 'Get Status', 'mcp-abilities-for-amelia' ),
			'description' => __( 'Amelia version, counts, native vs helper abilities.', 'mcp-abilities-for-amelia' ),
			'callback'    => __NAMESPACE__ . '\\ability_get_status',
			'readonly'    => true,
		)
	);
	Helpers::register(
		'amelia/list-api-surface',
		array(
			'label'       => __( 'List API Surface', 'mcp-abilities-for-amelia' ),
			'description' => __( 'Native Amelia MCP vs helper gap fillers.', 'mcp-abilities-for-amelia' ),
			'callback'    => __NAMESPACE__ . '\\ability_list_api_surface',
			'readonly'    => true,
		)
	);
	Helpers::register(
		'amelia/get-stats',
		array(
			'label'        => __( 'Get Stats', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Dashboard stats. Optional date_from/date_to.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_get_stats',
			'readonly'     => true,
			'input_schema' => Helpers::list_schema(
				array(
					'date_from' => array( 'type' => 'string' ),
					'date_to'   => array( 'type' => 'string' ),
				)
			),
		)
	);
	Helpers::register(
		'amelia/get-entities',
		array(
			'label'        => __( 'Get Entities', 'mcp-abilities-for-amelia' ),
			'description'  => __( 'Bulk admin entities dump (large, may include PII). Prefer targeted get-* abilities. Requires confirm=true.', 'mcp-abilities-for-amelia' ),
			'callback'     => __NAMESPACE__ . '\\ability_get_entities',
			'readonly'     => true,
			'input_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'confirm' ),
				'properties'           => array(
					'confirm' => array(
						'type'        => 'boolean',
						'description' => 'Must be true. Confirms intentional bulk dump (large / PII-heavy).',
					),
				),
			),
		)
	);
	Helpers::register(
		'amelia/get-settings-summary',
		array(
			'label'       => __( 'Get Settings Summary', 'mcp-abilities-for-amelia' ),
			'description' => __( 'Non-secret settings. Secrets redacted.', 'mcp-abilities-for-amelia' ),
			'callback'    => __NAMESPACE__ . '\\ability_get_settings_summary',
			'readonly'    => true,
		)
	);
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function ability_get_status( array $input = array() ) {
	unset( $input );
	return Helpers::status_payload();
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function ability_list_api_surface( array $input = array() ) {
	unset( $input );
	$ok = Helpers::require_amelia();
	return is_wp_error( $ok ) ? $ok : Helpers::api_surface();
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function ability_get_stats( array $input = array() ) {
	$params = Helpers::list_params( $input );
	if ( empty( $params['dates'] ) || ! is_array( $params['dates'] ) || 2 !== count( $params['dates'] ) ) {
		$params['dates'] = array(
			wp_date( 'Y-m-d', strtotime( '-30 days' ) ),
			wp_date( 'Y-m-d' ),
		);
	}
	if ( empty( $params['stats'] ) ) {
		$params['stats'] = array( 'approved', 'pending', 'canceled', 'rejected', 'no-show' );
	}
	return Helpers::invoke( GetStatsController::class, $params, array(), 'GET' );
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function ability_get_entities( array $input = array() ) {
	$ok = Helpers::require_confirm( $input );
	if ( is_wp_error( $ok ) ) {
		return new \WP_Error(
			'confirm_required',
			__( 'Bulk get-entities refused. Set confirm=true only when a full dump is required. Prefer get-service / get-customer / list-* with pagination.', 'mcp-abilities-for-amelia' )
		);
	}
	return Helpers::invoke( GetEntitiesController::class, array(), array(), 'GET' );
}

/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
function ability_get_settings_summary( array $input = array() ) {
	unset( $input );
	return Helpers::settings_summary();
}
