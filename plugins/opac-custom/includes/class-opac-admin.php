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

    /**
     * Cache (par chargement de page) des emails ayant deja une inscription la
     * saison precedente. Amorce en une seule requete via prime_returning_cache().
     * null = non amorce (contexte hors liste admin des inscriptions).
     *
     * @var array<string,bool>|null
     */
    private static $returning_emails = null;

    public static function boot() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_filter( 'manage_opac_atelier_posts_columns', [ __CLASS__, 'atelier_columns' ] );
        add_action( 'manage_opac_atelier_posts_custom_column', [ __CLASS__, 'atelier_column_content' ], 10, 2 );
        add_filter( 'manage_opac_inscription_posts_columns', [ __CLASS__, 'inscription_columns' ] );
        add_action( 'manage_opac_inscription_posts_custom_column', [ __CLASS__, 'inscription_column_content' ], 10, 2 );
        add_filter( 'post_row_actions', [ __CLASS__, 'inscription_row_actions' ], 10, 2 );
        add_action( 'admin_notices', [ __CLASS__, 'inscription_action_notice' ] );
        add_action( 'restrict_manage_posts', [ __CLASS__, 'inscription_status_filter' ] );
        add_filter( 'posts_clauses', [ __CLASS__, 'inscription_priority_clauses' ], 10, 2 );
        add_filter( 'the_posts', [ __CLASS__, 'prime_returning_cache' ], 10, 2 );

        // Galerie : meta box "atelier associe" + colonnes liste.
        add_action( 'add_meta_boxes', [ __CLASS__, 'gallery_meta_box' ] );
        add_action( 'save_post_opac_gallery_item', [ __CLASS__, 'save_gallery_meta' ], 10, 2 );
        add_filter( 'manage_opac_gallery_item_posts_columns', [ __CLASS__, 'gallery_columns' ] );
        add_action( 'manage_opac_gallery_item_posts_custom_column', [ __CLASS__, 'gallery_column_content' ], 10, 2 );

        // Ateliers : meta box d'edition des creneaux structures (palier 2).
        add_action( 'add_meta_boxes', [ __CLASS__, 'atelier_creneaux_meta_box' ] );
        add_action( 'save_post_opac_atelier', [ __CLASS__, 'save_atelier_creneaux' ], 10, 2 );
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
            'opac_insc_priorite' => __( 'Priorité', 'opac-custom' ),
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
            case 'opac_insc_priorite':
                self::render_priorite_cell( $post_id );
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
            'nochange'      => __( 'Statut déjà appliqué : aucun email renvoyé.', 'opac-custom' ),
        ];
        $msg = $labels[ $status ] ?? '';
        if ( ! $msg ) {
            return;
        }
        $notice_class = ( 'nochange' === $status ) ? 'notice-info' : 'notice-success';
        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr( $notice_class ),
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

    /**
     * Meta box sur opac_gallery_item : choix de l'atelier associe + legende.
     * Katell definit la photo via "Image mise en avant" et selectionne ici
     * l'atelier ou la realisation s'affiche.
     */
    public static function gallery_meta_box() {
        add_meta_box(
            'opac_gallery_link',
            __( 'Réalisation OPAC', 'opac-custom' ),
            [ __CLASS__, 'render_gallery_meta_box' ],
            'opac_gallery_item',
            'side',
            'high'
        );
    }

    public static function render_gallery_meta_box( $post ) {
        wp_nonce_field( 'opac_gallery_meta', 'opac_gallery_meta_nonce' );
        $current  = (int) get_post_meta( $post->ID, 'opac_gallery_atelier_id', true );
        $caption  = (string) get_post_meta( $post->ID, 'opac_gallery_caption', true );
        $ateliers = get_posts( [
            'post_type'      => 'opac_atelier',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        echo '<p><label for="opac_gallery_atelier_id"><strong>' . esc_html__( 'Atelier associé', 'opac-custom' ) . '</strong></label></p>';
        echo '<select id="opac_gallery_atelier_id" name="opac_gallery_atelier_id" style="width:100%">';
        echo '<option value="0">' . esc_html__( '— Aucun —', 'opac-custom' ) . '</option>';
        foreach ( $ateliers as $a ) {
            printf(
                '<option value="%d"%s>%s</option>',
                (int) $a->ID,
                selected( $current, $a->ID, false ),
                esc_html( get_the_title( $a ) )
            );
        }
        echo '</select>';

        echo '<p style="margin-top:12px"><label for="opac_gallery_caption"><strong>' . esc_html__( 'Légende (optionnelle)', 'opac-custom' ) . '</strong></label></p>';
        printf(
            '<input type="text" id="opac_gallery_caption" name="opac_gallery_caption" value="%s" style="width:100%%" />',
            esc_attr( $caption )
        );
        echo '<p class="description" style="margin-top:8px">' . esc_html__( 'Définissez la photo via « Image mise en avant », puis choisissez l\'atelier où elle apparaît.', 'opac-custom' ) . '</p>';
    }

    public static function save_gallery_meta( $post_id, $post ) {
        if ( ! isset( $_POST['opac_gallery_meta_nonce'] )
            || ! wp_verify_nonce( wp_unslash( $_POST['opac_gallery_meta_nonce'] ), 'opac_gallery_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        update_post_meta( $post_id, 'opac_gallery_atelier_id', isset( $_POST['opac_gallery_atelier_id'] ) ? absint( $_POST['opac_gallery_atelier_id'] ) : 0 );
        update_post_meta( $post_id, 'opac_gallery_caption', isset( $_POST['opac_gallery_caption'] ) ? sanitize_text_field( wp_unslash( $_POST['opac_gallery_caption'] ) ) : '' );
    }

    public static function gallery_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( 'title' === $key ) {
                $new['opac_gallery_thumb'] = __( 'Photo', 'opac-custom' );
            }
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['opac_gallery_atelier'] = __( 'Atelier', 'opac-custom' );
            }
        }
        return $new;
    }

    public static function gallery_column_content( $column, $post_id ) {
        if ( 'opac_gallery_thumb' === $column ) {
            echo has_post_thumbnail( $post_id ) ? get_the_post_thumbnail( $post_id, [ 48, 48 ] ) : '—';
        } elseif ( 'opac_gallery_atelier' === $column ) {
            $aid = (int) get_post_meta( $post_id, 'opac_gallery_atelier_id', true );
            echo $aid && get_post( $aid ) ? esc_html( get_the_title( $aid ) ) : '—';
        }
    }

    /**
     * Cellule "Priorité" de la liste des inscriptions : badge Plérinais /
     * Extérieur, commune, type d'adhésion, et indicateur informatif
     * "déjà inscrit l'an dernier" (palier 1 workflow inscription).
     */
    private static function render_priorite_cell( $post_id ) {
        $plerinais   = get_post_meta( $post_id, 'opac_insc_plerinais', true );
        $code_postal = (string) get_post_meta( $post_id, 'opac_insc_code_postal', true );
        $commune     = (string) get_post_meta( $post_id, 'opac_insc_commune', true );
        $adhesion    = (string) get_post_meta( $post_id, 'opac_insc_adhesion', true );

        $is_plerinais = ( '1' === (string) $plerinais || '22190' === $code_postal );

        if ( $is_plerinais ) {
            echo '<span class="opac-badge opac-badge-plerinais">' . esc_html__( 'Plérinais', 'opac-custom' ) . '</span>';
        } elseif ( '' !== $code_postal || '' !== $commune ) {
            echo '<span class="opac-badge opac-badge-exterieur">' . esc_html__( 'Extérieur', 'opac-custom' ) . '</span>';
        }

        $loc = trim( $commune . ' ' . $code_postal );
        if ( '' !== $loc ) {
            echo '<div class="opac-priorite-loc">' . esc_html( $loc ) . '</div>';
        }

        if ( '' !== $adhesion ) {
            $adh_labels = [
                'plerinais' => __( 'Adhésion Plérinais', 'opac-custom' ),
                'exterieur' => __( 'Adhésion Extérieur', 'opac-custom' ),
                'mineur'    => __( 'Adhésion Mineur', 'opac-custom' ),
            ];
            $label = $adh_labels[ $adhesion ] ?? $adhesion;
            echo '<div class="opac-priorite-adh">' . esc_html( $label ) . '</div>';
        }

        if ( self::already_registered_last_season( $post_id ) ) {
            echo '<div><span class="opac-badge opac-badge-reinscription">' . esc_html__( 'Déjà inscrit l\'an dernier', 'opac-custom' ) . '</span></div>';
        }
    }

    /**
     * Amorce, en UNE seule requete pour toute la page admin, l'ensemble des
     * emails ayant une inscription la saison precedente. Evite le N+1 (une
     * requete par ligne) : le cout ne depend ni du nombre de lignes affichees
     * ni du volume total d'inscriptions, uniquement de la taille de la page.
     */
    public static function prime_returning_cache( $posts, $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return $posts;
        }
        if ( 'opac_inscription' !== $query->get( 'post_type' ) ) {
            return $posts;
        }
        self::$returning_emails = [];
        if ( empty( $posts ) ) {
            return $posts;
        }

        // Meta de la page amorcee en une requete, puis lue depuis le cache objet.
        $ids = wp_list_pluck( $posts, 'ID' );
        update_postmeta_cache( $ids );
        $emails = [];
        foreach ( $ids as $id ) {
            $e = strtolower( trim( (string) get_post_meta( $id, 'opac_insc_email', true ) ) );
            if ( '' !== $e ) {
                $emails[ $e ] = true;
            }
        }
        if ( empty( $emails ) ) {
            return $posts;
        }

        list( $start, $end ) = self::previous_season_window();
        global $wpdb;
        $email_list   = array_keys( $emails );
        $placeholders = implode( ',', array_fill( 0, count( $email_list ), '%s' ) );
        $params       = array_merge( $email_list, [ $start, $end ] );
        $sql = $wpdb->prepare(
            "SELECT DISTINCT LOWER(pm_email.meta_value)
             FROM {$wpdb->postmeta} pm_email
             INNER JOIN {$wpdb->postmeta} pm_date
                 ON pm_date.post_id = pm_email.post_id
                 AND pm_date.meta_key = 'opac_insc_date_submitted'
             WHERE pm_email.meta_key = 'opac_insc_email'
               AND LOWER(pm_email.meta_value) IN ($placeholders)
               AND pm_date.meta_value >= %s
               AND pm_date.meta_value < %s",
            $params
        );
        $found = $wpdb->get_col( $sql );
        if ( $found ) {
            self::$returning_emails = array_fill_keys( array_map( 'strtolower', $found ), true );
        }
        return $posts;
    }

    /**
     * Vrai si l'email de cette inscription apparait deja la saison precedente.
     * Lit le cache amorce par prime_returning_cache() : aucune requete par ligne.
     * Indicateur informatif, vide la premiere saison (pas d'historique en ligne).
     */
    private static function already_registered_last_season( $post_id ) {
        if ( null === self::$returning_emails ) {
            return false;
        }
        $email = strtolower( trim( (string) get_post_meta( $post_id, 'opac_insc_email', true ) ) );
        if ( '' === $email || ! isset( self::$returning_emails[ $email ] ) ) {
            return false;
        }
        // Ne pas badger une ligne qui est elle-meme de la saison precedente (sa
        // propre date tombe dans la fenetre) : on cible les demandes recentes.
        $own_date = (string) get_post_meta( $post_id, 'opac_insc_date_submitted', true );
        list( $start, $end ) = self::previous_season_window();
        if ( $own_date >= $start && $own_date < $end ) {
            return false;
        }
        return true;
    }

    /**
     * Bornes [debut, fin] de la saison precedente au format mysql.
     * Saison academique : 1er septembre N -> 1er septembre N+1.
     */
    private static function previous_season_window() {
        $now   = current_time( 'timestamp' );
        $year  = (int) wp_date( 'Y', $now );
        $month = (int) wp_date( 'n', $now );
        $season_start = ( $month >= 9 ) ? $year : $year - 1;
        $current = sprintf( '%04d-09-01 00:00:00', $season_start );
        $prev    = sprintf( '%04d-09-01 00:00:00', $season_start - 1 );
        return [ $prev, $current ];
    }

    /**
     * Filtre "statut" dans la barre d'outils de la liste des inscriptions.
     * La taxonomie opac_inscription_status n'etant pas hierarchique, WordPress
     * n'ajoute pas ce filtre automatiquement : on le rend nous-memes.
     */
    public static function inscription_status_filter( $post_type ) {
        if ( 'opac_inscription' !== $post_type ) {
            return;
        }
        $terms = get_terms( [
            'taxonomy'   => 'opac_inscription_status',
            'hide_empty' => false,
        ] );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return;
        }
        $current = isset( $_GET['opac_inscription_status'] ) ? sanitize_key( wp_unslash( $_GET['opac_inscription_status'] ) ) : '';
        echo '<select name="opac_inscription_status">';
        echo '<option value="">' . esc_html__( 'Tous les statuts', 'opac-custom' ) . '</option>';
        foreach ( $terms as $term ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $term->slug ),
                selected( $current, $term->slug, false ),
                esc_html( $term->name )
            );
        }
        echo '</select>';
    }

    /**
     * Tri de la liste d'attente : Plerinais d'abord (flag opac_insc_plerinais),
     * puis chronologique (premier arrive). LEFT JOIN pour inclure aussi les
     * inscriptions sans le flag. Applique uniquement quand on filtre sur le
     * statut "liste-attente".
     */
    public static function inscription_priority_clauses( $clauses, $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return $clauses;
        }
        if ( 'opac_inscription' !== $query->get( 'post_type' ) ) {
            return $clauses;
        }
        if ( 'liste-attente' !== $query->get( 'opac_inscription_status' ) ) {
            return $clauses;
        }
        global $wpdb;
        $clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} AS opac_pri ON ( {$wpdb->posts}.ID = opac_pri.post_id AND opac_pri.meta_key = 'opac_insc_plerinais' ) ";
        $clauses['orderby'] = " COALESCE(opac_pri.meta_value+0, 0) DESC, {$wpdb->posts}.post_date ASC ";
        return $clauses;
    }

    /**
     * Meta box d'edition des creneaux structures sur l'atelier (palier 2).
     * Remplace l'edition via champs personnalises bruts : tableau repetable
     * jour / debut / fin / tarif / capacite / note, stocke dans opac_creneaux.
     */
    public static function atelier_creneaux_meta_box() {
        add_meta_box(
            'opac_atelier_creneaux',
            __( 'Créneaux hebdomadaires', 'opac-custom' ),
            [ __CLASS__, 'render_atelier_creneaux_box' ],
            'opac_atelier',
            'normal',
            'high'
        );
    }

    public static function render_atelier_creneaux_box( $post ) {
        wp_nonce_field( 'opac_atelier_creneaux', 'opac_atelier_creneaux_nonce' );
        $creneaux = get_post_meta( $post->ID, 'opac_creneaux', true );
        if ( ! is_array( $creneaux ) ) {
            $creneaux = [];
        }
        ?>
        <p class="description">
            <?php esc_html_e( 'Un créneau par ligne (jour + horaires). Tarif et capacité servent au formulaire d\'inscription et à l\'affichage des places (capacité 0 = pas de limite). Si vide, l\'ancien champ texte « Créneaux » reste utilisé.', 'opac-custom' ); ?>
        </p>
        <table class="widefat opac-creneaux-editor">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Jour', 'opac-custom' ); ?></th>
                    <th><?php esc_html_e( 'Début', 'opac-custom' ); ?></th>
                    <th><?php esc_html_e( 'Fin', 'opac-custom' ); ?></th>
                    <th><?php esc_html_e( 'Tarif (€)', 'opac-custom' ); ?></th>
                    <th><?php esc_html_e( 'Capacité', 'opac-custom' ); ?></th>
                    <th><?php esc_html_e( 'Note', 'opac-custom' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="opac-creneaux-rows">
                <?php
                $i = 0;
                foreach ( $creneaux as $row ) {
                    echo self::creneau_row_html( (string) $i, is_array( $row ) ? $row : [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    $i++;
                }
                ?>
            </tbody>
        </table>
        <p><button type="button" class="button" id="opac-creneaux-add"><?php esc_html_e( '+ Ajouter un créneau', 'opac-custom' ); ?></button></p>
        <script type="text/template" id="opac-creneaux-tpl"><?php echo self::creneau_row_html( '__i__', [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
        <script>
        (function(){
            var add=document.getElementById('opac-creneaux-add'),
                rows=document.getElementById('opac-creneaux-rows'),
                tpl=document.getElementById('opac-creneaux-tpl');
            if(!add||!rows||!tpl){return;}
            var n=rows.children.length;
            add.addEventListener('click',function(){
                var tmp=document.createElement('tbody');
                tmp.innerHTML=tpl.innerHTML.replace(/__i__/g,'n'+(n++)).trim();
                if(tmp.firstElementChild){rows.appendChild(tmp.firstElementChild);}
            });
            rows.addEventListener('click',function(e){
                var b=e.target.closest('.opac-creneau-del');
                if(b){e.preventDefault();var tr=b.closest('tr');if(tr){tr.parentNode.removeChild(tr);}}
            });
        })();
        </script>
        <?php
    }

    /**
     * Markup d'une ligne de l'editeur de creneaux. Reutilise pour les lignes
     * existantes (index numerique) et le template JS (index '__i__').
     */
    private static function creneau_row_html( $index, $row ) {
        $jours = [
            'lundi'    => __( 'Lundi', 'opac-custom' ),
            'mardi'    => __( 'Mardi', 'opac-custom' ),
            'mercredi' => __( 'Mercredi', 'opac-custom' ),
            'jeudi'    => __( 'Jeudi', 'opac-custom' ),
            'vendredi' => __( 'Vendredi', 'opac-custom' ),
            'samedi'   => __( 'Samedi', 'opac-custom' ),
            'dimanche' => __( 'Dimanche', 'opac-custom' ),
        ];
        $id       = isset( $row['id'] ) ? (string) $row['id'] : '';
        $jour     = isset( $row['jour'] ) ? (string) $row['jour'] : '';
        $debut    = isset( $row['debut'] ) ? (string) $row['debut'] : '';
        $fin      = isset( $row['fin'] ) ? (string) $row['fin'] : '';
        $tarif    = isset( $row['tarif'] ) && '' !== $row['tarif'] ? (int) $row['tarif'] : '';
        $capacite = isset( $row['capacite'] ) && '' !== $row['capacite'] ? (int) $row['capacite'] : '';
        $note     = isset( $row['note'] ) ? (string) $row['note'] : '';
        $base     = 'opac_creneaux[' . $index . ']';

        ob_start();
        ?>
        <tr>
            <td>
                <input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $id ); ?>" />
                <select name="<?php echo esc_attr( $base ); ?>[jour]">
                    <?php foreach ( $jours as $val => $lab ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $jour, $val ); ?>><?php echo esc_html( $lab ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="time" name="<?php echo esc_attr( $base ); ?>[debut]" value="<?php echo esc_attr( $debut ); ?>" /></td>
            <td><input type="time" name="<?php echo esc_attr( $base ); ?>[fin]" value="<?php echo esc_attr( $fin ); ?>" /></td>
            <td><input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( $base ); ?>[tarif]" value="<?php echo esc_attr( $tarif ); ?>" /></td>
            <td><input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( $base ); ?>[capacite]" value="<?php echo esc_attr( $capacite ); ?>" /></td>
            <td><input type="text" name="<?php echo esc_attr( $base ); ?>[note]" value="<?php echo esc_attr( $note ); ?>" /></td>
            <td><button type="button" class="button-link opac-creneau-del" aria-label="<?php esc_attr_e( 'Retirer le créneau', 'opac-custom' ); ?>">&times;</button></td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Sauvegarde des creneaux structures. Ignore les lignes sans horaire,
     * attribue un id stable (preserve a l'edition) pour le comptage palier 3.
     */
    public static function save_atelier_creneaux( $post_id, $post ) {
        if ( ! isset( $_POST['opac_atelier_creneaux_nonce'] )
            || ! wp_verify_nonce( wp_unslash( $_POST['opac_atelier_creneaux_nonce'] ), 'opac_atelier_creneaux' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $raw = ( isset( $_POST['opac_creneaux'] ) && is_array( $_POST['opac_creneaux'] ) )
            ? wp_unslash( $_POST['opac_creneaux'] )
            : [];
        $jours_ok = [ 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' ];
        $clean = [];
        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $debut = isset( $row['debut'] ) ? sanitize_text_field( $row['debut'] ) : '';
            $fin   = isset( $row['fin'] ) ? sanitize_text_field( $row['fin'] ) : '';
            if ( '' === $debut && '' === $fin ) {
                continue; // ligne vide, ignoree
            }
            $jour = isset( $row['jour'] ) ? sanitize_key( $row['jour'] ) : '';
            if ( ! in_array( $jour, $jours_ok, true ) ) {
                $jour = 'lundi';
            }
            $id = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
            if ( '' === $id ) {
                $id = uniqid( 'c', false );
            }
            $clean[] = [
                'id'       => $id,
                'jour'     => $jour,
                'debut'    => $debut,
                'fin'      => $fin,
                'tarif'    => isset( $row['tarif'] ) ? absint( $row['tarif'] ) : 0,
                'capacite' => isset( $row['capacite'] ) ? absint( $row['capacite'] ) : 0,
                'note'     => isset( $row['note'] ) ? sanitize_text_field( $row['note'] ) : '',
            ];
        }
        if ( empty( $clean ) ) {
            delete_post_meta( $post_id, 'opac_creneaux' );
        } else {
            update_post_meta( $post_id, 'opac_creneaux', $clean );
        }
    }
}
