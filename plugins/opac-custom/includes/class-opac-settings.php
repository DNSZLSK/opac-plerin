<?php
/**
 * OPAC Custom - Control panel admin Reglages OPAC
 *
 * Centralise toutes les donnees metier modifiables par Katell/Laurence
 * sans toucher au code : coordonnees asso, tarifs adhesion, hero homepage,
 * stats, templates emails inscription, sujets dropdown contact.
 *
 * Toutes les options sont lues via get_option('opac_<key>') ailleurs dans
 * le plugin et les templates (refactor M10). Le seeder mu-plugin populate
 * les valeurs initiales = aucun changement visuel a l'activation.
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Settings {

    const PAGE_SLUG       = 'opac-settings';
    const OPTION_GRP      = 'opac_settings_group';
    const LEGAL_SEED_FLAG = 'opac_legal_pages_seeded';

    public static function register() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        // Cree les pages legales manquantes une fois (plugin deja actif : pas de
        // re-activation necessaire). Le flag porte la version pour re-verifier
        // apres une mise a jour (creation seulement, jamais d'ecrasement).
        add_action( 'init', [ __CLASS__, 'maybe_seed_legal_pages' ] );
    }

    public static function add_menu_page() {
        add_menu_page(
            __( 'OPAC - Réglages', 'opac-custom' ),
            __( 'OPAC Réglages', 'opac-custom' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ],
            'dashicons-admin-generic',
            58
        );
    }

    /**
     * Toutes les options gerees par le panel.
     * Format : [ option_key => [ 'type', 'default', 'sanitize_callback' ] ]
     */
    public static function options_schema() {
        return [
            // Section 1 : Coordonnees asso
            'opac_org_name'              => [ 'type' => 'string',  'default' => 'Association OPAC',                            'sanitize' => 'sanitize_text_field' ],
            'opac_org_legal_name'        => [ 'type' => 'string',  'default' => 'Association Office Plérinais d\'Action Culturelle', 'sanitize' => 'sanitize_text_field' ],
            'opac_org_address_street'    => [ 'type' => 'string',  'default' => '10A rue fleurie',                              'sanitize' => 'sanitize_text_field' ],
            'opac_org_address_postal'    => [ 'type' => 'string',  'default' => '22190',                                        'sanitize' => 'sanitize_text_field' ],
            'opac_org_address_city'      => [ 'type' => 'string',  'default' => 'Plérin',                                       'sanitize' => 'sanitize_text_field' ],
            'opac_org_phone_accueil'     => [ 'type' => 'string',  'default' => '02 96 74 53 08',                               'sanitize' => 'sanitize_text_field' ],
            'opac_org_phone_admin'       => [ 'type' => 'string',  'default' => '06 74 47 98 53',                               'sanitize' => 'sanitize_text_field' ],
            'opac_org_email'             => [ 'type' => 'string',  'default' => 'contact@opacplerin.fr',                        'sanitize' => 'sanitize_email' ],
            'opac_org_hours'             => [ 'type' => 'string',  'default' => 'Lundi à vendredi, 14h15 à 17h45',              'sanitize' => 'sanitize_text_field' ],
            'opac_org_founding_year'     => [ 'type' => 'integer', 'default' => 1980,                                           'sanitize' => 'absint' ],
            'opac_org_facebook_url'      => [ 'type' => 'string',  'default' => '',                                             'sanitize' => 'esc_url_raw' ],
            'opac_org_instagram_url'     => [ 'type' => 'string',  'default' => '',                                             'sanitize' => 'esc_url_raw' ],
            'opac_org_tiktok_url'        => [ 'type' => 'string',  'default' => '',                                             'sanitize' => 'esc_url_raw' ],
            'opac_org_statuts_pdf_url'   => [ 'type' => 'string',  'default' => '',                                             'sanitize' => 'esc_url_raw' ],
            'opac_org_charte_pdf_url'    => [ 'type' => 'string',  'default' => '',                                             'sanitize' => 'esc_url_raw' ],
            'opac_org_helloasso_url'     => [ 'type' => 'string',  'default' => '',                                             'sanitize' => 'esc_url_raw' ],
            'opac_org_helloasso_qr_url'  => [ 'type' => 'string',  'default' => '',                                             'sanitize' => 'esc_url_raw' ],
            'opac_org_partenaire_logo_url' => [ 'type' => 'string', 'default' => '',                                          'sanitize' => 'esc_url_raw' ],

            // Section 2 : Tarifs adhesion
            'opac_adhesion_plerinais'    => [ 'type' => 'integer', 'default' => 15, 'sanitize' => 'absint' ],
            'opac_adhesion_exterieur'    => [ 'type' => 'integer', 'default' => 30, 'sanitize' => 'absint' ],
            'opac_adhesion_mineur'       => [ 'type' => 'integer', 'default' => 10, 'sanitize' => 'absint' ],

            // Section 3 : Saison + Hero homepage
            'opac_home_saison_badge'     => [ 'type' => 'string', 'default' => 'Saison 2025 / 2026',                                                                    'sanitize' => 'sanitize_text_field' ],
            'opac_home_hero_title'       => [ 'type' => 'string', 'default' => 'La culture au bout des doigts',                                                          'sanitize' => 'sanitize_text_field' ],
            'opac_home_hero_intro'       => [ 'type' => 'string', 'default' => 'Ateliers d\'expression culturelle, activités éphémères et sorties pour tous les âges. Association OPAC, asso loi 1901 à Plérin.', 'sanitize' => 'sanitize_textarea_field' ],
            'opac_home_cta_band_title'   => [ 'type' => 'string', 'default' => 'Inscription pour la saison 2025 / 2026',                                                  'sanitize' => 'sanitize_text_field' ],
            'opac_home_cta_band_text'    => [ 'type' => 'string', 'default' => 'Inscription en ligne, paiement sur place au secrétariat. Adhésion annuelle requise.',    'sanitize' => 'sanitize_textarea_field' ],

            // Section 4 : Stats homepage
            'opac_stats_ateliers'        => [ 'type' => 'string',  'default' => '10',    'sanitize' => 'sanitize_text_field' ],
            'opac_stats_adherents'       => [ 'type' => 'string',  'default' => '200+',  'sanitize' => 'sanitize_text_field' ],
            'opac_stats_ateliers_auto'   => [ 'type' => 'integer', 'default' => 0,       'sanitize' => 'absint' ],

            // Section 5 : Templates emails inscription
            'opac_email_validee'         => [ 'type' => 'string', 'default' => self::default_email( 'validee' ),       'sanitize' => 'wp_kses_post' ],
            'opac_email_refusee'         => [ 'type' => 'string', 'default' => self::default_email( 'refusee' ),       'sanitize' => 'wp_kses_post' ],
            'opac_email_liste_attente'   => [ 'type' => 'string', 'default' => self::default_email( 'liste-attente' ), 'sanitize' => 'wp_kses_post' ],
            'opac_email_place_liberee'   => [ 'type' => 'string', 'default' => self::default_email( 'place-liberee' ), 'sanitize' => 'wp_kses_post' ],

            // Section 6 : Sujets dropdown contact
            'opac_contact_subjects'      => [ 'type' => 'string', 'default' => "renseignement | Renseignement général\natelier-annee | Inscription atelier à l'année\nephemere | Atelier éphémère\nadhesion | Adhésion\nautre | Autre", 'sanitize' => 'sanitize_textarea_field' ],

            // Section 7 : Période d'inscription (workflow inscription, palier 1)
            'opac_insc_date_reinscription' => [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_text_field' ],
            'opac_insc_date_ouverture'     => [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_text_field' ],
            'opac_insc_date_fermeture'     => [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_text_field' ],
            'opac_insc_date_confirmation'  => [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_text_field' ],

            // Section 8 : Donnees personnelles (RGPD)
            'opac_insc_purge_months'       => [ 'type' => 'integer', 'default' => 24, 'sanitize' => 'absint' ],

            // Section 9 : Contenu des pages legales (rendu par opac/legal-content)
            'opac_legal_confidentialite' => [ 'type' => 'string', 'default' => self::default_legal( 'confidentialite' ), 'sanitize' => 'wp_kses_post' ],
            'opac_legal_mentions'        => [ 'type' => 'string', 'default' => self::default_legal( 'mentions' ),        'sanitize' => 'wp_kses_post' ],
            'opac_legal_cookies'         => [ 'type' => 'string', 'default' => self::default_legal( 'cookies' ),         'sanitize' => 'wp_kses_post' ],
            'opac_legal_cgu'             => [ 'type' => 'string', 'default' => self::default_legal( 'cgu' ),             'sanitize' => 'wp_kses_post' ],
        ];
    }

    /**
     * Carte des pages legales : slug de page => { cle d'option de contenu, titre }.
     * Source de verite partagee : enregistrement des options (ci-dessus), creation
     * des pages (ensure_legal_pages), rendu front (OPAC_Blocks::render_legal_content),
     * verrouillage de l'editeur et blocage de la suppression (OPAC_Admin), liens footer.
     */
    public static function legal_pages() {
        return [
            'politique-de-confidentialite' => [ 'key' => 'opac_legal_confidentialite', 'title' => __( 'Politique de confidentialité', 'opac-custom' ) ],
            'mentions-legales'             => [ 'key' => 'opac_legal_mentions',        'title' => __( 'Mentions légales', 'opac-custom' ) ],
            'politique-cookies'            => [ 'key' => 'opac_legal_cookies',         'title' => __( 'Politique cookies', 'opac-custom' ) ],
            'conditions-generales'         => [ 'key' => 'opac_legal_cgu',             'title' => __( 'Conditions générales d\'utilisation', 'opac-custom' ) ],
        ];
    }

    /**
     * Cree les pages legales manquantes (slug + titre depuis legal_pages()) afin
     * que les templates page-<slug>.html et les liens du footer se resolvent. Le
     * contenu vit dans les Reglages (le post_content de la page n'est jamais lu) ;
     * la page n'est qu'un point d'ancrage d'URL. Idempotent : une page deja
     * presente (meme slug) n'est pas touchee.
     */
    public static function ensure_legal_pages() {
        foreach ( self::legal_pages() as $slug => $info ) {
            if ( get_page_by_path( $slug ) ) {
                continue;
            }
            wp_insert_post( [
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'post_name'      => $slug,
                'post_title'     => $info['title'],
                'post_content'   => '',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ] );
        }
    }

    /**
     * Lance ensure_legal_pages() une seule fois par version (evite 4 requetes par
     * chargement). Appele sur init ; complete le seeding a l'activation pour les
     * installations ou le plugin etait deja actif.
     */
    public static function maybe_seed_legal_pages() {
        if ( get_option( self::LEGAL_SEED_FLAG ) === OPAC_CUSTOM_VERSION ) {
            return;
        }
        self::ensure_legal_pages();
        // Regenere les permaliens une fois par version : les archives de CPT
        // (/ateliers/, /ephemeres/, /agenda/) reposent sur les regles de reecriture,
        // qui peuvent etre perimees apres un deploiement ou une migration et
        // provoquer des 404 d'archive (contenu intact mais URL non routee). Ce hook
        // tourne a la priorite init par defaut (10), donc apres l'enregistrement des
        // CPT (priorite 5) et des taxonomies (6) : le flush capture bien toutes leurs
        // regles. Soft flush (regles stockees en option, suffisant pour le routage WP ;
        // pas de .htaccess requis sous nginx).
        flush_rewrite_rules( false );
        update_option( self::LEGAL_SEED_FLAG, OPAC_CUSTOM_VERSION );
    }

    public static function register_settings() {
        foreach ( self::options_schema() as $key => $meta ) {
            register_setting( self::OPTION_GRP, $key, [
                'type'              => $meta['type'],
                'default'           => $meta['default'],
                'sanitize_callback' => $meta['sanitize'],
            ] );
        }
    }

    /**
     * Templates emails par defaut, avec placeholders {prenom}, {nom},
     * {atelier}, {tarif}, {adresse}, {tel}, {horaires}.
     */
    private static function default_email( $type ) {
        $signature = "\n\n--\n{nom_asso}\n{adresse}\nTéléphone : {tel}\nEmail : {email}\n";
        switch ( $type ) {
            case 'validee':
                return "Bonjour {prenom},\n\nVotre demande d'inscription pour \"{atelier}\" a été validée.\n\nTarif de l'atelier : {tarif}\nLe règlement (tarif + adhésion annuelle à l'association) s'effectue sur place au secrétariat, en chèque, espèces ou CB :\n{horaires}\n{adresse}\n\nÀ très bientôt !" . $signature;

            case 'refusee':
                return "Bonjour {prenom},\n\nNous vous remercions de l'intérêt porté à \"{atelier}\".\n\nAprès examen, nous ne pouvons pas donner suite favorablement à votre demande pour le moment. N'hésitez pas à nous contacter au {tel} pour en discuter ou nous orienter vers un autre atelier susceptible de vous intéresser.\n\nBien cordialement," . $signature;

            case 'liste-attente':
                return "Bonjour {prenom},\n\nL'atelier \"{atelier}\" étant complet à ce jour, votre demande a été enregistrée en liste d'attente.\n\nNous vous recontacterons dès qu'une place se libère. Vous pouvez également nous contacter au {tel} si vous souhaitez vous orienter vers un autre atelier.\n\nBien cordialement," . $signature;

            case 'place-liberee':
                return "Bonjour {prenom},\n\nBonne nouvelle : une place s'est libérée pour \"{atelier}\" ({tarif}).\n\nVotre demande repasse en cours de traitement. Le règlement se fait sur place au secrétariat ; en cours d'année, le tarif de l'atelier est ajusté au prorata des séances restantes.\n\nMerci de nous confirmer rapidement votre intérêt au {tel} ou par retour d'email.\n\nBien cordialement," . $signature;
        }
        return '';
    }

    /**
     * Contenu HTML de depart des pages legales. Textes generiques a faire relire /
     * completer par l'association (responsabilite juridique). Placeholders
     * {nom_asso} {adresse} {tel} {email} substitues au rendu (cf.
     * OPAC_Blocks::render_legal_content). Ne PAS appeler self::get() ici :
     * default_legal() est invoque depuis options_schema(), elle-meme appelee par
     * get() (recursion).
     */
    private static function default_legal( $type ) {
        switch ( $type ) {
            case 'mentions':
                return "<h2>Éditeur du site</h2>\n"
                    . "<p>{nom_asso}<br>{adresse}<br>Téléphone : {tel}<br>Email : {email}</p>\n"
                    . "<p>Association loi 1901.</p>\n"
                    . "<h2>Directeur de la publication</h2>\n"
                    . "<p>Le représentant légal de l'association.</p>\n"
                    . "<h2>Hébergement</h2>\n"
                    . "<p>Ce site est hébergé par OVH SAS, 2 rue Kellermann, 59100 Roubaix, France (RCS Lille Métropole 424 761 419 00045).</p>\n"
                    . "<h2>Propriété intellectuelle</h2>\n"
                    . "<p>L'ensemble des contenus de ce site (textes, images, logo) est la propriété de {nom_asso}, sauf mention contraire. Toute reproduction sans autorisation est interdite.</p>";

            case 'confidentialite':
                return "<h2>Responsable du traitement</h2>\n"
                    . "<p>{nom_asso}, {adresse}. Pour toute question : {email} ou {tel}.</p>\n"
                    . "<h2>Données collectées</h2>\n"
                    . "<p>Lorsque vous remplissez le formulaire d'inscription ou de contact, nous collectons les informations que vous nous transmettez (nom, prénom, email, téléphone, commune, message). Aucune donnée n'est collectée à votre insu.</p>\n"
                    . "<h2>Finalité</h2>\n"
                    . "<p>Ces données servent uniquement à traiter votre demande d'inscription ou votre message. Elles ne sont ni vendues ni transmises à des tiers.</p>\n"
                    . "<h2>Durée de conservation</h2>\n"
                    . "<p>Les demandes d'inscription envoyées en ligne sont conservées le temps nécessaire à leur traitement, puis supprimées automatiquement.</p>\n"
                    . "<h2>Vos droits</h2>\n"
                    . "<p>Conformément au RGPD, vous disposez d'un droit d'accès, de rectification et de suppression de vos données. Pour l'exercer, écrivez-nous à {email}.</p>";

            case 'cookies':
                return "<h2>Utilisation des cookies</h2>\n"
                    . "<p>Ce site utilise uniquement les cookies techniques nécessaires à son bon fonctionnement. Il ne dépose aucun cookie publicitaire ni de suivi à des fins commerciales.</p>\n"
                    . "<h2>Contenus externes</h2>\n"
                    . "<p>Certaines pages peuvent intégrer des contenus externes (par exemple une carte de localisation) susceptibles de déposer leurs propres cookies. Vous pouvez configurer votre navigateur pour les refuser.</p>";

            case 'cgu':
                return "<h2>Objet</h2>\n"
                    . "<p>Les présentes conditions régissent l'utilisation du site de {nom_asso}.</p>\n"
                    . "<h2>Accès au site</h2>\n"
                    . "<p>Le site est accessible gratuitement. {nom_asso} s'efforce d'en assurer la disponibilité sans pouvoir en garantir l'accès permanent.</p>\n"
                    . "<h2>Inscriptions</h2>\n"
                    . "<p>Une demande d'inscription effectuée en ligne ne vaut pas inscription définitive : elle est confirmée par l'association, le règlement s'effectuant sur place au secrétariat.</p>\n"
                    . "<h2>Contact</h2>\n"
                    . "<p>Pour toute question : {email} ou {tel}.</p>";
        }
        return '';
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'OPAC - Réglages', 'opac-custom' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Centralise toutes les données métier modifiables sans toucher au code. Chaque changement se propage automatiquement dans les pages, formulaires, emails et données SEO.', 'opac-custom' ); ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields( self::OPTION_GRP ); ?>

                <h2><?php esc_html_e( 'Coordonnées de l\'association', 'opac-custom' ); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::render_input_row( 'opac_org_name', __( 'Nom court', 'opac-custom' ), __( 'Affiché dans le footer et og:site_name', 'opac-custom' ) );
                    self::render_input_row( 'opac_org_legal_name', __( 'Nom légal complet', 'opac-custom' ), __( 'Utilisé dans le JSON-LD Schema.org et les mentions légales', 'opac-custom' ) );
                    self::render_input_row( 'opac_org_address_street', __( 'Adresse (rue)', 'opac-custom' ) );
                    self::render_input_row( 'opac_org_address_postal', __( 'Code postal', 'opac-custom' ) );
                    self::render_input_row( 'opac_org_address_city', __( 'Ville', 'opac-custom' ) );
                    self::render_input_row( 'opac_org_phone_accueil', __( 'Téléphone accueil', 'opac-custom' ) );
                    self::render_input_row( 'opac_org_phone_admin', __( 'Téléphone administration', 'opac-custom' ) );
                    self::render_input_row( 'opac_org_email', __( 'Email', 'opac-custom' ), __( 'Destinataire des formulaires contact + inscriptions', 'opac-custom' ) );
                    self::render_input_row( 'opac_org_hours', __( 'Horaires', 'opac-custom' ) );
                    self::render_input_row( 'opac_org_founding_year', __( 'Année de fondation', 'opac-custom' ), '', 'number' );
                    self::render_input_row( 'opac_org_facebook_url', __( 'URL Facebook', 'opac-custom' ), __( 'Laisser vide pour masquer le lien', 'opac-custom' ), 'url' );
                    self::render_input_row( 'opac_org_instagram_url', __( 'URL Instagram', 'opac-custom' ), __( 'Laisser vide pour masquer le lien', 'opac-custom' ), 'url' );
                    self::render_input_row( 'opac_org_tiktok_url', __( 'URL TikTok', 'opac-custom' ), __( 'Laisser vide pour masquer le lien', 'opac-custom' ), 'url' );
                    self::render_media_row( 'opac_org_statuts_pdf_url', __( 'Statuts (PDF)', 'opac-custom' ), __( 'Affiché en lien sur la page Association. Cliquez sur « Choisir un fichier » pour téléverser ou remplacer le document via la médiathèque. Laisser vide pour masquer le lien.', 'opac-custom' ) );
                    self::render_media_row( 'opac_org_charte_pdf_url', __( 'Charte des ateliers', 'opac-custom' ), __( 'Affichée en lien dans le footer (PDF ou image). Cliquez sur « Choisir un fichier » pour téléverser ou remplacer le document via la médiathèque. Laisser vide pour masquer le lien.', 'opac-custom' ) );
                    self::render_input_row( 'opac_org_helloasso_url', __( 'URL HelloAsso (dons)', 'opac-custom' ), __( 'Lien vers la page de dons HelloAsso. Laisser vide pour masquer (footer + page Association).', 'opac-custom' ), 'url' );
                    self::render_input_row( 'opac_org_helloasso_qr_url', __( 'URL image QR HelloAsso', 'opac-custom' ), __( 'Optionnel. Uploadez le QR dans la médiathèque et collez son URL ici. Vide = placeholder dans la bande Soutenir.', 'opac-custom' ), 'url' );
                    self::render_media_row( 'opac_org_partenaire_logo_url', __( 'Logo partenaire Ville de Plérin', 'opac-custom' ), __( 'Affiché sur la page Association, à côté de « Ville de Plérin ». Cliquez sur « Choisir un fichier » pour téléverser le logo via la médiathèque. Laisser vide pour garder le placeholder texte.', 'opac-custom' ) );
                    ?>
                </table>

                <h2><?php esc_html_e( 'Tarifs d\'adhésion annuelle', 'opac-custom' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Affichés sur le formulaire d\'inscription. Modifier ici change automatiquement les options du formulaire.', 'opac-custom' ); ?></p>
                <table class="form-table" role="presentation">
                    <?php
                    self::render_input_row( 'opac_adhesion_plerinais', __( 'Plérinais (€)', 'opac-custom' ), '', 'number' );
                    self::render_input_row( 'opac_adhesion_exterieur', __( 'Extérieur (€)', 'opac-custom' ), '', 'number' );
                    self::render_input_row( 'opac_adhesion_mineur', __( 'Mineur (€)', 'opac-custom' ), '', 'number' );
                    ?>
                </table>

                <h2><?php esc_html_e( 'Saison + Page d\'accueil', 'opac-custom' ); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::render_input_row( 'opac_home_saison_badge', __( 'Badge saison', 'opac-custom' ), __( 'Affiché en haut du hero homepage. Ex: "Saison 2026 / 2027"', 'opac-custom' ) );
                    self::render_input_row( 'opac_home_hero_title', __( 'Titre hero homepage', 'opac-custom' ) );
                    self::render_textarea_row( 'opac_home_hero_intro', __( 'Intro hero homepage', 'opac-custom' ), '', 3 );
                    self::render_input_row( 'opac_home_cta_band_title', __( 'Titre bande CTA inscription', 'opac-custom' ), __( 'Section terracotta en bas de homepage', 'opac-custom' ) );
                    self::render_textarea_row( 'opac_home_cta_band_text', __( 'Texte bande CTA', 'opac-custom' ), '', 2 );
                    ?>
                </table>

                <h2><?php esc_html_e( 'Statistiques homepage', 'opac-custom' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Les 3 chiffres affichés sous le hero. "Années d\'activité" est calculé automatiquement depuis l\'année de fondation.', 'opac-custom' ); ?></p>
                <table class="form-table" role="presentation">
                    <?php
                    self::render_checkbox_row( 'opac_stats_ateliers_auto', __( 'Nombre d\'ateliers (auto)', 'opac-custom' ), __( 'Compte automatique des ateliers publiés. Si décoché, utilise le champ ci-dessous.', 'opac-custom' ) );
                    self::render_input_row( 'opac_stats_ateliers', __( 'Nombre d\'ateliers (manuel)', 'opac-custom' ), __( 'Ignoré si "auto" est coché', 'opac-custom' ) );
                    self::render_input_row( 'opac_stats_adherents', __( 'Nombre d\'adhérents', 'opac-custom' ), __( 'Ex: "200+"', 'opac-custom' ) );
                    ?>
                </table>

                <h2><?php esc_html_e( 'Templates des emails d\'inscription', 'opac-custom' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Emails automatiquement envoyés à l\'inscrit selon le statut choisi (Valider / Refuser / Liste d\'attente).', 'opac-custom' ); ?><br>
                    <?php esc_html_e( 'Placeholders disponibles :', 'opac-custom' ); ?>
                    <code>{prenom}</code> <code>{nom}</code> <code>{atelier}</code> <code>{tarif}</code>
                    <code>{adresse}</code> <code>{tel}</code> <code>{email}</code> <code>{horaires}</code> <code>{nom_asso}</code>
                </p>
                <table class="form-table" role="presentation">
                    <?php
                    self::render_textarea_row( 'opac_email_validee', __( 'Email validation', 'opac-custom' ), '', 10 );
                    self::render_textarea_row( 'opac_email_refusee', __( 'Email refus', 'opac-custom' ), '', 10 );
                    self::render_textarea_row( 'opac_email_liste_attente', __( 'Email liste d\'attente', 'opac-custom' ), '', 10 );
                    self::render_textarea_row( 'opac_email_place_liberee', __( 'Email place libérée (désistement)', 'opac-custom' ), '', 10 );
                    ?>
                </table>

                <h2><?php esc_html_e( 'Sujets du formulaire de contact', 'opac-custom' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Un sujet par ligne, au format : slug | Libellé affiché. Le slug est utilisé en interne (pas visible).', 'opac-custom' ); ?>
                </p>
                <table class="form-table" role="presentation">
                    <?php
                    self::render_textarea_row( 'opac_contact_subjects', __( 'Sujets disponibles', 'opac-custom' ), '', 6 );
                    ?>
                </table>

                <h2><?php esc_html_e( 'Période d\'inscription', 'opac-custom' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Dates affichées en information sur le formulaire d\'inscription. Le formulaire reste accessible en permanence : ces dates servent uniquement à indiquer la phase en cours (réinscription prioritaire des adhérents, puis ouverture générale).', 'opac-custom' ); ?>
                </p>
                <table class="form-table" role="presentation">
                    <?php
                    self::render_input_row( 'opac_insc_date_reinscription', __( 'Début réinscription prioritaire', 'opac-custom' ), __( 'Semaine où les adhérents déjà inscrits peuvent renouveler leur place en priorité.', 'opac-custom' ), 'date' );
                    self::render_input_row( 'opac_insc_date_ouverture', __( 'Début inscription générale', 'opac-custom' ), __( 'Ouverture des inscriptions à tous (priorité aux Plérinais).', 'opac-custom' ), 'date' );
                    self::render_input_row( 'opac_insc_date_fermeture', __( 'Fin des inscriptions', 'opac-custom' ), __( 'Optionnel. Laisser vide s\'il n\'y a pas de date de clôture.', 'opac-custom' ), 'date' );
                    self::render_input_row( 'opac_insc_date_confirmation', __( 'Date de confirmation des nouvelles inscriptions', 'opac-custom' ), __( 'Optionnel. Affichée pendant la phase d\'inscription ouverte : les nouvelles demandes seront confirmées à partir de cette date.', 'opac-custom' ), 'date' );
                    ?>
                </table>

                <h2><?php esc_html_e( 'Données personnelles (RGPD)', 'opac-custom' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Suppression automatique des anciennes demandes reçues via le formulaire d\'inscription en ligne (nom, prénom, email, téléphone, message), pour respecter la limitation de conservation prévue par le RGPD. Un nettoyage a lieu chaque jour. Vos adhérents gérés par ailleurs (papier, tableur) ne sont pas concernés.', 'opac-custom' ); ?>
                </p>
                <table class="form-table" role="presentation">
                    <?php
                    self::render_input_row( 'opac_insc_purge_months', __( 'Durée de conservation (mois)', 'opac-custom' ), __( 'Les demandes d\'inscription plus anciennes que cette durée sont supprimées automatiquement. Exemple : 24 = deux ans. Mettre 0 pour désactiver la suppression automatique.', 'opac-custom' ), 'number' );
                    ?>
                </table>

                <h2><?php esc_html_e( 'Pages légales', 'opac-custom' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Modifiez le texte de chaque page comme dans un traitement de texte (gras, titres, listes, liens), sans connaître le HTML.', 'opac-custom' ); ?>
                    <?php esc_html_e( 'Les mentions {nom_asso}, {adresse}, {tel} et {email} sont remplacées automatiquement par les coordonnées de l\'association : laissez-les telles quelles.', 'opac-custom' ); ?>
                </p>
                <?php
                self::render_editor_row( 'opac_legal_confidentialite', __( 'Politique de confidentialité', 'opac-custom' ) );
                self::render_editor_row( 'opac_legal_mentions', __( 'Mentions légales', 'opac-custom' ) );
                self::render_editor_row( 'opac_legal_cookies', __( 'Politique cookies', 'opac-custom' ) );
                self::render_editor_row( 'opac_legal_cgu', __( 'Conditions générales d\'utilisation', 'opac-custom' ) );
                ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function render_input_row( $key, $label, $desc = '', $type = 'text' ) {
        $value = self::get( $key );
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                <?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private static function render_textarea_row( $key, $label, $desc = '', $rows = 4 ) {
        $value = self::get( $key );
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="<?php echo (int) $rows; ?>" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
                <?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Champ « éditeur visuel » (TinyMCE) pour un contenu HTML riche : bien plus
     * simple qu'un textarea HTML pour une personne non technique (gras, titres,
     * listes, liens via des boutons, sans voir les balises). La valeur reste du
     * HTML, nettoyée par wp_kses_post à l'enregistrement. L'editor_id ne doit pas
     * contenir d'underscore (TinyMCE) ; le champ posté garde la clé d'option
     * exacte grâce à textarea_name. La liste des formats est volontairement
     * réduite (Paragraphe / Titre de section / Sous-titre) pour rester simple.
     */
    private static function render_editor_row( $key, $label ) {
        $value     = self::get( $key );
        $editor_id = str_replace( '_', '', $key );
        echo '<h3 style="margin:24px 0 6px">' . esc_html( $label ) . '</h3>';
        wp_editor( $value, $editor_id, [
            'textarea_name' => $key,
            'media_buttons' => false,
            'textarea_rows' => 12,
            'tinymce'       => [
                'toolbar1'      => 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo',
                'toolbar2'      => '',
                'block_formats' => 'Paragraphe=p;Titre de section=h2;Sous-titre=h3',
            ],
            'quicktags'     => [ 'buttons' => 'strong,em,link,ul,ol,li' ],
        ] );
    }

    private static function render_checkbox_row( $key, $label, $desc = '' ) {
        $value = (int) self::get( $key );
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <label>
                    <input type="checkbox" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( 1, $value ); ?> />
                    <?php if ( $desc ) : ?><?php echo esc_html( $desc ); ?><?php endif; ?>
                </label>
            </td>
        </tr>
        <?php
    }

    /**
     * Ligne « média » : champ URL alimenté par la médiathèque via un bouton
     * « Choisir un fichier » (téléversement/remplacement d'un document, ex :
     * PDF charte), pour éviter le copier-coller d'URL. Le script
     * admin-settings-media ouvre wp.media et écrit l'URL choisie dans l'input.
     */
    private static function render_media_row( $key, $label, $desc = '' ) {
        $value = self::get( $key );
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <span class="opac-media-field">
                    <input type="url" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text opac-media-url" placeholder="<?php esc_attr_e( 'Aucun fichier sélectionné', 'opac-custom' ); ?>" />
                    <button type="button" class="button opac-media-choose"><?php esc_html_e( 'Choisir un fichier', 'opac-custom' ); ?></button>
                    <button type="button" class="button-link opac-media-remove"<?php echo $value ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Retirer', 'opac-custom' ); ?></button>
                </span>
                <?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Charge la médiathèque + le script d'upload uniquement sur la page
     * OPAC Réglages (sélecteur de fichier des champs média, ex : charte PDF).
     */
    public static function enqueue_assets( $hook ) {
        if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'opac-settings-media',
            OPAC_CUSTOM_URL . 'assets/js/admin-settings-media.js',
            [ 'jquery' ],
            OPAC_CUSTOM_VERSION,
            true
        );
        wp_localize_script( 'opac-settings-media', 'opacSettingsMedia', [
            'title'  => __( 'Choisir un fichier', 'opac-custom' ),
            'button' => __( 'Utiliser ce fichier', 'opac-custom' ),
        ] );
    }

    /**
     * Helper public : retourne la valeur d'une option avec son default.
     * Lue partout dans le plugin/theme via OPAC_Settings::get('opac_org_email').
     */
    public static function get( $key ) {
        $schema = self::options_schema();
        $default = isset( $schema[ $key ] ) ? $schema[ $key ]['default'] : '';
        return get_option( $key, $default );
    }

    /**
     * Helper : compose une adresse formatee depuis les 3 champs.
     */
    public static function full_address() {
        return sprintf(
            '%s, %s %s',
            self::get( 'opac_org_address_street' ),
            self::get( 'opac_org_address_postal' ),
            self::get( 'opac_org_address_city' )
        );
    }

    /**
     * Helper : retourne le nombre d'annees depuis la fondation.
     */
    public static function years_since_founding() {
        $founding = (int) self::get( 'opac_org_founding_year' );
        if ( $founding <= 0 ) {
            return 0;
        }
        return max( 0, (int) wp_date( 'Y' ) - $founding );
    }

    /**
     * Helper : retourne le nombre d'ateliers (auto ou manuel selon l'option).
     */
    public static function stats_ateliers() {
        if ( (int) self::get( 'opac_stats_ateliers_auto' ) === 1 ) {
            $count = wp_count_posts( 'opac_atelier' );
            return $count && isset( $count->publish ) ? (string) $count->publish : '0';
        }
        return (string) self::get( 'opac_stats_ateliers' );
    }

    /**
     * Parse le textarea sujets contact : 1 ligne = slug | label.
     * Retourne array [ slug => label ].
     */
    public static function contact_subjects() {
        $raw = (string) self::get( 'opac_contact_subjects' );
        $out = [];
        foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
            $line = trim( $line );
            if ( $line === '' ) {
                continue;
            }
            $parts = array_map( 'trim', explode( '|', $line, 2 ) );
            if ( count( $parts ) === 2 && $parts[0] !== '' ) {
                $out[ sanitize_key( $parts[0] ) ] = $parts[1];
            }
        }
        if ( empty( $out ) ) {
            // Fallback si textarea vide.
            $out = [
                'renseignement' => 'Renseignement général',
                'autre'         => 'Autre',
            ];
        }
        return $out;
    }

    /**
     * Phase d'inscription courante, calculee depuis les 3 dates du panel.
     * Gating souple : sert uniquement a afficher un message, jamais a bloquer.
     *
     * @return string 'avant' | 'reinscription' | 'ouverte' | 'fermee'
     */
    public static function inscription_phase() {
        $today = current_time( 'Y-m-d' );
        $rein  = (string) self::get( 'opac_insc_date_reinscription' );
        $ouv   = (string) self::get( 'opac_insc_date_ouverture' );
        $ferm  = (string) self::get( 'opac_insc_date_fermeture' );

        if ( $ferm && $today > $ferm ) {
            return 'fermee';
        }
        if ( $ouv && $today >= $ouv ) {
            return 'ouverte';
        }
        if ( $rein && $today >= $rein ) {
            return 'reinscription';
        }
        if ( $rein || $ouv ) {
            return 'avant';
        }
        // Aucune date configuree : comportement actuel (toujours ouvert).
        return 'ouverte';
    }
}
