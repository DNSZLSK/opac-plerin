<?php
/**
 * Plugin Name: OPAC Custom
 * Plugin URI: https://opacplerin.fr
 * Description: Plugin métier pour le site OPAC Plérin. Déclare les CPTs (ateliers, stages, événements, équipe, inscriptions, galerie), les taxonomies, les meta fields, et les interfaces admin custom. Indépendant du thème - porte les données et la logique métier.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: DNSZLSK
 * Author URI: https://opacplerin.fr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: opac-custom
 * Domain Path: /languages
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'OPAC_CUSTOM_VERSION', '0.1.0' );
define( 'OPAC_CUSTOM_FILE', __FILE__ );
define( 'OPAC_CUSTOM_PATH', plugin_dir_path( __FILE__ ) );
define( 'OPAC_CUSTOM_URL', plugin_dir_url( __FILE__ ) );

require_once OPAC_CUSTOM_PATH . 'includes/class-opac-cpts.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-taxonomies.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-meta.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-admin.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-bindings.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-blocks.php';

add_action( 'init', [ 'OPAC_CPTs', 'register' ], 5 );
add_action( 'init', [ 'OPAC_Taxonomies', 'register' ], 6 );
add_action( 'init', [ 'OPAC_Meta', 'register' ], 7 );
add_action( 'init', [ 'OPAC_Bindings', 'register' ], 8 );
add_action( 'init', [ 'OPAC_Blocks', 'register' ], 9 );
add_action( 'admin_init', [ 'OPAC_Admin', 'boot' ] );
add_action( 'wp_dashboard_setup', [ 'OPAC_Admin', 'register_dashboard_widget' ] );

register_activation_hook( __FILE__, static function () {
    OPAC_CPTs::register();
    OPAC_Taxonomies::register();
    OPAC_Taxonomies::seed_default_terms();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function () {
    flush_rewrite_rules();
} );

add_action( 'plugins_loaded', static function () {
    load_plugin_textdomain( 'opac-custom', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );
