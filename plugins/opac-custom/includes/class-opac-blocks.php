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
            $notice = '<div class="opac-form-notice is-success">'
                . esc_html__( 'Votre message a bien été envoyé. Nous vous répondrons rapidement.', 'opac-custom' )
                . '</div>';
        } elseif ( isset( $_GET['erreur'] ) ) {
            $err = sanitize_key( wp_unslash( $_GET['erreur'] ) );
            $err_labels = [
                'champs'  => __( 'Merci de remplir tous les champs obligatoires.', 'opac-custom' ),
                'email'   => __( 'L\'adresse email saisie n\'est pas valide.', 'opac-custom' ),
                'envoi'   => __( 'L\'envoi a échoué. Merci de réessayer ou de nous contacter par téléphone.', 'opac-custom' ),
                'nonce'   => __( 'Session expirée, merci de soumettre à nouveau le formulaire.', 'opac-custom' ),
            ];
            $msg = $err_labels[ $err ] ?? __( 'Une erreur est survenue.', 'opac-custom' );
            $notice = '<div class="opac-form-notice is-error">' . esc_html( $msg ) . '</div>';
        }

        $subjects = [
            'renseignement' => __( 'Renseignement général', 'opac-custom' ),
            'atelier-annee' => __( 'Inscription atelier à l\'année', 'opac-custom' ),
            'ephemere'      => __( 'Atelier éphémère', 'opac-custom' ),
            'adhesion'      => __( 'Adhésion', 'opac-custom' ),
            'autre'         => __( 'Autre', 'opac-custom' ),
        ];

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
            $notice = '<div class="opac-form-notice is-success">'
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
            $notice = '<div class="opac-form-notice is-error">' . esc_html( $msg ) . '</div>';
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

        $out  = $notice;
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

        // Type d'adhesion (info pratique pour rappel montant).
        $out .= '<div class="opac-form-row"><label>' . esc_html__( 'Type d\'adhésion', 'opac-custom' ) . '</label>';
        $out .= '<div class="opac-form-radios">';
        $adhesions = [
            'plerinais' => __( 'Plérinais (15 €)', 'opac-custom' ),
            'exterieur' => __( 'Extérieur (30 €)', 'opac-custom' ),
            'mineur'    => __( 'Mineur (10 €)', 'opac-custom' ),
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
