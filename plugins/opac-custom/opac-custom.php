<?php
/**
 * Plugin Name: OPAC Custom
 * Plugin URI: https://opacplerin.fr
 * Description: Plugin métier pour le site OPAC Plérin. Déclare les CPTs (ateliers, stages, événements, équipe, inscriptions, galerie), les taxonomies, les meta fields, et les interfaces admin custom. Indépendant du thème - porte les données et la logique métier.
 * Version: 0.2.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: DNSZLSK
 * Author URI: https://kewin.io
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

define( 'OPAC_CUSTOM_VERSION', '0.2.0' );
define( 'OPAC_CUSTOM_FILE', __FILE__ );
define( 'OPAC_CUSTOM_PATH', plugin_dir_path( __FILE__ ) );
define( 'OPAC_CUSTOM_URL', plugin_dir_url( __FILE__ ) );

require_once OPAC_CUSTOM_PATH . 'includes/class-opac-cpts.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-taxonomies.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-calendar.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-labels.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-meta.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-admin.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-bindings.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-gallery.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-blocks.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-contact.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-inscriptions.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-seo.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-redirects.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-pwa.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-security.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-settings.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-meta-boxes.php';
require_once OPAC_CUSTOM_PATH . 'includes/class-opac-rgpd.php';

add_action( 'init', [ 'OPAC_CPTs', 'register' ], 5 );
add_action( 'init', [ 'OPAC_Taxonomies', 'register' ], 6 );
add_action( 'init', [ 'OPAC_Meta', 'register' ], 7 );
add_action( 'init', [ 'OPAC_Bindings', 'register' ], 8 );
add_action( 'init', [ 'OPAC_Blocks', 'register' ], 9 );
add_action( 'init', [ 'OPAC_Blocks', 'maybe_backfill_stage_dates' ], 10 );
add_action( 'init', [ 'OPAC_Gallery', 'maybe_migrate' ], 10 );
OPAC_Contact::register();
OPAC_Inscriptions::register();
OPAC_SEO::register();
OPAC_Redirects::register();
OPAC_PWA::register();
OPAC_Security::register();
OPAC_Settings::register();
OPAC_RGPD::register();
// Hors admin_init : les verrous de pages doivent aussi couvrir les ecritures
// REST (editeur de site du theme FSE), qui ne passent pas par admin_init.
OPAC_Admin::boot_page_guards();
add_action( 'admin_init', [ 'OPAC_Taxonomies', 'maybe_upgrade' ] );
add_action( 'admin_init', [ 'OPAC_Admin', 'boot' ] );
add_action( 'admin_init', [ 'OPAC_Meta_Boxes', 'boot' ] );
add_action( 'wp_dashboard_setup', [ 'OPAC_Admin', 'register_dashboard_widget' ] );

// Recalcule l'index apres les deux sauvegardes de metas, hookees en priorite 10.
add_action( 'save_post_opac_stage', static function ( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    OPAC_Blocks::store_stage_end_date( $post_id );
}, 20, 1 );

register_activation_hook( __FILE__, static function () {
    OPAC_CPTs::register();
    OPAC_Taxonomies::register();
    OPAC_Taxonomies::seed_default_terms();
    update_option( 'opac_tax_db_version', OPAC_Taxonomies::DB_VERSION );
    OPAC_Blocks::maybe_backfill_stage_dates();
    OPAC_Gallery::maybe_migrate();
    OPAC_Settings::ensure_legal_pages();
    OPAC_RGPD::maybe_schedule();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function () {
    OPAC_RGPD::unschedule();
    flush_rewrite_rules();
} );

add_action( 'plugins_loaded', static function () {
    load_plugin_textdomain( 'opac-custom', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/**
 * Ordonne les listes (Query Loop blocks) des CPT metier, que le bloc Query
 * natif ne sait pas trier par meta :
 * - ateliers ephemeres (opac_stage) : actifs d'apres leur date de fin
 *   effective, tries par date de debut du plus proche au plus lointain ;
 * - ateliers a l'annee (opac_atelier) : ordre alphabetique stable (vs ordre
 *   de creation des fiches).
 *
 * query_loop_block_query_vars filtre les args WP_Query de chaque Query Loop,
 * ce qui couvre d'un coup l'archive et les apercus d'accueil sans toucher au
 * markup des templates.
 */
// Pour les ephemeres : filtre sur la fin effective, tri par date de debut.
// Pour les ateliers annuels : ordre alphabetique stable.
add_filter( 'query_loop_block_query_vars', [ 'OPAC_Blocks', 'filter_query_loop_vars' ], 10, 1 );

/**
 * Injecte la classe opac-period-<slug> sur chaque post opac_stage rendu par un
 * Query Loop natif : post-template enveloppe chaque post dans un <li> passe par
 * get_post_class() (cf. wp-includes/blocks/post-template.php). Sans ca, la
 * couleur saisonniere du pave date (.opac-period-* .opac-stage-date) ne
 * s'applique pas sur l'apercu d'accueil (front-page.html, Query Loop natif).
 * L'archive /ephemeres/ pose deja la classe en dur via render_ephemeres_list.
 */
add_filter( 'post_class', static function ( $classes, $css_class, $post_id ) {
    if ( 'opac_stage' !== get_post_type( $post_id ) ) {
        return $classes;
    }
    $periods = wp_get_post_terms( $post_id, 'opac_period', [ 'fields' => 'slugs' ] );
    if ( ! is_wp_error( $periods ) && ! empty( $periods ) ) {
        $classes[] = 'opac-period-' . sanitize_html_class( $periods[0] );
    }
    return $classes;
}, 10, 3 );
