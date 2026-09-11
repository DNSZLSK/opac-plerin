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

    const REVISION_POST_TYPES = [ 'opac_atelier', 'opac_stage', 'opac_event', 'opac_person' ];

    private static function revision_taxonomies() {
        return [
            'opac_atelier' => [ 'opac_audience' ],
            'opac_stage'   => [ 'opac_audience', 'opac_period' ],
            'opac_event'   => [ 'opac_event_cat' ],
            'opac_person'  => [ 'opac_person_type' ],
        ];
    }

    /** Active l'historique des champs métier, de l'image et des taxonomies. */
    public static function boot_revisions() {
        add_filter( 'wp_post_revision_meta_keys', [ __CLASS__, 'revision_meta_keys' ], 10, 2 );
        add_action( 'save_post', [ __CLASS__, 'snapshot_revision_taxonomies' ], 20, 2 );
        add_action( 'wp_restore_post_revision', [ __CLASS__, 'restore_revision_taxonomies' ], 20, 2 );
    }

    /**
     * Tous les champs enregistrés du type sont versionnés automatiquement.
     * L'index de date reste exclu car il est recalculé depuis ses sources.
     */
    public static function revision_meta_keys( $keys, $post_type ) {
        if ( ! in_array( $post_type, self::REVISION_POST_TYPES, true ) ) {
            return $keys;
        }

        $registered = array_keys( get_registered_meta_keys( 'post', $post_type ) );
        $registered = array_diff( $registered, [ 'opac_date_last' ] );
        return array_values( array_unique( array_merge(
            $keys,
            $registered,
            [ '_thumbnail_id', '_opac_revision_taxonomies' ]
        ) ) );
    }

    /** Mémorise les termes courants avant que WordPress crée la révision. */
    public static function snapshot_revision_taxonomies( $post_id, $post ) {
        if ( ! $post || ! in_array( $post->post_type, self::REVISION_POST_TYPES, true )
            || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        $snapshot = [];
        foreach ( self::revision_taxonomies()[ $post->post_type ] ?? [] as $taxonomy ) {
            $ids = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
            $snapshot[ $taxonomy ] = is_wp_error( $ids ) ? [] : array_map( 'intval', $ids );
        }
        update_post_meta( $post_id, '_opac_revision_taxonomies', $snapshot );
    }

    /** Restaure les taxonomies absentes du mécanisme de révision natif. */
    public static function restore_revision_taxonomies( $post_id, $revision_id ) {
        $post_type = get_post_type( $post_id );
        if ( ! in_array( $post_type, self::REVISION_POST_TYPES, true ) ) {
            return;
        }

        $snapshot = get_post_meta( $revision_id, '_opac_revision_taxonomies', true );
        if ( is_array( $snapshot ) ) {
            foreach ( self::revision_taxonomies()[ $post_type ] ?? [] as $taxonomy ) {
                if ( array_key_exists( $taxonomy, $snapshot ) ) {
                    wp_set_object_terms( $post_id, array_map( 'intval', (array) $snapshot[ $taxonomy ] ), $taxonomy, false );
                }
            }
        }

        if ( 'opac_stage' === $post_type && class_exists( 'OPAC_Blocks' ) ) {
            OPAC_Blocks::store_stage_end_date( $post_id );
        }
    }

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

    private static function args_gallery_ids() {
        return [
            'type'          => 'array',
            'single'        => true,
            'show_in_rest'  => false,
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

        register_post_meta( 'opac_atelier', 'opac_gallery_ids', self::args_gallery_ids() );
    }

    private static function register_stage_meta() {
        register_post_meta( 'opac_stage', 'opac_date_debut', self::args_string() );
        register_post_meta( 'opac_stage', 'opac_date_fin', self::args_string() );
        // Index derive pour les filtres SQL. Calcule cote serveur, jamais edite.
        register_post_meta( 'opac_stage', 'opac_date_last', self::args_string( false ) );
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
        register_post_meta( 'opac_stage', 'opac_gallery_ids', self::args_gallery_ids() );
        register_post_meta( 'opac_stage', 'opac_show_gallery', self::args_int() );

        // Seances datees optionnelles (modele hybride) : un ephemere peut se
        // tenir sur plusieurs dates, chacune avec sa capacite. Meme structure
        // que opac_creneaux de l'atelier, mais 'date' (Y-m-d) au lieu de 'jour'.
        // La plage reste necessaire : une ligne peut representer plusieurs
        // jours tout en ne stockant que la date du premier jour.
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
        register_post_meta( 'opac_event', 'opac_gallery_ids', self::args_gallery_ids() );
        register_post_meta( 'opac_event', 'opac_show_gallery', self::args_int() );
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
