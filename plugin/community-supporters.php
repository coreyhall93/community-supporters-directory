<?php
/**
 * Plugin Name:       Community Supporters Directory
 * Description:        Displays a filterable directory of Community Event & Program Supporters from an Airtable base via a [community_supporters] shortcode.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Corey Hall
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       community-supporters
 *
 * Adapted from Maciej Pilarski's "Credits Program Mentors" plugin
 * (https://github.com/maciejpilarski/credits-program-mentors), reverse-engineered
 * and retargeted from WP Credits mentors to Community Event & Program Supporters.
 * The Airtable client, caching, avatar, map, and rendering mechanics are unchanged;
 * see FUTURE_COREY.md for what was renamed vs. what still needs real data.
 *
 * @package CommunitySupportersDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'COMSUP_VERSION', '1.0.0' );
define( 'COMSUP_PLUGIN_FILE', __FILE__ );
define( 'COMSUP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COMSUP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once COMSUP_PLUGIN_DIR . 'includes/class-comsup-airtable-client.php';
require_once COMSUP_PLUGIN_DIR . 'includes/class-comsup-avatars.php';
require_once COMSUP_PLUGIN_DIR . 'includes/class-comsup-map.php';
require_once COMSUP_PLUGIN_DIR . 'includes/class-comsup-settings.php';
require_once COMSUP_PLUGIN_DIR . 'includes/class-comsup-shortcode.php';
require_once COMSUP_PLUGIN_DIR . 'includes/class-comsup-block.php';

/**
 * Boot the plugin.
 */
function comsup_bootstrap() {
	new COMSUP_Settings();
	$shortcode = new COMSUP_Shortcode();
	new COMSUP_Block( $shortcode );
}
add_action( 'plugins_loaded', 'comsup_bootstrap' );

// Translations for WordPress.org-hosted plugins are loaded automatically since
// WordPress 4.6, so no load_plugin_textdomain() call is needed here.
