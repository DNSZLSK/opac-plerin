<?php
/**
 * OPAC Custom - Server-rendered blocks
 *
 * Blocs dynamiques rendus cote serveur. Utilises pour des cas ou
 * core/post-meta + block bindings ne suffit pas (ex: changer la
 * className en fonction de la valeur du meta, ce que bindings ne
 * permet pas nativement).
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Blocks {

    public static function register() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type( 'opac/places-tag', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_places_tag' ],
            'uses_context'    => [ 'postId', 'postType' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/breadcrumb', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_breadcrumb' ],
            'uses_context'    => [ 'postId', 'postType' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );
    }

    /**
     * Tag de disponibilite : lit opac_places_dispo (slug "ok" | "full" | "few")
     * et rend <p class="opac-tag opac-tag-X">Label</p> avec la bonne classe
     * couleur et le bon libelle FR.
     *
     * Pourquoi un bloc serveur et pas un binding ? Les block bindings ne
     * peuvent que substituer la valeur d'un attribut HTML (ex: content
     * d'un paragraphe). Ils ne peuvent pas modifier la className en
     * fonction de la valeur du meta. Or ici on veut un visuel different
     * pour "Complet" (rouge) vs "Places disponibles" (vert).
     */
    public static function render_places_tag( $attrs, $content, $block ) {
        $post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
        if ( ! $post_id ) {
            return '';
        }

        $slug = (string) get_post_meta( $post_id, 'opac_places_dispo', true );

        $map = [
            'ok'   => [ 'label' => __( 'Places disponibles', 'opac-custom' ), 'class' => 'opac-tag-ok' ],
            'full' => [ 'label' => __( 'Complet', 'opac-custom' ),            'class' => 'opac-tag-full' ],
            'few'  => [ 'label' => __( 'Quelques places', 'opac-custom' ),    'class' => 'opac-tag-few' ],
        ];

        if ( ! isset( $map[ $slug ] ) ) {
            return '';
        }

        return sprintf(
            '<p class="opac-tag %s">%s</p>',
            esc_attr( $map[ $slug ]['class'] ),
            esc_html( $map[ $slug ]['label'] )
        );
    }

    /**
     * Breadcrumb pour les pages single CPT : "Accueil / <Archive label> / <Post title>".
     * Le label de l'archive est lookup'e dynamiquement depuis la declaration
     * du CPT (post_type_object->labels->name) pour rester en sync.
     */
    public static function render_breadcrumb( $attrs, $content, $block ) {
        $post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
        if ( ! $post_id ) {
            return '';
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return '';
        }

        $post_type_obj = get_post_type_object( $post->post_type );
        if ( ! $post_type_obj ) {
            return '';
        }

        $archive_label = $post_type_obj->labels->name ?? '';
        $archive_link  = get_post_type_archive_link( $post->post_type );

        $items = [];
        $items[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( home_url( '/' ) ),
            esc_html__( 'Accueil', 'opac-custom' )
        );

        if ( $archive_link && $archive_label ) {
            $items[] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $archive_link ),
                esc_html( $archive_label )
            );
        }

        $items[] = sprintf( '<span aria-current="page">%s</span>', esc_html( get_the_title( $post_id ) ) );

        return sprintf(
            '<nav class="opac-breadcrumb" aria-label="%s">%s</nav>',
            esc_attr__( 'Fil d\'Ariane', 'opac-custom' ),
            implode( ' <span class="opac-breadcrumb-sep" aria-hidden="true">/</span> ', $items )
        );
    }
}
