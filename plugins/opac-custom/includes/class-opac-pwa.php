<?php
/**
 * OPAC Custom - Couche PWA (Progressive Web App)
 *
 * Rend le site installable sur l'ecran d'accueil (icone OPAC) et pilote un
 * service worker en network-first : le contenu reste toujours frais (saison,
 * places, agenda), les assets sont caches pour la vitesse mais invalides des
 * qu'ils changent.
 *
 * Tout vit dans le plugin (manifest + service worker + registration), donc la
 * PWA survit a un changement de theme, comme le SEO. L'icone d'application est
 * une image fournie par l'equipe (assets/img/icon-pwa.jpg) ; le favicon (Icone
 * du site WordPress) reste distinct et inchange.
 *
 * Points sensibles traites :
 * - Scope racine : le service worker est servi a /opac-sw.js (regle de
 *   reecriture) avec l'en-tete Service-Worker-Allowed: / . Un SW ne controle
 *   que son propre chemin, il DOIT donc etre servi depuis la racine.
 * - Cache fige : le nom des caches embarque une version derivee du filemtime
 *   des assets du theme. OPAC_Security::strip_ver_param retire le ?ver= des
 *   URLs ; sans cette cle, le service worker figerait opac.css/opac.js et un
 *   Ctrl+Shift+R ne suffirait plus (il faudrait vider les donnees du site). La
 *   version change des qu'un asset change -> le SW est reinstalle et purge les
 *   anciens caches a l'activation.
 * - POST : le handler fetch ne traite que les GET. Les envois d'inscription et
 *   de contact (POST vers /wp-admin/admin-post.php) filent au reseau, intacts.
 * - Admin : /wp-admin/, wp-login, wp-json, admin-ajax, cron sont ignores.
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_PWA {

    const SW_PATH       = 'opac-sw.js';
    const MANIFEST_PATH = 'opac-manifest.webmanifest';
    const QV_SW         = 'opac_sw';
    const QV_MANIFEST   = 'opac_manifest';
    const REWRITE_FLAG  = 'opac_pwa_rewrite_v';
    const REWRITE_VER   = '1'; // Incrementer si on modifie les regles de reecriture.
    const THEME_COLOR   = '#c0583a'; // Terracotta (palette theme.json : accent).
    const BG_COLOR      = '#faf9f6'; // Fond creme (palette theme.json : bg).

    public static function register() {
        add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
        add_action( 'init', [ __CLASS__, 'maybe_flush' ], 11 );
        add_action( 'template_redirect', [ __CLASS__, 'maybe_serve' ] );
        add_action( 'wp_head', [ __CLASS__, 'render_head' ], 2 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_registration' ] );
    }

    /**
     * Sert le manifest et le service worker depuis des URLs racine, via des
     * regles de reecriture (index.php?opac_sw=1 / opac_manifest=1).
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule( '^' . self::SW_PATH . '$', 'index.php?' . self::QV_SW . '=1', 'top' );
        add_rewrite_rule( '^' . self::MANIFEST_PATH . '$', 'index.php?' . self::QV_MANIFEST . '=1', 'top' );
    }

    public static function add_query_vars( $vars ) {
        $vars[] = self::QV_SW;
        $vars[] = self::QV_MANIFEST;
        return $vars;
    }

    /**
     * Flush unique par version de regles (meme mecanique idempotente que
     * OPAC_Settings::maybe_seed_legal_pages / maybe_set_timezone), pour eviter
     * un flush a chaque chargement. Les regles sont deja enregistrees a init:10.
     */
    public static function maybe_flush() {
        if ( get_option( self::REWRITE_FLAG ) === self::REWRITE_VER ) {
            return;
        }
        flush_rewrite_rules( false );
        update_option( self::REWRITE_FLAG, self::REWRITE_VER );
    }

    public static function maybe_serve() {
        if ( get_query_var( self::QV_SW ) ) {
            self::serve_service_worker();
        }
        if ( get_query_var( self::QV_MANIFEST ) ) {
            self::serve_manifest();
        }
    }

    /* ---------------------------------------------------------------------
     * Rendu <head> : lien manifest + couleur de theme + balises iOS.
     * ------------------------------------------------------------------- */

    public static function render_head() {
        echo '<link rel="manifest" href="' . esc_url( home_url( '/' . self::MANIFEST_PATH ) ) . '" />' . "\n";
        echo '<meta name="theme-color" content="' . esc_attr( self::THEME_COLOR ) . '" />' . "\n";

        // iOS ne supporte le manifest que partiellement : ces balises assurent
        // l'icone d'accueil et le mode plein ecran a l'installation manuelle
        // (Partager, puis « Sur l'ecran d'accueil »).
        echo '<meta name="apple-mobile-web-app-capable" content="yes" />' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default" />' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="OPAC" />' . "\n";

        // L'icone d'accueil iOS/iPadOS vient de apple-touch-icon (le manifest
        // n'est lu que partiellement par Safari). On l'emet TOUJOURS, jamais
        // conditionnee a un champ que l'equipe peut laisser vide : Icone du site
        // si elle est definie (override webmaster ponctuel), sinon l'icone OPAC
        // embarquee, la meme que le manifest -> icone identique sur Android et
        // Apple. Le PNG est opaque (logo sur fond blanc) : pas de fond noir
        // ajoute par iOS, et les marges evitent que l'arrondi iOS rogne le logo.
        $site_icon = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 180 ) : '';
        $apple     = $site_icon ? $site_icon : OPAC_CUSTOM_URL . 'assets/img/icon-192.png';
        echo '<link rel="apple-touch-icon" href="' . esc_url( $apple ) . '" />' . "\n";
    }

    /**
     * Enregistre le service worker cote public uniquement (wp_enqueue_scripts
     * ne tourne pas dans l'admin). L'URL du SW est passee au script.
     */
    public static function enqueue_registration() {
        wp_enqueue_script(
            'opac-pwa',
            OPAC_CUSTOM_URL . 'assets/js/opac-pwa.js',
            [],
            OPAC_CUSTOM_VERSION,
            true
        );
        wp_localize_script( 'opac-pwa', 'opacPwa', [
            'sw' => home_url( '/' . self::SW_PATH ),
        ] );
    }

    /* ---------------------------------------------------------------------
     * Manifest (application/manifest+json), genere dynamiquement.
     * ------------------------------------------------------------------- */

    private static function serve_manifest() {
        status_header( 200 );
        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        echo wp_json_encode( self::manifest_data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        exit;
    }

    private static function manifest_data() {
        $name = class_exists( 'OPAC_Settings' ) ? (string) OPAC_Settings::get( 'opac_org_name' ) : get_bloginfo( 'name' );
        if ( '' === $name ) {
            $name = 'Association OPAC';
        }

        $data = [
            'id'               => home_url( '/' ),
            'name'             => $name,
            'short_name'       => 'OPAC',
            'lang'             => 'fr-FR',
            'dir'              => 'ltr',
            'start_url'        => home_url( '/' ),
            'scope'            => home_url( '/' ),
            'display'          => 'standalone',
            'theme_color'      => self::THEME_COLOR,
            'background_color' => self::BG_COLOR,
            'icons'            => self::manifest_icons(),
        ];

        $desc = get_bloginfo( 'description' );
        if ( $desc ) {
            $data['description'] = $desc;
        }

        return $data;
    }

    /**
     * Icones de l'application, generees depuis l'image fournie par l'equipe
     * (assets/img/icon-pwa.jpg, logo OPAC sur fond blanc carre) vers PNG. Chrome
     * (Android) attend du PNG aux tailles 192 et 512 : le JPEG unique 1254 ne
     * suffisait pas, l'icone d'accueil ne s'affichait pas cote Android. Purpose
     * "any" : image sur fond blanc, marges conservees, rien n'est rogne. Le
     * favicon (Icone du site) reste distinct et n'est pas modifie.
     *
     * Les PNG sont commit dans le plugin : penser a les deployer (SFTP) avec le
     * reste, sinon le manifest reference des fichiers absents (404) et Android
     * n'a pas d'icone.
     */
    private static function manifest_icons() {
        return [
            [
                'src'     => OPAC_CUSTOM_URL . 'assets/img/icon-192.png',
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src'     => OPAC_CUSTOM_URL . 'assets/img/icon-512.png',
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any',
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     * Service worker (application/javascript), avec version de cache injectee.
     * ------------------------------------------------------------------- */

    private static function serve_service_worker() {
        status_header( 200 );
        // Le fichier SW lui-meme ne doit pas etre fige par le cache HTTP : le
        // navigateur doit pouvoir detecter une nouvelle version a chaque visite.
        nocache_headers();
        header( 'Content-Type: application/javascript; charset=utf-8' );
        header( 'Service-Worker-Allowed: /' );
        echo self::service_worker_js( self::cache_version() );
        exit;
    }

    /**
     * Cle de cache derivee de la version du plugin et du filemtime des assets
     * front du theme (meme logique que opac_asset_version). Tout changement
     * d'asset change cette cle -> nouveau texte de SW -> reinstallation + purge.
     */
    private static function cache_version() {
        $theme = get_template_directory();
        $parts = [ OPAC_CUSTOM_VERSION ];
        foreach ( [ '/assets/css/opac.css', '/assets/js/opac.js' ] as $rel ) {
            $abs     = $theme . $rel;
            $parts[] = file_exists( $abs ) ? (string) filemtime( $abs ) : '0';
        }
        return substr( md5( implode( '-', $parts ) ), 0, 12 );
    }

    private static function service_worker_js( $version ) {
        $js = <<<'JS'
const VERSION = '__VER__';
const PAGES_CACHE = 'opac-pages-' + VERSION;
const ASSETS_CACHE = 'opac-assets-' + VERSION;
const OFFLINE_URL = '/';

// Installation : precache best-effort de l'accueil (fallback hors-ligne) puis
// prise de controle immediate.
self.addEventListener('install', function (event) {
  event.waitUntil((async function () {
    try {
      const cache = await caches.open(PAGES_CACHE);
      await cache.add(new Request(OFFLINE_URL, { cache: 'reload' }));
    } catch (e) {}
    await self.skipWaiting();
  })());
});

// Activation : purge des caches d'anciennes versions. C'est ce qui evite les
// assets figes (le ?ver= etant retire des URLs cote serveur).
self.addEventListener('activate', function (event) {
  event.waitUntil((async function () {
    const keys = await caches.keys();
    await Promise.all(keys.map(function (k) {
      if (k !== PAGES_CACHE && k !== ASSETS_CACHE) { return caches.delete(k); }
    }));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', function (event) {
  const req = event.request;
  const url = new URL(req.url);

  // Seulement le meme origine et les GET : les POST (inscription, contact vers
  // /wp-admin/admin-post.php) filent au reseau sans interception.
  if (req.method !== 'GET' || url.origin !== self.location.origin) { return; }

  const p = url.pathname;

  // Ne jamais intercepter l'admin, l'auth, l'API REST, le cron, l'ajax, ni le
  // service worker / manifest eux-memes.
  if (p.indexOf('/wp-admin/') === 0 || p.indexOf('/wp-login') === 0 ||
      p.indexOf('/wp-json') === 0 || p.indexOf('/wp-cron') === 0 ||
      p.indexOf('/xmlrpc.php') === 0 ||
      p.indexOf('admin-ajax.php') !== -1 || p.indexOf('admin-post.php') !== -1 ||
      p === '/opac-sw.js' || p === '/opac-manifest.webmanifest') {
    return;
  }

  // Pages (navigations) : network-first. Le contenu reste toujours frais
  // (saison, places, agenda) ; fallback cache puis accueil si hors-ligne.
  if (req.mode === 'navigate') {
    event.respondWith((async function () {
      try {
        const fresh = await fetch(req);
        const cache = await caches.open(PAGES_CACHE);
        cache.put(req, fresh.clone());
        return fresh;
      } catch (e) {
        const cache = await caches.open(PAGES_CACHE);
        return (await cache.match(req)) || (await cache.match(OFFLINE_URL)) || Response.error();
      }
    })());
    return;
  }

  // Assets (css/js/img/polices) : stale-while-revalidate pour la vitesse. Le
  // busting entre versions est assure par le nom de cache (VERSION).
  if (/\.(?:css|js|mjs|png|jpe?g|webp|avif|gif|svg|ico|woff2?)$/.test(p)) {
    event.respondWith((async function () {
      const cache = await caches.open(ASSETS_CACHE);
      const cached = await cache.match(req);
      const fetching = fetch(req).then(function (res) {
        if (res && res.status === 200 && res.type === 'basic') { cache.put(req, res.clone()); }
        return res;
      }).catch(function () { return cached; });
      return cached || fetching;
    })());
    return;
  }

  // Reste : reseau d'abord, fallback cache.
  event.respondWith(fetch(req).catch(function () { return caches.match(req); }));
});
JS;
        return str_replace( '__VER__', $version, $js );
    }
}
