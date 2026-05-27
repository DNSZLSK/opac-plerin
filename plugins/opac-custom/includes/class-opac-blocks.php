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

        register_block_type( 'opac/agenda-list', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_agenda_list' ],
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

    /**
     * Liste agenda groupee par mois.
     *
     * Pourquoi un bloc serveur et pas un Query Loop + post-template ?
     * Le groupement par mois ("Juin 2026" comme separator entre les cards)
     * requiert un contexte "post precedent" qui ne se passe pas via
     * block bindings. Server-side, on parse opac_date_event et on insere
     * un <div class="opac-month-label"> quand le mois change.
     *
     * Chaque card est un <a href="permalink"> avec la classe opac-cat-<slug>
     * (premier term de opac_event_cat). Le filtrage par tab (JS client-side)
     * cible cette classe pour show/hide.
     */
    public static function render_agenda_list( $attrs, $content, $block ) {
        $events = get_posts( [
            'post_type'      => 'opac_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'opac_date_event',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ] );

        if ( empty( $events ) ) {
            return '<p class="opac-empty">' . esc_html__( 'Aucun événement programmé pour le moment.', 'opac-custom' ) . '</p>';
        }

        $months_fr = [
            1 => 'Janvier', 2  => 'Février',  3  => 'Mars',     4 => 'Avril',
            5 => 'Mai',     6  => 'Juin',     7  => 'Juillet',  8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        $out        = '<div class="opac-agenda">';
        $last_month = '';

        foreach ( $events as $event ) {
            $date_raw = (string) get_post_meta( $event->ID, 'opac_date_event', true );
            $ts       = $date_raw ? strtotime( $date_raw ) : false;
            if ( ! $ts ) {
                continue;
            }

            $ym = wp_date( 'Y-m', $ts );
            if ( $ym !== $last_month ) {
                $n     = (int) wp_date( 'n', $ts );
                $year  = wp_date( 'Y', $ts );
                $label = ( $months_fr[ $n ] ?? '' ) . ' ' . $year;
                $out  .= '<div class="opac-month-label">' . esc_html( trim( $label ) ) . '</div>';
                $last_month = $ym;
            }

            $terms     = wp_get_post_terms( $event->ID, 'opac_event_cat', [ 'number' => 1 ] );
            $cat_slug  = '';
            $cat_label = '';
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $cat_slug  = $terms[0]->slug;
                $cat_label = $terms[0]->name;
            }

            $n_event   = (int) wp_date( 'n', $ts );
            $year_ev   = wp_date( 'Y', $ts );
            $date_card = ( $months_fr[ $n_event ] ?? '' ) . ' ' . $year_ev;

            $desc = (string) get_post_meta( $event->ID, 'opac_description_courte', true );

            $out .= sprintf(
                '<a class="opac-event%s" href="%s">'
                    . '<span class="opac-event-stripe" aria-hidden="true"></span>'
                    . '<div class="opac-event-body">'
                        . '<div class="opac-event-top">'
                            . '<span class="opac-event-date">%s</span>'
                            . '%s'
                        . '</div>'
                        . '<h3 class="opac-event-name">%s</h3>'
                        . '%s'
                    . '</div>'
                . '</a>',
                $cat_slug ? ' opac-cat-' . esc_attr( sanitize_html_class( $cat_slug ) ) : '',
                esc_url( get_permalink( $event ) ),
                esc_html( trim( $date_card ) ),
                $cat_label ? '<span class="opac-event-cat">' . esc_html( $cat_label ) . '</span>' : '',
                esc_html( get_the_title( $event ) ),
                $desc ? '<p class="opac-event-desc">' . esc_html( $desc ) . '</p>' : ''
            );
        }

        $out .= '</div>';
        return $out;
    }
}
