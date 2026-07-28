<?php
/**
 * Native WordPress updates from public GitHub Releases (Update URI + update_plugins_github.com).
 *
 * When this plugin is later hosted on WordPress.org, remove or gate this class so
 * wordpress.org remains the sole update source.
 *
 * @package Harudigi_Amelia_MCP_Abilities
 */

namespace Harudigi_Amelia_MCP_Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GitHub_Updater {

	const REPO   = 'smvueno/harudigi-amelia-mcp-abilities';
	const HOST   = 'github.com';
	const CACHE  = 'harudigi_amelia_mcp_gh_release';
	const TTL    = HOUR_IN_SECONDS * 6;

	public static function init(): void {
		// Bail if Update URI points at wordpress.org (future directory listing).
		$update_uri = (string) ( get_file_data( HARUDIGI_AMELIA_MCP_FILE, array( 'UpdateURI' => 'Update URI' ), 'plugin' )['UpdateURI'] ?? '' );
		if ( $update_uri && false !== stripos( $update_uri, 'wordpress.org' ) ) {
			return;
		}

		add_filter( 'update_plugins_' . self::HOST, array( __CLASS__, 'check' ), 10, 4 );
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
		if ( plugin_basename( HARUDIGI_AMELIA_MCP_FILE ) !== $plugin_file ) {
			return $update;
		}

		$release = self::latest_release();
		if ( ! $release ) {
			return $update;
		}

		$new_version = ltrim( (string) ( $release['tag_name'] ?? '' ), 'vV' );
		if ( '' === $new_version || ! version_compare( $new_version, HARUDIGI_AMELIA_MCP_VERSION, '>' ) ) {
			return $update;
		}

		$package = self::zip_url( $release );
		if ( ! $package ) {
			return $update;
		}

		return array(
			'slug'    => 'harudigi-amelia-mcp-abilities',
			'version' => $new_version,
			'url'     => 'https://github.com/' . self::REPO,
			'package' => $package,
		);
	}

	/**
	 * GitHub source zips extract as repo-tag/; ensure folder matches plugin slug.
	 *
	 * @param string      $source        Extracted source path.
	 * @param string      $remote_source Remote source root.
	 * @param \WP_Upgrader $upgrader     Upgrader instance.
	 * @param array       $hook_extra    Hook context.
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

		$desired = trailingslashit( $remote_source ) . 'harudigi-amelia-mcp-abilities';
		$source  = untrailingslashit( $source );

		if ( $source === $desired || basename( $source ) === 'harudigi-amelia-mcp-abilities' ) {
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
			set_transient( self::CACHE, array(), 15 * MINUTE_IN_SECONDS );
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			set_transient( self::CACHE, array(), 15 * MINUTE_IN_SECONDS );
			return null;
		}

		set_transient( self::CACHE, $data, self::TTL );
		return $data;
	}

	/**
	 * Prefer attached release asset zip; fall back to GitHub source archive.
	 *
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
