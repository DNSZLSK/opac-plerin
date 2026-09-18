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
 * - Anti-enumeration d'utilisateur : REST users verrouille + archives auteur
 *   (?author=N et /author/{login}/) renvoyees en 404 (le login ne fuite plus)
 * - Rate-limit login par IP (transient), anti brute-force sans plugin tiers
 * - Mots de passe d'application desactives (voie REST hors du rate-limit)
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

    const LOGIN_MAX_ATTEMPTS = 10;
    const LOGIN_WINDOW_S     = 900; // 15 minutes

    public static function register() {
        // HTTP security headers via filter wp_headers.
        add_filter( 'wp_headers', [ __CLASS__, 'add_security_headers' ] );

        // RGPD : avatar local par defaut au lieu de Gravatar (Automattic/US).
        // Evite tout transfert d'IP / hash email vers secure.gravatar.com et garde
        // le CSP img-src sans origine tierce (cf. add_security_headers). Le seul
        // Gravatar cote public etait celui de la barre admin des membres connectes.
        add_filter( 'pre_get_avatar_data', [ __CLASS__, 'force_local_avatar' ], 10, 2 );

        // Masque la version WP partout (meta generator + ?ver= sur assets).
        remove_action( 'wp_head', 'wp_generator' );
        add_filter( 'the_generator', '__return_empty_string' );
        add_filter( 'style_loader_src', [ __CLASS__, 'obfuscate_ver_param' ], 9999 );
        add_filter( 'script_loader_src', [ __CLASS__, 'obfuscate_ver_param' ], 9999 );

        // Desactive XMLRPC (brute-force attack surface).
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'wp_headers', [ __CLASS__, 'remove_xmlrpc_header' ] );
        // Remove pingback header injection.
        add_filter( 'pings_open', '__return_false' );
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );

        // Limite la divulgation d'info dans l'API REST (users endpoint).
        add_filter( 'rest_endpoints', [ __CLASS__, 'restrict_rest_users' ] );

        // Bloque l'enumeration d'utilisateur via les archives auteur
        // (?author=N + /author/{login}/), qui divulguent l'identifiant de
        // connexion. Priorite 0 pour preceder redirect_canonical (priorite 10).
        add_action( 'template_redirect', [ __CLASS__, 'block_author_enumeration' ], 0 );

        // Login rate-limit anti-brute-force (transient par IP). Priorite 30,
        // donc APRES les handlers du coeur (priorite 20) : cf. le piege
        // documente sur check_login_attempts(), une WP_Error posee avant eux
        // serait purement et simplement ecrasee.
        add_filter( 'authenticate', [ __CLASS__, 'check_login_attempts' ], 30, 1 );
        add_action( 'wp_login_failed', [ __CLASS__, 'increment_failed_login' ] );
        add_action( 'wp_login', [ __CLASS__, 'reset_login_attempts' ] );

        // Desactive les mots de passe d'application (WP les active par defaut).
        // Ils authentifient via la REST par wp_authenticate_application_password(),
        // qui ne declenche jamais wp_login_failed : ces tentatives echappent donc
        // au compteur ci-dessus, ce qui rouvre une voie de brute-force sans
        // limite. Aucun usage dans le projet (pas de client REST externe), on
        // ferme la porte plutot que de dupliquer le rate-limit dessus.
        add_filter( 'wp_is_application_passwords_available', '__return_false' );

        // Francise le skip link WP (texte par defaut "Skip to the content").
        add_filter( 'gettext', [ __CLASS__, 'translate_skip_link' ], 10, 2 );

        // security.txt (RFC 9116). Servi par le plugin et non par un fichier
        // statique : deploy-demo.ps1 ne pousse que wp-content, un fichier pose
        // a la racine du site ne serait donc jamais deploye et disparaitrait au
        // premier transfert. Priorite 0, comme le blocage d'enumeration : il
        // faut passer avant que WordPress ne decide d'un 404.
        add_action( 'template_redirect', [ __CLASS__, 'serve_security_txt' ], 0 );

        // Block early access aux endpoints sensibles (PHP-level fallback
        // pour les envs sans .htaccess Apache : nginx, Local by Flywheel).
        // En prod Apache OVH, le .htaccess racine prend le relais et bloque
        // avant d'atteindre PHP, plus performant.
        add_action( 'init', [ __CLASS__, 'block_sensitive_endpoints' ], 0 );

        // Force locale fr_FR (A11y : lang="fr" sur <html> pour les lecteurs
        // d'ecran + SEO). WP par defaut peut etre en en_US si site language
        // pas configure. A overrider en /wp-admin > Reglages > General avant
        // prod pour persistance hors plugin.
        add_filter( 'locale', [ __CLASS__, 'force_french_locale' ] );
    }

    public static function force_french_locale( $locale ) {
        if ( $locale === 'en_US' || empty( $locale ) ) {
            return 'fr_FR';
        }
        return $locale;
    }

    /**
     * Sert /.well-known/security.txt (RFC 9116).
     *
     * A quoi ca sert concretement : quand quelqu'un trouve une faille sur le
     * site, il cherche a qui la dire. Sans ce fichier il ecrit au hasard, ou
     * n'ecrit pas. Pour une association qui heberge des donnees d'adherents,
     * apprendre un probleme par son auteur plutot que par ses consequences
     * vaut les quinze lignes que voici.
     *
     * L'adresse de contact est celle des Reglages OPAC (opac_org_email), pas
     * une adresse security@ dediee : l'association n'a pas de boite dediee, et
     * annoncer une adresse que personne ne releve serait pire que rien.
     *
     * Expires est obligatoire dans la RFC et doit rester dans le futur, sinon
     * le fichier est considere perime. Il est donc calcule (un an glissant) et
     * non ecrit en dur : un fichier statique aurait expire sans que personne
     * ne s'en apercoive.
     */
    public static function serve_security_txt() {
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = strtok( $uri, '?' );
        if ( '/.well-known/security.txt' !== rtrim( (string) $path, '/' ) ) {
            return;
        }

        $email = class_exists( 'OPAC_Settings' ) ? (string) OPAC_Settings::get( 'opac_org_email' ) : '';
        if ( ! is_email( $email ) ) {
            $email = 'contact@opacplerin.fr';
        }

        // Fuseau nomme cote WP, mais l'horodatage RFC 9116 s'ecrit en UTC.
        $expires = gmdate( 'Y-m-d\TH:i:s\Z', time() + YEAR_IN_SECONDS );

        $lines = [
            'Contact: mailto:' . $email,
            'Expires: ' . $expires,
            'Preferred-Languages: fr, en',
            'Canonical: ' . home_url( '/.well-known/security.txt' ),
        ];

        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        status_header( 200 );
        echo implode( "\n", $lines ) . "\n";
        exit;
    }

    public static function block_sensitive_endpoints() {
        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }
        $uri  = strtok( (string) $_SERVER['REQUEST_URI'], '?' );
        $path = strtolower( basename( $uri ) );
        $blocked = [ 'xmlrpc.php', 'readme.html', 'license.txt', 'wp-config-sample.php' ];
        if ( in_array( $path, $blocked, true ) ) {
            status_header( 403 );
            nocache_headers();
            exit;
        }
    }

    /**
     * Bloque l'enumeration d'utilisateur via les archives auteur.
     *
     * Deux vecteurs, fermes tous les deux en 404 :
     * - /?author=N : WordPress resout l'auteur puis, via redirect_canonical,
     *   renvoie un 301 vers /author/{login}/ -> l'identifiant de connexion
     *   fuite dans l'URL. On agit en priorite 0 sur template_redirect (donc
     *   avant redirect_canonical, priorite 10) et on retire cette redirection.
     * - /author/{login}/ : l'archive elle-meme affiche le login dans le <title>.
     *   Site associatif a auteur unique : aucune archive auteur n'a d'usage
     *   public -> 404 franc, pas un simple noindex (qui laisse le login lisible).
     *
     * Complementaire de restrict_rest_users() (endpoint REST users) et du
     * noindex auteur pose par OPAC_SEO::filter_robots(). is_admin() est exclu
     * pour ne pas gener le back-office (filtre "par auteur" des listes).
     */
    public static function block_author_enumeration() {
        if ( is_admin() ) {
            return;
        }
        if ( ! isset( $_GET['author'] ) && ! is_author() ) {
            return;
        }

        // Empeche la redirection canonique ?author=N -> /author/{login}/ qui
        // divulguerait l'identifiant avant meme le rendu de la 404.
        remove_action( 'template_redirect', 'redirect_canonical' );

        global $wp_query;
        if ( $wp_query instanceof WP_Query ) {
            $wp_query->set_404();
        }
        status_header( 404 );
        nocache_headers();
    }

    public static function add_security_headers( $headers ) {
        // Masque la version PHP exposee par defaut (expose_php). En prod OVH,
        // doubler avec expose_php = Off dans le php.ini (cf. DEPLOY.md).
        header_remove( 'X-Powered-By' );

        $headers['X-Frame-Options']        = 'SAMEORIGIN';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
        $headers['Permissions-Policy']     = 'geolocation=(), microphone=(), camera=(), payment=()';
        // Isole le contexte de navigation : une page ouverte depuis le site
        // (lien HelloAsso, reseaux sociaux) ne garde pas de reference
        // manipulable vers la fenetre d'origine. Aucun usage de window.opener
        // ici, donc same-origin ne casse rien.
        $headers['Cross-Origin-Opener-Policy'] = 'same-origin';

        // HSTS uniquement en HTTPS (sinon casse l'env local en HTTP).
        if ( is_ssl() ) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        // CSP : minimum viable pour ne rien casser. 'unsafe-inline' tolere
        // pour script + style car Gutenberg injecte beaucoup d'inline.
        // Polices auto-hebergees + carte OpenStreetMap : aucune origine Google
        // (RGPD, pas de transfert d'IP). Le tightening des inline via nonces
        // reste le residuel connu (couteux sous FSE, non fait volontairement).
        // frame-ancestors 'self' double X-Frame-Options (equivalent moderne,
        // couvre les navigateurs qui ignorent l'ancien header).
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            "img-src 'self' data: https://www.openstreetmap.org https://*.tile.openstreetmap.org",
            "frame-src https://www.openstreetmap.org",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
        ];
        // En HTTPS uniquement : auto-upgrade des sous-ressources http:// (defense
        // en profondeur si un lien/media http traine dans le contenu edite), sans
        // casser l'environnement local servi en http.
        if ( is_ssl() ) {
            $csp[] = 'upgrade-insecure-requests';
        }
        $headers['Content-Security-Policy'] = implode( '; ', $csp );

        return $headers;
    }

    /**
     * RGPD : remplace l'avatar Gravatar (requete vers secure.gravatar.com,
     * Automattic/US) par un SVG generique local servi en data: URI (deja autorise
     * par le CSP img-src). Aucune requete tierce, aucun transfert d'IP / hash
     * email. Court-circuite get_avatar_data() en posant directement $args['url'].
     */
    public static function force_local_avatar( $args, $id_or_email ) {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">'
            . '<rect width="96" height="96" fill="#e7e2db"/>'
            . '<circle cx="48" cy="38" r="17" fill="#b7ac9e"/>'
            . '<path d="M16 86c0-16 14-28 32-28s32 12 32 28z" fill="#b7ac9e"/>'
            . '</svg>';
        $args['url']          = 'data:image/svg+xml;base64,' . base64_encode( $svg );
        $args['found_avatar'] = true;
        return $args;
    }

    /**
     * Remplace la VALEUR du query arg ?ver=X.Y par un jeton opaque derive du
     * sel du site (wp_hash). Deux objectifs tenus en meme temps :
     *
     * - Securite : la version exacte de WP / du plugin n'est plus lisible dans
     *   les URLs d'assets (entropie crawler, exploits cibles). C'est
     *   l'intention d'origine, elle est preservee.
     * - Cache-busting : le jeton change des que la version source change
     *   (filemtime via opac_asset_version() pour le theme, numero de version
     *   pour WP et le plugin), donc le navigateur retelecharge le fichier
     *   modifie de lui-meme.
     *
     * L'implementation precedente SUPPRIMAIT le ?ver=. La version etait bien
     * masquee, mais le cache-busting disparaissait avec.
     *
     * En pratique ca ne genait pas le developpement : en local, nginx (Local
     * by Flywheel) sert les .css/.js en "no-cache, must-revalidate", donc le
     * navigateur revalide a chaque rafraichissement ; sur la demo OVH, le
     * max-age plafonne a 900 s, soit 15 min de CSS perime apres un deploiement.
     * Le vrai enjeu est ailleurs : sans cache-busting, impossible de monter le
     * max-age (cf. wp-content/.htaccess) sans figer le site pendant un an.
     */
    public static function obfuscate_ver_param( $src ) {
        if ( strpos( $src, 'ver=' ) === false ) {
            return $src;
        }

        $query = wp_parse_url( $src, PHP_URL_QUERY );
        if ( empty( $query ) ) {
            return $src;
        }

        $args = [];
        wp_parse_str( $query, $args );
        // strpos() ci-dessus matche aussi ?driver=... : on ne touche a rien
        // tant qu'un vrai arg "ver" non vide n'est pas present.
        if ( ! isset( $args['ver'] ) || '' === $args['ver'] ) {
            return $src;
        }

        $token = substr( wp_hash( (string) $args['ver'] ), 0, 8 );

        return add_query_arg( 'ver', $token, $src );
    }

    public static function remove_xmlrpc_header( $headers ) {
        unset( $headers['X-Pingback'] );
        return $headers;
    }

    /**
     * Anti brute-force login : transient WP par IP, LOGIN_MAX_ATTEMPTS
     * tentatives sur LOGIN_WINDOW_S. Pattern leger, pas de plugin tiers.
     *
     * Le refus est INCONDITIONNEL pendant la fenetre de blocage, y compris
     * quand les identifiants sont bons. C'est tout l'interet du garde-fou et
     * c'est ce qui manquait : la version precedente n'entrait dans le test que
     * si $user etait deja une WP_Error, donc elle rejetait les mauvais essais
     * (qui l'auraient ete de toute facon) mais laissait passer le bon mot de
     * passe des que l'attaquant tombait dessus. Autrement dit, elle ne limitait
     * pas le nombre de mots de passe testables, soit exactement ce qu'un
     * anti-brute-force doit faire.
     *
     * Piege verifie en conditions reelles : le filtre DOIT rester en priorite 30,
     * apres les handlers du coeur. Poser la WP_Error plus tot (priorite 1) ne
     * marche pas, car wp_authenticate_username_password() ne rend la main sur une
     * erreur deja presente que si l'identifiant ou le mot de passe sont vides ;
     * sinon il refait son propre get_user_by() + wp_check_password() et ECRASE
     * notre erreur, laissant la connexion passer. Consequence assumee : le hash
     * est calcule avant qu'on refuse. Ca coute un peu de CPU par tentative, mais
     * ca n'affaiblit rien, puisqu'on repond la meme erreur que le mot de passe
     * soit bon ou mauvais : l'attaquant n'apprend rien et n'entre pas.
     *
     * Contrepartie assumee : le verrou devient reel, donc une equipe derriere une
     * meme IP publique (bureau OPAC) peut se bloquer sur des fautes de frappe.
     * D'ou un seuil a 10 et non 5 : large pour un humain, hors de portee d'un
     * brute-force utile. Le transient expire seul au bout de LOGIN_WINDOW_S.
     *
     * En prod, Cloudflare/OVH rate-limit en plus est recommande
     * pour bloquer en amont (avant que la requete touche PHP).
     */
    public static function check_login_attempts( $user ) {
        $ip = self::get_client_ip();
        if ( ! $ip ) {
            return $user;
        }

        $attempts = (int) get_transient( 'opac_login_fail_' . md5( $ip ) );
        if ( $attempts < self::LOGIN_MAX_ATTEMPTS ) {
            return $user;
        }

        return new WP_Error(
            'opac_too_many_attempts',
            sprintf(
                /* translators: %d : minutes restantes */
                __( 'Trop de tentatives de connexion. Merci de réessayer dans %d minutes.', 'opac-custom' ),
                (int) ceil( self::LOGIN_WINDOW_S / 60 )
            )
        );
    }

    public static function increment_failed_login() {
        $ip = self::get_client_ip();
        if ( ! $ip ) {
            return;
        }
        $key      = 'opac_login_fail_' . md5( $ip );
        $attempts = (int) get_transient( $key );
        set_transient( $key, $attempts + 1, self::LOGIN_WINDOW_S );
    }

    public static function reset_login_attempts() {
        $ip = self::get_client_ip();
        if ( ! $ip ) {
            return;
        }
        delete_transient( 'opac_login_fail_' . md5( $ip ) );
    }

    /**
     * IP du client. Source unique reutilisee par le rate-limit login et
     * l'anti-doublon des inscriptions.
     *
     * Par defaut REMOTE_ADDR (le dernier hop), correct en hebergement direct
     * (OVH sans reverse-proxy) : c'est la vraie IP du visiteur. Si le site passe
     * un jour derriere un proxy / CDN de confiance (Cloudflare...), definir
     * OPAC_TRUST_PROXY dans wp-config.php : on lit alors la premiere entree de
     * X-Forwarded-For (l'IP cliente d'origine). Opt-in volontaire, car ce header
     * est falsifiable tant qu'aucun proxy de confiance ne le pose lui-meme :
     * l'activer sans proxy devant permettrait de contourner les rate-limits.
     */
    public static function get_client_ip() {
        if ( defined( 'OPAC_TRUST_PROXY' ) && OPAC_TRUST_PROXY && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $parts = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $first = trim( $parts[0] );
            if ( '' !== $first ) {
                return $first;
            }
        }
        return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * Le skip link WP par defaut affiche "Skip to the content" en anglais.
     * Filter early-return pour traduire en francais sans cout perceptible
     * (early bail out pour les 99% de strings non concernees).
     */
    public static function translate_skip_link( $translation, $text ) {
        if ( $text === 'Skip to the content' || $text === 'Skip to content' ) {
            return 'Aller au contenu';
        }
        return $translation;
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
