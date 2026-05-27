<?php
/**
 * OPAC Plérin - theme functions.
 *
 * Le thème reste léger : design tokens dans theme.json, structure dans
 * templates/parts FSE, logique métier exclusivement dans le plugin
 * opac-custom. Cette séparation garantit qu'un changement de thème
 * ne supprime aucune donnée OPAC.
 *
 * @package OPAC\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'OPAC_THEME_VERSION' ) ) {
    define( 'OPAC_THEME_VERSION', '0.2.0' );
}

/**
 * Renvoie la version pour cache-busting d'un asset du thème.
 * En dev (WP_DEBUG ON), utilise filemtime pour invalider à chaque save.
 * En prod, retombe sur OPAC_THEME_VERSION.
 */
function opac_asset_version( $relative_path ) {
    $abs = get_template_directory() . '/' . ltrim( $relative_path, '/' );
    if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && file_exists( $abs ) ) {
        return (string) filemtime( $abs );
    }
    return OPAC_THEME_VERSION;
}

add_action( 'after_setup_theme', static function () {
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
        'navigation-widgets',
    ] );

    load_theme_textdomain( 'opac', get_template_directory() . '/languages' );
} );

add_action( 'wp_enqueue_scripts', static function () {
    wp_enqueue_style(
        'opac-fonts',
        'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400&display=swap',
        [],
        null
    );

    wp_enqueue_style(
        'opac-main',
        get_template_directory_uri() . '/assets/css/opac.css',
        [ 'opac-fonts' ],
        opac_asset_version( 'assets/css/opac.css' )
    );

    wp_enqueue_script(
        'opac-main',
        get_template_directory_uri() . '/assets/js/opac.js',
        [],
        opac_asset_version( 'assets/js/opac.js' ),
        true
    );
} );

add_filter( 'wp_resource_hints', static function ( $urls, $relation_type ) {
    if ( 'preconnect' === $relation_type ) {
        $urls[] = [ 'href' => 'https://fonts.googleapis.com', 'crossorigin' => '' ];
        $urls[] = [ 'href' => 'https://fonts.gstatic.com', 'crossorigin' => '' ];
    }
    return $urls;
}, 10, 2 );

add_action( 'init', static function () {
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
} );

add_action( 'init', static function () {
    if ( function_exists( 'register_block_pattern_category' ) ) {
        register_block_pattern_category(
            'opac',
            [ 'label' => __( 'OPAC Plérin', 'opac' ) ]
        );
    }
} );

// Block styles custom pour les boutons (apparaissent dans le sélecteur
// "Styles" du bloc Bouton dans l'éditeur). CSS dans assets/css/opac.css.
add_action( 'init', static function () {
    if ( ! function_exists( 'register_block_style' ) ) {
        return;
    }
    register_block_style( 'core/button', [
        'name'  => 'opac-primary',
        'label' => __( 'OPAC Primaire', 'opac' ),
    ] );
    register_block_style( 'core/button', [
        'name'  => 'opac-ghost',
        'label' => __( 'OPAC Ghost (sur fond sombre)', 'opac' ),
    ] );
    register_block_style( 'core/button', [
        'name'  => 'opac-on-teal',
        'label' => __( 'OPAC Sur fond teal', 'opac' ),
    ] );
} );

// Shortcode utilitaire pour l'année courante (footer dynamique).
add_shortcode( 'opac_year', static function () {
    return esc_html( wp_date( 'Y' ) );
} );

// Override du séparateur de document title : middle dot à la place
// de l'en dash que WordPress utilise par défaut. Cohérent avec
// le séparateur du footer copyright et avec la règle typographique
// du projet (pas d'em/en dash dans aucun contenu visible).
add_filter( 'document_title_separator', static function () {
    return '·';
} );
