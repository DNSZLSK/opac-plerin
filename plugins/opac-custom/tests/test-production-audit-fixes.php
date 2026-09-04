<?php
/**
 * Harnais des corrections issues de l'audit de production : endpoints PWA,
 * sitemap et directives robots des contenus non editoriaux.
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['rewrite_rules'] = [];
$GLOBALS['is_page']       = false;
$GLOBALS['is_author']     = false;
$GLOBALS['singular_type'] = '';

function add_rewrite_rule( $regex, $query, $position ) {
    $GLOBALS['rewrite_rules'][] = [ $regex, $query, $position ];
}
function wp_parse_url( $url, $component = -1 ) {
    return parse_url( $url, $component );
}
function is_page( $slug = '' ) {
    return $GLOBALS['is_page'] === $slug;
}
function is_author() {
    return $GLOBALS['is_author'];
}
function is_singular( $type = '' ) {
    return $GLOBALS['singular_type'] === $type;
}

require_once dirname( __DIR__ ) . '/includes/class-opac-pwa.php';
require_once dirname( __DIR__ ) . '/includes/class-opac-seo.php';

$n    = 0;
$fail = 0;
function check( $label, $got, $expected ) {
    global $n, $fail;
    ++$n;
    $ok = $got === $expected;
    if ( ! $ok ) {
        ++$fail;
    }
    printf(
        "%2d  %-4s  %-58s obtenu=%s attendu=%s\n",
        $n,
        $ok ? 'OK' : 'FAIL',
        $label,
        var_export( $got, true ),
        var_export( $expected, true )
    );
}

echo "=== A. Endpoints PWA sans redirection canonique ===\n";
OPAC_PWA::add_rewrite_rules();
check( 'deux regles enregistrees', count( $GLOBALS['rewrite_rules'] ), 2 );
check( 'service worker accepte le slash optionnel', $GLOBALS['rewrite_rules'][0][0], '^opac-sw.js/?$' );
check( 'manifest accepte le slash optionnel', $GLOBALS['rewrite_rules'][1][0], '^opac-manifest.webmanifest/?$' );

$_SERVER['REQUEST_URI'] = '/opac-manifest.webmanifest';
check( 'manifest sans slash : pas de canonical', OPAC_PWA::disable_endpoint_canonical_redirect( 'https://example.test/manifest/', '' ), false );
$_SERVER['REQUEST_URI'] = '/opac-manifest.webmanifest/';
check( 'manifest avec slash : pas de canonical', OPAC_PWA::disable_endpoint_canonical_redirect( 'https://example.test/manifest', '' ), false );
$_SERVER['REQUEST_URI'] = '/une-page/';
check( 'page ordinaire : canonical conserve', OPAC_PWA::disable_endpoint_canonical_redirect( 'https://example.test/une-page/', '' ), 'https://example.test/une-page/' );

echo "\n=== B. Sitemap public ===\n";
$types = OPAC_SEO::filter_sitemap_post_types( [
    'post'              => 'post-provider',
    'page'              => 'page-provider',
    'opac_event'        => 'event-provider',
    'opac_person'       => 'person-provider',
    'opac_gallery_item' => 'gallery-provider',
] );
check( 'articles standards exclus', isset( $types['post'] ), false );
check( 'pages conservees', $types['page'], 'page-provider' );
check( 'agenda conserve', $types['opac_event'], 'event-provider' );
check( 'provider utilisateurs exclu', OPAC_SEO::filter_sitemap_provider( 'users-provider', 'users' ), false );
check( 'autre provider conserve', OPAC_SEO::filter_sitemap_provider( 'posts-provider', 'posts' ), 'posts-provider' );

$taxonomies = OPAC_SEO::filter_sitemap_taxonomies( [
    'category'       => 'category-provider',
    'post_tag'       => 'tag-provider',
    'opac_period'    => 'period-provider',
    'opac_event_cat' => 'event-category-provider',
] );
check( 'categories standards exclues', isset( $taxonomies['category'] ), false );
check( 'etiquettes standards exclues', isset( $taxonomies['post_tag'] ), false );
check( 'periodes OPAC conservees', $taxonomies['opac_period'], 'period-provider' );
check( 'categories agenda conservees', $taxonomies['opac_event_cat'], 'event-category-provider' );

echo "\n=== C. Robots ===\n";
$base = [ 'index' => true, 'max-image-preview' => 'large' ];
$GLOBALS['is_page'] = 'inscription';
$robots = OPAC_SEO::filter_robots( $base );
check( 'inscription en noindex', $robots['noindex'], true );
check( 'directive index contradictoire retiree', isset( $robots['index'] ), false );

$GLOBALS['is_page']   = false;
$GLOBALS['is_author'] = true;
$robots = OPAC_SEO::filter_robots( $base );
check( 'archive auteur en noindex', $robots['noindex'], true );

$GLOBALS['is_author']     = false;
$GLOBALS['singular_type'] = 'post';
$robots = OPAC_SEO::filter_robots( $base );
check( 'article standard en noindex', $robots['noindex'], true );

$GLOBALS['singular_type'] = 'opac_event';
$robots = OPAC_SEO::filter_robots( $base );
check( 'evenement reste indexable', isset( $robots['noindex'] ), false );

echo "\nRESULTAT : $n cas, " . ( $n - $fail ) . " OK, $fail FAIL\n";
exit( 0 === $fail ? 0 : 1 );
