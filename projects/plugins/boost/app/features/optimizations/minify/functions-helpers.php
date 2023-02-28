<?php

use Automattic\Jetpack_Boost\Features\Optimizations\Minify\Config;
use Automattic\Jetpack_Boost\Features\Optimizations\Minify\Dependency_Path_Mapping;

// @todo - refactor this. Dump of functions from page optimize.

function jetpack_boost_page_optimize_cache_cleanup( $cache_folder = false, $file_age = DAY_IN_SECONDS ) {
	if ( ! is_dir( $cache_folder ) ) {
		return;
	}

	$defined_cache_dir = Config::get_cache_dir_path();

	// If cache is disabled when the cleanup runs, purge it
	$using_cache = ! empty( $defined_cache_dir );
	if ( ! $using_cache ) {
		$file_age = 0;
	}
	// If the cache folder changed since queueing, purge it
	if ( $using_cache && $cache_folder !== $defined_cache_dir ) {
		$file_age = 0;
	}

	// Grab all files in the cache directory
	$cache_files = glob( $cache_folder . '/page-optimize-cache-*' );

	// Cleanup all files older than $file_age
	foreach ( $cache_files as $cache_file ) {
		if ( ! is_file( $cache_file ) ) {
			continue;
		}

		if ( ( time() - $file_age ) > filemtime( $cache_file ) ) {
			unlink( $cache_file );
		}
	}
}

// Unschedule cache cleanup, and purge cache directory
function jetpack_boost_page_optimize_deactivate() {
	$cache_folder = Config::get_cache_dir_path();

	jetpack_boost_page_optimize_cache_cleanup( $cache_folder, 0 /* max file age in seconds */ );

	wp_clear_scheduled_hook( Config::get_cron_cache_cleanup_hook(), [ $cache_folder ] );
}

function jetpack_boost_page_optimize_uninstall() {
	// Run cleanup on uninstall. You can uninstall an active plugin w/o deactivation.
	jetpack_boost_page_optimize_deactivate();

	// JS
	delete_option( 'page_optimize-js' );
	delete_option( 'page_optimize-load-mode' );
	delete_option( 'page_optimize-js-exclude' );
	// CSS
	delete_option( 'page_optimize-css' );
	delete_option( 'page_optimize-css-exclude' );

}

function jetpack_boost_page_optimize_get_text_domain() {
	return 'page-optimize';
}

function jetpack_boost_page_optimize_should_concat_js() {
	// Support query param for easy testing
	if ( isset( $_GET['concat-js'] ) ) {
		return $_GET['concat-js'] !== '0';
	}

	return (bool) get_option( 'page_optimize-js', jetpack_boost_page_optimize_js_default() );
}

// TODO: Support JS load mode regardless of whether concat is enabled
function jetpack_boost_page_optimize_load_mode_js() {
	// Support query param for easy testing
	if ( ! empty( $_GET['load-mode-js'] ) ) {
		$load_mode = jetpack_boost_page_optimize_sanitize_js_load_mode( $_GET['load-mode-js'] );
	} else {
		$load_mode = jetpack_boost_page_optimize_sanitize_js_load_mode( get_option( 'page_optimize-load-mode', jetpack_boost_page_optimize_js_load_mode_default() ) );
	}

	return $load_mode;
}

function jetpack_boost_page_optimize_should_concat_css() {
	// Support query param for easy testing
	if ( isset( $_GET['concat-css'] ) ) {
		return $_GET['concat-css'] !== '0';
	}

	return (bool) get_option( 'page_optimize-css', jetpack_boost_page_optimize_css_default() );
}

function jetpack_boost_page_optimize_js_default() {
	return true;
}

function jetpack_boost_page_optimize_css_default() {
	return true;
}

function jetpack_boost_page_optimize_js_load_mode_default() {
	return '';
}

function jetpack_boost_page_optimize_js_exclude_list() {
	$exclude_list = get_option( 'page_optimize-js-exclude' );
	if ( false === $exclude_list ) {
		// Use the default since the option is not set
		return jetpack_boost_page_optimize_js_exclude_list_default();
	}
	if ( '' === $exclude_list ) {
		return array();
	}

	return explode( ',', $exclude_list );
}

function jetpack_boost_page_optimize_js_exclude_list_default() {
	// WordPress core stuff, a lot of other plugins depend on it.
	return array( 'jquery', 'jquery-core', 'underscore', 'backbone' );
}

function jetpack_boost_page_optimize_css_exclude_list() {
	$exclude_list = get_option( 'page_optimize-css-exclude' );
	if ( false === $exclude_list ) {
		// Use the default since the option is not set
		return jetpack_boost_page_optimize_css_exclude_list_default();
	}
	if ( '' === $exclude_list ) {
		return array();
	}

	return explode( ',', $exclude_list );
}

function jetpack_boost_page_optimize_css_exclude_list_default() {
	// WordPress core stuff
	return array( 'admin-bar', 'dashicons' );
}

function jetpack_boost_page_optimize_sanitize_js_load_mode( $value ) {
	switch ( $value ) {
		case 'async':
		case 'defer':
			break;
		default:
			$value = '';
			break;
	}

	return $value;
}

