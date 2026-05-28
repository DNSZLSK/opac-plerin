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

        register_block_type( 'opac/team-grid', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_team_grid' ],
            'attributes'      => [
                'type'  => [ 'type' => 'string', 'default' => '' ],
                'title' => [ 'type' => 'string', 'default' => '' ],
                'cols'  => [ 'type' => 'number', 'default' => 3 ],
            ],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/contact-form', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_contact_form' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/inscription-button', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_inscription_button' ],
            'uses_context'    => [ 'postId', 'postType' ],
            'attributes'      => [
                'label'   => [ 'type' => 'string', 'default' => 'S\'inscrire' ],
                'variant' => [ 'type' => 'string', 'default' => 'primary' ],
            ],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/inscription-form', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_inscription_form' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/coord-block', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_coord_block' ],
            'attributes'      => [
                'variant' => [ 'type' => 'string', 'default' => 'compact' ],
            ],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/upcoming-events', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_upcoming_events' ],
            'attributes'      => [
                'count' => [ 'type' => 'number', 'default' => 2 ],
            ],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/statuts-link', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_statuts_link' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/adhesion-line', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_adhesion_line' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/gallery-grid', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_gallery_grid' ],
            'uses_context'    => [ 'postId', 'postType' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );
    }

    public static function render_statuts_link( $attrs, $content, $block ) {
        $url = class_exists( 'OPAC_Settings' ) ? (string) OPAC_Settings::get( 'opac_org_statuts_pdf_url' ) : '';
        if ( ! $url ) {
            return '';
        }
        return sprintf(
            '<p class="has-text-align-center opac-statuts-link" style="margin-top:24px"><a href="%s">%s</a></p>',
            esc_url( $url ),
            esc_html__( 'Télécharger les statuts (PDF) →', 'opac-custom' )
        );
    }

    /**
     * Ligne des tarifs d'adhesion annuelle, lue depuis OPAC > Reglages.
     * Remplace le texte en dur de l'archive ateliers : si Katell change un
     * tarif dans le panneau, la ligne suit automatiquement.
     */
    public static function render_adhesion_line( $attrs, $content, $block ) {
        if ( ! class_exists( 'OPAC_Settings' ) ) {
            return '';
        }
        $p = (int) OPAC_Settings::get( 'opac_adhesion_plerinais' );
        $e = (int) OPAC_Settings::get( 'opac_adhesion_exterieur' );
        $m = (int) OPAC_Settings::get( 'opac_adhesion_mineur' );
        return sprintf(
            '<p class="has-text-align-center opac-info-bar">%s : <strong>%d € %s</strong> · <strong>%d € %s</strong> · <strong>%d € %s</strong></p>',
            esc_html__( 'Adhésion annuelle', 'opac-custom' ),
            $p, esc_html__( 'Plérinais', 'opac-custom' ),
            $e, esc_html__( 'extérieur', 'opac-custom' ),
            $m, esc_html__( 'mineur', 'opac-custom' )
        );
    }

    /**
     * Grille des realisations (opac_gallery_item) liees a l'atelier courant
     * via le meta opac_gallery_atelier_id. Rend la section complete (titre +
     * grille) seulement s'il existe au moins une realisation, sinon '' pour
     * masquer la section. Reutilise les classes .opac-gallery-grid /
     * .opac-gallery-item (cadre opac-img-frame) deja stylees.
     */
    public static function render_gallery_grid( $attrs, $content, $block ) {
        $post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
        if ( ! $post_id ) {
            return '';
        }

        $items = get_posts( [
            'post_type'      => 'opac_gallery_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order date',
            'order'          => 'ASC',
            'meta_key'       => 'opac_gallery_atelier_id',
            'meta_value'     => $post_id,
        ] );
        if ( empty( $items ) ) {
            return '';
        }

        // Carrousel : toutes les vignettes dans un track scrollable (scroll-snap),
        // navigables aux fleches ou au swipe. Chaque vignette est un <button>
        // portant data-full (grande image) pour la lightbox (cf. opac.js).
        // Vignettes en taille 'medium' (affichees petites) + decoding async.
        $cards    = '';
        $rendered = 0;
        foreach ( $items as $item ) {
            if ( ! has_post_thumbnail( $item->ID ) ) {
                continue;
            }
            $caption  = (string) get_post_meta( $item->ID, 'opac_gallery_caption', true );
            $alt      = $caption ?: get_the_title( $item->ID );
            $thumb_id = get_post_thumbnail_id( $item->ID );
            $full     = (string) wp_get_attachment_image_url( $thumb_id, 'large' );
            $img      = get_the_post_thumbnail( $item->ID, 'medium', [ 'alt' => $alt, 'loading' => 'lazy', 'decoding' => 'async' ] );
            $cards   .= sprintf(
                '<button type="button" class="opac-gallery-item" data-full="%s" data-caption="%s" aria-label="%s">%s</button>',
                esc_url( $full ),
                esc_attr( $caption ),
                esc_attr( sprintf( __( 'Agrandir la photo : %s', 'opac-custom' ), $alt ) ),
                $img
            );
            $rendered++;
        }
        if ( 0 === $rendered ) {
            return '';
        }

        return sprintf(
            '<section class="wp-block-group alignwide opac-section-padded opac-gallery-section" style="border-top-color:#e8e5e0;border-top-width:1px;border-top-style:solid">'
                . '<h2 class="wp-block-heading opac-section-title has-display-font-family">%s</h2>'
                . '<div class="opac-gallery-carousel">'
                    . '<button type="button" class="opac-gallery-nav opac-gallery-prev" aria-label="%s" hidden>‹</button>'
                    . '<div class="opac-gallery-track">%s</div>'
                    . '<button type="button" class="opac-gallery-nav opac-gallery-next" aria-label="%s">›</button>'
                . '</div>'
            . '</section>',
            esc_html__( 'Réalisations', 'opac-custom' ),
            esc_attr__( 'Photos précédentes', 'opac-custom' ),
            $cards,
            esc_attr__( 'Photos suivantes', 'opac-custom' )
        );
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

    /**
     * Grid d'equipe filtree par opac_person_type.
     * Attributes : type (slug taxonomy), title (titre H2 affiche), cols (3 par defaut).
     *
     * Pourquoi un bloc serveur ? Query Loop native peut afficher des opac_person,
     * mais pour filtrer par taxonomy + appliquer un layout grid avec avatars
     * a initiales calculees, c'est plus simple et lisible cote serveur.
     */
    public static function render_team_grid( $attrs, $content, $block ) {
        $type  = isset( $attrs['type'] ) ? sanitize_key( $attrs['type'] ) : '';
        $title = isset( $attrs['title'] ) ? (string) $attrs['title'] : '';
        $cols  = isset( $attrs['cols'] ) ? max( 1, (int) $attrs['cols'] ) : 3;

        if ( ! $type ) {
            return '';
        }

        $persons = get_posts( [
            'post_type'      => 'opac_person',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => 'opac_person_type',
                    'field'    => 'slug',
                    'terms'    => $type,
                ],
            ],
        ] );

        if ( empty( $persons ) ) {
            return '';
        }

        $out = '<section class="opac-team-section">';
        if ( $title ) {
            $out .= '<h2 class="opac-section-title">' . esc_html( $title ) . '</h2>';
        }
        $out .= '<div class="opac-team-grid is-cols-' . (int) $cols . '">';

        foreach ( $persons as $person ) {
            $name     = get_the_title( $person );
            $role     = (string) get_post_meta( $person->ID, 'opac_role', true );
            $initials = (string) get_post_meta( $person->ID, 'opac_initials', true );
            if ( ! $initials ) {
                $initials = self::compute_initials( $name );
            }

            // Rotation deterministe sur 8 couleurs basee sur le hash du nom.
            $color_index = ( abs( crc32( $name ) ) % 8 ) + 1;

            $out .= sprintf(
                '<div class="opac-person">'
                    . '<div class="opac-person-avatar opac-person-color-%d" aria-hidden="true">%s</div>'
                    . '<div class="opac-person-name">%s</div>'
                    . '%s'
                . '</div>',
                $color_index,
                esc_html( $initials ),
                esc_html( $name ),
                $role ? '<div class="opac-person-role">' . esc_html( $role ) . '</div>' : ''
            );
        }

        $out .= '</div></section>';
        return $out;
    }

    /**
     * Form de contact custom, leger.
     * Champs : nom, prenom, email, telephone, sujet (select), message.
     * Securite : nonce WP + honeypot off-screen.
     * Submit : POST vers admin-post.php avec action=opac_contact, handled
     * par OPAC_Contact::handle_submit qui valide + wp_mail + redirect.
     */
    public static function render_contact_form( $attrs, $content, $block ) {
        $notice = '';
        if ( isset( $_GET['envoye'] ) && $_GET['envoye'] === '1' ) {
            $notice = '<div class="opac-form-notice is-success" role="status" aria-live="polite">'
                . esc_html__( 'Votre message a bien été envoyé. Nous vous répondrons rapidement.', 'opac-custom' )
                . '</div>';
        } elseif ( isset( $_GET['erreur'] ) ) {
            $err = sanitize_key( wp_unslash( $_GET['erreur'] ) );
            $err_labels = [
                'champs'  => __( 'Merci de remplir tous les champs obligatoires.', 'opac-custom' ),
                'email'   => __( 'L\'adresse email saisie n\'est pas valide.', 'opac-custom' ),
                'envoi'   => __( 'L\'envoi a échoué. Merci de réessayer ou de nous contacter par téléphone.', 'opac-custom' ),
                'nonce'   => __( 'Session expirée, merci de soumettre à nouveau le formulaire.', 'opac-custom' ),
                'doublon' => __( 'Un message vient d\'être envoyé. Merci de patienter un instant avant d\'en renvoyer un.', 'opac-custom' ),
            ];
            $msg = $err_labels[ $err ] ?? __( 'Une erreur est survenue.', 'opac-custom' );
            $notice = '<div class="opac-form-notice is-error" role="alert" aria-live="assertive">' . esc_html( $msg ) . '</div>';
        }

        // Sujets dropdown : lus depuis le panel admin OPAC > Reglages.
        $subjects = class_exists( 'OPAC_Settings' )
            ? OPAC_Settings::contact_subjects()
            : [ 'renseignement' => 'Renseignement général', 'autre' => 'Autre' ];

        $action_url = esc_url( admin_url( 'admin-post.php' ) );
        $nonce      = wp_nonce_field( 'opac_contact_submit', 'opac_contact_nonce', true, false );

        $out  = $notice;
        $out .= '<form class="opac-form-card" method="post" action="' . $action_url . '">';
        $out .= '<input type="hidden" name="action" value="opac_contact" />';
        $out .= $nonce;
        // Honeypot : champ off-screen, doit rester vide. Si rempli = bot.
        $out .= '<div class="opac-honeypot" aria-hidden="true">'
            . '<label>Site web<input type="text" name="opac_hp_website" tabindex="-1" autocomplete="off" /></label>'
            . '</div>';

        $out .= '<div class="opac-form-row-2">';
        $out .= '<div class="opac-form-row"><label for="opac-nom">' . esc_html__( 'Nom', 'opac-custom' ) . '</label>'
            . '<input class="opac-form-input" type="text" id="opac-nom" name="opac_nom" required /></div>';
        $out .= '<div class="opac-form-row"><label for="opac-prenom">' . esc_html__( 'Prénom', 'opac-custom' ) . '</label>'
            . '<input class="opac-form-input" type="text" id="opac-prenom" name="opac_prenom" required /></div>';
        $out .= '</div>';

        $out .= '<div class="opac-form-row"><label for="opac-email">' . esc_html__( 'Email', 'opac-custom' ) . '</label>'
            . '<input class="opac-form-input" type="email" id="opac-email" name="opac_email" required /></div>';

        $out .= '<div class="opac-form-row"><label for="opac-tel">' . esc_html__( 'Téléphone', 'opac-custom' ) . '</label>'
            . '<input class="opac-form-input" type="tel" id="opac-tel" name="opac_telephone" /></div>';

        $out .= '<div class="opac-form-row"><label for="opac-sujet">' . esc_html__( 'Sujet', 'opac-custom' ) . '</label>'
            . '<select class="opac-form-input" id="opac-sujet" name="opac_sujet" required>';
        foreach ( $subjects as $val => $lab ) {
            $out .= '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
        }
        $out .= '</select></div>';

        $out .= '<div class="opac-form-row"><label for="opac-message">' . esc_html__( 'Message', 'opac-custom' ) . '</label>'
            . '<textarea class="opac-form-input opac-form-textarea" id="opac-message" name="opac_message" rows="6" required></textarea></div>';

        $out .= '<button type="submit" class="opac-form-btn">' . esc_html__( 'Envoyer le message', 'opac-custom' ) . '</button>';

        $out .= '</form>';
        return $out;
    }

    /**
     * Bouton "S'inscrire" qui pointe vers /inscription/?atelier=X ou ?stage=X
     * selon le contexte du post courant. Resout le pb : wp:button standard
     * ne peut pas avoir une URL dynamique avec query arg dependant du post.
     */
    public static function render_inscription_button( $attrs, $content, $block ) {
        $post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
        if ( ! $post_id ) {
            return '';
        }

        $post_type = get_post_type( $post_id );
        $param     = '';
        if ( $post_type === 'opac_atelier' ) {
            $param = 'atelier';
        } elseif ( $post_type === 'opac_stage' ) {
            $param = 'stage';
        }

        if ( ! $param ) {
            return '';
        }

        $inscription_page = get_page_by_path( 'inscription' );
        $base_url = $inscription_page ? get_permalink( $inscription_page ) : home_url( '/inscription/' );
        $href     = add_query_arg( $param, $post_id, $base_url );

        $label   = isset( $attrs['label'] )   ? (string) $attrs['label']   : __( 'S\'inscrire', 'opac-custom' );
        $variant = isset( $attrs['variant'] ) ? sanitize_key( $attrs['variant'] ) : 'primary';

        $btn_class = 'wp-block-button is-style-opac-' . esc_attr( $variant );

        return sprintf(
            '<div class="wp-block-buttons">'
                . '<div class="%s">'
                    . '<a class="wp-block-button__link wp-element-button" href="%s">%s</a>'
                . '</div>'
            . '</div>',
            $btn_class,
            esc_url( $href ),
            esc_html( $label )
        );
    }

    /**
     * Formulaire d'inscription frontend.
     *
     * Pre-remplit le contexte depuis ?atelier=ID ou ?stage=ID dans l'URL.
     * Si ni l'un ni l'autre : affiche un select de tous les ateliers/stages.
     * Pour atelier a l'annee : select des creneaux disponibles parses depuis
     * opac_creneaux_text (multilignes "Lundi 14h30 - 17h30").
     *
     * Securite : nonce + honeypot + checkbox RGPD obligatoire.
     * Submit : POST vers admin-post.php, action=opac_inscription.
     */
    public static function render_inscription_form( $attrs, $content, $block ) {
        $notice = '';
        if ( isset( $_GET['envoye'] ) && $_GET['envoye'] === '1' ) {
            $notice = '<div class="opac-form-notice is-success" role="status" aria-live="polite">'
                . esc_html__( 'Votre demande d\'inscription a bien été enregistrée. Katell ou Laurence vous contactera prochainement pour confirmation.', 'opac-custom' )
                . '</div>';
        } elseif ( isset( $_GET['erreur'] ) ) {
            $err = sanitize_key( wp_unslash( $_GET['erreur'] ) );
            $err_labels = [
                'champs'      => __( 'Merci de remplir tous les champs obligatoires.', 'opac-custom' ),
                'email'       => __( 'L\'adresse email saisie n\'est pas valide.', 'opac-custom' ),
                'atelier'     => __( 'Merci de sélectionner un atelier ou un stage.', 'opac-custom' ),
                'rgpd'        => __( 'Vous devez accepter l\'utilisation de vos données pour soumettre la demande.', 'opac-custom' ),
                'doublon'     => __( 'Une demande a déjà été enregistrée récemment. Merci de patienter quelques instants.', 'opac-custom' ),
                'enregistrement' => __( 'L\'enregistrement a échoué. Merci de réessayer ou de nous contacter par téléphone.', 'opac-custom' ),
                'nonce'       => __( 'Session expirée, merci de soumettre à nouveau le formulaire.', 'opac-custom' ),
            ];
            $msg = $err_labels[ $err ] ?? __( 'Une erreur est survenue.', 'opac-custom' );
            $notice = '<div class="opac-form-notice is-error" role="alert" aria-live="assertive">' . esc_html( $msg ) . '</div>';
        }

        // Contexte pre-rempli depuis l'URL.
        $atelier_id = isset( $_GET['atelier'] ) ? absint( $_GET['atelier'] ) : 0;
        $stage_id   = isset( $_GET['stage'] )   ? absint( $_GET['stage'] )   : 0;

        $context_html = '';
        $hidden_inputs = '';
        $creneaux_lines = [];

        if ( $atelier_id && get_post_type( $atelier_id ) === 'opac_atelier' ) {
            $titre  = get_the_title( $atelier_id );
            $tarif  = (int) get_post_meta( $atelier_id, 'opac_tarif_annuel', true );
            $cren   = (string) get_post_meta( $atelier_id, 'opac_creneaux_text', true );
            if ( $cren ) {
                $lines = preg_split( '/\r?\n/', $cren );
                foreach ( $lines as $l ) {
                    $l = trim( $l );
                    if ( $l !== '' ) {
                        $creneaux_lines[] = $l;
                    }
                }
            }
            $context_html = sprintf(
                '<div class="opac-form-context">'
                    . '<div class="opac-form-context-label">%s</div>'
                    . '<div class="opac-form-context-name">%s</div>'
                    . '%s'
                . '</div>',
                esc_html__( 'Inscription pour atelier à l\'année', 'opac-custom' ),
                esc_html( $titre ),
                $tarif > 0 ? '<div class="opac-form-context-tarif">' . sprintf( esc_html__( 'Tarif annuel : %d € + adhésion', 'opac-custom' ), $tarif ) . '</div>' : ''
            );
            $hidden_inputs = '<input type="hidden" name="opac_atelier_id" value="' . esc_attr( $atelier_id ) . '" />';
        } elseif ( $stage_id && get_post_type( $stage_id ) === 'opac_stage' ) {
            $titre = get_the_title( $stage_id );
            $tarif = (int) get_post_meta( $stage_id, 'opac_tarif_seance', true );
            $context_html = sprintf(
                '<div class="opac-form-context">'
                    . '<div class="opac-form-context-label">%s</div>'
                    . '<div class="opac-form-context-name">%s</div>'
                    . '%s'
                . '</div>',
                esc_html__( 'Inscription pour atelier éphémère', 'opac-custom' ),
                esc_html( $titre ),
                $tarif > 0 ? '<div class="opac-form-context-tarif">' . sprintf( esc_html__( 'Tarif séance : %d € + adhésion', 'opac-custom' ), $tarif ) . '</div>' : ''
            );
            $hidden_inputs = '<input type="hidden" name="opac_stage_id" value="' . esc_attr( $stage_id ) . '" />';
        }

        $action_url = esc_url( admin_url( 'admin-post.php' ) );
        $nonce      = wp_nonce_field( 'opac_inscription_submit', 'opac_inscription_nonce', true, false );

        // Notice de phase (gating souple : informatif, ne bloque jamais le formulaire).
        $phase_notice = '';
        if ( class_exists( 'OPAC_Settings' ) ) {
            $phase  = OPAC_Settings::inscription_phase();
            $d_rein = (string) OPAC_Settings::get( 'opac_insc_date_reinscription' );
            $d_ouv  = (string) OPAC_Settings::get( 'opac_insc_date_ouverture' );
            $fmt    = static function ( $d ) {
                $ts = $d ? strtotime( $d ) : false;
                return $ts ? wp_date( 'j F Y', $ts ) : '';
            };
            $phase_msg = '';
            if ( 'avant' === $phase ) {
                $phase_msg = $d_rein
                    ? sprintf( __( 'Les inscriptions ouvriront le %s. Vous pouvez déjà préparer votre demande.', 'opac-custom' ), $fmt( $d_rein ) )
                    : __( 'Les inscriptions ouvriront prochainement.', 'opac-custom' );
            } elseif ( 'reinscription' === $phase ) {
                $phase_msg = $d_ouv
                    ? sprintf( __( 'Réinscriptions prioritaires en cours pour les adhérents déjà inscrits. Les nouvelles inscriptions ouvrent le %s.', 'opac-custom' ), $fmt( $d_ouv ) )
                    : __( 'Réinscriptions prioritaires en cours pour les adhérents déjà inscrits.', 'opac-custom' );
            } elseif ( 'fermee' === $phase ) {
                $phase_msg = __( 'Les inscriptions de la saison sont closes. Vous pouvez tout de même envoyer une demande : nous vous recontacterons.', 'opac-custom' );
            }
            if ( $phase_msg ) {
                $phase_notice = '<div class="opac-form-notice is-info" role="status">' . esc_html( $phase_msg ) . '</div>';
            }
        }

        $out  = $notice . $phase_notice;
        $out .= '<form class="opac-form-card opac-inscription-form" method="post" action="' . $action_url . '">';
        $out .= '<input type="hidden" name="action" value="opac_inscription" />';
        $out .= $nonce;
        $out .= '<div class="opac-honeypot" aria-hidden="true">'
            . '<label>Site web<input type="text" name="opac_hp_website" tabindex="-1" autocomplete="off" /></label>'
            . '</div>';

        $out .= $context_html;
        $out .= $hidden_inputs;

        // Si pas de contexte pre-rempli : select atelier/stage.
        if ( ! $hidden_inputs ) {
            $ateliers = get_posts( [
                'post_type'      => 'opac_atelier',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ] );
            $stages = get_posts( [
                'post_type'      => 'opac_stage',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ] );

            $out .= '<div class="opac-form-row"><label for="opac-cible">' . esc_html__( 'Atelier choisi', 'opac-custom' ) . ' *</label>';
            $out .= '<select class="opac-form-input" id="opac-cible" name="opac_cible" required>';
            $out .= '<option value="">' . esc_html__( 'Sélectionner...', 'opac-custom' ) . '</option>';
            if ( $ateliers ) {
                $out .= '<optgroup label="' . esc_attr__( 'Ateliers à l\'année', 'opac-custom' ) . '">';
                foreach ( $ateliers as $a ) {
                    $out .= '<option value="atelier:' . (int) $a->ID . '">' . esc_html( get_the_title( $a ) ) . '</option>';
                }
                $out .= '</optgroup>';
            }
            if ( $stages ) {
                $out .= '<optgroup label="' . esc_attr__( 'Ateliers éphémères', 'opac-custom' ) . '">';
                foreach ( $stages as $s ) {
                    $out .= '<option value="stage:' . (int) $s->ID . '">' . esc_html( get_the_title( $s ) ) . '</option>';
                }
                $out .= '</optgroup>';
            }
            $out .= '</select></div>';
        }

        // Creneaux pour atelier a l'annee (si plusieurs disponibles).
        if ( $atelier_id && count( $creneaux_lines ) > 0 ) {
            $out .= '<div class="opac-form-row"><label for="opac-creneau">' . esc_html__( 'Créneau choisi', 'opac-custom' ) . ' *</label>';
            $out .= '<select class="opac-form-input" id="opac-creneau" name="opac_creneau" required>';
            $out .= '<option value="">' . esc_html__( 'Sélectionner un créneau...', 'opac-custom' ) . '</option>';
            foreach ( $creneaux_lines as $line ) {
                $out .= '<option value="' . esc_attr( $line ) . '">' . esc_html( $line ) . '</option>';
            }
            $out .= '</select></div>';
        }

        $out .= '<div class="opac-form-row-2">';
        $out .= '<div class="opac-form-row"><label for="opac-nom">' . esc_html__( 'Nom', 'opac-custom' ) . ' *</label>'
            . '<input class="opac-form-input" type="text" id="opac-nom" name="opac_nom" required /></div>';
        $out .= '<div class="opac-form-row"><label for="opac-prenom">' . esc_html__( 'Prénom', 'opac-custom' ) . ' *</label>'
            . '<input class="opac-form-input" type="text" id="opac-prenom" name="opac_prenom" required /></div>';
        $out .= '</div>';

        $out .= '<div class="opac-form-row-2">';
        $out .= '<div class="opac-form-row"><label for="opac-email">' . esc_html__( 'Email', 'opac-custom' ) . ' *</label>'
            . '<input class="opac-form-input" type="email" id="opac-email" name="opac_email" required /></div>';
        $out .= '<div class="opac-form-row"><label for="opac-tel">' . esc_html__( 'Téléphone', 'opac-custom' ) . '</label>'
            . '<input class="opac-form-input" type="tel" id="opac-tel" name="opac_telephone" /></div>';
        $out .= '</div>';

        // Code postal + commune (sert a la priorite Plerinais en admin).
        $out .= '<div class="opac-form-row-2">';
        $out .= '<div class="opac-form-row"><label for="opac-cp">' . esc_html__( 'Code postal', 'opac-custom' ) . ' *</label>'
            . '<input class="opac-form-input" type="text" id="opac-cp" name="opac_code_postal" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required /></div>';
        $out .= '<div class="opac-form-row"><label for="opac-commune">' . esc_html__( 'Commune', 'opac-custom' ) . '</label>'
            . '<input class="opac-form-input" type="text" id="opac-commune" name="opac_commune" /></div>';
        $out .= '</div>';

        // Type d'adhesion (info pratique pour rappel montant).
        $out .= '<div class="opac-form-row"><label>' . esc_html__( 'Type d\'adhésion', 'opac-custom' ) . '</label>';
        $out .= '<div class="opac-form-radios">';
        // Tarifs adhesion lus depuis le panel admin OPAC > Reglages.
        $tarif_p = class_exists( 'OPAC_Settings' ) ? (int) OPAC_Settings::get( 'opac_adhesion_plerinais' ) : 15;
        $tarif_e = class_exists( 'OPAC_Settings' ) ? (int) OPAC_Settings::get( 'opac_adhesion_exterieur' ) : 30;
        $tarif_m = class_exists( 'OPAC_Settings' ) ? (int) OPAC_Settings::get( 'opac_adhesion_mineur' )    : 10;
        $adhesions = [
            'plerinais' => sprintf( __( 'Plérinais (%d €)', 'opac-custom' ), $tarif_p ),
            'exterieur' => sprintf( __( 'Extérieur (%d €)', 'opac-custom' ), $tarif_e ),
            'mineur'    => sprintf( __( 'Mineur (%d €)', 'opac-custom' ), $tarif_m ),
        ];
        foreach ( $adhesions as $val => $lab ) {
            $checked = ( $val === 'plerinais' ) ? ' checked' : '';
            $out .= '<label class="opac-form-radio"><input type="radio" name="opac_adhesion" value="' . esc_attr( $val ) . '"' . $checked . ' /> ' . esc_html( $lab ) . '</label>';
        }
        $out .= '</div></div>';

        $out .= '<div class="opac-form-row"><label for="opac-message">' . esc_html__( 'Message (optionnel)', 'opac-custom' ) . '</label>'
            . '<textarea class="opac-form-input opac-form-textarea" id="opac-message" name="opac_message" rows="4"></textarea></div>';

        // RGPD checkbox obligatoire.
        $out .= '<div class="opac-form-rgpd"><label>'
            . '<input type="checkbox" name="opac_rgpd" value="1" required /> '
            . esc_html__( 'J\'accepte que ces données soient utilisées par l\'OPAC pour traiter ma demande d\'inscription (RGPD).', 'opac-custom' )
            . '</label></div>';

        $out .= '<button type="submit" class="opac-form-btn">' . esc_html__( 'Envoyer ma demande', 'opac-custom' ) . '</button>';
        $out .= '</form>';

        return $out;
    }

    /**
     * Bloc coordonnees centralisees. Lit toutes les data depuis OPAC_Settings
     * options (tel, email, adresse, horaires, reseaux, statuts). 3 variants :
     *   - compact  : pour footer brand (nom + adresse simple)
     *   - infos    : pour page Association (grid 2x2 cards)
     *   - sidebar  : pour page Contact (4 cards verticales)
     */
    public static function render_coord_block( $attrs, $content, $block ) {
        if ( ! class_exists( 'OPAC_Settings' ) ) {
            return '';
        }
        $variant = isset( $attrs['variant'] ) ? sanitize_key( $attrs['variant'] ) : 'compact';

        $street   = OPAC_Settings::get( 'opac_org_address_street' );
        $postal   = OPAC_Settings::get( 'opac_org_address_postal' );
        $city     = OPAC_Settings::get( 'opac_org_address_city' );
        $tel_acc  = OPAC_Settings::get( 'opac_org_phone_accueil' );
        $tel_adm  = OPAC_Settings::get( 'opac_org_phone_admin' );
        $email    = OPAC_Settings::get( 'opac_org_email' );
        $hours    = OPAC_Settings::get( 'opac_org_hours' );
        $name     = OPAC_Settings::get( 'opac_org_name' );
        $legal    = OPAC_Settings::get( 'opac_org_legal_name' );
        // Reseaux sociaux : icones officielles (helper), masquage si vide.
        $reseaux_html = self::social_links_html();
        if ( '' === $reseaux_html ) {
            $reseaux_html = '<span class="opac-empty-inline">' . esc_html__( 'À venir', 'opac-custom' ) . '</span>';
        }

        switch ( $variant ) {
            case 'infos':
                // Page Association : 2x2 grid cards.
                return sprintf(
                    '<div class="opac-infos-grid">'
                        . '<div class="opac-info-card"><h3>%s</h3><p>%s<br/>%s %s</p></div>'
                        . '<div class="opac-info-card"><h3>%s</h3><p>%s</p></div>'
                        . '<div class="opac-info-card"><h3>%s</h3><p>%s : %s<br/>%s : %s</p></div>'
                        . '<div class="opac-info-card"><h3>%s</h3><p>%s<br/>%s</p></div>'
                    . '</div>',
                    esc_html__( 'Adresse', 'opac-custom' ),
                    esc_html( $street ),
                    esc_html( $postal ),
                    esc_html( $city ),
                    esc_html__( 'Secrétariat', 'opac-custom' ),
                    esc_html( $hours ),
                    esc_html__( 'Téléphone', 'opac-custom' ),
                    esc_html__( 'Accueil', 'opac-custom' ),
                    esc_html( $tel_acc ),
                    esc_html__( 'Administration', 'opac-custom' ),
                    esc_html( $tel_adm ),
                    esc_html__( 'Email et réseaux', 'opac-custom' ),
                    esc_html( $email ),
                    $reseaux_html
                );

            case 'sidebar':
                // Page Contact : 4 cards verticales.
                return sprintf(
                    '<div class="opac-info-card"><h3>%s</h3><p>%s<br/>%s %s</p></div>'
                    . '<div class="opac-info-card"><h3>%s</h3><p>%s : %s<br/>%s : %s</p></div>'
                    . '<div class="opac-info-card"><h3>%s</h3><p>%s</p></div>'
                    . '<div class="opac-info-card"><h3>%s</h3><p>%s</p></div>',
                    esc_html__( 'Adresse', 'opac-custom' ),
                    esc_html( $street ),
                    esc_html( $postal ),
                    esc_html( $city ),
                    esc_html__( 'Téléphone', 'opac-custom' ),
                    esc_html__( 'Accueil', 'opac-custom' ),
                    esc_html( $tel_acc ),
                    esc_html__( 'Administration', 'opac-custom' ),
                    esc_html( $tel_adm ),
                    esc_html__( 'Secrétariat', 'opac-custom' ),
                    esc_html( $hours ),
                    esc_html__( 'Réseaux', 'opac-custom' ),
                    $reseaux_html
                );

            case 'coord-only':
                // Footer colonne 3 : tel + email + horaires.
                return sprintf(
                    '<p class="has-muted-color has-text-color" style="font-size:13px;line-height:1.7">%s<br/>%s<br/>%s</p>',
                    esc_html( $tel_acc ),
                    esc_html( $email ),
                    esc_html( $hours )
                );

            case 'compact':
            default:
                // Footer brand block : nom + tagline + adresse simple + reseaux.
                $social = self::social_links_html();
                return sprintf(
                    '<h3 class="wp-block-heading has-display-font-family" style="font-size:18px;font-weight:500;line-height:1.2">%s</h3>'
                    . '<p class="has-muted-color has-text-color" style="margin-top:6px;font-size:13px;line-height:1.7">%s<br/>%s, %s %s</p>'
                    . '%s',
                    esc_html( $name ),
                    esc_html( $legal ),
                    esc_html( $street ),
                    esc_html( $postal ),
                    esc_html( $city ),
                    $social ? '<div class="opac-footer-social">' . $social . '</div>' : ''
                );
        }
    }

    /**
     * Icone SVG officielle d'un reseau social (chemins repris de core/social-link).
     */
    private static function social_icon_svg( $network ) {
        $paths = [
            'facebook'  => 'M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z',
            'instagram' => 'M12 4.622c2.403 0 2.688.01 3.637.052.877.04 1.354.187 1.671.31.42.163.72.358 1.035.673.315.315.51.615.673 1.035.123.317.27.794.31 1.671.042.949.052 1.234.052 3.637 0 2.403-.01 2.688-.052 3.637-.04.877-.187 1.354-.31 1.671-.163.42-.358.72-.673 1.035-.315.315-.615.51-1.035.673-.317.123-.794.27-1.671.31-.949.042-1.234.052-3.637.052-2.403 0-2.688-.01-3.637-.052-.877-.04-1.354-.187-1.671-.31-.42-.163-.72-.358-1.035-.673-.315-.315-.51-.615-.673-1.035-.123-.317-.27-.794-.31-1.671-.042-.949-.052-1.234-.052-3.637 0-2.403.01-2.688.052-3.637.04-.877.187-1.354.31-1.671.163-.42.358-.72.673-1.035.315-.315.615-.51 1.035-.673.317-.123.794-.27 1.671-.31.949-.042 1.234-.052 3.637-.052M12 3c-2.444 0-2.751.01-3.711.054-.958.044-1.612.196-2.184.418-.592.23-1.094.538-1.594 1.038-.5.5-.808 1.002-1.038 1.594-.222.572-.374 1.226-.418 2.184C3.01 9.249 3 9.556 3 12s.01 2.751.054 3.711c.044.958.196 1.612.418 2.184.23.592.538 1.094 1.038 1.594.5.5 1.002.808 1.594 1.038.572.222 1.226.374 2.184.418C9.249 20.99 9.556 21 12 21s2.751-.01 3.711-.054c.958-.044 1.612-.196 2.184-.418.592-.23 1.094-.538 1.594-1.038.5-.5.808-1.002 1.038-1.594.222-.572.374-1.226.418-2.184C20.99 14.751 21 14.444 21 12s-.01-2.751-.054-3.711c-.044-.958-.196-1.612-.418-2.184-.23-.592-.538-1.094-1.038-1.594-.5-.5-1.002-.808-1.594-1.038-.572-.222-1.226-.374-2.184-.418C14.751 3.01 14.444 3 12 3zm0 4.378c-2.552 0-4.622 2.069-4.622 4.622 0 2.552 2.069 4.622 4.622 4.622 2.552 0 4.622-2.069 4.622-4.622 0-2.552-2.069-4.622-4.622-4.622zm0 7.629c-1.658 0-3.007-1.343-3.007-3.007 0-1.658 1.343-3.007 3.007-3.007 1.658 0 3.007 1.343 3.007 3.007 0 1.658-1.343 3.007-3.007 3.007zm5.884-7.813c0 .597-.484 1.08-1.08 1.08-.596 0-1.08-.483-1.08-1.08 0-.595.484-1.079 1.08-1.079.595 0 1.079.484 1.079 1.079z',
        ];
        if ( empty( $paths[ $network ] ) ) {
            return '';
        }
        return sprintf(
            '<svg class="opac-social-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="%s"></path></svg>',
            $paths[ $network ]
        );
    }

    /**
     * Lien reseau social : icone officielle + label. Le label est masque
     * visuellement au footer via .opac-footer-social .opac-social-label.
     */
    private static function social_link_tag( $network, $url ) {
        $labels = [ 'facebook' => 'Facebook', 'instagram' => 'Instagram' ];
        $label  = isset( $labels[ $network ] ) ? $labels[ $network ] : ucfirst( $network );
        return sprintf(
            '<a class="opac-social-link" href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s<span class="opac-social-label">%s</span></a>',
            esc_url( $url ),
            esc_attr( $label ),
            self::social_icon_svg( $network ),
            esc_html( $label )
        );
    }

    /**
     * Bloc reseaux sociaux (FB + IG) lu depuis OPAC_Settings.
     * Retourne '' si aucune URL renseignee (masquage).
     */
    private static function social_links_html() {
        $networks = [
            'facebook'  => OPAC_Settings::get( 'opac_org_facebook_url' ),
            'instagram' => OPAC_Settings::get( 'opac_org_instagram_url' ),
        ];
        $links = [];
        foreach ( $networks as $net => $url ) {
            if ( $url ) {
                $links[] = self::social_link_tag( $net, $url );
            }
        }
        return $links ? '<span class="opac-social-links">' . implode( '', $links ) . '</span>' : '';
    }

    /**
     * Bloc upcoming events : affiche les N prochains opac_event a venir
     * (date_event >= aujourd'hui), fallback sur les N derniers passes
     * si pas assez d'a venir. Utilise pour la section "Actualités" homepage.
     */
    public static function render_upcoming_events( $attrs, $content, $block ) {
        $count = isset( $attrs['count'] ) ? max( 1, (int) $attrs['count'] ) : 2;
        $today = wp_date( 'Y-m-d' );

        // Fetch tous les events publies en une query, tri par date ASC.
        $all = get_posts( [
            'post_type'      => 'opac_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'opac_date_event',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ] );

        // Split upcoming vs past cote PHP (plus stable que double meta_query).
        $upcoming = [];
        $past     = [];
        foreach ( $all as $e ) {
            $d = (string) get_post_meta( $e->ID, 'opac_date_event', true );
            if ( $d && $d >= $today ) {
                $upcoming[] = $e;
            } elseif ( $d ) {
                $past[] = $e;
            }
        }
        // Past les plus récents en premier.
        $past = array_reverse( $past );

        $events = array_slice( $upcoming, 0, $count );
        if ( count( $events ) < $count ) {
            $needed = $count - count( $events );
            $events = array_merge( $events, array_slice( $past, 0, $needed ) );
        }

        if ( empty( $events ) ) {
            return '';
        }

        $months_fr = [
            1 => 'Janvier', 2 => 'Février',  3 => 'Mars',     4 => 'Avril',
            5 => 'Mai',     6 => 'Juin',     7 => 'Juillet',  8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        // Markup aligne avec l'ancien design statique : wp-block-columns +
        // wp-block-column + opac-card avec fond bg + padding + border-radius.
        // Memes classes CSS donc styles existants s'appliquent sans ajout.
        $out = '<div class="wp-block-columns opac-actus-columns is-layout-flex wp-container-core-columns-is-layout-1 wp-block-columns-is-layout-flex">';
        foreach ( $events as $event ) {
            $date_raw = (string) get_post_meta( $event->ID, 'opac_date_event', true );
            $ts       = $date_raw ? strtotime( $date_raw ) : false;
            $date_lbl = '';
            if ( $ts ) {
                $n     = (int) wp_date( 'n', $ts );
                $year  = wp_date( 'Y', $ts );
                $date_lbl = ( $months_fr[ $n ] ?? '' ) . ' ' . $year;
            }
            $desc = (string) get_post_meta( $event->ID, 'opac_description_courte', true );

            $out .= sprintf(
                '<div class="wp-block-column is-layout-flow wp-block-column-is-layout-flow">'
                    . '<div class="wp-block-group opac-card has-bg-background-color has-background" style="border-radius:10px;padding:20px;height:100%%">'
                        . '<p class="opac-event-date has-muted-color has-text-color" style="text-transform:uppercase;letter-spacing:0.06em">%s</p>'
                        . '<p class="opac-card-name"><a href="%s" style="color:inherit;text-decoration:none">%s</a></p>'
                        . '%s'
                    . '</div>'
                . '</div>',
                esc_html( strtoupper( $date_lbl ) ),
                esc_url( get_permalink( $event ) ),
                esc_html( get_the_title( $event ) ),
                $desc ? '<p class="opac-actu-excerpt has-muted-color has-text-color" style="font-size:13px;line-height:1.5">' . esc_html( $desc ) . '</p>' : ''
            );
        }
        $out .= '</div>';
        return $out;
    }

    /**
     * Calcule les initiales (max 2 lettres) depuis un nom complet.
     * "Anne Lemogne" => "AL", "Katell Guerin-Metrope" => "KG".
     */
    private static function compute_initials( $full_name ) {
        $full_name = trim( (string) $full_name );
        if ( ! $full_name ) {
            return '';
        }
        $parts = preg_split( '/[\s\-]+/u', $full_name );
        $out = '';
        foreach ( $parts as $part ) {
            if ( $part === '' ) {
                continue;
            }
            $out .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
            if ( mb_strlen( $out ) >= 2 ) {
                break;
            }
        }
        return $out;
    }
}
