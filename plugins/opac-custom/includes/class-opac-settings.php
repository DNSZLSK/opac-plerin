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

    const PAGE_SLUG  = 'opac-settings';
    const OPTION_GRP = 'opac_settings_group';

    public static function register() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
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
            'opac_org_address_city'      => [ 'type' => 'string',  'default' => 'Plérin-sur-Mer',                               'sanitize' => 'sanitize_text_field' ],
            'opac_org_phone_accueil'     => [ 'type' => 'string',  'default' => '02 96 74 53 08',                               'sanitize' => 'sanitize_text_field' ],
            'opac_org_phone_admin'       => [ 'type' => 'string',  'default' => '06 74 47 98 53',                               'sanitize' => 'sanitize_text_field' ],
            'opac_org_email'             => [ 'type' => 'string',  'default' => 'contact@opacplerin.fr',                        'sanitize' => 'sanitize_email' ],
            'opac_org_hours'             => [ 'type' => 'string',  'default' => 'Lundi à vendredi, 14h15 à 17h45',              'sanitize' => 'sanitize_text_field' ],
            'opac_org_founding_year'     => [ 'type' => 'integer', 'default' => 1980,                                           'sanitize' => 'absint' ],
            'opac_org_facebook_url'      => [ 'type' => 'string',  'default' => '',                                             'sanitize' => 'esc_url_raw' ],
            'opac_org_instagram_url'     => [ 'type' => 'string',  'default' => '',                                             'sanitize' => 'esc_url_raw' ],
            'opac_org_statuts_pdf_url'   => [ 'type' => 'string',  'default' => '',                                             'sanitize' => 'esc_url_raw' ],

            // Section 2 : Tarifs adhesion
            'opac_adhesion_plerinais'    => [ 'type' => 'integer', 'default' => 15, 'sanitize' => 'absint' ],
            'opac_adhesion_exterieur'    => [ 'type' => 'integer', 'default' => 30, 'sanitize' => 'absint' ],
            'opac_adhesion_mineur'       => [ 'type' => 'integer', 'default' => 10, 'sanitize' => 'absint' ],

            // Section 3 : Saison + Hero homepage
            'opac_home_saison_badge'     => [ 'type' => 'string', 'default' => 'Saison 2025 / 2026',                                                                    'sanitize' => 'sanitize_text_field' ],
            'opac_home_hero_title'       => [ 'type' => 'string', 'default' => 'La culture au bout des doigts',                                                          'sanitize' => 'sanitize_text_field' ],
            'opac_home_hero_intro'       => [ 'type' => 'string', 'default' => 'Ateliers d\'expression culturelle, activités éphémères et sorties pour tous les âges. Association OPAC, asso loi 1901 à Plérin-sur-Mer.', 'sanitize' => 'sanitize_textarea_field' ],
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

            // Section 6 : Sujets dropdown contact
            'opac_contact_subjects'      => [ 'type' => 'string', 'default' => "renseignement | Renseignement général\natelier-annee | Inscription atelier à l'année\nephemere | Atelier éphémère\nadhesion | Adhésion\nautre | Autre", 'sanitize' => 'sanitize_textarea_field' ],

            // Section 7 : Période d'inscription (workflow inscription, palier 1)
            'opac_insc_date_reinscription' => [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_text_field' ],
            'opac_insc_date_ouverture'     => [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_text_field' ],
            'opac_insc_date_fermeture'     => [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_text_field' ],
        ];
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
                    self::render_input_row( 'opac_org_statuts_pdf_url', __( 'URL PDF des statuts', 'opac-custom' ), __( 'Lien affiché sur la page Association', 'opac-custom' ), 'url' );
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
                    ?>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function render_input_row( $key, $label, $desc = '', $type = 'text' ) {
        $value = get_option( $key, '' );
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
        $value = get_option( $key, '' );
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

    private static function render_checkbox_row( $key, $label, $desc = '' ) {
        $value = (int) get_option( $key, 0 );
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
