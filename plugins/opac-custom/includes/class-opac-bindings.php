<?php
/**
 * OPAC Custom - Block Bindings sources
 *
 * Sources de binding custom pour formatter les meta fields des CPTs
 * (tarif "X EUR / an", date "Avr." + "2026", places "Places disponibles"
 * a partir du slug "ok"). Utilisees via metadata.bindings dans le block
 * markup avec source="opac/atelier-meta" ou "opac/stage-meta".
 *
 * Nativement, core/post-meta retourne la valeur brute du meta. On a
 * besoin du formatage parce que :
 * - tarif_annuel est stocke en int (335), affiche en "335 EUR / an"
 * - opac_places_dispo est stocke en slug (ok/full/few), affiche en label
 * - opac_date_debut est stocke en "2026-04-15", affiche en "Avr." + "2026"
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Bindings {

    public static function register() {
        if ( ! function_exists( 'register_block_bindings_source' ) ) {
            return; // WP < 6.5
        }

        register_block_bindings_source( 'opac/atelier-meta', [
            'label'              => __( 'OPAC Atelier Meta', 'opac-custom' ),
            'get_value_callback' => [ __CLASS__, 'get_atelier_meta' ],
            'uses_context'       => [ 'postId', 'postType' ],
        ] );

        register_block_bindings_source( 'opac/stage-meta', [
            'label'              => __( 'OPAC Stage Meta', 'opac-custom' ),
            'get_value_callback' => [ __CLASS__, 'get_stage_meta' ],
            'uses_context'       => [ 'postId', 'postType' ],
        ] );
    }

    /**
     * Source binding pour les meta d'un atelier.
     * Args: { key: 'opac_tarif_annuel' | 'opac_animator' | 'opac_description_courte' | 'opac_places_dispo' }
     */
    public static function get_atelier_meta( $source_args, $block_instance, $attribute_name ) {
        $post_id = self::resolve_post_id( $block_instance );
        if ( ! $post_id ) {
            return '';
        }

        $key = isset( $source_args['key'] ) ? (string) $source_args['key'] : '';
        if ( ! $key ) {
            return '';
        }

        $value = get_post_meta( $post_id, $key, true );

        switch ( $key ) {
            case 'opac_tarif_annuel':
                $int = (int) $value;
                if ( $int <= 0 ) {
                    return '';
                }
                return number_format_i18n( $int, 0 ) . ' € / an';

            case 'opac_places_dispo':
                $labels = [
                    'ok'   => __( 'Places disponibles', 'opac-custom' ),
                    'full' => __( 'Complet', 'opac-custom' ),
                    'few'  => __( 'Quelques places', 'opac-custom' ),
                ];
                return isset( $labels[ $value ] ) ? $labels[ $value ] : '';

            default:
                return is_scalar( $value ) ? (string) $value : '';
        }
    }

    /**
     * Source binding pour les meta d'un stage ephemere.
     * Args: { key: 'opac_tarif_seance' | 'opac_animator' | 'opac_description_courte'
     *       | 'opac_date_debut' | 'opac_public' }
     *       + part: 'month' | 'year' (uniquement pour opac_date_debut)
     */
    public static function get_stage_meta( $source_args, $block_instance, $attribute_name ) {
        $post_id = self::resolve_post_id( $block_instance );
        if ( ! $post_id ) {
            return '';
        }

        $key = isset( $source_args['key'] ) ? (string) $source_args['key'] : '';
        if ( ! $key ) {
            return '';
        }

        $value = get_post_meta( $post_id, $key, true );

        switch ( $key ) {
            case 'opac_tarif_seance':
                $int = (int) $value;
                if ( $int <= 0 ) {
                    return '';
                }
                return number_format_i18n( $int, 0 ) . ' €';

            case 'opac_date_debut':
            case 'opac_date_fin':
                if ( ! $value ) {
                    return '';
                }
                $ts = strtotime( $value );
                if ( ! $ts ) {
                    return '';
                }
                $part = isset( $source_args['part'] ) ? (string) $source_args['part'] : 'month';
                if ( $part === 'year' ) {
                    return wp_date( 'Y', $ts );
                }
                // Abreviation FR forcee (independant de la locale WP qui
                // peut etre en_US par defaut sur certains serveurs).
                $months_fr = [
                    1 => 'Janv.', 2  => 'Févr.', 3  => 'Mars',  4 => 'Avr.',
                    5 => 'Mai',   6  => 'Juin',  7  => 'Juil.', 8 => 'Août',
                    9 => 'Sept.', 10 => 'Oct.',  11 => 'Nov.', 12 => 'Déc.',
                ];
                $n = (int) wp_date( 'n', $ts );
                return isset( $months_fr[ $n ] ) ? $months_fr[ $n ] : '';

            default:
                return is_scalar( $value ) ? (string) $value : '';
        }
    }

    /**
     * Resolve le post ID depuis le contexte du block ou du loop courant.
     */
    private static function resolve_post_id( $block_instance ) {
        if ( isset( $block_instance->context['postId'] ) && $block_instance->context['postId'] ) {
            return (int) $block_instance->context['postId'];
        }
        $current = get_the_ID();
        return $current ? (int) $current : 0;
    }
}
