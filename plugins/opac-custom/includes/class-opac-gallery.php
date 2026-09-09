<?php
/**
 * OPAC Custom - Gestion des galeries rattachees aux fiches metier.
 *
 * Les fiches stockent directement une liste ordonnee d'IDs de pieces jointes.
 * Les anciens opac_gallery_item restent conserves et servent a la migration
 * initiale ainsi qu'au maintien de leurs legendes historiques.
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Gallery {

    const DB_VERSION = 1;
    const META_KEY   = 'opac_gallery_ids';
    const POST_TYPES = [ 'opac_atelier', 'opac_stage', 'opac_event' ];

    /** Garde uniquement des IDs uniques correspondant a des images. */
    public static function sanitize_attachment_ids( $ids ) {
        if ( ! is_array( $ids ) ) {
            return [];
        }

        $valid = [];
        foreach ( $ids as $id ) {
            $id = absint( $id );
            if ( ! $id || isset( $valid[ $id ] ) ) {
                continue;
            }
            if ( 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
                continue;
            }
            $valid[ $id ] = $id;
        }
        return array_values( $valid );
    }

    /** Anciennes entrees Galerie associees a une fiche, dans leur ordre actuel. */
    public static function legacy_items( $post_id ) {
        return get_posts( [
            'post_type'      => 'opac_gallery_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order date',
            'order'          => 'ASC',
            'meta_key'       => 'opac_gallery_atelier_id',
            'meta_value'     => (int) $post_id,
        ] );
    }

    /** IDs d'images des anciennes entrees Galerie. */
    public static function legacy_attachment_ids( $post_id ) {
        $ids = [];
        foreach ( self::legacy_items( $post_id ) as $item ) {
            $ids[] = get_post_thumbnail_id( $item->ID );
        }
        return self::sanitize_attachment_ids( $ids );
    }

    /**
     * Liste ordonnee utilisee par l'admin et le site.
     *
     * Avant migration, le repli dynamique evite toute disparition de photo.
     * Apres migration, meme une liste vide est volontaire et fait autorite.
     */
    public static function attachment_ids( $post_id ) {
        $stored = get_post_meta( $post_id, self::META_KEY, true );
        if ( metadata_exists( 'post', $post_id, self::META_KEY ) ) {
            return self::sanitize_attachment_ids( $stored );
        }
        return self::legacy_attachment_ids( $post_id );
    }

    /** Legendes propres aux anciennes entrees, indexees par ID d'image. */
    public static function legacy_captions( $post_id ) {
        $captions = [];
        foreach ( self::legacy_items( $post_id ) as $item ) {
            $attachment_id = (int) get_post_thumbnail_id( $item->ID );
            $caption       = (string) get_post_meta( $item->ID, 'opac_gallery_caption', true );
            if ( $attachment_id && '' !== $caption && ! isset( $captions[ $attachment_id ] ) ) {
                $captions[ $attachment_id ] = $caption;
            }
        }
        return $captions;
    }

    /**
     * Recopie une seule fois les anciennes galeries dans les fiches.
     * Les anciens posts ne sont ni modifies ni supprimes.
     */
    public static function maybe_migrate() {
        if ( (int) get_option( 'opac_gallery_db_version', 0 ) >= self::DB_VERSION ) {
            return;
        }

        $post_ids = get_posts( [
            'post_type'      => self::POST_TYPES,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $post_ids as $post_id ) {
            $stored = get_post_meta( $post_id, self::META_KEY, true );
            $ids    = is_array( $stored ) ? $stored : [];
            $ids    = array_merge( $ids, self::legacy_attachment_ids( $post_id ) );
            update_post_meta( $post_id, self::META_KEY, self::sanitize_attachment_ids( $ids ) );
        }

        update_option( 'opac_gallery_db_version', self::DB_VERSION );
    }
}
