<?php
/**
 * Native WordPress updates from public GitHub Releases.
 *
 * Uses Update URI (update_plugins_github.com) plus a transient fallback so
 * updates still appear if the hostname filter is skipped.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GitHub_Updater {

	const REPO  = 'smvueno/harudigi-amelia-mcp-abilities';
	const HOST  = 'github.com';
	const CACHE = 'harudigi_amelia_mcp_gh_release';
	const TTL   = HOUR_IN_SECONDS * 6;
	const SLUG  = 'harudigi-amelia-mcp-abilities';

	public static function init(): void {
		$update_uri = (string) ( get_file_data( HARUDIGI_AMELIA_MCP_FILE, array( 'UpdateURI' => 'Update URI' ), 'plugin' )['UpdateURI'] ?? '' );
		if ( $update_uri && false !== stripos( $update_uri, 'wordpress.org' ) ) {
			return;
		}

		add_filter( 'update_plugins_' . self::HOST, array( __CLASS__, 'check' ), 10, 4 );
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_transient' ), 20 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
	}

	/**
	 * @param array|false $update      Existing update data.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @param string[]    $locales     Requested locales.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file, $locales ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! empty( $update ) ) {
			return $update;
		}
		if ( plugin_basename( HARUDIGI_AMELIA_MCP_FILE ) !== $plugin_file ) {
			return $update;
		}

		$payload = self::update_payload();
		return $payload ? $payload : $update;
	}

	/**
	 * Fallback: inject into the update transient (covers hosts that skip Update URI).
	 *
	 * @param object|mixed $transient Update transient.
	 * @return object|mixed
	 */
	public static function inject_transient( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		$plugin_file = plugin_basename( HARUDIGI_AMELIA_MCP_FILE );
		$installed   = HARUDIGI_AMELIA_MCP_VERSION;
		if ( function_exists( 'get_plugin_data' ) ) {
			$data = get_plugin_data( HARUDIGI_AMELIA_MCP_FILE, false, false );
			if ( ! empty( $data['Version'] ) ) {
				$installed = (string) $data['Version'];
			}
		}

		$payload = self::update_payload();
		if ( ! $payload ) {
			return $transient;
		}

		$item = (object) array_merge(
			$payload,
			array(
				'id'          => 'https://github.com/' . self::REPO,
				'plugin'      => $plugin_file,
				'new_version' => $payload['version'],
			)
		);

		unset( $transient->response[ $plugin_file ], $transient->no_update[ $plugin_file ] );

		if ( version_compare( $payload['version'], $installed, '>' ) ) {
			$transient->response[ $plugin_file ] = $item;
		} else {
			$transient->no_update[ $plugin_file ] = $item;
		}

		return $transient;
	}

	/**
	 * Always return latest release metadata when GitHub is reachable.
	 * WordPress decides response vs no_update via version_compare.
	 *
	 * @return array<string,string>|false
	 */
	private static function update_payload( string $_installed_version ) {
		$release = self::latest_release();
		if ( ! $release ) {
			return false;
		}

		$new_version = self::normalize_version( (string) ( $release['tag_name'] ?? '' ) );
		if ( '' === $new_version ) {
			return false;
		}

		$package = self::zip_url( $release );
		if ( ! $package ) {
			return false;
		}

		return array(
			'slug'         => self::SLUG,
			'version'      => $new_version,
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $package,
			'tested'       => '6.9',
			'requires_php' => '7.4',
		);
	}

	private static function normalize_version( string $tag ): string {
		$tag = trim( $tag );
		if ( preg_match( '/^v?(\d+\.\d+(?:\.\d+)?(?:[.-].+)?)$/i', $tag, $m ) ) {
			return $m[1];
		}
		return ltrim( $tag, "vV \t" );
	}

	/**
	 * @param string       $source        Extracted source path.
	 * @param string       $remote_source Remote source root.
	 * @param \WP_Upgrader $upgrader      Upgrader instance.
	 * @param array        $hook_extra    Hook context.
	 * @return string|\WP_Error
	 */
	public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || plugin_basename( HARUDIGI_AMELIA_MCP_FILE ) !== $hook_extra['plugin'] ) {
			return $source;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . self::SLUG;
		$source  = untrailingslashit( $source );

		if ( $source === $desired || basename( $source ) === self::SLUG ) {
			return trailingslashit( $source );
		}

		if ( $wp_filesystem->move( $source, $desired ) ) {
			return trailingslashit( $desired );
		}

		return new \WP_Error(
			'harudigi_rename_failed',
			__( 'Could not rename the GitHub plugin folder for install.', 'harudigi-amelia-mcp-abilities' )
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function latest_release(): ?array {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			if ( ! empty( $cached['_failed'] ) ) {
				return null;
			}
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout'    => 15,
				'user-agent' => 'HaruDigi-Amelia-MCP-Abilities/' . HARUDIGI_AMELIA_MCP_VERSION . '; ' . home_url( '/' ),
				'headers'    => array(
					'Accept' => 'application/vnd.github+json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CACHE, array( '_failed' => 1 ), 15 * MINUTE_IN_SECONDS );
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			set_transient( self::CACHE, array( '_failed' => 1 ), 15 * MINUTE_IN_SECONDS );
			return null;
		}

		set_transient( self::CACHE, $data, self::TTL );
		return $data;
	}

	/**
	 * @param array<string,mixed> $release Release payload.
	 */
	private static function zip_url( array $release ): string {
		$assets = isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );
			if ( $url && preg_match( '/harudigi-amelia-mcp-abilities.*\.zip$/i', $name ) ) {
				return $url;
			}
		}

		return (string) ( $release['zipball_url'] ?? '' );
	}
}
