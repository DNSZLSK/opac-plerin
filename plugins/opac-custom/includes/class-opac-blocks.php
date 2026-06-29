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

        register_block_type( 'opac/event-cat-tag', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_event_cat_tag' ],
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

        register_block_type( 'opac/charte-link', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_charte_link' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/partenaire', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_partenaire' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/soutenir', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_soutenir' ],
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

        register_block_type( 'opac/atelier-animator', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_atelier_animator' ],
            'uses_context'    => [ 'postId', 'postType' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/creneaux-list', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_creneaux_list' ],
            'uses_context'    => [ 'postId', 'postType' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/ephemeres-list', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_ephemeres_list' ],
            'attributes'      => [],
            'supports'        => [ 'html' => false ],
        ] );

        register_block_type( 'opac/legal-content', [
            'api_version'     => 3,
            'render_callback' => [ __CLASS__, 'render_legal_content' ],
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
     * Lien vers la charte des ateliers (PDF) dans le footer. URL éditable via
     * OPAC > Réglages > Coordonnées. Rend '' si aucun PDF n'est défini (le
     * séparateur « · » fait partie du rendu pour disparaître avec le lien).
     */
    public static function render_charte_link( $attrs, $content, $block ) {
        $url = class_exists( 'OPAC_Settings' ) ? (string) OPAC_Settings::get( 'opac_org_charte_pdf_url' ) : '';
        if ( ! $url ) {
            return '';
        }
        return sprintf(
            '<p class="opac-footer-charte">· <a href="%s" target="_blank" rel="noopener">%s</a></p>',
            esc_url( $url ),
            esc_html__( 'Charte des ateliers', 'opac-custom' )
        );
    }

    /**
     * Corps d'une page legale, lu depuis OPAC > Reglages selon le slug de la page
     * courante (cf. OPAC_Settings::legal_pages). Le titre est rendu a part par
     * core/post-title dans le template ; ce bloc ne rend que le contenu HTML, avec
     * substitution des placeholders {nom_asso}/{adresse}/{tel}/{email}. Slug non
     * mappe ou contenu vide : rend une chaine vide.
     */
    public static function render_legal_content( $attrs, $content, $block ) {
        if ( ! class_exists( 'OPAC_Settings' ) ) {
            return '';
        }
        $page = get_queried_object();
        $slug = ( $page instanceof WP_Post ) ? $page->post_name : '';

        $map = OPAC_Settings::legal_pages();
        if ( '' === $slug || ! isset( $map[ $slug ] ) ) {
            return '';
        }

        $raw = (string) OPAC_Settings::get( $map[ $slug ]['key'] );
        if ( '' === trim( $raw ) ) {
            return '';
        }

        $vars = [
            '{nom_asso}' => (string) OPAC_Settings::get( 'opac_org_legal_name' ),
            '{adresse}'  => OPAC_Settings::full_address(),
            '{tel}'      => (string) OPAC_Settings::get( 'opac_org_phone_accueil' ),
            '{email}'    => (string) OPAC_Settings::get( 'opac_org_email' ),
        ];
        // wpautop() indispensable : le contenu est stocke sans <p> (l'editeur WP
        // les retire et compte sur wpautop au rendu). Sans ca, les lignes d'un
        // meme bloc (nom + adresse + tel + email de l'editeur) se collent sur une
        // seule ligne et aucun paragraphe n'a d'espacement. On enveloppe avant
        // wp_kses_post, qui autorise les <p>/<br> produits.
        return wp_kses_post( wpautop( strtr( $raw, $vars ) ) );
    }

    /**
     * Bloc partenaire "Ville de Plerin" sur la page Association. Le logo est
     * editable via OPAC > Reglages > Coordonnees (opac_org_partenaire_logo_url) :
     * si une image est renseignee (mediatheque), on la rend contenue (object-fit:
     * contain, jamais rognee comme un logo/QR), sinon on garde le placeholder
     * texte "Ville de / Plerin". Meme pattern que render_statuts_link /
     * render_charte_link (lecture d'option) + branche img/placeholder de
     * render_soutenir.
     */
    public static function render_partenaire( $attrs, $content, $block ) {
        $logo_url = class_exists( 'OPAC_Settings' )
            ? (string) OPAC_Settings::get( 'opac_org_partenaire_logo_url' ) : '';

        if ( $logo_url ) {
            // alt vide : le nom "Ville de Plerin" est deja porte par .opac-part-name.
            $logo = sprintf(
                '<div class="opac-part-logo opac-part-logo--img" aria-hidden="true"><img src="%s" alt="" loading="lazy" decoding="async" /></div>',
                esc_url( $logo_url )
            );
        } else {
            $logo = '<div class="opac-part-logo" aria-hidden="true">Ville de<br/>Plérin</div>';
        }

        // Toute la card (logo + textes) est cliquable vers le site de la ville :
        // cible de clic large, plus pratique qu'un lien sur le seul nom.
        return '<a class="opac-partenaire" href="' . esc_url( 'https://www.ville-plerin.fr/' ) . '" target="_blank" rel="noopener">' . $logo
            . '<div>'
                . '<div class="opac-part-name">Ville de Plérin</div>'
                . '<div class="opac-part-sub">' . esc_html__( 'Partenaire institutionnel', 'opac-custom' ) . '</div>'
            . '</div>'
        . '</a>';
    }

    /**
     * Encart discret "Faire un don" (dons HelloAsso) en haut de la page
     * Association : QR + fleche manuscrite + lien cursif. URL editable via
     * OPAC > Reglages > Coordonnees. Rend '' si l'URL est vide.
     */
    public static function render_soutenir( $attrs, $content, $block ) {
        if ( ! class_exists( 'OPAC_Settings' ) ) {
            return '';
        }
        $url = (string) OPAC_Settings::get( 'opac_org_helloasso_url' );
        if ( ! $url ) {
            return '';
        }

        // QR : vrai QR si l'URL de l'image est renseignee (mediatheque),
        // sinon placeholder. Un QR ne doit jamais etre rogne.
        $qr_url = (string) OPAC_Settings::get( 'opac_org_helloasso_qr_url' );
        if ( $qr_url ) {
            $qr = sprintf(
                '<img class="opac-don-qr" src="%s" alt="%s" width="110" height="110" loading="lazy" decoding="async" />',
                esc_url( $qr_url ),
                esc_attr__( 'QR code pour faire un don sur HelloAsso', 'opac-custom' )
            );
        } else {
            $qr = self::qr_placeholder_svg();
        }

        // QR (image OU placeholder) cliquable vers le don HelloAsso.
        $qr = sprintf(
            '<a class="opac-don-qr-link" href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
            esc_url( $url ),
            esc_attr__( 'Faire un don sur HelloAsso (nouvel onglet)', 'opac-custom' ),
            $qr
        );

        // "Faire un don" (cursive) + petite fleche manuscrite -> QR.
        return sprintf(
            '<div class="opac-don-encart">'
                . '<span class="opac-don-aside">'
                    . '<a class="opac-don-link" href="%s" target="_blank" rel="noopener noreferrer">%s</a>'
                    . '%s'
                . '</span>'
                . '%s'
            . '</div>',
            esc_url( $url ),
            esc_html__( 'Faire un don', 'opac-custom' ),
            self::don_arrow_svg(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique
            $qr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- img (esc_url) / svg statique surs
        );
    }

    /**
     * Petite fleche dessinee a la main (SVG manuscrit) pointant vers le QR.
     * Decorative, masquee aux lecteurs d'ecran.
     */
    private static function don_arrow_svg() {
        return '<svg class="opac-don-arrow" width="46" height="30" viewBox="0 0 46 30" fill="none" aria-hidden="true" focusable="false">'
            . '<path d="M2 13 C 15 5, 29 9, 39 16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"></path>'
            . '<path d="M31 10 L 41 16 L 32 22" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path>'
        . '</svg>';
    }

    /**
     * Placeholder QR (SVG inline, sans dependance) pour la bande Soutenir, le
     * temps que l'OPAC ait son vrai QR HelloAsso. Markup statique : quand le vrai
     * QR existera, le remplacer ici (ou ajouter un champ URL QR si Katell doit le
     * gerer elle-meme via la mediatheque). Un QR ne doit jamais etre rogne.
     */
    private static function qr_placeholder_svg() {
        return '<svg class="opac-qr-placeholder" viewBox="0 0 100 100" width="110" height="110" role="img" aria-label="'
            . esc_attr__( 'Emplacement du QR code, à venir', 'opac-custom' ) . '">'
            . '<rect x="2" y="2" width="96" height="96" rx="6" fill="#ffffff" stroke="#d8d3cb"></rect>'
            . '<g fill="none" stroke="#9a958c" stroke-width="4">'
                . '<rect x="12" y="12" width="22" height="22" rx="2"></rect>'
                . '<rect x="66" y="12" width="22" height="22" rx="2"></rect>'
                . '<rect x="12" y="66" width="22" height="22" rx="2"></rect>'
            . '</g>'
            . '<g fill="#9a958c">'
                . '<rect x="19" y="19" width="8" height="8"></rect><rect x="73" y="19" width="8" height="8"></rect><rect x="19" y="73" width="8" height="8"></rect>'
                . '<rect x="48" y="16" width="5" height="5"></rect><rect x="58" y="26" width="5" height="5"></rect><rect x="44" y="40" width="5" height="5"></rect>'
                . '<rect x="62" y="46" width="5" height="5"></rect><rect x="50" y="56" width="5" height="5"></rect><rect x="70" y="62" width="5" height="5"></rect>'
                . '<rect x="56" y="72" width="5" height="5"></rect><rect x="44" y="66" width="5" height="5"></rect>'
            . '</g>'
        . '</svg>';
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

        // Case "Afficher les realisations" decochee (atelier) : masquer la
        // section meme si des items sont lies. Meta vide = affiche (defaut).
        if ( '0' === (string) get_post_meta( $post_id, 'opac_show_gallery', true ) ) {
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

        // Titre contextuel : "Realisations" pour un atelier, "En images" pour un
        // evenement / une exposition (meme bloc, meme requete keyee sur l'ID courant).
        $is_event = ( 'opac_event' === get_post_type( $post_id ) );
        $heading  = $is_event ? __( 'En images', 'opac-custom' ) : __( 'Réalisations', 'opac-custom' );

        return sprintf(
            '<section class="wp-block-group alignwide opac-section-padded opac-gallery-section" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;border-top-style:solid">'
                . '<h2 class="wp-block-heading opac-section-title has-display-font-family">%s</h2>'
                . '<div class="opac-gallery-carousel">'
                    . '<button type="button" class="opac-gallery-nav opac-gallery-prev" aria-label="%s" hidden>‹</button>'
                    . '<div class="opac-gallery-track">%s</div>'
                    . '<button type="button" class="opac-gallery-nav opac-gallery-next" aria-label="%s">›</button>'
                . '</div>'
            . '</section>',
            esc_html( $heading ),
            esc_attr__( 'Photos précédentes', 'opac-custom' ),
            $cards,
            esc_attr__( 'Photos suivantes', 'opac-custom' )
        );
    }

    /**
     * Vrai si le post est un atelier ephemere (opac_stage) dont la date de
     * debut est passee. Centralise la logique "termine" reutilisee par le tag
     * de statut, le bouton d'inscription et la liste showcase des ephemeres.
     */
    public static function stage_is_past( $post_id ) {
        if ( get_post_type( $post_id ) !== 'opac_stage' ) {
            return false;
        }
        $debut = (string) get_post_meta( $post_id, 'opac_date_debut', true );
        if ( '' === $debut ) {
            return false;
        }
        return $debut < current_time( 'Y-m-d' );
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

        // Atelier ephemere passe : statut "Termine" (showcase), quel que soit
        // le champ Places choisi en admin.
        if ( self::stage_is_past( $post_id ) ) {
            return '<p class="opac-tag opac-tag-past">' . esc_html__( 'Terminé', 'opac-custom' ) . '</p>';
        }

        $slug = (string) get_post_meta( $post_id, 'opac_places_dispo', true );

        // Libelles centralises (OPAC_Labels) ; la classe couleur reste propre au tag.
        $labels  = OPAC_Labels::places();
        $classes = [ 'ok' => 'opac-tag-ok', 'full' => 'opac-tag-full', 'few' => 'opac-tag-few' ];
        if ( ! isset( $labels[ $slug ], $classes[ $slug ] ) ) {
            return '';
        }

        return sprintf(
            '<p class="opac-tag %s">%s</p>',
            esc_attr( $classes[ $slug ] ),
            esc_html( $labels[ $slug ] )
        );
    }

    /**
     * Tag de categorie d'un event (taxonomy opac_event_cat) : equivalent du
     * places-tag pour la fiche evenement. Pastille coloree par categorie,
     * reutilise les couleurs .opac-cat-X / .opac-event-cat. Vide si pas de term.
     */
    public static function render_event_cat_tag( $attrs, $content, $block ) {
        $post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
        if ( ! $post_id ) {
            return '';
        }
        $terms = wp_get_post_terms( $post_id, 'opac_event_cat', [ 'number' => 1 ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return '';
        }
        return sprintf(
            '<p class="opac-tag opac-event-cat opac-cat-%s">%s</p>',
            esc_attr( sanitize_html_class( $terms[0]->slug ) ),
            esc_html( $terms[0]->name )
        );
    }

    /**
     * Liste "vitrine" des ateliers ephemeres pour la page d'archive : tous les
     * ephemeres, a venir d'abord (du plus proche au plus lointain) puis passes
     * (du plus recent au plus ancien). Les passes sont attenues (.is-past), avec
     * le tag "Termine" et sans bouton d'inscription (geres par stage_is_past
     * dans render_places_tag / render_inscription_button).
     *
     * Bloc serveur (et pas Query Loop) car l'ordre "a venir puis passes" et le
     * traitement par carte ne s'expriment pas avec le bloc Query natif. Calque
     * render_agenda_list. La classe opac-period-<slug> est posee sur chaque
     * carte pour le filtrage par onglet (JS initStageTabs).
     */
    public static function render_ephemeres_list( $attrs, $content, $block ) {
        $stages = get_posts( [
            'post_type'      => 'opac_stage',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'opac_date_debut',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ] );

        if ( empty( $stages ) ) {
            return '<p class="opac-empty has-text-align-center has-muted-color has-text-color">'
                . esc_html__( 'Aucun atelier éphémère publié pour le moment.', 'opac-custom' ) . '</p>';
        }

        // A venir / en cours d'abord (deja triee ASC), puis passes du plus
        // recent au plus ancien. Un stage sans date est traite comme "a venir".
        $today    = current_time( 'Y-m-d' );
        $upcoming = [];
        $past     = [];
        foreach ( $stages as $s ) {
            $d = (string) get_post_meta( $s->ID, 'opac_date_debut', true );
            if ( '' !== $d && $d < $today ) {
                $past[] = $s;
            } else {
                $upcoming[] = $s;
            }
        }
        $ordered  = array_merge( $upcoming, array_reverse( $past ) );
        $boundary = count( $upcoming ); // index de bascule a-venir -> passes

        $months_abbr = OPAC_Calendar::months_abbr();

        $out = '<div class="opac-stages-list">';

        foreach ( $ordered as $i => $s ) {
            // Separateur "Ephemeres passes" a la bascule a-venir -> passes,
            // seulement si les deux groupes existent. Visibilite reajustee par
            // onglet de periode cote JS (initStageTabs).
            if ( $i === $boundary && $boundary > 0 && ! empty( $past ) ) {
                $out .= '<div class="opac-stages-sep"><span>'
                    . esc_html__( 'Éphémères passés', 'opac-custom' )
                    . '</span></div>';
            }

            $id      = (int) $s->ID;
            $is_past = self::stage_is_past( $id );
            $ctx     = (object) [ 'context' => [ 'postId' => $id ] ];

            $d     = (string) get_post_meta( $id, 'opac_date_debut', true );
            $ts    = $d ? strtotime( $d ) : false;
            $month = $ts ? ( $months_abbr[ (int) wp_date( 'n', $ts ) ] ?? '' ) : '';
            $year  = $ts ? wp_date( 'Y', $ts ) : '';

            $periods      = wp_get_post_terms( $id, 'opac_period', [ 'fields' => 'slugs' ] );
            $period_class = ( ! is_wp_error( $periods ) && ! empty( $periods ) )
                ? ' opac-period-' . sanitize_html_class( $periods[0] ) : '';

            $public = (string) get_post_meta( $id, 'opac_public', true );
            $desc   = (string) get_post_meta( $id, 'opac_description_courte', true );

            // Tag de statut + bouton via les blocs date-aware (contexte postId
            // simule) : "Termine" + pas de bouton pour un ephemere passe.
            $status_tag = $is_past ? '' : self::render_places_tag( [], '', $ctx );
            // Markup identique au bouton "S'inscrire" (render_inscription_button) :
            // memes divs, memes classes y compris is-style-opac-primary, juste
            // <span> au lieu de <a> (non cliquable) et le texte. La classe
            // opac-stage-ended sur le wrapper ne surcharge que la couleur (CSS).
            $button     = $is_past
                ? '<div class="wp-block-buttons"><div class="wp-block-button is-style-opac-primary opac-stage-ended">'
                    . '<span class="wp-block-button__link wp-element-button">'
                    . esc_html__( 'Terminé', 'opac-custom' ) . '</span>'
                    . '</div></div>'
                : self::render_inscription_button( [], '', $ctx );

            $img = has_post_thumbnail( $id )
                ? '<figure class="wp-block-post-featured-image opac-stage-bg">' . get_the_post_thumbnail( $id, 'large', [ 'alt' => '' ] ) . '</figure>'
                : '';

            $card_class = 'wp-block-group opac-card opac-stage-card' . $period_class . ( $is_past ? ' is-past' : '' );

            $out .= '<div class="' . esc_attr( $card_class ) . '">'
                . '<div class="wp-block-group opac-stage-date has-card-color has-text-color">'
                    . '<p class="opac-stage-day has-text-align-center">' . esc_html( $month ) . '</p>'
                    . '<p class="opac-stage-month has-text-align-center">' . esc_html( $year ) . '</p>'
                . '</div>'
                . '<div class="wp-block-group opac-stage-body" style="padding-top:16px;padding-right:20px;padding-bottom:16px;padding-left:20px">'
                    . '<h3 class="wp-block-post-title opac-stage-name"><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3>'
                    . '<p class="opac-stage-desc">' . esc_html( $desc ) . '</p>'
                    . '<div class="wp-block-group opac-stage-tags">'
                        . ( $public ? '<p class="opac-tag opac-tag-neutral">' . esc_html( $public ) . '</p>' : '' )
                        . $status_tag
                    . '</div>'
                . '</div>'
                . '<div class="wp-block-group opac-stage-action" style="padding-right:20px;padding-left:10px">'
                    . $button
                . '</div>'
                . $img
            . '</div>';
        }

        $out .= '</div>';

        return $out;
    }

    /**
     * Liste des creneaux d'un atelier (palier 2). Lit le meta structure
     * opac_creneaux (jour/debut/fin/tarif/capacite). Fallback sur l'ancien
     * champ texte opac_creneaux_text si aucun creneau structure.
     */
    public static function render_creneaux_list( $attrs, $content, $block ) {
        $post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
        if ( ! $post_id ) {
            return '';
        }

        $creneaux = get_post_meta( $post_id, 'opac_creneaux', true );
        if ( is_array( $creneaux ) && ! empty( $creneaux ) ) {
            $items = '';
            foreach ( $creneaux as $c ) {
                if ( ! is_array( $c ) ) {
                    continue;
                }
                $line = OPAC_Calendar::creneau_label( $c );
                if ( '' === $line ) {
                    continue;
                }
                $tarif = isset( $c['tarif'] ) ? (int) $c['tarif'] : 0;
                $tarif_html = $tarif > 0
                    ? ' <span class="opac-creneau-tarif">' . esc_html( OPAC_Labels::euros( $tarif ) ) . '</span>'
                    : '';
                $note = ( isset( $c['note'] ) && '' !== $c['note'] )
                    ? ' <span class="opac-creneau-note">' . esc_html( (string) $c['note'] ) . '</span>'
                    : '';
                $state_html = '';
                $cap = isset( $c['capacite'] ) ? (int) $c['capacite'] : 0;
                if ( $cap > 0 && ! empty( $c['id'] ) && class_exists( 'OPAC_Inscriptions' ) ) {
                    $left = $cap - OPAC_Inscriptions::count_validees( $post_id, (string) $c['id'] );
                    if ( $left <= 0 ) {
                        $state_html = ' <span class="opac-creneau-state is-full">' . esc_html__( 'Complet', 'opac-custom' ) . '</span>';
                    } elseif ( $left <= 2 ) {
                        $state_html = ' <span class="opac-creneau-state is-few">' . esc_html__( 'Dernières places', 'opac-custom' ) . '</span>';
                    }
                }
                $items .= '<li><span class="opac-creneau-when">' . esc_html( $line ) . '</span>' . $tarif_html . $state_html . $note . '</li>';
            }
            if ( '' !== $items ) {
                return '<ul class="opac-creneaux-items">' . $items . '</ul>';
            }
        }

        // Fallback : ancien champ texte libre (white-space: pre-line via CSS).
        $text = (string) get_post_meta( $post_id, 'opac_creneaux_text', true );
        if ( '' === trim( $text ) ) {
            return '';
        }
        return '<p class="opac-creneaux-list">' . esc_html( $text ) . '</p>';
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

        $months_fr   = OPAC_Calendar::months();
        $months_abbr = OPAC_Calendar::months_abbr();

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

            $n_event = (int) wp_date( 'n', $ts );
            $day     = wp_date( 'j', $ts );
            $mon     = $months_abbr[ $n_event ] ?? '';

            $desc = (string) get_post_meta( $event->ID, 'opac_description_courte', true );

            // Badge categorie seul dans la barre du haut : le jour/mois est dans le
            // pave date a gauche, l'annee dans le label de mois de la section.
            $top = $cat_label
                ? '<div class="opac-event-top"><span class="opac-event-cat">' . esc_html( $cat_label ) . '</span></div>'
                : '';

            // Photo de fond optionnelle (degrade clair par-dessus, cf. CSS .opac-event-bg).
            // Calque .opac-stage-bg des ephemeres : meme langage visuel.
            $img = has_post_thumbnail( $event->ID )
                ? '<figure class="opac-event-bg" aria-hidden="true">' . get_the_post_thumbnail( $event->ID, 'large', [ 'alt' => '' ] ) . '</figure>'
                : '';

            $out .= sprintf(
                '<a class="opac-event%s" href="%s">'
                    . '<span class="opac-event-date-box"><span class="opac-event-day">%s</span><span class="opac-event-mon">%s</span></span>'
                    . '<div class="opac-event-body">'
                        . '%s'
                        . '<h3 class="opac-event-name">%s</h3>'
                        . '%s'
                    . '</div>'
                    . '%s'
                . '</a>',
                $cat_slug ? ' opac-cat-' . esc_attr( sanitize_html_class( $cat_slug ) ) : '',
                esc_url( get_permalink( $event ) ),
                esc_html( $day ),
                esc_html( $mon ),
                $top,
                esc_html( get_the_title( $event ) ),
                $desc ? '<p class="opac-event-desc">' . esc_html( $desc ) . '</p>' : '',
                $img
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
     * Carte animateur de la fiche atelier. Rendue en PHP (et non plus via un
     * template statique) pour CALCULER les initiales de l'avatar depuis le champ
     * texte opac_animator, exactement comme la grille d'equipe (compute_initials).
     * L'ancien markup statique affichait un "." fige, faute de pouvoir executer
     * de logique. L'animateur est un champ libre (pas une fiche opac_person).
     */
    public static function render_atelier_animator( $attrs, $content, $block ) {
        $post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
        if ( ! $post_id ) {
            return '';
        }

        $name = trim( (string) get_post_meta( $post_id, 'opac_animator', true ) );

        // Champ vide (rare : l'animateur est une info fixe) : placeholder neutre
        // plutot qu'un avatar vide ou une section orpheline.
        if ( '' === $name ) {
            $initials  = '·';
            $name_html = esc_html__( 'Animateur·rice', 'opac-custom' );
        } else {
            $initials = self::compute_initials( $name );
            if ( '' === $initials ) {
                $initials = '·';
            }
            $name_html = esc_html( $name );
        }

        return '<div class="opac-animateur has-card-background-color has-background">'
            . '<div class="opac-animateur-avatar" aria-hidden="true">' . esc_html( $initials ) . '</div>'
            . '<div class="opac-animateur-info">'
                . '<p class="opac-animateur-name">' . $name_html . '</p>'
                . '<p class="opac-animateur-role has-muted-color has-text-color">'
                    . esc_html__( 'Animateur·rice de l\'atelier OPAC', 'opac-custom' )
                . '</p>'
            . '</div>'
        . '</div>';
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

        // Mention d'information RGPD : le contact ne stocke rien (simple envoi
        // d'email), avec lien vers la politique de confidentialite si presente.
        $opac_privacy_url = class_exists( 'OPAC_RGPD' ) ? OPAC_RGPD::privacy_policy_url() : '';
        $out .= '<p class="opac-form-legal">'
            . esc_html__( 'Vos coordonnées servent uniquement à traiter votre message et ne sont pas conservées au-delà.', 'opac-custom' );
        if ( $opac_privacy_url ) {
            $out .= ' <a href="' . esc_url( $opac_privacy_url ) . '" target="_blank" rel="noopener">'
                . esc_html__( 'Politique de confidentialité', 'opac-custom' ) . '</a>.';
        }
        $out .= '</p>';

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

        // Atelier ephemere passe : plus d'inscription possible (showcase).
        if ( 'opac_stage' === $post_type && self::stage_is_past( $post_id ) ) {
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
            $msg = ( isset( $_GET['attente'] ) && '1' === $_GET['attente'] )
                ? __( 'Ce créneau est complet : votre demande a été enregistrée en liste d\'attente. Nous vous recontacterons dès qu\'une place se libère.', 'opac-custom' )
                : __( 'Votre demande d\'inscription a bien été enregistrée. Le secrétariat vous contactera prochainement pour confirmation.', 'opac-custom' );
            $notice = '<div class="opac-form-notice is-success" role="status" aria-live="polite">' . esc_html( $msg ) . '</div>';
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
        $creneaux_struct = [];

        if ( $atelier_id && get_post_type( $atelier_id ) === 'opac_atelier' ) {
            $titre  = get_the_title( $atelier_id );
            $tarif  = (int) get_post_meta( $atelier_id, 'opac_tarif_annuel', true );
            $struct = get_post_meta( $atelier_id, 'opac_creneaux', true );
            if ( is_array( $struct ) ) {
                $creneaux_struct = $struct;
            }
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
                $tarif > 0 ? '<div class="opac-form-context-tarif">' . sprintf( esc_html__( 'Tarif séance : %d €', 'opac-custom' ), $tarif ) . '</div>' : ''
            );
            $hidden_inputs = '<input type="hidden" name="opac_stage_id" value="' . esc_attr( $stage_id ) . '" />';
        }

        $action_url = esc_url( admin_url( 'admin-post.php' ) );
        $nonce      = wp_nonce_field( 'opac_inscription_submit', 'opac_inscription_nonce', true, false );

        // Notice de phase (gating souple : informatif, ne bloque jamais le formulaire).
        // Uniquement pour les ateliers à l'année : un atelier éphémère n'a pas
        // de phase de réinscription / ouverture des adhérents.
        $phase_notice = '';
        if ( ! $stage_id && class_exists( 'OPAC_Settings' ) ) {
            $phase  = OPAC_Settings::inscription_phase();
            $d_rein = (string) OPAC_Settings::get( 'opac_insc_date_reinscription' );
            $d_ouv  = (string) OPAC_Settings::get( 'opac_insc_date_ouverture' );
            $d_conf = (string) OPAC_Settings::get( 'opac_insc_date_confirmation' );
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
            } elseif ( 'ouverte' === $phase ) {
                // Confirmation des nouvelles inscriptions : info optionnelle, affichee
                // seulement si la date est renseignee (sinon formulaire ouvert sans notice).
                if ( $d_conf ) {
                    $phase_msg = sprintf( __( 'Inscriptions ouvertes. Les nouvelles inscriptions seront confirmées à partir du %s.', 'opac-custom' ), $fmt( $d_conf ) );
                }
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

        // Creneaux pour atelier a l'annee. Prefere les creneaux structures
        // (value = id, avec tarif), sinon fallback sur l'ancien champ texte.
        if ( $atelier_id && count( $creneaux_struct ) > 0 ) {
            $out .= '<div class="opac-form-row"><label for="opac-creneau">' . esc_html__( 'Créneau choisi', 'opac-custom' ) . ' *</label>';
            $out .= '<select class="opac-form-input" id="opac-creneau" name="opac_creneau_id" required>';
            $out .= '<option value="">' . esc_html__( 'Sélectionner un créneau...', 'opac-custom' ) . '</option>';
            foreach ( $creneaux_struct as $c ) {
                if ( ! is_array( $c ) || empty( $c['id'] ) ) {
                    continue;
                }
                $tarif_c = isset( $c['tarif'] ) ? (int) $c['tarif'] : 0;
                $opt     = OPAC_Calendar::creneau_label( $c );
                if ( $tarif_c > 0 ) {
                    $opt .= ' (' . OPAC_Labels::euros( $tarif_c ) . ')';
                }
                if ( class_exists( 'OPAC_Inscriptions' ) && OPAC_Inscriptions::creneau_is_full( $atelier_id, $c ) ) {
                    $opt .= ' - ' . __( 'Complet (liste d\'attente)', 'opac-custom' );
                }
                $out .= '<option value="' . esc_attr( (string) $c['id'] ) . '">' . esc_html( $opt ) . '</option>';
            }
            $out .= '</select></div>';
        } elseif ( $atelier_id && count( $creneaux_lines ) > 0 ) {
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

        // Adhésion : uniquement pour les ateliers à l'année. Un atelier
        // éphémère (?stage=ID) ne demande aucune adhésion : on masque la case
        // mineur, l'estimation par code postal et son script.
        if ( ! $stage_id ) {
            // Dérivée côté serveur (CP + case mineur). Le visiteur ne choisit
            // plus son type, il coche seulement "mineur". Le montant affiché
            // est indicatif (la résidence est vérifiée au secrétariat).
            $tarif_p = class_exists( 'OPAC_Settings' ) ? (int) OPAC_Settings::get( 'opac_adhesion_plerinais' ) : 15;
            $tarif_e = class_exists( 'OPAC_Settings' ) ? (int) OPAC_Settings::get( 'opac_adhesion_exterieur' ) : 30;
            $tarif_m = class_exists( 'OPAC_Settings' ) ? (int) OPAC_Settings::get( 'opac_adhesion_mineur' )    : 10;

            $out .= '<div class="opac-form-rgpd opac-form-mineur">'
                . '<label><input type="checkbox" id="opac-mineur" name="opac_mineur" value="1" /> '
                . esc_html__( 'La personne inscrite est mineure (moins de 18 ans)', 'opac-custom' )
                . '</label>'
                . '<p class="opac-form-hint">' . esc_html__( 'Pour un mineur, la demande est effectuée par son représentant légal.', 'opac-custom' ) . '</p>'
                . '</div>';

            $hint = __( 'Renseignez votre code postal pour estimer le montant de l\'adhésion.', 'opac-custom' );
            $out .= '<p class="opac-adhesion-info" id="opac-adhesion-info" aria-live="polite"'
                . ' data-plerinais="' . esc_attr( $tarif_p ) . '"'
                . ' data-exterieur="' . esc_attr( $tarif_e ) . '"'
                . ' data-mineur="' . esc_attr( $tarif_m ) . '"'
                . ' data-cp="22190"'
                . ' data-prefix="' . esc_attr__( 'Adhésion annuelle estimée :', 'opac-custom' ) . '"'
                . ' data-suffix="' . esc_attr__( 'à confirmer au secrétariat', 'opac-custom' ) . '"'
                . ' data-lp="' . esc_attr__( 'Plérinais', 'opac-custom' ) . '"'
                . ' data-le="' . esc_attr__( 'Extérieur', 'opac-custom' ) . '"'
                . ' data-lm="' . esc_attr__( 'Mineur', 'opac-custom' ) . '"'
                . ' data-default="' . esc_attr( $hint ) . '">'
                . esc_html( $hint )
                . '</p>';

            $out .= '<script>'
                . '(function(){'
                . 'var cp=document.getElementById("opac-cp"),mn=document.getElementById("opac-mineur"),el=document.getElementById("opac-adhesion-info");'
                . 'if(!cp||!el){return;}'
                . 'function upd(){'
                . 'var v=(cp.value||"").replace(/\\s/g,""),amt,lab;'
                . 'if(mn&&mn.checked){amt=el.getAttribute("data-mineur");lab=el.getAttribute("data-lm");}'
                . 'else if(v===el.getAttribute("data-cp")){amt=el.getAttribute("data-plerinais");lab=el.getAttribute("data-lp");}'
                . 'else if(v.length===5){amt=el.getAttribute("data-exterieur");lab=el.getAttribute("data-le");}'
                . 'else{el.textContent=el.getAttribute("data-default");return;}'
                . 'el.textContent=el.getAttribute("data-prefix")+" "+amt+" \\u20AC ("+lab+") - "+el.getAttribute("data-suffix");'
                . '}'
                . 'cp.addEventListener("input",upd);if(mn){mn.addEventListener("change",upd);}upd();'
                . '})();'
                . '</script>';
        }

        $out .= '<div class="opac-form-row"><label for="opac-message">' . esc_html__( 'Message (optionnel)', 'opac-custom' ) . '</label>'
            . '<textarea class="opac-form-input opac-form-textarea" id="opac-message" name="opac_message" rows="4"></textarea></div>';

        // RGPD checkbox obligatoire + lien vers la politique de confidentialite
        // (resolu automatiquement, cf. OPAC_RGPD::privacy_policy_url).
        $out .= '<div class="opac-form-rgpd">'
            . '<label><input type="checkbox" name="opac_rgpd" value="1" required /> '
            . esc_html__( 'J\'accepte que mes données soient utilisées par l\'Association OPAC pour traiter ma demande d\'inscription.', 'opac-custom' )
            . '</label>';
        $opac_privacy_url = class_exists( 'OPAC_RGPD' ) ? OPAC_RGPD::privacy_policy_url() : '';
        if ( $opac_privacy_url ) {
            $out .= ' <a class="opac-form-privacy-link" href="' . esc_url( $opac_privacy_url ) . '" target="_blank" rel="noopener">'
                . esc_html__( 'En savoir plus sur vos données', 'opac-custom' ) . '</a>';
        }
        $out .= '</div>';

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
                    self::phone_link( $tel_acc ),
                    esc_html__( 'Administration', 'opac-custom' ),
                    self::phone_link( $tel_adm ),
                    esc_html__( 'Email et réseaux', 'opac-custom' ),
                    self::email_link( $email ),
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
                    self::phone_link( $tel_acc ),
                    esc_html__( 'Administration', 'opac-custom' ),
                    self::phone_link( $tel_adm ),
                    esc_html__( 'Secrétariat', 'opac-custom' ),
                    esc_html( $hours ),
                    esc_html__( 'Réseaux', 'opac-custom' ),
                    $reseaux_html
                );

            case 'coord-only':
                // Footer colonne 3 : tel + email + horaires.
                return sprintf(
                    '<p class="has-muted-color has-text-color" style="font-size:13px;line-height:1.7">%s<br/>%s<br/>%s</p>',
                    self::phone_link( $tel_acc ),
                    self::email_link( $email ),
                    esc_html( $hours )
                );

            case 'compact':
            default:
                // Footer brand block : nom + tagline + adresse simple + reseaux + HelloAsso.
                $social = self::social_links_html( true );
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
     * Numero de telephone cliquable (lien tel:). Sur iOS Safari, un numero en
     * texte brut declenche l'auto-detection (Data Detectors), au comportement
     * incoherent (popup qui "bloque") ; un vrai lien tel: ouvre l'appel
     * proprement et de facon identique sur toutes les pages.
     * Fallback : placeholder muet si le champ n'est pas renseigne.
     */
    private static function phone_link( $number ) {
        $number = trim( (string) $number );
        if ( '' === $number ) {
            return '<span class="opac-empty-inline">' . esc_html__( 'Non renseigné', 'opac-custom' ) . '</span>';
        }
        // href : on ne garde que les chiffres et un eventuel + en tete.
        $href = preg_replace( '/[^0-9+]/', '', $number );
        return sprintf( '<a href="tel:%s">%s</a>', esc_attr( $href ), esc_html( $number ) );
    }

    /**
     * Adresse email cliquable (lien mailto:). Meme logique que phone_link :
     * lien explicite plutot que de dependre de l'auto-detection du navigateur.
     * Fallback : placeholder muet si le champ n'est pas renseigne.
     */
    private static function email_link( $email ) {
        $email = trim( (string) $email );
        if ( '' === $email ) {
            return '<span class="opac-empty-inline">' . esc_html__( 'Non renseigné', 'opac-custom' ) . '</span>';
        }
        return sprintf( '<a href="mailto:%s">%s</a>', esc_attr( $email ), esc_html( $email ) );
    }

    /**
     * Icone SVG officielle d'un reseau social (chemins repris de core/social-link).
     */
    private static function social_icon_svg( $network ) {
        $paths = [
            'facebook'  => 'M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z',
            'instagram' => 'M12 4.622c2.403 0 2.688.01 3.637.052.877.04 1.354.187 1.671.31.42.163.72.358 1.035.673.315.315.51.615.673 1.035.123.317.27.794.31 1.671.042.949.052 1.234.052 3.637 0 2.403-.01 2.688-.052 3.637-.04.877-.187 1.354-.31 1.671-.163.42-.358.72-.673 1.035-.315.315-.615.51-1.035.673-.317.123-.794.27-1.671.31-.949.042-1.234.052-3.637.052-2.403 0-2.688-.01-3.637-.052-.877-.04-1.354-.187-1.671-.31-.42-.163-.72-.358-1.035-.673-.315-.315-.51-.615-.673-1.035-.123-.317-.27-.794-.31-1.671-.042-.949-.052-1.234-.052-3.637 0-2.403.01-2.688.052-3.637.04-.877.187-1.354.31-1.671.163-.42.358-.72.673-1.035.315-.315.615-.51 1.035-.673.317-.123.794-.27 1.671-.31.949-.042 1.234-.052 3.637-.052M12 3c-2.444 0-2.751.01-3.711.054-.958.044-1.612.196-2.184.418-.592.23-1.094.538-1.594 1.038-.5.5-.808 1.002-1.038 1.594-.222.572-.374 1.226-.418 2.184C3.01 9.249 3 9.556 3 12s.01 2.751.054 3.711c.044.958.196 1.612.418 2.184.23.592.538 1.094 1.038 1.594.5.5 1.002.808 1.594 1.038.572.222 1.226.374 2.184.418C9.249 20.99 9.556 21 12 21s2.751-.01 3.711-.054c.958-.044 1.612-.196 2.184-.418.592-.23 1.094-.538 1.594-1.038.5-.5.808-1.002 1.038-1.594.222-.572.374-1.226.418-2.184C20.99 14.751 21 14.444 21 12s-.01-2.751-.054-3.711c-.044-.958-.196-1.612-.418-2.184-.23-.592-.538-1.094-1.038-1.594-.5-.5-1.002-.808-1.594-1.038-.572-.222-1.226-.374-2.184-.418C14.751 3.01 14.444 3 12 3zm0 4.378c-2.552 0-4.622 2.069-4.622 4.622 0 2.552 2.069 4.622 4.622 4.622 2.552 0 4.622-2.069 4.622-4.622 0-2.552-2.069-4.622-4.622-4.622zm0 7.629c-1.658 0-3.007-1.343-3.007-3.007 0-1.658 1.343-3.007 3.007-3.007 1.658 0 3.007 1.343 3.007 3.007 0 1.658-1.343 3.007-3.007 3.007zm5.884-7.813c0 .597-.484 1.08-1.08 1.08-.596 0-1.08-.483-1.08-1.08 0-.595.484-1.079 1.08-1.079.595 0 1.079.484 1.079 1.079z',
            'tiktok'    => 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
            'helloasso' => 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z',
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
        $labels = [ 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok' ];
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
     * Bloc reseaux sociaux (FB + IG + TikTok) lu depuis OPAC_Settings.
     * Retourne '' si aucune URL renseignee (masquage). Si $include_helloasso,
     * ajoute le lien dons HelloAsso a la suite (footer uniquement).
     */
    private static function social_links_html( $include_helloasso = false ) {
        $networks = [
            'facebook'  => OPAC_Settings::get( 'opac_org_facebook_url' ),
            'instagram' => OPAC_Settings::get( 'opac_org_instagram_url' ),
            'tiktok'    => OPAC_Settings::get( 'opac_org_tiktok_url' ),
        ];
        $links = [];
        foreach ( $networks as $net => $url ) {
            if ( $url ) {
                $links[] = self::social_link_tag( $net, $url );
            }
        }
        if ( $include_helloasso ) {
            $helloasso = self::helloasso_link_html();
            if ( '' !== $helloasso ) {
                $links[] = $helloasso;
            }
        }
        return $links ? '<span class="opac-social-links">' . implode( '', $links ) . '</span>' : '';
    }

    /**
     * Lien HelloAsso (dons) avec icone coeur, pour le footer (a cote de FB/IG).
     * Retourne '' si l'URL n'est pas renseignee. Separe de social_links_html :
     * HelloAsso n'apparait qu'au footer, pas dans la ligne "reseaux" des cartes
     * Association / Contact (le bloc CTA dedie joue ce role sur Association).
     */
    private static function helloasso_link_html() {
        $url = OPAC_Settings::get( 'opac_org_helloasso_url' );
        if ( ! $url ) {
            return '';
        }
        $label = __( 'Soutenir l\'OPAC', 'opac-custom' );
        return sprintf(
            '<a class="opac-social-link opac-helloasso-link" href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s<span class="opac-social-label">%s</span></a>',
            esc_url( $url ),
            esc_attr( $label ),
            self::social_icon_svg( 'helloasso' ),
            esc_html( $label )
        );
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

        $months_fr = OPAC_Calendar::months();

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
                    . '<div class="wp-block-group opac-card has-bg-background-color has-background">'
                        . '<p class="opac-event-date has-muted-color has-text-color" style="text-transform:uppercase;letter-spacing:0.06em">%s</p>'
                        . '<p class="opac-card-name"><a href="%s">%s</a></p>'
                        . '%s'
                    . '</div>'
                . '</div>',
                esc_html( strtoupper( $date_lbl ) ),
                esc_url( get_permalink( $event ) ),
                esc_html( get_the_title( $event ) ),
                $desc ? '<p class="opac-actu-excerpt has-muted-color has-text-color">' . esc_html( $desc ) . '</p>' : ''
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