function jetpack_boost_page_optimize_sanitize_exclude_field( $value ) {
	if ( empty( $value ) ) {
		return '';
	}

	$excluded_strings = explode( ',', sanitize_text_field( $value ) );
	$sanitized_values = array();
	foreach ( $excluded_strings as $excluded_string ) {
		if ( ! empty( $excluded_string ) ) {
			$sanitized_values[] = trim( $excluded_string );
		}
	}

	return implode( ',', $sanitized_values );
}

/**
 * Determines whether a string starts with another string.
 */
function jetpack_boost_page_optimize_starts_with( $prefix, $str ) {
	$prefix_length = strlen( $prefix );
	if ( strlen( $str ) < $prefix_length ) {
		return false;
	}

	return substr( $str, 0, $prefix_length ) === $prefix;
}

/**
 * Answers whether the plugin should provide concat resource URIs
 * that are relative to a common ancestor directory. Assuming a common ancestor
 * allows us to skip resolving resource URIs to filesystem paths later on.
 */
function jetpack_boost_page_optimize_use_concat_base_dir() {
	return defined( 'PAGE_OPTIMIZE_CONCAT_BASE_DIR' ) && file_exists( PAGE_OPTIMIZE_CONCAT_BASE_DIR );
}

/**
 * Get a filesystem path relative to a configured base path for resources
 * that will be concatenated. Assuming a common ancestor allows us to skip
 * resolving resource URIs to filesystem paths later on.
 */
function jetpack_boost_page_optimize_remove_concat_base_prefix( $original_fs_path ) {
	$abspath = Config::get_abspath();

	// Always check longer path first
	if ( strlen( $abspath ) > strlen( PAGE_OPTIMIZE_CONCAT_BASE_DIR ) ) {
		$longer_path  = $abspath;
		$shorter_path = PAGE_OPTIMIZE_CONCAT_BASE_DIR;
	} else {
		$longer_path  = PAGE_OPTIMIZE_CONCAT_BASE_DIR;
		$shorter_path = $abspath;
	}

	$prefix_abspath = trailingslashit( $longer_path );
	if ( jetpack_boost_page_optimize_starts_with( $prefix_abspath, $original_fs_path ) ) {
		return substr( $original_fs_path, strlen( $prefix_abspath ) );
	}

	$prefix_basedir = trailingslashit( $shorter_path );
	if ( jetpack_boost_page_optimize_starts_with( $prefix_basedir, $original_fs_path ) ) {
		return substr( $original_fs_path, strlen( $prefix_basedir ) );
	}

	// If we end up here, this is a resource we shouldn't have tried to concat in the first place
	return '/page-optimize-resource-outside-base-path/' . basename( $original_fs_path );
}

function jetpack_boost_page_optimize_schedule_cache_cleanup() {
	$cache_folder = Config::get_cache_dir_path();
	$args = array( $cache_folder );

	$cache_cleanup_hook = Config::get_cron_cache_cleanup_hook();

	// If caching is on, and job isn't queued for current cache folder
	if ( false !== $cache_folder && false === wp_next_scheduled( $cache_cleanup_hook, $args ) ) {
		wp_schedule_event( time(), 'daily', $cache_cleanup_hook, $args );
	}
}

// Cases when we don't want to concat
function jetpack_boost_page_optimize_bail() {
	// Bail if we're in customizer
	global $wp_customize;
	if ( isset( $wp_customize ) ) {
		return true;
	}

	// Bail if Divi theme is active, and we're in the Divi Front End Builder
	if ( ! empty( $_GET['et_fb'] ) && 'Divi' === wp_get_theme()->get_template() ) {
		return true;
	}

	// Bail if we're editing pages in Brizy Editor
	if ( class_exists( 'Brizy_Editor' ) && method_exists( 'Brizy_Editor', 'prefix' ) && ( isset( $_GET[ Brizy_Editor::prefix( '-edit-iframe' ) ] ) || isset( $_GET[ Brizy_Editor::prefix( '-edit' ) ] ) ) ) {
		return true;
	}

	return false;
}

// Taken from utils.php/Jetpack_Boost_Page_Optimize_Utils
function jetpack_boost_page_optimize_cache_bust_mtime( $path, $siteurl ) {
	static $dependency_path_mapping;

	$url = $siteurl . $path;

	if ( strpos( $url, '?m=' ) ) {
		return $url;
	}

	$parts = parse_url( $url );
	if ( ! isset( $parts['path'] ) || empty( $parts['path'] ) ) {
		return $url;
	}

	if ( empty( $dependency_path_mapping ) ) {
		$dependency_path_mapping = new Dependency_Path_Mapping();
	}

	$file = $dependency_path_mapping->dependency_src_to_fs_path( $url );

	$mtime = false;
	if ( file_exists( $file ) ) {
		$mtime = filemtime( $file );
	}

	if ( ! $mtime ) {
		return $url;
	}

	if ( false === strpos( $url, '?' ) ) {
		$q = '';
	} else {
		list( $url, $q ) = explode( '?', $url, 2 );
		if ( strlen( $q ) ) {
			$q = '&amp;' . $q;
		}
	}

	return "$url?m={$mtime}{$q}";
}