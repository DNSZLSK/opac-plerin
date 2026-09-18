<?php
/**
 * OPAC Custom - Redirections 301 des anciennes URLs.
 *
 * L'ancien site (aujourd'hui servi par old.opacplerin.fr) utilisait une autre
 * arborescence : /les-ateliers/, /les-stages/, /les-equipes-de-lopac/, etc.
 * Google avait indexe ces URLs sous opacplerin.fr avant la bascule demo -> prod,
 * et aucune redirection n'a ete posee : chaque URL indexee tombe donc en 404.
 * Consequences : les visiteurs venant de Google ou d'un favori atterrissent sur
 * une erreur, les liens externes (partenaires, mairie, reseaux) restent morts,
 * et le referencement acquis par les anciennes pages est perdu au lieu d'etre
 * transfere vers les nouvelles.
 *
 * Cette classe pose des redirections 301 (permanentes) des anciens chemins vers
 * les nouveaux, UNIQUEMENT quand la requete finirait sinon en 404 : aucune page
 * valide de la refonte n'est impactee. La table est derivee de la navigation
 * reelle de l'ancien site.
 *
 * Pourquoi ici et pas dans le .htaccess : le .htaccess racine n'est pas deploye
 * par deploy-demo.ps1 (hors wp-content), alors que le plugin l'est ; la logique
 * reste versionnee et testable (cf. tests/test-redirects.php).
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Redirects {

    /**
     * Correspondances exactes : ancien chemin (sans slash) -> nouveau chemin
     * (sans slash). Pages de l'ancien site sans equivalent d'URL direct sur la
     * refonte, rattachees a la section la plus proche.
     */
    const MAP = [
        'les-equipes-de-lopac'            => 'association',
        'lopac-cest-quoi'                 => 'association',
        'partenaires'                     => 'association',
        'infos-pratiques'                 => 'contact',
        'acces-plan'                      => 'contact',
        'actus'                           => 'agenda',
        'assemblee-generale'              => 'agenda',
        'forum-des-associations'          => 'agenda',
        'journees-du-patrimoine'          => 'agenda',
        'telethon'                        => 'agenda',
        'carte-blanche'                   => 'agenda',
        'expos-fin-dannee'                => 'agenda',
        'sorties-expositions-exterieures' => 'agenda',
        'stages-de-printemps'             => 'ephemeres',

        // Complement releve en confrontant le sitemap de old.opacplerin.fr
        // (37 URLs) a la prod : ces quatre chemins etaient les seuls a tomber
        // encore en 404. Ils comptent parce que la mise hors service de
        // old.opacplerin.fr passe par une 301 preservant le chemin vers le
        // domaine principal : chaque trou de cette table redevient un 404 pour
        // un visiteur venant de Google ou d'un favori.
        'charte-des-ateliers'             => 'association', // charte publiee sur la page Association
        'les-actions-en-partenariat'      => 'association',
        '8332-2'                          => 'agenda',      // slug auto WP, page « Saison culturelle »
        'newsletter'                      => 'contact',     // pas de newsletter sur la refonte
    ];

    /**
     * Corrections de slug d'atelier (ancien slug -> nouveau slug) pour les
     * fiches dont l'URL a change entre les deux sites. Les slugs identiques
     * passent par la regle generique et n'ont rien a faire ici.
     */
    const ATELIER_SLUG_FIXUPS = [
        'tapisserie-dameublement-2' => 'tapisserie-dameublement',
    ];

    public static function register() {
        // Priorite 1 : agir tot, mais seulement sur les 404 (cf. maybe_redirect).
        add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect' ], 1 );
    }

    public static function maybe_redirect() {
        // On ne touche qu'aux requetes qui finiraient en 404 : aucune page
        // valide (contact, mentions-legales, ateliers existants...) n'est vue ici.
        if ( ! is_404() ) {
            return;
        }

        $request = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
        $target  = self::resolve( $request );
        if ( '' === $target ) {
            return;
        }

        // Garde anti-chaine 301 -> 404 : si la cible est une fiche atelier qui
        // n'existe pas (ancien slug supprime), on retombe sur l'archive.
        if ( preg_match( '#^/ateliers/([^/]+)/$#', $target, $m ) ) {
            if ( ! get_page_by_path( $m[1], OBJECT, 'opac_atelier' ) ) {
                $target = '/ateliers/';
            }
        }

        wp_safe_redirect( home_url( $target ), 301 );
        exit;
    }

    /**
     * Logique pure (aucun appel WordPress) : chemin demande -> chemin cible.
     * Retourne un chemin avec slash de debut et de fin (ex. '/ateliers/'), ou
     * '' si aucune redirection ne s'applique.
     *
     * @param string $request Chemin ou URI (query string toleree).
     * @return string
     */
    public static function resolve( $request ) {
        // Isole le chemin : retire la query string, decode, normalise les slashs.
        $path = (string) $request;
        $qpos = strpos( $path, '?' );
        if ( false !== $qpos ) {
            $path = substr( $path, 0, $qpos );
        }
        $path = rawurldecode( $path );
        $path = strtolower( trim( $path, '/' ) );
        if ( '' === $path ) {
            return '';
        }

        // Ateliers a l'annee : archive, page tarifs, puis fiches.
        if ( 'les-ateliers' === $path || 'les-ateliers/tarifs' === $path ) {
            return '/ateliers/';
        }
        if ( 0 === strpos( $path, 'les-ateliers/' ) ) {
            $slug = explode( '/', substr( $path, strlen( 'les-ateliers/' ) ) )[0];
            if ( '' === $slug ) {
                return '/ateliers/';
            }
            if ( isset( self::ATELIER_SLUG_FIXUPS[ $slug ] ) ) {
                $slug = self::ATELIER_SLUG_FIXUPS[ $slug ];
            }
            return '/ateliers/' . $slug . '/';
        }

        // Ateliers ephemeres (anciens "stages") : tout vers l'archive.
        if ( 'les-stages' === $path || 0 === strpos( $path, 'les-stages/' ) ) {
            return '/ephemeres/';
        }

        // Pages fixes.
        if ( array_key_exists( $path, self::MAP ) ) {
            return '/' . self::MAP[ $path ] . '/';
        }

        return '';
    }
}
