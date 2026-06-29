<?php
/**
 * OPAC Custom - Meta boxes (formulaires CPT sans Gutenberg)
 *
 * Remplace l'editeur de blocs des 4 CPTs metier (atelier, ephemere, evenement,
 * personne) par un formulaire unique, calque visuellement sur la page OPAC
 * Reglages : champs etiquetes, selects, selecteur d'image integre, un bouton
 * Enregistrer. Concu pour des utilisatrices non-techniques (Katell) qui ne
 * doivent jamais voir Gutenberg ni la sidebar encombree.
 *
 * Le block editor est deja desactive en amont par le retrait du support
 * 'editor' (cf. OPAC_CPTs). Cette classe ajoute le formulaire, nettoie l'ecran
 * (image native, slug, boites tags), et sauvegarde meta + termes + image
 * mise en avant + (pour l'evenement) post_content.
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Meta_Boxes {

    /** CPTs geres par ce formulaire. */
    const POST_TYPES = [ 'opac_atelier', 'opac_stage', 'opac_event', 'opac_person' ];

    /** Cle du nonce du formulaire fiche. */
    const NONCE = 'opac_fiche_nonce';

    public static function boot() {
        add_filter( 'use_block_editor_for_post_type', [ __CLASS__, 'disable_block_editor' ], 10, 2 );
        add_filter( 'enter_title_here', [ __CLASS__, 'title_placeholder' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

        // Priorite 9 : la fiche s'enregistre avant la box creneaux d'OPAC_Admin
        // (priorite 10) pour s'afficher au-dessus d'elle sur l'atelier.
        add_action( 'add_meta_boxes', [ __CLASS__, 'register_boxes' ], 9 );
        add_action( 'add_meta_boxes', [ __CLASS__, 'remove_clutter' ], 99 );

        // post_content de l'evenement : injecte sans recursion au moment de l'insert.
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'inject_event_content' ], 10, 2 );

        foreach ( self::POST_TYPES as $pt ) {
            add_action( 'save_post_' . $pt, [ __CLASS__, 'save' ], 10, 2 );
        }
    }

    /**
     * Coupe l'editeur de blocs pour nos CPTs (en plus du retrait du support
     * 'editor'). Ceinture + bretelles, et documente l'intention.
     */
    public static function disable_block_editor( $use_block_editor, $post_type ) {
        return in_array( $post_type, self::POST_TYPES, true ) ? false : $use_block_editor;
    }

    public static function title_placeholder( $text, $post ) {
        switch ( $post->post_type ) {
            case 'opac_atelier':
                return __( 'Nom de l\'atelier', 'opac-custom' );
            case 'opac_stage':
                return __( 'Nom de l\'atelier éphémère', 'opac-custom' );
            case 'opac_event':
                return __( 'Nom de l\'événement', 'opac-custom' );
            case 'opac_person':
                return __( 'Nom et prénom', 'opac-custom' );
        }
        return $text;
    }

    /* --------------------------------------------------------------------- *
     * Schema des champs
     * --------------------------------------------------------------------- */

    private static function box_title( $post_type ) {
        switch ( $post_type ) {
            case 'opac_atelier':
                return __( 'Fiche de l\'atelier', 'opac-custom' );
            case 'opac_stage':
                return __( 'Fiche de l\'atelier éphémère', 'opac-custom' );
            case 'opac_event':
                return __( 'Fiche de l\'événement', 'opac-custom' );
            case 'opac_person':
                return __( 'Fiche de la personne', 'opac-custom' );
        }
        return __( 'Fiche', 'opac-custom' );
    }

    /** Options du champ "Places" (slug stocke, libelle affiche cote front). */
    private static function places_options() {
        // '— Non précisé —' (admin) + libelles centralises (OPAC_Labels::places()).
        return [ '' => __( '— Non précisé —', 'opac-custom' ) ] + OPAC_Labels::places();
    }

    /**
     * Schema ordonne des champs d'un CPT.
     * Chaque champ : key, label, type, + desc/options/taxonomy/rows optionnels.
     * Types : text, number, date, textarea, select, taxonomy, image, wysiwyg.
     * Cles speciales : '_thumbnail' (image mise en avant), '_content'
     * (post_content via wysiwyg).
     */
    public static function fields_for( $post_type ) {
        switch ( $post_type ) {
            case 'opac_atelier':
                return [
                    [ 'key' => '_thumbnail', 'type' => 'image', 'label' => __( 'Image mise en avant', 'opac-custom' ), 'desc' => __( 'Photo affichée sur la fiche et les listings.', 'opac-custom' ) ],
                    [ 'key' => 'opac_animator', 'type' => 'text', 'label' => __( 'Animateur', 'opac-custom' ) ],
                    [ 'key' => 'opac_description_courte', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Description courte', 'opac-custom' ), 'desc' => __( 'Affichée sur les cartes (accueil, listing des ateliers).', 'opac-custom' ) ],
                    [ 'key' => 'opac_tagline', 'type' => 'textarea', 'rows' => 4, 'label' => __( 'Description longue', 'opac-custom' ), 'desc' => __( 'Affichée en haut de la fiche détaillée de l\'atelier.', 'opac-custom' ) ],
                    [ 'key' => 'opac_tarif_annuel', 'type' => 'number', 'label' => __( 'Tarif annuel (€)', 'opac-custom' ) ],
                    [ 'key' => 'opac_public', 'type' => 'text', 'label' => __( 'Public', 'opac-custom' ), 'desc' => __( 'Ex : Adultes, Enfants 6-10 ans, Tous publics.', 'opac-custom' ) ],
                    [ 'key' => 'opac_places_dispo', 'type' => 'select', 'options' => self::places_options(), 'label' => __( 'Places', 'opac-custom' ) ],
                    [ 'key' => 'opac_notice', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Note spéciale', 'opac-custom' ), 'desc' => __( 'Encart optionnel sur la fiche (ex : matériel à prévoir). Laisser vide pour masquer.', 'opac-custom' ) ],
                    [ 'key' => 'opac_show_gallery', 'type' => 'checkbox', 'label' => __( 'Afficher les réalisations', 'opac-custom' ), 'desc' => __( 'Décochez pour masquer la section Réalisations même si des photos sont liées. La section se masque de toute façon quand aucune photo n\'est liée.', 'opac-custom' ) ],
                ];

            case 'opac_stage':
                return [
                    [ 'key' => '_thumbnail', 'type' => 'image', 'label' => __( 'Image mise en avant', 'opac-custom' ) ],
                    [ 'key' => 'opac_description_courte', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Description courte', 'opac-custom' ), 'desc' => __( 'Affichée sur les cartes (listing des éphémères).', 'opac-custom' ) ],
                    [ 'key' => 'opac_tagline', 'type' => 'textarea', 'rows' => 4, 'label' => __( 'Description longue', 'opac-custom' ), 'desc' => __( 'Affichée en haut de la fiche détaillée.', 'opac-custom' ) ],
                    [ 'key' => 'opac_date_debut', 'type' => 'date', 'label' => __( 'Date de début', 'opac-custom' ) ],
                    [ 'key' => 'opac_date_fin', 'type' => 'date', 'label' => __( 'Date de fin', 'opac-custom' ) ],
                    [ 'key' => 'opac_tarif_seance', 'type' => 'number', 'label' => __( 'Tarif séance (€)', 'opac-custom' ) ],
                    [ 'key' => 'opac_animator', 'type' => 'text', 'label' => __( 'Animateur', 'opac-custom' ) ],
                    [ 'key' => 'opac_public', 'type' => 'text', 'label' => __( 'Public', 'opac-custom' ), 'desc' => __( 'Ex : Adultes, Enfants 6-10 ans, Tous publics.', 'opac-custom' ) ],
                    [ 'key' => 'opac_lieu', 'type' => 'text', 'label' => __( 'Lieu', 'opac-custom' ) ],
                    [ 'key' => 'opac_period', 'type' => 'taxonomy', 'taxonomy' => 'opac_period', 'label' => __( 'Période', 'opac-custom' ), 'desc' => __( 'Classe l\'éphémère dans l\'onglet correspondant de la page éphémères.', 'opac-custom' ) ],
                    [ 'key' => 'opac_places_dispo', 'type' => 'select', 'options' => self::places_options(), 'label' => __( 'Places', 'opac-custom' ) ],
                    [ 'key' => 'opac_notice', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Note spéciale', 'opac-custom' ), 'desc' => __( 'Encart optionnel sur la fiche. Laisser vide pour masquer.', 'opac-custom' ) ],
                ];

            case 'opac_event':
                return [
                    [ 'key' => '_thumbnail', 'type' => 'image', 'label' => __( 'Image mise en avant', 'opac-custom' ) ],
                    [ 'key' => 'opac_date_event', 'type' => 'date', 'label' => __( 'Date', 'opac-custom' ) ],
                    [ 'key' => 'opac_lieu', 'type' => 'text', 'label' => __( 'Lieu', 'opac-custom' ) ],
                    [ 'key' => 'opac_event_cat', 'type' => 'taxonomy', 'taxonomy' => 'opac_event_cat', 'label' => __( 'Catégorie', 'opac-custom' ) ],
                    [ 'key' => 'opac_description_courte', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Description courte', 'opac-custom' ), 'desc' => __( 'Affichée sur les cartes de l\'agenda.', 'opac-custom' ) ],
                    [ 'key' => '_content', 'type' => 'wysiwyg', 'label' => __( 'Description complète', 'opac-custom' ), 'desc' => __( 'Affichée sur la fiche détaillée de l\'événement.', 'opac-custom' ) ],
                ];

            case 'opac_person':
                return [
                    [ 'key' => '_thumbnail', 'type' => 'image', 'label' => __( 'Photo', 'opac-custom' ), 'desc' => __( 'Optionnelle. L\'affichage actuel utilise un avatar à initiales.', 'opac-custom' ) ],
                    [ 'key' => 'opac_role', 'type' => 'text', 'label' => __( 'Rôle / fonction', 'opac-custom' ), 'desc' => __( 'Ex : Céramique, Présidente, Secrétaire.', 'opac-custom' ) ],
                    [ 'key' => 'opac_person_type', 'type' => 'taxonomy', 'taxonomy' => 'opac_person_type', 'label' => __( 'Type', 'opac-custom' ), 'desc' => __( 'Détermine la grille où la personne apparaît sur la page Association.', 'opac-custom' ) ],
                ];
        }
        return [];
    }

    /* --------------------------------------------------------------------- *
     * Rendu
     * --------------------------------------------------------------------- */

    public static function register_boxes() {
        foreach ( self::POST_TYPES as $pt ) {
            add_meta_box(
                'opac_fiche_' . $pt,
                self::box_title( $pt ),
                [ __CLASS__, 'render_box' ],
                $pt,
                'normal',
                'high'
            );
        }
    }

    /**
     * Nettoie l'ecran d'edition : image native (remplacee par le selecteur
     * in-form), slug, et boites "tags" des taxonomies (remplacees par des
     * selects dans la fiche). remove_meta_box est sans effet si la boite
     * n'existe pas : sans risque.
     */
    public static function remove_clutter() {
        foreach ( self::POST_TYPES as $pt ) {
            remove_meta_box( 'postimagediv', $pt, 'side' );
            remove_meta_box( 'slugdiv', $pt, 'normal' );
        }
        remove_meta_box( 'tagsdiv-opac_period', 'opac_stage', 'side' );
        remove_meta_box( 'tagsdiv-opac_event_cat', 'opac_event', 'side' );
        remove_meta_box( 'tagsdiv-opac_person_type', 'opac_person', 'side' );
    }

    public static function render_box( $post ) {
        wp_nonce_field( 'opac_fiche_save', self::NONCE );
        echo '<table class="form-table opac-fiche" role="presentation"><tbody>';
        foreach ( self::fields_for( $post->post_type ) as $field ) {
            self::render_field( $post, $field );
        }
        echo '</tbody></table>';
    }

    private static function render_field( $post, $field ) {
        $type  = $field['type'];
        $key   = $field['key'];
        $label = $field['label'];
        $desc  = isset( $field['desc'] ) ? $field['desc'] : '';

        // Identifiant du controle pour le label[for].
        if ( 'taxonomy' === $type ) {
            $control_id = 'opac_tax_' . $field['taxonomy'];
        } elseif ( 'image' === $type ) {
            $control_id = 'opac_thumbnail_id';
        } elseif ( 'wysiwyg' === $type ) {
            $control_id = 'opac_event_content';
        } else {
            $control_id = $key;
        }

        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr( $control_id ) . '">' . esc_html( $label ) . '</label></th>';
        echo '<td>';

        switch ( $type ) {
            case 'text':
            case 'number':
            case 'date':
                $value      = get_post_meta( $post->ID, $key, true );
                $input_type = ( 'number' === $type ) ? 'number' : ( ( 'date' === $type ) ? 'date' : 'text' );
                $extra      = ( 'number' === $type ) ? ' min="0" step="1"' : '';
                printf(
                    '<input type="%s" id="%s" name="%s" value="%s" class="regular-text"%s />',
                    esc_attr( $input_type ),
                    esc_attr( $key ),
                    esc_attr( $key ),
                    esc_attr( $value ),
                    $extra // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constante litterale
                );
                break;

            case 'textarea':
                $value = get_post_meta( $post->ID, $key, true );
                $rows  = isset( $field['rows'] ) ? (int) $field['rows'] : 4;
                printf(
                    '<textarea id="%s" name="%s" rows="%d" class="large-text">%s</textarea>',
                    esc_attr( $key ),
                    esc_attr( $key ),
                    $rows,
                    esc_textarea( $value )
                );
                break;

            case 'select':
                $value   = get_post_meta( $post->ID, $key, true );
                $options = isset( $field['options'] ) ? $field['options'] : [];
                echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
                foreach ( $options as $val => $lab ) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        esc_attr( $val ),
                        selected( $value, $val, false ),
                        esc_html( $lab )
                    );
                }
                echo '</select>';
                break;

            case 'checkbox':
                $value   = get_post_meta( $post->ID, $key, true );
                $checked = ( '0' !== (string) $value ); // defaut coche (meta vide = affiche)
                printf(
                    '<label><input type="checkbox" id="%s" name="%s" value="1"%s /> %s</label>',
                    esc_attr( $key ),
                    esc_attr( $key ),
                    checked( true, $checked, false ),
                    esc_html__( 'Oui', 'opac-custom' )
                );
                break;

            case 'taxonomy':
                self::render_taxonomy_select( $post, $field['taxonomy'], $control_id );
                break;

            case 'image':
                self::render_image_field( $post, $control_id );
                break;

            case 'wysiwyg':
                wp_editor(
                    $post->post_content,
                    'opac_event_content',
                    [
                        'textarea_name' => 'opac_event_content',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'teeny'         => true,
                        'quicktags'     => false,
                        'tinymce'       => [ 'toolbar1' => 'bold,italic,bullist,numlist,link,undo,redo' ],
                    ]
                );
                break;
        }

        if ( $desc ) {
            echo '<p class="description">' . esc_html( $desc ) . '</p>';
        }
        echo '</td></tr>';
    }

    private static function render_taxonomy_select( $post, $taxonomy, $control_id ) {
        $terms   = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        $current = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
        $current_id = ( ! is_wp_error( $current ) && ! empty( $current ) ) ? (int) $current[0] : 0;

        echo '<select id="' . esc_attr( $control_id ) . '" name="' . esc_attr( $control_id ) . '">';
        echo '<option value="0">' . esc_html__( '— Choisir —', 'opac-custom' ) . '</option>';
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                printf(
                    '<option value="%d"%s>%s</option>',
                    (int) $term->term_id,
                    selected( $current_id, $term->term_id, false ),
                    esc_html( $term->name )
                );
            }
        }
        echo '</select>';
    }

    private static function render_image_field( $post, $control_id ) {
        $thumb_id = (int) get_post_thumbnail_id( $post->ID );
        $img_url  = $thumb_id ? (string) wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
        ?>
        <div class="opac-image-field">
            <div class="opac-img-frame opac-image-preview<?php echo $img_url ? '' : ' is-empty'; ?>">
                <?php if ( $img_url ) : ?>
                    <img src="<?php echo esc_url( $img_url ); ?>" alt="" />
                <?php endif; ?>
            </div>
            <p class="opac-image-actions">
                <button type="button" class="button opac-image-choose"><?php esc_html_e( 'Choisir une image', 'opac-custom' ); ?></button>
                <button type="button" class="button-link opac-image-remove"<?php echo $thumb_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Retirer', 'opac-custom' ); ?></button>
            </p>
            <input type="hidden" id="<?php echo esc_attr( $control_id ); ?>" name="opac_thumbnail_id" value="<?php echo esc_attr( $thumb_id ); ?>" />
        </div>
        <?php
    }

    /* --------------------------------------------------------------------- *
     * Sauvegarde
     * --------------------------------------------------------------------- */

    public static function save( $post_id, $post ) {
        if ( ! isset( $_POST[ self::NONCE ] )
            || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE ] ), 'opac_fiche_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        foreach ( self::fields_for( $post->post_type ) as $field ) {
            $type = $field['type'];
            $key  = $field['key'];

            if ( 'image' === $type ) {
                $thumb_id = isset( $_POST['opac_thumbnail_id'] ) ? absint( $_POST['opac_thumbnail_id'] ) : 0;
                if ( $thumb_id > 0 ) {
                    set_post_thumbnail( $post_id, $thumb_id );
                } else {
                    delete_post_thumbnail( $post_id );
                }
                continue;
            }

            if ( 'wysiwyg' === $type ) {
                // post_content gere par inject_event_content (filtre wp_insert_post_data).
                continue;
            }

            if ( 'taxonomy' === $type ) {
                $taxonomy = $field['taxonomy'];
                $term_id  = isset( $_POST[ 'opac_tax_' . $taxonomy ] ) ? absint( $_POST[ 'opac_tax_' . $taxonomy ] ) : 0;
                wp_set_object_terms( $post_id, $term_id > 0 ? [ $term_id ] : [], $taxonomy, false );
                continue;
            }

            $raw   = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
            $value = self::sanitize_value( $field, $raw );
            update_post_meta( $post_id, $key, $value );
        }
    }

    private static function sanitize_value( $field, $raw ) {
        switch ( $field['type'] ) {
            case 'number':
                return absint( $raw );

            case 'date':
                $val = sanitize_text_field( $raw );
                if ( '' === $val ) {
                    return '';
                }
                if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $val, $m )
                    || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
                    return '';
                }
                return $val;

            case 'select':
                $val     = sanitize_text_field( $raw );
                $allowed = isset( $field['options'] ) ? array_keys( $field['options'] ) : [];
                return in_array( $val, $allowed, true ) ? $val : '';

            case 'textarea':
                return sanitize_textarea_field( $raw );

            case 'checkbox':
                return empty( $raw ) ? 0 : 1;

            default:
                return sanitize_text_field( $raw );
        }
    }

    /**
     * Ecrit le post_content de l'evenement depuis le champ wp_editor du
     * formulaire, au moment de l'insert (filtre wp_insert_post_data) : pas de
     * wp_update_post imbrique, donc pas de recursion, et fonctionne meme sans
     * support 'editor'. $data est attendu slashe par le coeur WP.
     */
    public static function inject_event_content( $data, $postarr ) {
        if ( ! isset( $data['post_type'] ) || 'opac_event' !== $data['post_type'] ) {
            return $data;
        }
        if ( ! isset( $_POST[ self::NONCE ] )
            || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE ] ), 'opac_fiche_save' ) ) {
            return $data;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $data;
        }
        $post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
        if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
            return $data;
        }
        if ( isset( $_POST['opac_event_content'] ) ) {
            $content = wp_kses_post( wp_unslash( $_POST['opac_event_content'] ) );
            $data['post_content'] = wp_slash( $content );
        }
        return $data;
    }

    /* --------------------------------------------------------------------- *
     * Assets
     * --------------------------------------------------------------------- */

    public static function enqueue( $hook ) {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, self::POST_TYPES, true ) ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'opac-admin-media',
            OPAC_CUSTOM_URL . 'assets/js/admin-media.js',
            [ 'jquery' ],
            OPAC_CUSTOM_VERSION,
            true
        );
        wp_localize_script( 'opac-admin-media', 'opacMedia', [
            'title'  => __( 'Choisir une image', 'opac-custom' ),
            'button' => __( 'Utiliser cette image', 'opac-custom' ),
        ] );
    }
}
