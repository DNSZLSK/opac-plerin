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

        // Collage dans l'editeur visuel : retire les polices heritees du texte
        // colle (Word, page web) pour que la police du site soit respectee.
        add_filter( 'tiny_mce_before_init', [ __CLASS__, 'strip_pasted_fonts' ] );

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

    /**
     * Nettoie les styles herites au collage dans l'editeur visuel.
     *
     * invalid_styles agit au niveau du parseur/serialiseur de TinyMCE : les
     * proprietes listees sont retirees du contenu colle ET du contenu re-edite,
     * independamment du plugin de collage charge (fiable en mode teeny). Les
     * options paste_* renforcent le nettoyage cote Word (classes mso-*, styles
     * webkit). On garde volontairement gras, italique, listes et liens : seules
     * la police, la taille et la couleur sont supprimees, car la barre d'outils
     * ne propose aucun choix de police (toute font collee est donc parasite).
     */
    public static function strip_pasted_fonts( $init ) {
        $init['invalid_styles']                = 'font font-family font-size color background background-color line-height';
        $init['paste_strip_class_attributes']  = 'all';
        $init['paste_remove_styles_if_webkit'] = true;
        $init['paste_merge_formats']           = true;
        return $init;
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

    /** Case commune aux trois types de fiches possedant un carrousel. */
    private static function gallery_visibility_field() {
        return [
            'key'   => 'opac_show_gallery',
            'type'  => 'checkbox',
            'label' => __( 'Afficher le carrousel', 'opac-custom' ),
            'desc'  => __( 'Décochez pour masquer les photos. Le carrousel se masque automatiquement lorsqu’aucune photo n’est ajoutée.', 'opac-custom' ),
        ];
    }

    /**
     * Schema ordonne des champs d'un CPT.
     * Chaque champ : key, label, type, + desc/options/taxonomy/rows optionnels.
     * Types : text, number, date, textarea, select, taxonomy, image, gallery,
     * wysiwyg.
     * Cles speciales : '_thumbnail' (image mise en avant), '_content'
     * (post_content via wysiwyg).
     */
    public static function fields_for( $post_type ) {
        switch ( $post_type ) {
            case 'opac_atelier':
                return [
                    [ 'key' => '_thumbnail', 'type' => 'image', 'label' => __( 'Image mise en avant', 'opac-custom' ), 'desc' => __( 'Photo affichée sur la fiche et les listings.', 'opac-custom' ) ],
                    [ 'key' => 'opac_animator_id', 'type' => 'person_select', 'role' => 'pedagogique', 'fallback_key' => 'opac_animator', 'label' => __( 'Animateur', 'opac-custom' ), 'desc' => __( 'Choisissez un animateur dans la liste (section « Animateurs » de l\'Équipe). « Autre / saisie libre » permet de saisir un intervenant ponctuel hors équipe. Laisser vide si l\'atelier n\'a pas d\'animateur nommé.', 'opac-custom' ) ],
                    [ 'key' => 'opac_description_courte', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Description courte (aperçu dans les listes)', 'opac-custom' ), 'desc' => __( 'Résumé d\'1 à 2 lignes, affiché dans les listes (accueil, page Ateliers) avant de cliquer sur l\'atelier.', 'opac-custom' ) ],
                    [ 'key' => 'opac_tagline', 'type' => 'textarea', 'rows' => 4, 'label' => __( 'Description longue (sur la page de l\'atelier)', 'opac-custom' ), 'desc' => __( 'Texte de présentation complet, affiché en haut de la page de l\'atelier (quand on a cliqué dessus).', 'opac-custom' ) ],
                    [ 'key' => 'opac_tarif_annuel', 'type' => 'number', 'label' => __( 'Tarif annuel (€)', 'opac-custom' ) ],
                    [ 'key' => 'opac_audience', 'type' => 'audience_select', 'taxonomy' => 'opac_audience', 'label' => __( 'Public', 'opac-custom' ), 'desc' => __( 'Catégorie de public visée. Gérez la liste depuis le menu Publics.', 'opac-custom' ) ],
                    [ 'key' => 'opac_public_precision', 'type' => 'text', 'label' => __( 'Précision d\'âge', 'opac-custom' ), 'desc' => __( 'Optionnel. Ex : 6-10 ans, à partir de 8 ans. Affiché à côté de la catégorie (ex : « Enfants 6-10 ans »).', 'opac-custom' ) ],
                    [ 'key' => 'opac_places_dispo', 'type' => 'select', 'options' => self::places_options(), 'label' => __( 'Places', 'opac-custom' ) ],
                    [ 'key' => 'opac_notice', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Note spéciale (encart sur la page)', 'opac-custom' ), 'desc' => __( 'Encart optionnel affiché sur la page de l\'atelier (ex : matériel à prévoir). Laisser vide pour masquer.', 'opac-custom' ) ],
                    [ 'key' => 'opac_gallery_ids', 'type' => 'gallery', 'label' => __( 'Photos du carrousel', 'opac-custom' ), 'desc' => __( 'Ajoutez plusieurs photos depuis votre ordinateur ou la médiathèque. Faites-les glisser pour changer leur ordre.', 'opac-custom' ) ],
                    self::gallery_visibility_field(),
                ];

            case 'opac_stage':
                return [
                    [ 'key' => '_thumbnail', 'type' => 'image', 'label' => __( 'Image mise en avant', 'opac-custom' ) ],
                    [ 'key' => 'opac_description_courte', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Description courte (aperçu dans les listes)', 'opac-custom' ), 'desc' => __( 'Résumé d\'1 à 2 lignes, affiché dans la liste des éphémères avant de cliquer.', 'opac-custom' ) ],
                    [ 'key' => 'opac_tagline', 'type' => 'textarea', 'rows' => 4, 'label' => __( 'Description longue (sur la page de l\'éphémère)', 'opac-custom' ), 'desc' => __( 'Texte de présentation complet, affiché en haut de la page de l\'éphémère (quand on a cliqué dessus).', 'opac-custom' ) ],
                    [ 'key' => 'opac_date_debut', 'type' => 'date', 'label' => __( 'Date de début', 'opac-custom' ) ],
                    [ 'key' => 'opac_date_fin', 'type' => 'date', 'label' => __( 'Date de fin', 'opac-custom' ) ],
                    [ 'key' => 'opac_tarif_seance', 'type' => 'number', 'label' => __( 'Tarif séance (€)', 'opac-custom' ) ],
                    [ 'key' => 'opac_animator_id', 'type' => 'person_select', 'role' => 'pedagogique', 'fallback_key' => 'opac_animator', 'label' => __( 'Animateur', 'opac-custom' ), 'desc' => __( 'Choisissez un animateur dans la liste (section « Animateurs » de l\'Équipe). « Autre / saisie libre » permet de saisir un intervenant ponctuel hors équipe. Laisser vide si l\'éphémère n\'a pas d\'animateur nommé.', 'opac-custom' ) ],
                    [ 'key' => 'opac_audience', 'type' => 'audience_select', 'taxonomy' => 'opac_audience', 'label' => __( 'Public', 'opac-custom' ), 'desc' => __( 'Catégorie de public visée. Gérez la liste depuis le menu Publics.', 'opac-custom' ) ],
                    [ 'key' => 'opac_public_precision', 'type' => 'text', 'label' => __( 'Précision d\'âge', 'opac-custom' ), 'desc' => __( 'Optionnel. Ex : 6-10 ans, à partir de 8 ans. Affiché à côté de la catégorie.', 'opac-custom' ) ],
                    [ 'key' => 'opac_lieu', 'type' => 'text', 'label' => __( 'Lieu', 'opac-custom' ) ],
                    [ 'key' => 'opac_period', 'type' => 'taxonomy', 'taxonomy' => 'opac_period', 'label' => __( 'Période', 'opac-custom' ), 'desc' => __( 'Classe l\'éphémère dans l\'onglet correspondant de la page éphémères. Pré-cochée automatiquement selon la date de début, corrigez si besoin.', 'opac-custom' ) ],
                    [ 'key' => 'opac_places_dispo', 'type' => 'select', 'options' => self::places_options(), 'label' => __( 'Places', 'opac-custom' ) ],
                    [ 'key' => 'opac_notice', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Note spéciale (encart sur la page)', 'opac-custom' ), 'desc' => __( 'Encart optionnel affiché sur la page de l\'éphémère. Laisser vide pour masquer.', 'opac-custom' ) ],
                    [ 'key' => 'opac_gallery_ids', 'type' => 'gallery', 'label' => __( 'Photos du carrousel', 'opac-custom' ), 'desc' => __( 'Ajoutez plusieurs photos depuis votre ordinateur ou la médiathèque. Faites-les glisser pour changer leur ordre.', 'opac-custom' ) ],
                    self::gallery_visibility_field(),
                ];

            case 'opac_event':
                return [
                    [ 'key' => '_thumbnail', 'type' => 'image', 'label' => __( 'Image mise en avant', 'opac-custom' ) ],
                    [ 'key' => 'opac_date_event', 'type' => 'date', 'label' => __( 'Date', 'opac-custom' ) ],
                    [ 'key' => 'opac_lieu', 'type' => 'text', 'label' => __( 'Lieu', 'opac-custom' ) ],
                    [ 'key' => 'opac_event_cat', 'type' => 'taxonomy', 'taxonomy' => 'opac_event_cat', 'label' => __( 'Catégorie', 'opac-custom' ) ],
                    [ 'key' => 'opac_description_courte', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Description courte (aperçu dans l\'agenda)', 'opac-custom' ), 'desc' => __( 'Résumé affiché dans l\'agenda avant de cliquer sur l\'événement.', 'opac-custom' ) ],
                    [ 'key' => '_content', 'type' => 'wysiwyg', 'label' => __( 'Description complète (sur la page de l\'événement)', 'opac-custom' ), 'desc' => __( 'Texte complet affiché sur la page de l\'événement (quand on a cliqué dessus).', 'opac-custom' ) ],
                    [ 'key' => 'opac_gallery_ids', 'type' => 'gallery', 'label' => __( 'Photos du carrousel', 'opac-custom' ), 'desc' => __( 'Ajoutez plusieurs photos depuis votre ordinateur ou la médiathèque. Faites-les glisser pour changer leur ordre.', 'opac-custom' ) ],
                    self::gallery_visibility_field(),
                ];

            case 'opac_person':
                return [
                    [ 'key' => '_thumbnail', 'type' => 'image', 'label' => __( 'Photo', 'opac-custom' ), 'desc' => __( 'Optionnelle. Si vous ajoutez une photo, elle remplace l\'avatar à initiales. Sans photo, un avatar coloré avec les initiales s\'affiche automatiquement.', 'opac-custom' ) ],
                    [ 'key' => 'opac_role', 'type' => 'text', 'label' => __( 'Fonction affichée (sous le nom)', 'opac-custom' ), 'desc' => __( 'Texte affiché sous le nom de la personne sur la page Association. Ex : Céramique, Présidente, Secrétaire.', 'opac-custom' ) ],
                    [ 'key' => 'opac_person_type', 'type' => 'taxonomy', 'taxonomy' => 'opac_person_type', 'label' => __( 'Sections où l\'afficher (Animateurs, Bureau...)', 'opac-custom' ), 'desc' => __( 'Cochez une ou plusieurs sections : la personne apparaît dans chacune sur la page Association.', 'opac-custom' ) ],
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
        remove_meta_box( 'tagsdiv-opac_audience', 'opac_atelier', 'side' );
        remove_meta_box( 'tagsdiv-opac_audience', 'opac_stage', 'side' );
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
        } elseif ( 'audience_select' === $type ) {
            $control_id = 'opac_tax_' . $field['taxonomy'];
        } elseif ( 'image' === $type ) {
            $control_id = 'opac_thumbnail_id';
        } elseif ( 'wysiwyg' === $type ) {
            $control_id = 'opac_event_content';
        } else {
            $control_id = $key;
        }

        echo '<tr>';
        // Un groupe de checkboxes (taxonomy) n'a pas de controle unique a cibler :
        // libelle en texte simple plutot qu'un label[for] qui pointe dans le vide.
        if ( in_array( $type, [ 'taxonomy', 'gallery' ], true ) ) {
            echo '<th scope="row">' . esc_html( $label ) . '</th>';
        } else {
            echo '<th scope="row"><label for="' . esc_attr( $control_id ) . '">' . esc_html( $label ) . '</label></th>';
        }
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
                self::render_taxonomy_checkboxes( $post, $field['taxonomy'], $control_id );
                break;

            case 'person_select':
                self::render_person_select( $post, $field );
                break;

            case 'audience_select':
                self::render_audience_select( $post, $field );
                break;

            case 'image':
                self::render_image_field( $post, $control_id );
                break;

            case 'gallery':
                self::render_gallery_field( $post, $control_id );
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

    private static function render_taxonomy_checkboxes( $post, $taxonomy, $control_id ) {
        $terms   = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        $current = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
        $current = is_wp_error( $current ) ? [] : array_map( 'intval', $current );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            echo '<p class="description">' . esc_html__( 'Aucun type disponible.', 'opac-custom' ) . '</p>';
            return;
        }

        // Cases a cocher (et non un select) : une personne peut cumuler des roles,
        // ex. Bureau + Conseil d'administration, et apparait alors dans chaque
        // section correspondante de la page Association.
        // data-term-slug : permet à admin-fiche.js de pré-cocher la bonne période
        // à partir de la date de début (mapping mois -> slug), sans dépendre des
        // term_id (qui varient d'une install à l'autre).
        echo '<fieldset class="opac-tax-checkboxes">';
        foreach ( $terms as $term ) {
            printf(
                '<label><input type="checkbox" name="%1$s[]" value="%2$d" data-term-slug="%3$s"%4$s /> %5$s</label>',
                esc_attr( $control_id ),
                (int) $term->term_id,
                esc_attr( $term->slug ),
                checked( in_array( (int) $term->term_id, $current, true ), true, false ),
                esc_html( $term->name )
            );
        }
        echo '</fieldset>';
    }

    /**
     * Déroulant d'animateur : liste les personnes de l'Équipe portant le rôle
     * demandé (terme opac_person_type, ex. "pedagogique"/Animateurs), plus une
     * entrée « Autre / saisie libre » qui révèle un champ texte pour un
     * intervenant ponctuel hors équipe. Stocke l'ID choisi dans $field['key']
     * (opac_animator_id) ; le nom résolu à l'affichage. La saisie libre est
     * conservée dans la meta $field['fallback_key'] (opac_animator).
     */
    private static function render_person_select( $post, $field ) {
        $key          = $field['key'];
        $fallback_key = isset( $field['fallback_key'] ) ? $field['fallback_key'] : 'opac_animator';
        $role         = isset( $field['role'] ) ? $field['role'] : '';

        $current_id    = (int) get_post_meta( $post->ID, $key, true );
        $fallback_text = (string) get_post_meta( $post->ID, $fallback_key, true );

        // Mode courant : personne liée, saisie libre (texte présent sans ID,
        // couvre aussi les fiches créées avant le picker), ou rien.
        if ( $current_id > 0 ) {
            $mode = 'person';
        } elseif ( '' !== $fallback_text ) {
            $mode = 'libre';
        } else {
            $mode = 'none';
        }

        $people = $role ? get_posts( [
            'post_type'      => 'opac_person',
            'post_status'    => 'publish',
            'numberposts'    => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'tax_query'      => [ [
                'taxonomy' => 'opac_person_type',
                'field'    => 'slug',
                'terms'    => $role,
            ] ],
            'suppress_filters' => false,
        ] ) : [];

        $ids = array_map( static function ( $p ) { return (int) $p->ID; }, $people );

        echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" class="opac-person-select">';
        echo '<option value="">' . esc_html__( '— Choisir —', 'opac-custom' ) . '</option>';
        foreach ( $people as $p ) {
            printf(
                '<option value="%d"%s>%s</option>',
                (int) $p->ID,
                selected( $current_id, (int) $p->ID, false ),
                esc_html( get_the_title( $p ) )
            );
        }
        // Personne liée mais absente de la liste (rôle retiré, ou dépubliée) :
        // on préserve la sélection au lieu de la perdre silencieusement.
        if ( $current_id > 0 && ! in_array( $current_id, $ids, true ) ) {
            $lost = get_post( $current_id );
            if ( $lost && 'opac_person' === $lost->post_type ) {
                printf(
                    '<option value="%d" selected>%s</option>',
                    (int) $current_id,
                    esc_html( get_the_title( $lost ) )
                );
            }
        }
        printf(
            '<option value="__libre__"%s>%s</option>',
            selected( 'libre', $mode, false ),
            esc_html__( 'Autre / saisie libre…', 'opac-custom' )
        );
        echo '</select>';

        // Champ texte révélé par admin-fiche.js quand « Autre » est choisi.
        // Rendu visible d'entrée si on est déjà en mode libre (fallback JS off).
        printf(
            '<p class="opac-animator-libre js-opac-animator-libre"%s><input type="text" name="%s" value="%s" class="regular-text" placeholder="%s" /></p>',
            'libre' === $mode ? '' : ' style="display:none"',
            esc_attr( $fallback_key ),
            esc_attr( $fallback_text ),
            esc_attr__( 'Nom de l\'intervenant', 'opac-custom' )
        );
    }

    /**
     * Déroulant simple à choix unique sur une taxonomie (ici le Public visé) :
     * remplace la saisie libre par un vocabulaire contrôlé et extensible. La
     * précision d'âge éventuelle est un champ texte séparé (opac_public_precision).
     */
    private static function render_audience_select( $post, $field ) {
        $taxonomy = $field['taxonomy'];
        $terms    = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        $current  = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
        $current  = ( is_wp_error( $current ) || empty( $current ) ) ? 0 : (int) $current[0];

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            echo '<p class="description">' . esc_html__( 'Aucun public disponible.', 'opac-custom' ) . '</p>';
            return;
        }

        $name = 'opac_tax_' . $taxonomy;
        echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
        echo '<option value="">' . esc_html__( '— Choisir —', 'opac-custom' ) . '</option>';
        foreach ( $terms as $term ) {
            printf(
                '<option value="%d"%s>%s</option>',
                (int) $term->term_id,
                selected( $current, (int) $term->term_id, false ),
                esc_html( $term->name )
            );
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

    /** Selecteur multiple ordonne pour le carrousel de la fiche. */
    private static function render_gallery_field( $post, $control_id ) {
        $ids = OPAC_Gallery::attachment_ids( $post->ID );
        ?>
        <div class="opac-gallery-field" id="<?php echo esc_attr( $control_id ); ?>">
            <ul class="opac-gallery-selection" aria-live="polite">
                <?php foreach ( $ids as $attachment_id ) : ?>
                    <li class="opac-gallery-selection-item" data-id="<?php echo (int) $attachment_id; ?>">
                        <span class="dashicons dashicons-move opac-gallery-drag" aria-hidden="true"></span>
                        <?php echo wp_get_attachment_image( $attachment_id, 'medium', false, [ 'loading' => 'lazy' ] ); ?>
                        <span class="opac-gallery-image-title"><?php echo esc_html( get_the_title( $attachment_id ) ); ?></span>
                        <button type="button" class="button-link-delete opac-gallery-remove"><?php esc_html_e( 'Retirer', 'opac-custom' ); ?></button>
                        <input type="hidden" name="opac_gallery_ids[]" value="<?php echo (int) $attachment_id; ?>" />
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="opac-gallery-empty"<?php echo $ids ? ' style="display:none"' : ''; ?>><?php esc_html_e( 'Aucune photo dans ce carrousel.', 'opac-custom' ); ?></p>
            <p><button type="button" class="button opac-gallery-add"><?php esc_html_e( 'Ajouter des photos', 'opac-custom' ); ?></button></p>
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

            if ( 'gallery' === $type ) {
                $raw_ids = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : [];
                update_post_meta( $post_id, $key, OPAC_Gallery::sanitize_attachment_ids( $raw_ids ) );
                continue;
            }

            if ( 'wysiwyg' === $type ) {
                // post_content gere par inject_event_content (filtre wp_insert_post_data).
                continue;
            }

            if ( 'taxonomy' === $type ) {
                $taxonomy = $field['taxonomy'];
                $raw_ids  = isset( $_POST[ 'opac_tax_' . $taxonomy ] ) ? (array) wp_unslash( $_POST[ 'opac_tax_' . $taxonomy ] ) : [];
                $term_ids = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );
                wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
                continue;
            }

            // Déroulant à choix unique sur une taxonomie (Public).
            if ( 'audience_select' === $type ) {
                $taxonomy = $field['taxonomy'];
                $raw_id   = isset( $_POST[ 'opac_tax_' . $taxonomy ] ) ? absint( wp_unslash( $_POST[ 'opac_tax_' . $taxonomy ] ) ) : 0;
                $valid    = ( $raw_id > 0 && ! is_wp_error( get_term( $raw_id, $taxonomy ) ) && get_term( $raw_id, $taxonomy ) );
                wp_set_object_terms( $post_id, $valid ? [ $raw_id ] : [], $taxonomy, false );
                continue;
            }

            // Déroulant animateur : ID de personne, ou saisie libre en fallback.
            if ( 'person_select' === $type ) {
                $fallback_key = isset( $field['fallback_key'] ) ? $field['fallback_key'] : 'opac_animator';
                $choice       = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

                if ( '__libre__' === $choice ) {
                    // Intervenant hors équipe : on garde le texte, pas d'ID.
                    $text = isset( $_POST[ $fallback_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $fallback_key ] ) ) : '';
                    update_post_meta( $post_id, $key, 0 );
                    update_post_meta( $post_id, $fallback_key, $text );
                    continue;
                }

                $person_id = absint( $choice );
                if ( $person_id > 0 && 'opac_person' === get_post_type( $person_id ) ) {
                    // Personne liée : l'ID fait foi, on vide le texte libre pour
                    // que le nom soit toujours résolu depuis la fiche (source unique).
                    update_post_meta( $post_id, $key, $person_id );
                    update_post_meta( $post_id, $fallback_key, '' );
                } else {
                    // Aucun animateur.
                    update_post_meta( $post_id, $key, 0 );
                    update_post_meta( $post_id, $fallback_key, '' );
                }
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

        wp_enqueue_script(
            'opac-admin-gallery',
            OPAC_CUSTOM_URL . 'assets/js/admin-gallery.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            filemtime( OPAC_CUSTOM_PATH . 'assets/js/admin-gallery.js' ) ?: OPAC_CUSTOM_VERSION,
            true
        );
        wp_localize_script( 'opac-admin-gallery', 'opacGallery', [
            'title'       => __( 'Choisir les photos du carrousel', 'opac-custom' ),
            'button'      => __( 'Ajouter au carrousel', 'opac-custom' ),
            'remove'      => __( 'Retirer', 'opac-custom' ),
            'empty'       => __( 'Aucune photo dans ce carrousel.', 'opac-custom' ),
            'untitled'    => __( 'Photo sans titre', 'opac-custom' ),
        ] );

        // Comportements de la fiche : saisie libre d'animateur révélée à la
        // demande, et pré-sélection de la période d'un éphémère d'après sa date.
        wp_enqueue_script(
            'opac-admin-fiche',
            OPAC_CUSTOM_URL . 'assets/js/admin-fiche.js',
            [],
            filemtime( OPAC_CUSTOM_PATH . 'assets/js/admin-fiche.js' ) ?: OPAC_CUSTOM_VERSION,
            true
        );
        wp_localize_script( 'opac-admin-fiche', 'opacFiche', [
            'dateRange'    => __( 'La date de fin doit être postérieure ou égale à la date de début.', 'opac-custom' ),
            'timeRange'    => __( 'L’heure de fin doit être postérieure à l’heure de début.', 'opac-custom' ),
            'sessionRange' => __( 'La séance doit être comprise entre les dates de début et de fin.', 'opac-custom' ),
            'duplicate'    => __( 'Cette séance ou ce créneau existe déjà.', 'opac-custom' ),
        ] );
    }
}
