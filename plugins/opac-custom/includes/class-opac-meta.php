<?php
/**
 * OPAC Custom - Meta fields
 *
 * Enregistre les meta fields des CPTs avec show_in_rest pour qu'ils soient
 * exploitables par le block editor (lecture / écriture dans des blocs custom
 * ou via le panneau latéral en M1+).
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Meta {

    public static function register() {
        self::register_atelier_meta();
        self::register_stage_meta();
        self::register_event_meta();
        self::register_person_meta();
        self::register_inscription_meta();
        self::register_gallery_meta();
    }

    private static function auth_can_edit() {
        return current_user_can( 'edit_posts' );
    }

    private static function args_string( $rest = true ) {
        return [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => $rest,
            'auth_callback' => [ __CLASS__, 'auth_can_edit' ],
        ];
    }

    private static function args_int( $rest = true ) {
        return [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => $rest,
            'auth_callback' => [ __CLASS__, 'auth_can_edit' ],
        ];
    }

    private static function register_atelier_meta() {
        register_post_meta( 'opac_atelier', 'opac_tarif_annuel', self::args_int() );
        register_post_meta( 'opac_atelier', 'opac_animator', self::args_string() );
        register_post_meta( 'opac_atelier', 'opac_animator_id', self::args_int() );
        // Legacy : opac_public (saisie libre) n'est plus ecrit depuis le passage
        // du Public a la taxonomie opac_audience. Conserve en lecture seule pour
        // le fallback des fiches d'avant la taxonomie (cf. OPAC_Bindings::resolve_public).
        register_post_meta( 'opac_atelier', 'opac_public', self::args_string() );
        register_post_meta( 'opac_atelier', 'opac_public_precision', self::args_string() );
        register_post_meta( 'opac_atelier', 'opac_places_dispo', self::args_string() );
        register_post_meta( 'opac_atelier', 'opac_description_courte', self::args_string() );

        register_post_meta( 'opac_atelier', 'opac_creneaux', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [ 'type' => 'string' ],
                            'jour' => [ 'type' => 'string' ],
                            'debut' => [ 'type' => 'string' ],
                            'fin' => [ 'type' => 'string' ],
                            'tarif' => [ 'type' => 'integer' ],
                            'capacite' => [ 'type' => 'integer' ],
                            'note' => [ 'type' => 'string' ],
                        ],
                    ],
                ],
            ],
            'auth_callback' => [ __CLASS__, 'auth_can_edit' ],
        ] );

        register_post_meta( 'opac_atelier', 'opac_card_color', self::args_string() );
        register_post_meta( 'opac_atelier', 'opac_tagline', self::args_string() );
        register_post_meta( 'opac_atelier', 'opac_creneaux_text', self::args_string() );
        register_post_meta( 'opac_atelier', 'opac_notice', self::args_string() );
        register_post_meta( 'opac_atelier', 'opac_show_gallery', self::args_int() );

        register_post_meta( 'opac_atelier', 'opac_gallery_ids', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [ 'type' => 'integer' ],
                ],
            ],
            'auth_callback' => [ __CLASS__, 'auth_can_edit' ],
        ] );
    }

    private static function register_stage_meta() {
        register_post_meta( 'opac_stage', 'opac_date_debut', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_date_fin', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_tarif_seance', self::args_int() );
        register_post_meta( 'opac_stage', 'opac_animator', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_animator_id', self::args_int() );
        // Legacy : cf. note sur opac_public dans register_atelier_meta (fallback
        // des fiches d'avant la taxonomie opac_audience).
        register_post_meta( 'opac_stage', 'opac_public', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_public_precision', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_places_dispo', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_lieu', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_description_courte', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_card_color', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_tagline', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_notice', self::args_string() );

        // Seances datees optionnelles (modele hybride) : un ephemere peut se
        // tenir sur plusieurs dates, chacune avec sa capacite. Meme structure
        // que opac_creneaux de l'atelier, mais 'date' (Y-m-d) au lieu de 'jour'.
        // Vide => on retombe sur la plage opac_date_debut/opac_date_fin.
        register_post_meta( 'opac_stage', 'opac_stage_seances', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [ 'type' => 'string' ],
                            'date' => [ 'type' => 'string' ],
                            'debut' => [ 'type' => 'string' ],
                            'fin' => [ 'type' => 'string' ],
                            'tarif' => [ 'type' => 'integer' ],
                            'capacite' => [ 'type' => 'integer' ],
                            'note' => [ 'type' => 'string' ],
                        ],
                    ],
                ],
            ],
            'auth_callback' => [ __CLASS__, 'auth_can_edit' ],
        ] );
    }

    private static function register_event_meta() {
        register_post_meta( 'opac_event', 'opac_date_event', self::args_string() );
        register_post_meta( 'opac_event', 'opac_description_courte', self::args_string() );
        register_post_meta( 'opac_event', 'opac_lieu', self::args_string() );
    }

    private static function register_person_meta() {
        register_post_meta( 'opac_person', 'opac_role', self::args_string() );
        register_post_meta( 'opac_person', 'opac_initials', self::args_string() );
    }

    private static function register_inscription_meta() {
        // Inscriptions : pas exposées en REST (PII).
        register_post_meta( 'opac_inscription', 'opac_insc_nom', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_prenom', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_email', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_telephone', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_atelier_id', self::args_int( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_creneau', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_message', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_date_submitted', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_source', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_adhesion', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_code_postal', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_commune', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_plerinais', self::args_int( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_creneau_id', self::args_string( false ) );
        register_post_meta( 'opac_inscription', 'opac_insc_tarif', self::args_int( false ) );
    }

    private static function register_gallery_meta() {
        register_post_meta( 'opac_gallery_item', 'opac_gallery_atelier_id', self::args_int() );
        register_post_meta( 'opac_gallery_item', 'opac_gallery_caption', self::args_string() );
    }
}
