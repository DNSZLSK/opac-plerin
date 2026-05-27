<?php
/**
 * OPAC Custom - Admin UI
 *
 * Interfaces admin custom : dashboard widget pour les inscriptions en attente,
 * colonnes custom dans les listes de CPTs, enqueue admin.css.
 *
 * Le menu principal "OPAC" et les meta boxes riches seront ajoutés en M7
 * (module Inscriptions). En P0, on se contente du widget de dashboard.
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Admin {

    public static function boot() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_filter( 'manage_opac_atelier_posts_columns', [ __CLASS__, 'atelier_columns' ] );
        add_action( 'manage_opac_atelier_posts_custom_column', [ __CLASS__, 'atelier_column_content' ], 10, 2 );
        add_filter( 'manage_opac_inscription_posts_columns', [ __CLASS__, 'inscription_columns' ] );
        add_action( 'manage_opac_inscription_posts_custom_column', [ __CLASS__, 'inscription_column_content' ], 10, 2 );
        add_filter( 'post_row_actions', [ __CLASS__, 'inscription_row_actions' ], 10, 2 );
        add_action( 'admin_notices', [ __CLASS__, 'inscription_action_notice' ] );
    }

    public static function enqueue_admin_assets( $hook ) {
        wp_enqueue_style(
            'opac-admin',
            OPAC_CUSTOM_URL . 'assets/css/admin.css',
            [],
            OPAC_CUSTOM_VERSION
        );
    }

    public static function atelier_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['opac_animator'] = __( 'Animateur', 'opac-custom' );
                $new['opac_tarif_annuel'] = __( 'Tarif annuel', 'opac-custom' );
                $new['opac_places_dispo'] = __( 'Places', 'opac-custom' );
            }
        }
        return $new;
    }

    public static function atelier_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'opac_animator':
                echo esc_html( get_post_meta( $post_id, 'opac_animator', true ) );
                break;
            case 'opac_tarif_annuel':
                $tarif = (int) get_post_meta( $post_id, 'opac_tarif_annuel', true );
                echo $tarif > 0 ? esc_html( $tarif . ' € / an' ) : '-';
                break;
            case 'opac_places_dispo':
                $places = get_post_meta( $post_id, 'opac_places_dispo', true );
                echo $places ? esc_html( $places ) : '-';
                break;
        }
    }

    public static function inscription_columns( $columns ) {
        return [
            'cb' => $columns['cb'] ?? '',
            'title' => __( 'Nom', 'opac-custom' ),
            'opac_insc_email' => __( 'Email', 'opac-custom' ),
            'opac_insc_atelier' => __( 'Atelier', 'opac-custom' ),
            'taxonomy-opac_inscription_status' => __( 'Statut', 'opac-custom' ),
            'date' => $columns['date'] ?? __( 'Date', 'opac-custom' ),
        ];
    }

    public static function inscription_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'opac_insc_email':
                $email = get_post_meta( $post_id, 'opac_insc_email', true );
                if ( $email ) {
                    printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
                } else {
                    echo '-';
                }
                break;
            case 'opac_insc_atelier':
                $atelier_id = (int) get_post_meta( $post_id, 'opac_insc_atelier_id', true );
                if ( $atelier_id && get_post( $atelier_id ) ) {
                    printf(
                        '<a href="%s">%s</a>',
                        esc_url( get_edit_post_link( $atelier_id ) ),
                        esc_html( get_the_title( $atelier_id ) )
                    );
                } else {
                    echo '-';
                }
                break;
        }
    }

    /**
     * Row actions Valider / Refuser / Liste d'attente sur la liste admin
     * des inscriptions. Chaque lien declenche admin_post_opac_insc_action
     * (cf. OPAC_Inscriptions::handle_action) qui change le term de statut
     * + envoie un email a l'inscrit.
     *
     * Les actions deja appliquees sont masquees (pas de "Valider" si deja
     * validee), pour eviter les renvois d'email accidentels.
     */
    public static function inscription_row_actions( $actions, $post ) {
        if ( ! $post || $post->post_type !== 'opac_inscription' ) {
            return $actions;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            return $actions;
        }

        $current_terms = wp_get_object_terms( $post->ID, 'opac_inscription_status', [ 'fields' => 'slugs' ] );
        $current = is_wp_error( $current_terms ) ? [] : (array) $current_terms;

        $available = [
            'validee'       => [ 'label' => __( 'Valider', 'opac-custom' ),         'class' => 'opac-row-valider' ],
            'refusee'       => [ 'label' => __( 'Refuser', 'opac-custom' ),         'class' => 'opac-row-refuser' ],
            'liste-attente' => [ 'label' => __( 'Liste d\'attente', 'opac-custom' ), 'class' => 'opac-row-attente' ],
        ];

        $new_actions = [];
        foreach ( $available as $status => $meta ) {
            if ( in_array( $status, $current, true ) ) {
                continue;
            }
            $nonce = wp_create_nonce( 'opac_insc_action_' . $post->ID . '_' . $status );
            $url = add_query_arg(
                [
                    'action'   => 'opac_insc_action',
                    'id'       => $post->ID,
                    'status'   => $status,
                    '_wpnonce' => $nonce,
                ],
                admin_url( 'admin-post.php' )
            );
            $new_actions[ 'opac_' . $status ] = sprintf(
                '<a class="%s" href="%s">%s</a>',
                esc_attr( $meta['class'] ),
                esc_url( $url ),
                esc_html( $meta['label'] )
            );
        }

        return array_merge( $new_actions, $actions );
    }

    /**
     * Notice admin apres une action row (Valider/Refuser/Liste d'attente).
     * Lue depuis ?opac_insc_done=<status> que handle_action ajoute au redirect.
     */
    public static function inscription_action_notice() {
        if ( ! isset( $_GET['opac_insc_done'] ) || ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'opac_inscription' ) {
            return;
        }
        $status = sanitize_key( wp_unslash( $_GET['opac_insc_done'] ) );
        $labels = [
            'validee'       => __( 'Inscription validée. Un email a été envoyé à l\'inscrit.', 'opac-custom' ),
            'refusee'       => __( 'Inscription refusée. Un email a été envoyé à l\'inscrit.', 'opac-custom' ),
            'liste-attente' => __( 'Inscription placée en liste d\'attente. Un email a été envoyé à l\'inscrit.', 'opac-custom' ),
        ];
        $msg = $labels[ $status ] ?? '';
        if ( ! $msg ) {
            return;
        }
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html( $msg )
        );
    }

    public static function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'opac_inscriptions_widget',
            __( 'OPAC - Inscriptions en attente', 'opac-custom' ),
            [ __CLASS__, 'render_dashboard_widget' ]
        );
    }

    public static function render_dashboard_widget() {
        $term = get_term_by( 'slug', 'en-attente', 'opac_inscription_status' );

        if ( ! $term || is_wp_error( $term ) ) {
            echo '<p>' . esc_html__( 'Le statut « En attente » n\'est pas encore créé. Réactivez le plugin pour seeder les termes par défaut.', 'opac-custom' ) . '</p>';
            return;
        }

        $pending = new WP_Query( [
            'post_type' => 'opac_inscription',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'tax_query' => [
                [
                    'taxonomy' => 'opac_inscription_status',
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ],
            ],
        ] );

        $count = (int) $pending->found_posts;

        printf(
            '<p><strong>%d</strong> %s</p>',
            $count,
            esc_html(
                _n(
                    'inscription en attente de validation.',
                    'inscriptions en attente de validation.',
                    $count,
                    'opac-custom'
                )
            )
        );

        if ( $count > 0 ) {
            echo '<ul>';
            while ( $pending->have_posts() ) {
                $pending->the_post();
                $edit_link = get_edit_post_link( get_the_ID() );
                printf(
                    '<li><a href="%s">%s</a></li>',
                    esc_url( $edit_link ),
                    esc_html( get_the_title() )
                );
            }
            echo '</ul>';
            wp_reset_postdata();
        }

        $admin_url = admin_url( 'edit.php?post_type=opac_inscription' );
        printf(
            '<p><a href="%s">%s</a></p>',
            esc_url( $admin_url ),
            esc_html__( 'Voir toutes les inscriptions →', 'opac-custom' )
        );
    }
}
