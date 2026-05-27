<?php
/**
 * OPAC Custom - Security hardening
 *
 * Durcissement WP avant mise en prod :
 * - Security headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy,
 *   Permissions-Policy, HSTS conditionnel si SSL)
 * - CSP minimale (avec 'unsafe-inline' pour script+style car WP/Gutenberg
 *   injecte beaucoup d'inline ; tightening avec nonces possible en M10)
 * - Masquage version WP (meta generator + ?ver= sur enqueues)
 * - Desactivation XMLRPC (vecteur brute-force standard)
 * - Filter REST API : opac_inscription deja non expose (show_in_rest=false)
 *
 * Constants additionnelles a mettre dans wp-config.php (manuel) :
 * - DISALLOW_FILE_EDIT = true (bloque editeur PHP dans /wp-admin)
 * - FORCE_SSL_ADMIN = true (admin uniquement en HTTPS)
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Security {

    public static function register() {
        // HTTP security headers via filter wp_headers.
        add_filter( 'wp_headers', [ __CLASS__, 'add_security_headers' ] );

        // Masque la version WP partout (meta generator + ?ver= sur assets).
        remove_action( 'wp_head', 'wp_generator' );
        add_filter( 'the_generator', '__return_empty_string' );
        add_filter( 'style_loader_src', [ __CLASS__, 'strip_ver_param' ], 9999 );
        add_filter( 'script_loader_src', [ __CLASS__, 'strip_ver_param' ], 9999 );

        // Desactive XMLRPC (brute-force attack surface).
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'wp_headers', [ __CLASS__, 'remove_xmlrpc_header' ] );
        // Remove pingback header injection.
        add_filter( 'pings_open', '__return_false' );
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );

        // Limite la divulgation d'info dans l'API REST (users endpoint).
        add_filter( 'rest_endpoints', [ __CLASS__, 'restrict_rest_users' ] );
    }

    public static function add_security_headers( $headers ) {
        $headers['X-Frame-Options']        = 'SAMEORIGIN';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
        $headers['Permissions-Policy']     = 'geolocation=(), microphone=(), camera=(), payment=()';

        // HSTS uniquement en HTTPS (sinon casse l'env local en HTTP).
        if ( is_ssl() ) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        // CSP : minimum viable pour ne rien casser. 'unsafe-inline' tolere
        // pour script + style car Gutenberg injecte beaucoup d'inline.
        // En M10, tightening possible avec nonces si requis par audit.
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https://www.openstreetmap.org https://*.tile.openstreetmap.org",
            "frame-src https://www.openstreetmap.org",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];
        $headers['Content-Security-Policy'] = implode( '; ', $csp );

        return $headers;
    }

    /**
     * Supprime le query arg ?ver=X.Y de tous les enqueues (entropie crawler
     * + obfuscation version WP/plugin). Le cache-busting fonctionne deja via
     * filemtime() dans le theme (opac_asset_version) et via la version
     * du plugin pour les assets opac-custom.
     */
    public static function strip_ver_param( $src ) {
        if ( strpos( $src, 'ver=' ) === false ) {
            return $src;
        }
        return remove_query_arg( 'ver', $src );
    }

    public static function remove_xmlrpc_header( $headers ) {
        unset( $headers['X-Pingback'] );
        return $headers;
    }

    /**
     * Bloque l'endpoint /wp-json/wp/v2/users qui leakait les noms d'utilisateur
     * admin. Endpoint reste accessible pour les utilisateurs logges (utile pour
     * Site Editor) mais 401/403 pour les anonymes.
     */
    public static function restrict_rest_users( $endpoints ) {
        if ( isset( $endpoints['/wp/v2/users'] ) ) {
            foreach ( $endpoints['/wp/v2/users'] as $i => $endpoint ) {
                if ( isset( $endpoint['methods'] ) && in_array( 'GET', (array) $endpoint['methods'], true ) ) {
                    $endpoints['/wp/v2/users'][ $i ]['permission_callback'] = static function () {
                        return current_user_can( 'list_users' );
                    };
                }
            }
        }
        if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
            foreach ( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] as $i => $endpoint ) {
                if ( isset( $endpoint['methods'] ) && in_array( 'GET', (array) $endpoint['methods'], true ) ) {
                    $endpoints['/wp/v2/users/(?P<id>[\d]+)'][ $i ]['permission_callback'] = static function () {
                        return current_user_can( 'list_users' );
                    };
                }
            }
        }
        return $endpoints;
    }
}
