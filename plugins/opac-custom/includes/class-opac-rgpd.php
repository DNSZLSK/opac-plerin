<?php
/**
 * OPAC Custom - Conformite RGPD (donnees personnelles)
 *
 * Regroupe la logique "privacy by design" autour des inscriptions :
 *
 * 1. Purge automatique : un cron quotidien supprime les inscriptions plus
 *    anciennes que la duree de conservation definie dans OPAC > Reglages
 *    (opac_insc_purge_months, 0 = desactive). Limitation de la conservation
 *    (RGPD art. 5.1.e).
 *
 * 2. Droits des personnes : branche les inscriptions sur les outils natifs
 *    WordPress (Outils > Exporter / Effacer les donnees personnelles, par
 *    email). Permet de repondre a une demande d'acces (art. 15) ou
 *    d'effacement (art. 17) sans manipulation manuelle en base.
 *
 * 3. Helper privacy_policy_url() : resout l'URL de la politique de
 *    confidentialite (API native WP, repli sur la page par slug) pour les
 *    mentions affichees sur les formulaires.
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_RGPD {

    const CRON_HOOK   = 'opac_rgpd_purge';
    const BATCH_LIMIT = 200;

    public static function register() {
        // Garde-fou : (re)planifie le cron s'il manque (plugin deja actif avant
        // l'ajout de cette fonctionnalite, ou evenement saute).
        add_action( 'init', [ __CLASS__, 'maybe_schedule' ] );
        add_action( self::CRON_HOOK, [ __CLASS__, 'purge_old_inscriptions' ] );

        // Droits des personnes : exporter + eraser natifs WP.
        add_filter( 'wp_privacy_personal_data_exporters', [ __CLASS__, 'register_exporter' ] );
        add_filter( 'wp_privacy_personal_data_erasers', [ __CLASS__, 'register_eraser' ] );
    }

    /* ----------------------------------------------------------------------
     * Cron de purge
     * -------------------------------------------------------------------- */

    public static function maybe_schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    public static function unschedule() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Supprime les inscriptions dont la date de soumission depasse la duree de
     * conservation. Borne a BATCH_LIMIT par execution : le cron quotidien
     * rattrape le reliquat sur les jours suivants (jamais de requete massive).
     */
    public static function purge_old_inscriptions() {
        $months = class_exists( 'OPAC_Settings' ) ? (int) OPAC_Settings::get( 'opac_insc_purge_months' ) : 0;
        if ( $months <= 0 ) {
            return; // Purge desactivee.
        }

        // Date limite en heure locale du site (coherent avec le stockage de
        // opac_insc_date_submitted via current_time('mysql')).
        $cutoff = ( new DateTimeImmutable( "-{$months} months", wp_timezone() ) )->format( 'Y-m-d H:i:s' );

        $ids = get_posts( [
            'post_type'      => 'opac_inscription',
            'post_status'    => 'any',
            'posts_per_page' => self::BATCH_LIMIT,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => 'opac_insc_date_submitted',
                    'value'   => $cutoff,
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ],
            ],
        ] );

        foreach ( $ids as $id ) {
            wp_delete_post( (int) $id, true ); // true = suppression definitive (pas de corbeille).
        }
    }

    /* ----------------------------------------------------------------------
     * Droits des personnes (export / effacement natifs WP, par email)
     * -------------------------------------------------------------------- */

    public static function register_exporter( $exporters ) {
        $exporters['opac-inscriptions'] = [
            'exporter_friendly_name' => __( 'Inscriptions OPAC', 'opac-custom' ),
            'callback'               => [ __CLASS__, 'export_inscriptions' ],
        ];
        return $exporters;
    }

    public static function export_inscriptions( $email_address, $page = 1 ) {
        $email = sanitize_email( $email_address );
        $page  = (int) $page;
        $items = [];

        $posts = self::find_by_email( $email, $page );

        foreach ( $posts as $post ) {
            $id          = $post->ID;
            $cible_id    = (int) get_post_meta( $id, 'opac_insc_atelier_id', true );
            $items[]     = [
                'group_id'    => 'opac_inscriptions',
                'group_label' => __( 'Inscriptions OPAC', 'opac-custom' ),
                'item_id'     => 'opac-insc-' . $id,
                'data'        => [
                    [ 'name' => __( 'Atelier / stage', 'opac-custom' ), 'value' => $cible_id ? get_the_title( $cible_id ) : '' ],
                    [ 'name' => __( 'Nom', 'opac-custom' ),             'value' => get_post_meta( $id, 'opac_insc_nom', true ) ],
                    [ 'name' => __( 'Prénom', 'opac-custom' ),          'value' => get_post_meta( $id, 'opac_insc_prenom', true ) ],
                    [ 'name' => __( 'Email', 'opac-custom' ),           'value' => get_post_meta( $id, 'opac_insc_email', true ) ],
                    [ 'name' => __( 'Téléphone', 'opac-custom' ),       'value' => get_post_meta( $id, 'opac_insc_telephone', true ) ],
                    [ 'name' => __( 'Créneau', 'opac-custom' ),         'value' => OPAC_Inscriptions::creneau_display( $id ) ],
                    [ 'name' => __( 'Code postal', 'opac-custom' ),     'value' => get_post_meta( $id, 'opac_insc_code_postal', true ) ],
                    [ 'name' => __( 'Commune', 'opac-custom' ),         'value' => get_post_meta( $id, 'opac_insc_commune', true ) ],
                    [ 'name' => __( 'Message', 'opac-custom' ),         'value' => get_post_meta( $id, 'opac_insc_message', true ) ],
                    [ 'name' => __( 'Date de la demande', 'opac-custom' ), 'value' => get_post_meta( $id, 'opac_insc_date_submitted', true ) ],
                ],
            ];
        }

        return [
            'data' => $items,
            'done' => count( $posts ) < self::BATCH_LIMIT,
        ];
    }

    public static function register_eraser( $erasers ) {
        $erasers['opac-inscriptions'] = [
            'eraser_friendly_name' => __( 'Inscriptions OPAC', 'opac-custom' ),
            'callback'             => [ __CLASS__, 'erase_inscriptions' ],
        ];
        return $erasers;
    }

    public static function erase_inscriptions( $email_address, $page = 1 ) {
        $email   = sanitize_email( $email_address );
        $page    = (int) $page;
        $removed = false;

        $posts = self::find_by_email( $email, $page );
        foreach ( $posts as $post ) {
            // L'inscription ne contient que des PII : effacement complet du post
            // (plutot qu'anonymisation, qui ne laisserait rien d'exploitable).
            wp_delete_post( $post->ID, true );
            $removed = true;
        }

        return [
            'items_removed'  => $removed,
            'items_retained' => false,
            'messages'       => [],
            'done'           => count( $posts ) < self::BATCH_LIMIT,
        ];
    }

    private static function find_by_email( $email, $page ) {
        if ( ! $email || ! is_email( $email ) ) {
            return [];
        }
        return get_posts( [
            'post_type'      => 'opac_inscription',
            'post_status'    => 'any',
            'posts_per_page' => self::BATCH_LIMIT,
            'paged'          => max( 1, (int) $page ),
            'no_found_rows'  => true,
            'meta_query'     => [
                [ 'key' => 'opac_insc_email', 'value' => $email ],
            ],
        ] );
    }

    /* ----------------------------------------------------------------------
     * Helper : URL de la politique de confidentialite
     * -------------------------------------------------------------------- */

    /**
     * Resout l'URL de la politique de confidentialite, sans reglage a saisir :
     *   1. API native WP (Reglages > Confidentialite) si une page est designee ;
     *   2. repli sur la page par slug 'politique-de-confidentialite' ;
     *   3. sinon chaine vide (le texte de consentement s'affiche sans lien).
     */
    public static function privacy_policy_url() {
        if ( function_exists( 'get_privacy_policy_url' ) ) {
            $url = get_privacy_policy_url();
            if ( $url ) {
                return $url;
            }
        }
        $page = get_page_by_path( 'politique-de-confidentialite' );
        if ( $page ) {
            return get_permalink( $page );
        }
        return '';
    }
}
