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

    /** Types geres dans les listes admin (confirms suppression + Quick Edit retire). */
    const CONFIRM_TYPES = [ 'opac_atelier', 'opac_stage', 'opac_event', 'opac_person', 'opac_inscription' ];

    public static function boot() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_filter( 'manage_opac_atelier_posts_columns', [ __CLASS__, 'atelier_columns' ] );
        add_action( 'manage_opac_atelier_posts_custom_column', [ __CLASS__, 'atelier_column_content' ], 10, 2 );
        add_filter( 'manage_opac_inscription_posts_columns', [ __CLASS__, 'inscription_columns' ] );
        add_action( 'manage_opac_inscription_posts_custom_column', [ __CLASS__, 'inscription_column_content' ], 10, 2 );
        add_filter( 'post_row_actions', [ __CLASS__, 'inscription_row_actions' ], 10, 2 );
        add_filter( 'post_row_actions', [ __CLASS__, 'remove_quick_edit' ], 10, 2 );
        add_action( 'admin_notices', [ __CLASS__, 'inscription_action_notice' ] );
        add_action( 'restrict_manage_posts', [ __CLASS__, 'inscription_status_filter' ] );
        add_action( 'pre_get_posts', [ __CLASS__, 'filter_inscriptions_by_atelier' ] );
        add_filter( 'posts_clauses', [ __CLASS__, 'inscription_priority_clauses' ], 10, 2 );
        add_filter( 'the_posts', [ __CLASS__, 'prime_returning_cache' ], 10, 2 );
        add_action( 'admin_notices', [ __CLASS__, 'inscription_email_notice' ] );

        // Galerie : meta box "atelier associe" + colonnes liste.
        add_action( 'add_meta_boxes', [ __CLASS__, 'gallery_meta_box' ] );
        add_action( 'save_post_opac_gallery_item', [ __CLASS__, 'save_gallery_meta' ], 10, 2 );
        add_filter( 'manage_opac_gallery_item_posts_columns', [ __CLASS__, 'gallery_columns' ] );
        add_action( 'manage_opac_gallery_item_posts_custom_column', [ __CLASS__, 'gallery_column_content' ], 10, 2 );

        // Ateliers : meta box d'edition des creneaux structures (palier 2).
        add_action( 'add_meta_boxes', [ __CLASS__, 'atelier_creneaux_meta_box' ] );
        add_action( 'save_post_opac_atelier', [ __CLASS__, 'save_atelier_creneaux' ], 10, 2 );

        // Ephemeres : meta box d'edition des seances datees (modele hybride).
        add_action( 'add_meta_boxes', [ __CLASS__, 'stage_seances_meta_box' ] );
        add_action( 'save_post_opac_stage', [ __CLASS__, 'save_stage_seances' ], 10, 2 );

        // Inscriptions : fiche editable (consultation + saisie manuelle) remplacant
        // les champs bruts. Le titre [Atelier] Prenom Nom est compose a l'insert.
        add_action( 'add_meta_boxes', [ __CLASS__, 'inscription_details_meta_box' ] );
        add_action( 'save_post_opac_inscription', [ __CLASS__, 'save_inscription_details' ], 10, 2 );
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'inject_inscription_title' ], 10, 2 );

    }

    /**
     * Verrous des pages dont le contenu n'est pas edite dans l'editeur de pages.
     *
     * Enregistre au chargement du plugin, et non dans boot() : boot() est
     * accroche a admin_init, qui ne se declenche pas sur les requetes REST. Or
     * « Apparence > Editeur > Pages » (thème FSE) enregistre justement en REST,
     * sans passer ni par admin_init ni par load-post.php. Un verrou pose dans
     * boot() est donc absent du chemin d'ecriture le plus accessible.
     *
     * Les hooks purement admin de la liste (redirection, notice, actions de
     * ligne) sont inoffensifs hors admin : ils ne se declenchent jamais.
     */
    public static function boot_page_guards() {
        add_filter( 'map_meta_cap', [ __CLASS__, 'protect_locked_pages_caps' ], 10, 4 );
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'protect_locked_page_slug' ], 10, 2 );
        // wp_trash_post() et wp_delete_post() ne verifient aucune capacite :
        // le refus de delete_post ferme les chemins d'interface, pas l'API.
        add_filter( 'pre_trash_post', [ __CLASS__, 'block_locked_page_removal' ], 10, 2 );
        add_filter( 'pre_delete_post', [ __CLASS__, 'block_locked_page_removal' ], 10, 2 );
        add_action( 'load-post.php', [ __CLASS__, 'redirect_locked_page_editor' ] );
        add_filter( 'page_row_actions', [ __CLASS__, 'locked_page_row_actions' ], 10, 2 );
        add_action( 'admin_notices', [ __CLASS__, 'locked_page_notice' ] );
    }

    /** Meta posee sur les pages legales, marqueur independant du slug. */
    const LEGAL_MARKER = '_opac_legal_key';

    /**
     * Vrai si la page est verrouillee, c'est-a-dire si l'editeur de pages n'a
     * aucune prise reelle sur ce qui s'affiche.
     *
     * Critere unique et auto-entretenu : le template qui rend la page ne
     * contient pas de bloc `wp:post-content`. Dans ce cas le post_content n'est
     * jamais lu, et laisser la page modifiable revient a offrir un formulaire
     * qui n'ecrit nulle part. C'est le cas des 4 pages legales (contenu dans les
     * Reglages) comme de Contact, Inscription et Association (composees par des
     * blocs serveur du plugin).
     *
     * L'interet de deduire plutot que de lister : une page ajoutee plus tard
     * avec son propre template se verrouille seule, et si on ajoute un jour un
     * `wp:post-content` a l'un de ces templates, la page redevient modifiable
     * sans qu'il y ait quoi que ce soit a defaire ici.
     */
    private static function is_locked_page( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || 'page' !== $post->post_type ) {
            return false;
        }
        return self::template_hides_content( $post );
    }

    /**
     * Vrai si le template qui rend $post n'affiche pas son post_content.
     *
     * Resolution volontairement calquee sur la hierarchie de WordPress, via
     * get_block_template() qui rend aussi bien un template de fichier qu'une
     * version personnalisee stockee en base (une retouche dans l'editeur de site
     * doit etre prise en compte, sinon le verrou se baserait sur un fichier qui
     * n'est plus celui qui s'affiche).
     *
     * En cas de doute (aucun template resolu, theme non-bloc), on rend false :
     * un verrou qui se trompe doit laisser passer, jamais bloquer tout le site.
     * Resultat mis en cache par requete : map_meta_cap est appele en rafale sur
     * la liste des pages.
     */
    private static function template_hides_content( $post ) {
        static $cache = [];
        if ( isset( $cache[ $post->ID ] ) ) {
            return $cache[ $post->ID ];
        }

        // Avant init, le type wp_template n'est pas encore enregistre : la
        // resolution rendrait un faux negatif. On repond false sans le mettre en
        // cache, pour que la vraie reponse soit calculee au premier appel utile.
        if ( ! did_action( 'init' ) ) {
            return false;
        }

        if ( ! function_exists( 'get_block_template' ) || ! wp_is_block_theme() ) {
            return $cache[ $post->ID ] = false;
        }

        // Hierarchie des pages, du plus specifique au plus general. Un template
        // choisi a la main sur la fiche (_wp_page_template) prime sur tout.
        $candidates = [];
        $chosen     = (string) get_post_meta( $post->ID, '_wp_page_template', true );
        if ( '' !== $chosen && 'default' !== $chosen ) {
            $candidates[] = $chosen;
        }
        $candidates = array_merge( $candidates, [
            'page-' . $post->post_name,
            'page-' . $post->ID,
            'page',
            'singular',
            'index',
        ] );

        $stylesheet = get_stylesheet();
        foreach ( $candidates as $slug ) {
            $template = get_block_template( $stylesheet . '//' . $slug, 'wp_template' );
            if ( $template && isset( $template->content ) ) {
                return $cache[ $post->ID ] = ( false === strpos( $template->content, 'wp:post-content' ) );
            }
        }

        return $cache[ $post->ID ] = false;
    }

    /**
     * Vrai si $post_id est l'une des pages legales gerees via les Reglages.
     *
     * Le marqueur de meta fait foi et resiste a un changement de slug. Le match
     * par slug reste en second (pages creees avant le marqueur) et retro-pose la
     * meta au passage, pour que la reconnaissance ne dependent plus de l'URL.
     */
    private static function is_legal_page( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || 'page' !== $post->post_type || ! class_exists( 'OPAC_Settings' ) ) {
            return false;
        }

        if ( '' !== (string) get_post_meta( $post->ID, self::LEGAL_MARKER, true ) ) {
            return true;
        }

        if ( array_key_exists( $post->post_name, OPAC_Settings::legal_pages() ) ) {
            update_post_meta( $post->ID, self::LEGAL_MARKER, $post->post_name );
            return true;
        }

        return false;
    }

    /**
     * Ouvrir une page verrouillee dans l'editeur classique redirige, au lieu de
     * laisser WordPress afficher son « vous n'avez pas l'autorisation » brut. La
     * redirection est posee sur load-post.php, donc avant la verification de
     * edit_post.
     *
     * Destination selon ce qu'on peut honnetement proposer : une page legale a
     * un vrai ecran d'edition dans les Reglages, on y envoie. Les autres pages
     * verrouillees (Contact, Inscription, Association) n'en ont pas, leur
     * contenu vient des blocs du plugin et des Reglages : on revient a la liste
     * avec une explication, plutot qu'un lien qui promettrait un ecran
     * d'edition inexistant.
     */
    public static function redirect_locked_page_editor() {
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        if ( ! $post_id || ! self::is_locked_page( $post_id ) ) {
            return;
        }
        if ( self::is_legal_page( $post_id ) ) {
            $url = add_query_arg(
                [ 'page' => OPAC_Settings::PAGE_SLUG, 'opac_page_lock' => 'legal' ],
                admin_url( 'admin.php' )
            );
        } else {
            $url = add_query_arg(
                [ 'post_type' => 'page', 'opac_page_lock' => 'composee' ],
                admin_url( 'edit.php' )
            );
        }
        wp_safe_redirect( $url );
        exit;
    }

    /** Explique la redirection une fois arrive a destination. */
    public static function locked_page_notice() {
        $type = isset( $_GET['opac_page_lock'] ) ? sanitize_key( wp_unslash( $_GET['opac_page_lock'] ) ) : '';
        if ( 'legal' === $type ) {
            $message = __( 'Le contenu des pages légales se modifie ici, dans la section « Pages légales » ci-dessous, et non dans l\'éditeur de pages.', 'opac-custom' );
        } elseif ( 'composee' === $type ) {
            $message = __( 'Cette page est composée par la mise en page du site : son contenu ne se modifie pas dans l\'éditeur de pages. Les informations qu\'elle affiche (coordonnées, équipe, partenaires, formulaires) se règlent dans OPAC > Réglages.', 'opac-custom' );
        } else {
            return;
        }
        printf(
            '<div class="notice notice-info is-dismissible"><p>%s</p></div>',
            esc_html( $message )
        );
    }

    /**
     * Verrouille edition et suppression des pages concernees au niveau des
     * capacites : c'est le point de passage commun a tous les chemins d'ecriture
     * de WordPress (editeur classique, « Apparence > Editeur » du theme FSE,
     * Modification rapide, REST API). Un verrou pose sur un seul ecran laisse
     * les autres ouverts.
     *
     * Sans consequence sur ce qui s'affiche : par definition, ces pages sont
     * celles dont le post_content n'est jamais rendu. Et sans consequence sur
     * leur creation : ensure_legal_pages() passe par wp_insert_post(), qui ne
     * verifie aucune capacite.
     */
    public static function protect_locked_pages_caps( $caps, $cap, $user_id, $args ) {
        if ( ! in_array( $cap, [ 'edit_post', 'delete_post' ], true ) || empty( $args[0] ) ) {
            return $caps;
        }
        if ( self::is_locked_page( (int) $args[0] ) ) {
            $caps[] = 'do_not_allow';
        }
        return $caps;
    }

    /**
     * Bloque corbeille et suppression definitive d'une page verrouillee au
     * niveau de l'API, la ou le refus de capacite ne porte pas : wp_trash_post()
     * et wp_delete_post() s'executent sans verifier les capacites de l'appelant.
     * Retourner false court-circuite l'operation, qui rend alors false comme si
     * elle avait echoue.
     *
     * Ces pages portent les templates page-<slug>.html, la navigation et les
     * liens du footer : en perdre une casse une section entiere du site, et pour
     * les legales une obligation legale.
     */
    public static function block_locked_page_removal( $check, $post ) {
        if ( $post && self::is_locked_page( is_object( $post ) ? $post->ID : (int) $post ) ) {
            return false;
        }
        return $check;
    }

    /**
     * Ceinture et bretelles sur le slug : meme si une ecriture contourne les
     * capacites (WP-CLI, script, autre extension), le permalien d'une page
     * verrouillee ne change pas.
     *
     * Ce n'est pas cosmetique : c'est le slug qui designe le template
     * page-<slug>.html. Renommer « contact » revient a lui retirer sa mise en
     * page, et la page se verrouille justement parce qu'elle en a une.
     *
     * Ne s'applique qu'aux mises a jour : a la creation, ensure_legal_pages()
     * pose justement le slug attendu.
     */
    public static function protect_locked_page_slug( $data, $postarr ) {
        if ( empty( $postarr['ID'] ) || 'page' !== $data['post_type'] ) {
            return $data;
        }
        $post_id = (int) $postarr['ID'];
        if ( ! self::is_locked_page( $post_id ) ) {
            return $data;
        }
        $existing = get_post( $post_id );
        if ( $existing && $existing->post_name !== $data['post_name'] ) {
            $data['post_name'] = $existing->post_name;
        }
        return $data;
    }

    /**
     * Actions de ligne des pages verrouillees dans la liste « Pages » : retire
     * la « Modification rapide » et la « Corbeille » (toutes deux deja bloquees
     * par map_meta_cap, on retire aussi les liens pour la clarte). WordPress
     * retire de lui-meme son propre lien « Modifier » puisque edit_post est
     * refuse : sans remplacement, la ligne n'offrirait plus aucune indication.
     *
     * Une page legale gagne un lien vers les Reglages, ou son texte se modifie
     * vraiment. Une page composee gagne une simple mention : il n'y a nulle part
     * ou l'envoyer, et un lien vers un ecran qui ne gere pas cette page serait
     * plus trompeur qu'utile.
     */
    public static function locked_page_row_actions( $actions, $post ) {
        if ( ! $post || ! self::is_locked_page( $post->ID ) ) {
            return $actions;
        }
        unset( $actions['inline hide-if-no-js'], $actions['trash'], $actions['delete'], $actions['edit'] );

        if ( self::is_legal_page( $post->ID ) ) {
            $first = sprintf(
                '<a href="%s">%s</a>',
                esc_url( add_query_arg(
                    [ 'page' => OPAC_Settings::PAGE_SLUG, 'opac_page_lock' => 'legal' ],
                    admin_url( 'admin.php' )
                ) ),
                esc_html__( 'Modifier le contenu (Réglages)', 'opac-custom' )
            );
        } else {
            $first = '<span class="opac-page-locked">' . esc_html__( 'Mise en page du site (non modifiable)', 'opac-custom' ) . '</span>';
        }

        return [ 'opac_page_lock' => $first ] + $actions;
    }

    public static function enqueue_admin_assets( $hook ) {
        wp_enqueue_style(
            'opac-admin',
            OPAC_CUSTOM_URL . 'assets/css/admin.css',
            [],
            OPAC_CUSTOM_VERSION
        );

        // Inscriptions : le titre [Atelier] Prenom Nom est toujours genere depuis
        // l'atelier + le nom/prenom. On masque le champ « Saisissez le titre »
        // (creation comme modification) : un titre manuel deviendrait faux des
        // qu'on change l'atelier ou le nom.
        if ( in_array( $hook, [ 'post-new.php', 'post.php' ], true ) ) {
            $screen = get_current_screen();
            if ( $screen && 'opac_inscription' === $screen->post_type ) {
                wp_add_inline_style( 'opac-admin', '#titlediv{display:none;}' );
            }
        }

        // Garde-fous suppression + "modifications non enregistrees" : listes et
        // editeurs des CPTs geres + inscriptions uniquement.
        if ( in_array( $hook, [ 'edit.php', 'post.php', 'post-new.php' ], true ) ) {
            $screen = get_current_screen();
            if ( $screen && in_array( $screen->post_type, self::CONFIRM_TYPES, true ) ) {
                wp_enqueue_script(
                    'opac-admin-confirm',
                    OPAC_CUSTOM_URL . 'assets/js/admin-confirm.js',
                    [],
                    OPAC_CUSTOM_VERSION,
                    true
                );
                wp_localize_script( 'opac-admin-confirm', 'opacConfirm', [
                    'trash'      => __( 'Êtes-vous sûr de vouloir mettre cet élément à la corbeille ?', 'opac-custom' ),
                    'del'        => __( 'Supprimer définitivement ? Cette action est irréversible.', 'opac-custom' ),
                    'emptyTrash' => __( 'Vider la corbeille supprimera définitivement tous les éléments. Continuer ?', 'opac-custom' ),
                    'bulkTrash'  => __( 'Mettre les éléments sélectionnés à la corbeille ?', 'opac-custom' ),
                    'bulkDelete' => __( 'Supprimer définitivement les éléments sélectionnés ? Cette action est irréversible.', 'opac-custom' ),
                    'unsaved'    => __( 'Des modifications ne sont pas enregistrées. Voulez-vous vraiment quitter cette page ?', 'opac-custom' ),
                ] );
            }
        }

        // Listes OPAC (ateliers, ephemeres, agenda, equipe, inscriptions) :
        // recherche au fil de la frappe (relance la recherche native apres une
        // courte pause, sans clic sur « Rechercher »). Le script est generique
        // (pilote la barre native #post-search-input + le form #posts-filter),
        // donc le meme JS sert toutes les listes ; on reutilise CONFIRM_TYPES.
        if ( 'edit.php' === $hook ) {
            $screen = get_current_screen();
            if ( $screen && in_array( $screen->post_type, self::CONFIRM_TYPES, true ) ) {
                wp_enqueue_script(
                    'opac-insc-livesearch',
                    OPAC_CUSTOM_URL . 'assets/js/admin-insc-livesearch.js',
                    [],
                    OPAC_CUSTOM_VERSION,
                    true
                );
            }
            // « Copier pour Excel » : liste des inscriptions uniquement.
            if ( $screen && 'opac_inscription' === $screen->post_type ) {
                wp_enqueue_script(
                    'opac-insc-copy-excel',
                    OPAC_CUSTOM_URL . 'assets/js/admin-insc-copy-excel.js',
                    [],
                    OPAC_CUSTOM_VERSION,
                    true
                );
                wp_localize_script( 'opac-insc-copy-excel', 'opacCopyExcel', [
                    'label'     => __( 'Copier pour Excel', 'opac-custom' ),
                    'empty'     => __( 'Rien à copier', 'opac-custom' ),
                    'copiedSel' => __( '%d ligne(s) copiée(s)', 'opac-custom' ),
                    'copiedAll' => __( '%d copiée(s), collez dans Excel', 'opac-custom' ),
                    'failed'    => __( 'Copie impossible', 'opac-custom' ),
                ] );
            }
        }
    }

    /**
     * Retire la "Modification rapide" (Quick Edit) des CPTs geres : elle n'expose
     * pas les champs metier (tarif, dates, animateur...) edites via le formulaire
     * fiche, donc elle induit en erreur.
     */
    public static function remove_quick_edit( $actions, $post ) {
        if ( $post && in_array( $post->post_type, self::CONFIRM_TYPES, true ) ) {
            unset( $actions['inline hide-if-no-js'] );
        }
        return $actions;
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
                $animator = OPAC_Bindings::resolve_animator( $post_id );
                echo $animator ? esc_html( $animator ) : '-';
                break;
            case 'opac_tarif_annuel':
                $tarif = (int) get_post_meta( $post_id, 'opac_tarif_annuel', true );
                echo $tarif > 0 ? esc_html( OPAC_Labels::tarif_annuel( $tarif ) ) : '-';
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
            // 'title' reste la colonne cliquable (lien d'edition + actions de ligne) ;
            // relabel « Inscription » car le nom/prenom ont leurs propres colonnes.
            'title' => __( 'Inscription', 'opac-custom' ),
            'opac_insc_nom' => __( 'Nom', 'opac-custom' ),
            'opac_insc_prenom' => __( 'Prénom', 'opac-custom' ),
            'opac_insc_email' => __( 'Email', 'opac-custom' ),
            'opac_insc_atelier' => __( 'Atelier', 'opac-custom' ),
            'opac_insc_creneau' => __( 'Créneau', 'opac-custom' ),
            'opac_insc_priorite' => __( 'Priorité', 'opac-custom' ),
            'taxonomy-opac_inscription_status' => __( 'Statut', 'opac-custom' ),
            'date' => $columns['date'] ?? __( 'Date', 'opac-custom' ),
        ];
    }

    public static function inscription_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'opac_insc_nom':
                $nom = get_post_meta( $post_id, 'opac_insc_nom', true );
                echo $nom ? esc_html( $nom ) : '-';
                // Porteur cache de la ligne "prete a coller" dans le fichier Excel
                // de suivi : lu par admin-insc-copy-excel.js au clic sur le bouton
                // « Copier pour Excel ». Une seule sortie par ligne (cellule Nom
                // toujours rendue). La tabulation reste litterale dans l'attribut.
                if ( class_exists( 'OPAC_Inscriptions' ) ) {
                    printf(
                        '<span class="opac-insc-tsv" data-tsv="%s" hidden></span>',
                        esc_attr( OPAC_Inscriptions::excel_tsv_line( $post_id ) )
                    );
                }
                break;
            case 'opac_insc_prenom':
                $prenom = get_post_meta( $post_id, 'opac_insc_prenom', true );
                echo $prenom ? esc_html( $prenom ) : '-';
                break;
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
            case 'opac_insc_creneau':
                $creneau = get_post_meta( $post_id, 'opac_insc_creneau', true );
                echo $creneau ? esc_html( $creneau ) : '-';
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
        if ( ! current_user_can( 'edit_others_posts' ) ) {
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

        // Avertissement si cette validation a fait depasser la capacite du creneau
        // (flag opac_insc_over pose par OPAC_Inscriptions::handle_action). Ne
        // bloque rien : l'admin reste maitre, mais le depassement est visible.
        if ( 'validee' === $status && isset( $_GET['opac_insc_over'] ) && '1' === $_GET['opac_insc_over'] ) {
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                esc_html__( 'Attention : ce créneau dépasse désormais sa capacité. Vérifiez qu\'il reste de la place ou basculez un inscrit en liste d\'attente.', 'opac-custom' )
            );
        }
    }

    /**
     * Fiche d'une inscription : remplace l'affichage des « champs personnalises »
     * bruts par un formulaire etiquete. Les coordonnees (nom, prenom, email,
     * telephone, code postal, commune, message) sont editables pour corriger une
     * demande (changement d'adresse, de numero...). Atelier, creneau, adhesion,
     * statut et date restent en lecture seule (geres par le workflow / la demande).
     */
    public static function inscription_details_meta_box() {
        add_meta_box(
            'opac_inscription_details',
            __( 'Détails de la demande', 'opac-custom' ),
            [ __CLASS__, 'render_inscription_details_box' ],
            'opac_inscription',
            'normal',
            'high'
        );
    }

    public static function render_inscription_details_box( $post ) {
        $id = $post->ID;
        wp_nonce_field( 'opac_inscription_details', 'opac_inscription_details_nonce' );

        $nom         = (string) get_post_meta( $id, 'opac_insc_nom', true );
        $prenom      = (string) get_post_meta( $id, 'opac_insc_prenom', true );
        $email       = (string) get_post_meta( $id, 'opac_insc_email', true );
        $tel         = (string) get_post_meta( $id, 'opac_insc_telephone', true );
        $code_postal = (string) get_post_meta( $id, 'opac_insc_code_postal', true );
        $commune     = (string) get_post_meta( $id, 'opac_insc_commune', true );
        $atelier_id  = (int) get_post_meta( $id, 'opac_insc_atelier_id', true );
        $creneau     = (string) get_post_meta( $id, 'opac_insc_creneau', true );
        $creneau_id  = (string) get_post_meta( $id, 'opac_insc_creneau_id', true );
        $adhesion    = (string) get_post_meta( $id, 'opac_insc_adhesion', true );
        $message     = (string) get_post_meta( $id, 'opac_insc_message', true );
        $date        = (string) get_post_meta( $id, 'opac_insc_date_submitted', true );

        $adh_labels = OPAC_Labels::adhesions();

        echo '<table class="form-table" role="presentation"><tbody>';

        // Coordonnees editables.
        self::insc_edit_row( 'opac_insc_nom', __( 'Nom', 'opac-custom' ), $nom );
        self::insc_edit_row( 'opac_insc_prenom', __( 'Prénom', 'opac-custom' ), $prenom );
        self::insc_edit_row( 'opac_insc_email', __( 'Email', 'opac-custom' ), $email, 'email' );
        self::insc_edit_row( 'opac_insc_telephone', __( 'Téléphone', 'opac-custom' ), $tel, 'tel' );
        self::insc_edit_row( 'opac_insc_code_postal', __( 'Code postal', 'opac-custom' ), $code_postal );
        self::insc_edit_row( 'opac_insc_commune', __( 'Commune', 'opac-custom' ), $commune );

        // Atelier / ephemere : selecteur (modifiable, requis pour une saisie manuelle).
        echo '<tr><th scope="row"><label for="opac_insc_atelier_id">' . esc_html__( 'Atelier / éphémère', 'opac-custom' ) . '</label></th><td>';
        self::render_insc_cible_select( $atelier_id );
        echo '</td></tr>';

        // Creneau : selecteur dependant de l'atelier choisi (rempli par le JS
        // ci-dessous depuis les creneaux structures de l'atelier, ou parses depuis
        // l'ancien champ texte). data-current = id structure ou libelle deja stocke.
        $current_creneau_value = ( '' !== $creneau_id ) ? $creneau_id : $creneau;
        echo '<tr><th scope="row"><label for="opac_insc_creneau_choice">' . esc_html__( 'Créneau', 'opac-custom' ) . '</label></th><td>';
        printf(
            '<select id="opac_insc_creneau_choice" name="opac_insc_creneau_choice" data-current="%s"></select>',
            esc_attr( $current_creneau_value )
        );
        echo '<p class="description">' . esc_html__( 'Créneaux de l\'atelier sélectionné. Vide si l\'atelier n\'a pas encore de créneaux (à définir sur la fiche de l\'atelier).', 'opac-custom' ) . '</p>';

        // Carte { cible_id => [ {v:id|libelle, t:libelle affiché}, ... ] }.
        // Ateliers a l'annee (creneaux) ET ephemeres (seances datees) : une
        // inscription saisie a la main sur l'un ou l'autre propose ses options.
        $creneaux_map = [];
        foreach ( get_posts( [ 'post_type' => 'opac_atelier', 'post_status' => 'publish', 'posts_per_page' => -1 ] ) as $a ) {
            $opts = self::atelier_creneau_options( $a->ID );
            if ( ! empty( $opts ) ) {
                $creneaux_map[ (string) $a->ID ] = $opts;
            }
        }
        foreach ( get_posts( [ 'post_type' => 'opac_stage', 'post_status' => 'publish', 'posts_per_page' => -1 ] ) as $s ) {
            $opts = self::stage_seance_options( $s->ID );
            if ( ! empty( $opts ) ) {
                $creneaux_map[ (string) $s->ID ] = $opts;
            }
        }
        ?>
        <script>
        (function(){
            var map = <?php echo wp_json_encode( $creneaux_map, JSON_HEX_TAG ); ?> || {};
            var aSel = document.getElementById('opac_insc_atelier_id');
            var cSel = document.getElementById('opac_insc_creneau_choice');
            if(!aSel||!cSel){return;}
            function fill(){
                var aid=aSel.value, cur=cSel.getAttribute('data-current')||'', list=map[aid]||[];
                cSel.innerHTML='';
                var o0=document.createElement('option');
                o0.value='';
                o0.textContent=list.length?'<?php echo esc_js( __( '— Choisir un créneau —', 'opac-custom' ) ); ?>':'<?php echo esc_js( __( '— Aucun créneau —', 'opac-custom' ) ); ?>';
                cSel.appendChild(o0);
                list.forEach(function(c){
                    var o=document.createElement('option');
                    o.value=c.v; o.textContent=c.t;
                    if(c.v===cur){o.selected=true;}
                    cSel.appendChild(o);
                });
            }
            fill();
            aSel.addEventListener('change',function(){cSel.setAttribute('data-current','');fill();});
        })();
        </script>
        <?php
        echo '</td></tr>';

        // Adhesion : select (vide = déduit du code postal à l'enregistrement).
        echo '<tr><th scope="row"><label for="opac_insc_adhesion">' . esc_html__( 'Adhésion', 'opac-custom' ) . '</label></th><td>';
        $adh_options = [ '' => __( '— Selon code postal —', 'opac-custom' ) ] + $adh_labels;
        echo '<select id="opac_insc_adhesion" name="opac_insc_adhesion">';
        foreach ( $adh_options as $val => $lab ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $adhesion, $val, false ), esc_html( $lab ) );
        }
        echo '</select></td></tr>';

        // Statut : select des termes (modifiable sans envoi d'email ; les emails
        // restent declenches par les actions Valider/Refuser de la liste).
        echo '<tr><th scope="row"><label for="opac_insc_status_term">' . esc_html__( 'Statut', 'opac-custom' ) . '</label></th><td>';
        self::render_insc_status_select( $id );
        echo '</td></tr>';

        // Date : lecture seule (definie automatiquement a l'enregistrement si vide).
        // Stockee en heure locale du site (current_time('mysql')) : on la parse
        // dans le fuseau du site. strtotime() l'interpreterait en UTC (PHP tourne
        // en UTC sous WP) et wp_date() reconvertirait vers Paris : +1h/+2h a tort.
        $date_html = '';
        if ( '' !== $date ) {
            $dt = date_create_immutable( $date, wp_timezone() );
            $date_html = $dt ? esc_html( wp_date( 'j F Y à H:i', $dt->getTimestamp() ) ) : esc_html( $date );
        } else {
            $date_html = '<em>' . esc_html__( 'Définie à l\'enregistrement', 'opac-custom' ) . '</em>';
        }
        self::insc_detail_row( __( 'Date de la demande', 'opac-custom' ), $date_html );

        // Message editable.
        printf(
            '<tr><th scope="row"><label for="opac_insc_message">%s</label></th><td><textarea id="opac_insc_message" name="opac_insc_message" rows="4" class="large-text">%s</textarea></td></tr>',
            esc_html__( 'Message', 'opac-custom' ),
            esc_textarea( $message )
        );

        echo '</tbody></table>';
        echo '<p class="description">' . esc_html__( 'Saisie ou correction d\'une inscription : renseignez l\'atelier, le nom/prénom et les coordonnées, puis cliquez sur « Publier » (ou « Mettre à jour »). Le titre est généré automatiquement depuis l\'atelier et le nom ; la date et la priorité Plérinais sont calculées automatiquement.', 'opac-custom' ) . '</p>';
    }

    /** Ligne editable de la fiche inscription : libelle + champ input. */
    private static function insc_edit_row( $key, $label, $value, $type = 'text' ) {
        printf(
            '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s" id="%1$s" name="%1$s" value="%4$s" class="regular-text" /></td></tr>',
            esc_attr( $key ),
            esc_html( $label ),
            esc_attr( $type ),
            esc_attr( $value )
        );
    }

    /** Selecteur de la cible (atelier annuel ou ephemere) d'une inscription. */
    private static function render_insc_cible_select( $current_id ) {
        $args = [ 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ];
        $ateliers = get_posts( array_merge( $args, [ 'post_type' => 'opac_atelier' ] ) );
        $stages   = get_posts( array_merge( $args, [ 'post_type' => 'opac_stage' ] ) );

        echo '<select id="opac_insc_atelier_id" name="opac_insc_atelier_id">';
        echo '<option value="0">' . esc_html__( '— Choisir —', 'opac-custom' ) . '</option>';
        $groups = [
            __( 'Ateliers à l\'année', 'opac-custom' ) => $ateliers,
            __( 'Ateliers éphémères', 'opac-custom' )  => $stages,
        ];
        foreach ( $groups as $label => $posts ) {
            if ( empty( $posts ) ) {
                continue;
            }
            printf( '<optgroup label="%s">', esc_attr( $label ) );
            foreach ( $posts as $p ) {
                printf(
                    '<option value="%d"%s>%s</option>',
                    (int) $p->ID,
                    selected( $current_id, $p->ID, false ),
                    esc_html( get_the_title( $p ) )
                );
            }
            echo '</optgroup>';
        }
        echo '</select>';
    }

    /** Selecteur du statut d'une inscription (defaut « validée » pour une saisie). */
    private static function render_insc_status_select( $post_id ) {
        $terms   = get_terms( [ 'taxonomy' => 'opac_inscription_status', 'hide_empty' => false ] );
        $current = wp_get_object_terms( $post_id, 'opac_inscription_status', [ 'fields' => 'slugs' ] );
        $current_slug = ( ! is_wp_error( $current ) && ! empty( $current ) ) ? $current[0] : 'validee';

        echo '<select id="opac_insc_status_term" name="opac_insc_status_term">';
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr( $t->slug ),
                    selected( $current_slug, $t->slug, false ),
                    esc_html( $t->name )
                );
            }
        }
        echo '</select>';
    }

    /**
     * Options de creneaux d'un atelier pour le select de la fiche inscription :
     * creneaux structures (value = id, comptage des places possible), ou a defaut
     * creneaux parses de l'ancien champ texte (value = libelle).
     *
     * @return array<int,array{v:string,t:string}>
     */
    private static function atelier_creneau_options( $atelier_id ) {
        $struct = get_post_meta( $atelier_id, 'opac_creneaux', true );
        if ( ! is_array( $struct ) || empty( $struct ) ) {
            $struct = self::parse_creneaux_text( (string) get_post_meta( $atelier_id, 'opac_creneaux_text', true ) );
        }
        $opts = [];
        foreach ( $struct as $c ) {
            if ( ! is_array( $c ) ) {
                continue;
            }
            $label = OPAC_Calendar::creneau_label( $c );
            if ( '' === $label ) {
                continue;
            }
            $tarif = isset( $c['tarif'] ) ? (int) $c['tarif'] : 0;
            $text  = $tarif > 0 ? $label . ' (' . OPAC_Labels::euros( $tarif ) . ')' : $label;
            $cid   = isset( $c['id'] ) ? (string) $c['id'] : '';
            $opts[] = [ 'v' => ( '' !== $cid ) ? $cid : $label, 't' => $text ];
        }
        return $opts;
    }

    /**
     * Options de seances d'un ephemere pour le select de la fiche inscription
     * (saisie manuelle admin). Value = id de seance (comptage des places),
     * libelle = date + horaires + tarif eventuel. Vide si aucune seance datee.
     *
     * @return array<int,array{v:string,t:string}>
     */
    private static function stage_seance_options( $stage_id ) {
        $struct = get_post_meta( $stage_id, 'opac_stage_seances', true );
        if ( ! is_array( $struct ) || empty( $struct ) ) {
            return [];
        }
        $opts = [];
        foreach ( $struct as $s ) {
            if ( ! is_array( $s ) || empty( $s['id'] ) ) {
                continue;
            }
            $label = OPAC_Calendar::seance_label( $s );
            if ( '' === $label ) {
                continue;
            }
            $tarif = isset( $s['tarif'] ) ? (int) $s['tarif'] : 0;
            $text  = $tarif > 0 ? $label . ' (' . OPAC_Labels::euros( $tarif ) . ')' : $label;
            $opts[] = [ 'v' => (string) $s['id'], 't' => $text ];
        }
        return $opts;
    }

    /** Ligne en lecture seule de la fiche inscription : libelle + valeur (HTML deja echappe). */
    private static function insc_detail_row( $label, $value_html ) {
        if ( '' === $value_html ) {
            $value_html = '<em>' . esc_html__( '—', 'opac-custom' ) . '</em>';
        }
        printf(
            '<tr><th scope="row" style="width:200px">%s</th><td>%s</td></tr>',
            esc_html( $label ),
            $value_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- echappe par l'appelant
        );
    }

    /**
     * Sauvegarde des coordonnees editees sur la fiche inscription. Recalcule le
     * flag Plerinais (tri prioritaire) depuis le code postal. N'interfere pas
     * avec la creation via le formulaire public (nonce absent a ce moment-la).
     */
    public static function save_inscription_details( $post_id, $post ) {
        if ( ! isset( $_POST['opac_inscription_details_nonce'] )
            || ! wp_verify_nonce( wp_unslash( $_POST['opac_inscription_details_nonce'] ), 'opac_inscription_details' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $text_fields = [ 'opac_insc_nom', 'opac_insc_prenom', 'opac_insc_telephone', 'opac_insc_code_postal', 'opac_insc_commune' ];
        foreach ( $text_fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
            }
        }
        if ( isset( $_POST['opac_insc_email'] ) ) {
            update_post_meta( $post_id, 'opac_insc_email', sanitize_email( wp_unslash( $_POST['opac_insc_email'] ) ) );
        }
        if ( isset( $_POST['opac_insc_message'] ) ) {
            update_post_meta( $post_id, 'opac_insc_message', sanitize_textarea_field( wp_unslash( $_POST['opac_insc_message'] ) ) );
        }

        $atelier_id = isset( $_POST['opac_insc_atelier_id'] ) ? absint( $_POST['opac_insc_atelier_id'] ) : 0;
        update_post_meta( $post_id, 'opac_insc_atelier_id', $atelier_id );

        // Creneau : resolu depuis le select dependant. Si l'option correspond a un
        // creneau structure (match par id), on enregistre id + tarif (comptage des
        // places). Sinon le libelle est conserve en texte (creneau_id efface).
        $choice        = isset( $_POST['opac_insc_creneau_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['opac_insc_creneau_choice'] ) ) : '';
        $creneau_label = '';
        $creneau_id    = '';
        $creneau_tarif = 0;
        if ( $atelier_id && '' !== $choice ) {
            // Check explicite par type (symetrique du front handle_submit) :
            // atelier a l'annee = creneaux hebdomadaires (ou ancien texte parse) ;
            // ephemere = seances datees. Tout autre type : aucune resolution.
            $cible_type = get_post_type( $atelier_id );
            $struct     = [];
            $labeller   = null;
            if ( 'opac_atelier' === $cible_type ) {
                $struct = get_post_meta( $atelier_id, 'opac_creneaux', true );
                if ( ! is_array( $struct ) || empty( $struct ) ) {
                    $struct = self::parse_creneaux_text( (string) get_post_meta( $atelier_id, 'opac_creneaux_text', true ) );
                }
                $labeller = [ 'OPAC_Calendar', 'creneau_label' ];
            } elseif ( 'opac_stage' === $cible_type ) {
                $struct   = get_post_meta( $atelier_id, 'opac_stage_seances', true );
                $labeller = [ 'OPAC_Calendar', 'seance_label' ];
            }
            if ( ! is_array( $struct ) ) {
                $struct = [];
            }
            foreach ( ( $labeller ? $struct : [] ) as $c ) {
                if ( ! is_array( $c ) ) {
                    continue;
                }
                $cid   = isset( $c['id'] ) ? (string) $c['id'] : '';
                $label = call_user_func( $labeller, $c );
                if ( ( '' !== $cid && $cid === $choice ) || ( '' === $cid && $label === $choice ) ) {
                    $creneau_label = $label;
                    if ( '' !== $cid ) {
                        $creneau_id    = $cid;
                        $creneau_tarif = isset( $c['tarif'] ) ? (int) $c['tarif'] : 0;
                    }
                    break;
                }
            }
            if ( '' === $creneau_label ) {
                $creneau_label = $choice; // valeur non reconnue : conservee telle quelle
            }
        }
        update_post_meta( $post_id, 'opac_insc_creneau', $creneau_label );
        if ( '' !== $creneau_id ) {
            update_post_meta( $post_id, 'opac_insc_creneau_id', $creneau_id );
        } else {
            delete_post_meta( $post_id, 'opac_insc_creneau_id' );
        }
        if ( $creneau_tarif > 0 ) {
            update_post_meta( $post_id, 'opac_insc_tarif', $creneau_tarif );
        } else {
            delete_post_meta( $post_id, 'opac_insc_tarif' );
        }

        // Flag Plerinais recalcule depuis le code postal corrige (cf. handle_submit).
        $cp = isset( $_POST['opac_insc_code_postal'] ) ? sanitize_text_field( wp_unslash( $_POST['opac_insc_code_postal'] ) ) : '';
        update_post_meta( $post_id, 'opac_insc_plerinais', OPAC_Settings::PLERIN_POSTAL === $cp ? 1 : 0 );

        // Adhesion : valeur choisie si valide, sinon deduite du code postal.
        $allowed_adh = OPAC_Labels::adhesion_keys();
        $adhesion = isset( $_POST['opac_insc_adhesion'] ) ? sanitize_key( wp_unslash( $_POST['opac_insc_adhesion'] ) ) : '';
        if ( ! in_array( $adhesion, $allowed_adh, true ) ) {
            $adhesion = ( OPAC_Settings::PLERIN_POSTAL === $cp ) ? 'plerinais' : 'exterieur';
        }
        update_post_meta( $post_id, 'opac_insc_adhesion', $adhesion );

        // Statut : applique le terme choisi (sans email ; emails via actions de liste).
        $valid_status = [ 'en-attente', 'validee', 'refusee', 'liste-attente' ];
        $status = isset( $_POST['opac_insc_status_term'] ) ? sanitize_key( wp_unslash( $_POST['opac_insc_status_term'] ) ) : '';
        if ( in_array( $status, $valid_status, true ) ) {
            wp_set_object_terms( $post_id, [ $status ], 'opac_inscription_status', false );
        }

        // Date de la demande + source : renseignees une fois (saisie manuelle).
        if ( '' === (string) get_post_meta( $post_id, 'opac_insc_date_submitted', true ) ) {
            update_post_meta( $post_id, 'opac_insc_date_submitted', current_time( 'mysql' ) );
        }
        if ( '' === (string) get_post_meta( $post_id, 'opac_insc_source', true ) ) {
            update_post_meta( $post_id, 'opac_insc_source', 'saisie-admin' );
        }
    }

    /**
     * Compose le titre [Atelier] Prenom Nom d'une inscription au moment de
     * l'insert (filtre wp_insert_post_data, pas de recursion), pour la saisie
     * manuelle comme pour la correction. Meme format que le formulaire public.
     */
    public static function inject_inscription_title( $data, $postarr ) {
        if ( ! isset( $data['post_type'] ) || 'opac_inscription' !== $data['post_type'] ) {
            return $data;
        }
        if ( ! isset( $_POST['opac_inscription_details_nonce'] )
            || ! wp_verify_nonce( wp_unslash( $_POST['opac_inscription_details_nonce'] ), 'opac_inscription_details' ) ) {
            return $data;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $data;
        }
        $post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
        if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
            return $data;
        }

        $prenom = isset( $_POST['opac_insc_prenom'] ) ? sanitize_text_field( wp_unslash( $_POST['opac_insc_prenom'] ) ) : '';
        $nom    = isset( $_POST['opac_insc_nom'] ) ? sanitize_text_field( wp_unslash( $_POST['opac_insc_nom'] ) ) : '';
        $name   = trim( $prenom . ' ' . $nom );
        if ( '' === $name ) {
            return $data; // rien a composer (laisse le titre saisi tel quel)
        }
        $aid    = isset( $_POST['opac_insc_atelier_id'] ) ? absint( $_POST['opac_insc_atelier_id'] ) : 0;
        $atitre = ( $aid && get_post( $aid ) ) ? get_the_title( $aid ) : '';
        $title  = $atitre ? sprintf( '[%s] %s', $atitre, $name ) : $name;
        $data['post_title'] = wp_slash( $title );
        return $data;
    }

    public static function register_dashboard_widget() {
        // Le widget liste des noms d'inscrits (PII) : editeurs/admins uniquement.
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            return;
        }
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
     * Meta box sur opac_gallery_item : choix de l'atelier ou de l'evenement
     * associe + legende. Katell definit la photo via "Image mise en avant" et
     * selectionne ici l'atelier ou l'evenement ou la photo s'affiche.
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
        $current   = (int) get_post_meta( $post->ID, 'opac_gallery_atelier_id', true );
        $caption   = (string) get_post_meta( $post->ID, 'opac_gallery_caption', true );
        $list_args = [
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $ateliers = get_posts( array_merge( $list_args, [ 'post_type' => 'opac_atelier' ] ) );
        $events   = get_posts( array_merge( $list_args, [ 'post_type' => 'opac_event' ] ) );

        echo '<p><label for="opac_gallery_atelier_id"><strong>' . esc_html__( 'Atelier ou événement associé', 'opac-custom' ) . '</strong></label></p>';
        echo '<select id="opac_gallery_atelier_id" name="opac_gallery_atelier_id" style="width:100%">';
        echo '<option value="0">' . esc_html__( '— Aucun —', 'opac-custom' ) . '</option>';

        // Le meta opac_gallery_atelier_id stocke un ID de post (atelier OU
        // evenement) : un ID est unique tous types confondus, donc render_gallery_grid
        // (keye sur l'ID du post courant) affiche les bonnes photos sans distinction.
        $groups = [
            __( 'Ateliers', 'opac-custom' )                 => $ateliers,
            __( 'Expositions / Événements', 'opac-custom' ) => $events,
        ];
        foreach ( $groups as $group_label => $group_posts ) {
            if ( empty( $group_posts ) ) {
                continue;
            }
            printf( '<optgroup label="%s">', esc_attr( $group_label ) );
            foreach ( $group_posts as $p ) {
                printf(
                    '<option value="%d"%s>%s</option>',
                    (int) $p->ID,
                    selected( $current, $p->ID, false ),
                    esc_html( get_the_title( $p ) )
                );
            }
            echo '</optgroup>';
        }
        echo '</select>';

        echo '<p style="margin-top:12px"><label for="opac_gallery_caption"><strong>' . esc_html__( 'Légende (optionnelle)', 'opac-custom' ) . '</strong></label></p>';
        printf(
            '<input type="text" id="opac_gallery_caption" name="opac_gallery_caption" value="%s" style="width:100%%" />',
            esc_attr( $caption )
        );
        echo '<p class="description" style="margin-top:8px">' . esc_html__( 'Définissez la photo via « Image mise en avant », puis choisissez l\'atelier ou l\'événement où elle apparaît.', 'opac-custom' ) . '</p>';
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
                $new['opac_gallery_atelier'] = __( 'Associé à', 'opac-custom' );
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

        $is_plerinais = ( '1' === (string) $plerinais || OPAC_Settings::PLERIN_POSTAL === $code_postal );

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
            $base  = OPAC_Labels::adhesions();
            $label = isset( $base[ $adhesion ] )
                ? sprintf( __( 'Adhésion %s', 'opac-custom' ), $base[ $adhesion ] )
                : $adhesion;
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
        // wp_date() sans timestamp = maintenant, deja formate dans le fuseau du
        // site. Lui passer current_time('timestamp') appliquerait l'offset deux
        // fois (decalage possible a quelques heures de la bascule de saison).
        $year  = (int) wp_date( 'Y' );
        $month = (int) wp_date( 'n' );
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

        // Filtre par atelier / ephemere : cible tous les inscrits d'un atelier
        // (ex : ceramique) avant export ou envoi d'email. opac_insc_atelier_id
        // pointe vers un atelier OU un stage.
        $cur_atelier = isset( $_GET['opac_insc_atelier_id'] ) ? absint( $_GET['opac_insc_atelier_id'] ) : 0;
        $list_args   = [ 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ];
        $ateliers    = get_posts( array_merge( $list_args, [ 'post_type' => 'opac_atelier' ] ) );
        $stages      = get_posts( array_merge( $list_args, [ 'post_type' => 'opac_stage' ] ) );
        echo '<select name="opac_insc_atelier_id">';
        echo '<option value="">' . esc_html__( 'Tous les ateliers', 'opac-custom' ) . '</option>';
        if ( $ateliers ) {
            echo '<optgroup label="' . esc_attr__( 'Ateliers', 'opac-custom' ) . '">';
            foreach ( $ateliers as $a ) {
                printf(
                    '<option value="%d"%s>%s</option>',
                    (int) $a->ID,
                    selected( $cur_atelier, $a->ID, false ),
                    esc_html( get_the_title( $a ) )
                );
            }
            echo '</optgroup>';
        }
        if ( $stages ) {
            echo '<optgroup label="' . esc_attr__( 'Éphémères', 'opac-custom' ) . '">';
            foreach ( $stages as $s ) {
                printf(
                    '<option value="%d"%s>%s</option>',
                    (int) $s->ID,
                    selected( $cur_atelier, $s->ID, false ),
                    esc_html( get_the_title( $s ) )
                );
            }
            echo '</optgroup>';
        }
        echo '</select>';

        // Filtres courants partages par l'export CSV et l'envoi d'email (statut,
        // atelier, recherche par nom, mois) : l'action porte sur tout ce qui matche.
        $cur_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $cur_month  = isset( $_GET['m'] ) ? absint( $_GET['m'] ) : 0;
        $filters    = [];
        if ( '' !== $current ) {
            $filters['opac_inscription_status'] = $current;
        }
        if ( $cur_atelier ) {
            $filters['opac_insc_atelier_id'] = $cur_atelier;
        }
        if ( '' !== $cur_search ) {
            $filters['s'] = $cur_search;
        }
        if ( $cur_month > 0 ) {
            $filters['m'] = $cur_month;
        }

        // Bouton export CSV (admin-post).
        $export_args = array_merge(
            [ 'action' => 'opac_insc_export', '_wpnonce' => wp_create_nonce( 'opac_insc_export' ) ],
            $filters
        );
        printf(
            '<a class="button" href="%s">%s</a> ',
            esc_url( add_query_arg( $export_args, admin_url( 'admin-post.php' ) ) ),
            esc_html__( 'Exporter en CSV', 'opac-custom' )
        );

        // Bouton « Copier pour Excel » : cote client (admin-insc-copy-excel.js), copie
        // les inscriptions cochees (ou toute la page si rien n'est coche) dans le
        // presse-papier, en lignes tabulees pretes a coller dans le fichier de suivi
        // de la secretaire. type="button" pour ne pas soumettre le formulaire de filtre.
        printf(
            '<button type="button" class="button" id="opac-copy-excel">%s</button> ',
            esc_html__( 'Copier pour Excel', 'opac-custom' )
        );

        // Bouton « Envoyer un email » : ouvre l'ecran de redaction cache avec les
        // memes filtres + un nonce de vue (cf. OPAC_Inscriptions::render_email_page).
        $email_args = array_merge(
            [ 'post_type' => 'opac_inscription', 'page' => 'opac-insc-email', '_wpnonce' => wp_create_nonce( 'opac_insc_email_view' ) ],
            $filters
        );
        printf(
            '<a class="button button-primary" href="%s">%s</a>',
            esc_url( add_query_arg( $email_args, admin_url( 'edit.php' ) ) ),
            esc_html__( 'Envoyer un email', 'opac-custom' )
        );
    }

    /**
     * Filtre la liste admin des inscriptions par atelier/ephemere choisi dans le
     * dropdown (meta opac_insc_atelier_id), pour que la liste affichee corresponde
     * exactement aux destinataires d'un futur export / email.
     */
    public static function filter_inscriptions_by_atelier( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }
        if ( 'opac_inscription' !== $query->get( 'post_type' ) ) {
            return;
        }
        $atelier_id = isset( $_GET['opac_insc_atelier_id'] ) ? absint( $_GET['opac_insc_atelier_id'] ) : 0;
        if ( ! $atelier_id ) {
            return;
        }
        $meta_query   = (array) $query->get( 'meta_query' );
        $meta_query[] = [ 'key' => 'opac_insc_atelier_id', 'value' => $atelier_id ];
        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Notice apres un envoi d'email groupe : ?opac_insc_email_sent = nombre de
     * destinataires (succes) ou 'error' (rien envoye).
     */
    public static function inscription_email_notice() {
        if ( ! isset( $_GET['opac_insc_email_sent'] ) || ! isset( $_GET['post_type'] ) || 'opac_inscription' !== $_GET['post_type'] ) {
            return;
        }
        $val = sanitize_text_field( wp_unslash( $_GET['opac_insc_email_sent'] ) );
        if ( 'error' === $val ) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                esc_html__( 'Email non envoyé : sujet ou message vide, aucun destinataire valide, ou erreur d\'envoi.', 'opac-custom' )
            );
            return;
        }
        $count = absint( $val );
        if ( $count < 1 ) {
            return;
        }
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html( sprintf( _n( 'Email envoyé à %d personne.', 'Email envoyé à %d personnes.', $count, 'opac-custom' ), $count ) )
        );
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

        // Pre-remplissage non destructif : si aucun creneau structure mais que
        // l'ancien champ texte libre « opac_creneaux_text » contient des horaires
        // (ex : ateliers crees avant le passage au format structure), on parse ce
        // texte pour afficher les lignes existantes. Rien n'est enregistre tant que
        // l'utilisateur ne clique pas « Enregistrer » : le texte reste le fallback.
        $prefilled = false;
        if ( empty( $creneaux ) ) {
            $text = (string) get_post_meta( $post->ID, 'opac_creneaux_text', true );
            $parsed = self::parse_creneaux_text( $text );
            if ( ! empty( $parsed ) ) {
                $creneaux  = $parsed;
                $prefilled = true;
            }
        }
        ?>
        <p class="description">
            <?php esc_html_e( 'Un créneau par ligne (jour + horaires). Tarif et capacité servent au formulaire d\'inscription et à l\'affichage des places (capacité 0 = pas de limite). Si vide, l\'ancien champ texte « Créneaux » reste utilisé.', 'opac-custom' ); ?>
        </p>
        <?php if ( $prefilled ) : ?>
        <p class="description" style="color:#996800">
            <?php esc_html_e( 'Horaires repris automatiquement de l\'ancien champ texte. Vérifiez les lignes ci-dessous puis cliquez sur « Mettre à jour » pour les convertir au nouveau format.', 'opac-custom' ); ?>
        </p>
        <?php endif; ?>
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
                if(tmp.firstElementChild){rows.appendChild(tmp.firstElementChild);rows.dispatchEvent(new Event('change',{bubbles:true}));}
            });
            rows.addEventListener('click',function(e){
                var b=e.target.closest('.opac-creneau-del');
                if(b){e.preventDefault();var tr=b.closest('tr');if(tr){tr.parentNode.removeChild(tr);rows.dispatchEvent(new Event('change',{bubbles:true}));}}
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
        $jours = OPAC_Calendar::jours();
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
     * Parse l'ancien champ texte libre des creneaux (opac_creneaux_text) en
     * lignes structurees, pour pre-remplir l'editeur. Une ligne par creneau, ex :
     *   "Mercredi 14h - 16h (enfants)"  ->  jour=mercredi, debut=14:00, fin=16:00, note=enfants
     *   "Jeudi 14h30 - 16h30 (1 séance sur 2)" -> jour=jeudi, debut=14:30, fin=16:30, note=...
     * tarif/capacite a 0, id laisse vide (assigne par save_atelier_creneaux).
     *
     * @return array<int,array<string,mixed>> Lignes parsees (vide si rien d'exploitable).
     */
    public static function parse_creneaux_text( $text ) {
        $text = trim( (string) $text );
        if ( '' === $text ) {
            return [];
        }
        $rows = [];
        foreach ( preg_split( '/\r?\n/', $text ) as $line ) {
            $line = trim( $line );
            if ( '' === $line ) {
                continue;
            }

            // Jour : premier libelle reconnu en debut de ligne (insensible a la casse).
            $jour = 'lundi';
            foreach ( OPAC_Calendar::jours_keys() as $slug ) {
                if ( 0 === stripos( $line, $slug ) ) {
                    $jour = $slug;
                    break;
                }
            }

            // Horaires : deux premiers "14h" / "14h30" -> "HH:MM".
            $debut = '';
            $fin   = '';
            if ( preg_match_all( '/(\d{1,2})\s*h\s*(\d{2})?/i', $line, $m, PREG_SET_ORDER ) ) {
                $fmt = static function ( $set ) {
                    $h = (int) $set[1];
                    $mm = isset( $set[2] ) && '' !== $set[2] ? (int) $set[2] : 0;
                    return sprintf( '%02d:%02d', $h, $mm );
                };
                if ( isset( $m[0] ) ) {
                    $debut = $fmt( $m[0] );
                }
                if ( isset( $m[1] ) ) {
                    $fin = $fmt( $m[1] );
                }
            }

            // Ligne sans aucun horaire reconnu : on l'ignore (rien d'exploitable).
            if ( '' === $debut && '' === $fin ) {
                continue;
            }

            // Note : contenu entre parentheses, si present.
            $note = '';
            if ( preg_match( '/\(([^)]*)\)/', $line, $pm ) ) {
                $note = trim( $pm[1] );
            }

            $rows[] = [
                'id'       => '',
                'jour'     => $jour,
                'debut'    => $debut,
                'fin'      => $fin,
                'tarif'    => 0,
                'capacite' => 0,
                'note'     => $note,
            ];
        }
        return $rows;
    }

    /**
     * Genere un id de creneau/seance garanti unique dans la sauvegarde courante.
     * uniqid() avec entropie (2e argument a true) rend une collision quasi
     * impossible, et le do/while contre $used la ferme completement : deux
     * nouvelles lignes ajoutees d'un coup ne peuvent jamais partager un id (ce
     * qui melangerait leur comptage de places). $used indexe les ids deja pris.
     */
    private static function next_unique_id( $prefix, $used ) {
        do {
            $id = sanitize_key( uniqid( $prefix, true ) );
        } while ( '' === $id || isset( $used[ $id ] ) );
        return $id;
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
        $jours_ok = OPAC_Calendar::jours_keys();
        $clean = [];
        $used  = [];
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
            if ( '' === $id || isset( $used[ $id ] ) ) {
                $id = self::next_unique_id( 'c', $used );
            }
            $used[ $id ] = true;
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

    /* --------------------------------------------------------------------- *
     * Ephemeres : seances datees (modele hybride)
     * --------------------------------------------------------------------- */

    /**
     * Meta box d'edition des seances datees sur l'ephemere. Optionnelle : un
     * ephemere one-shot garde ses champs Date de debut / Date de fin ; une liste
     * de seances ne sert que quand l'atelier se tient sur plusieurs dates, avec
     * une capacite par date. Meme mecanique repetable que les creneaux d'atelier.
     */
    public static function stage_seances_meta_box() {
        add_meta_box(
            'opac_stage_seances',
            __( 'Séances (dates) - optionnel', 'opac-custom' ),
            [ __CLASS__, 'render_stage_seances_box' ],
            'opac_stage',
            'normal',
            'high'
        );
    }

    public static function render_stage_seances_box( $post ) {
        wp_nonce_field( 'opac_stage_seances', 'opac_stage_seances_nonce' );
        $seances = get_post_meta( $post->ID, 'opac_stage_seances', true );
        if ( ! is_array( $seances ) ) {
            $seances = [];
        }
        ?>
        <p class="description">
            <?php esc_html_e( 'Laissez vide si l\'atelier a lieu sur une seule période : les champs « Date de début » et « Date de fin » de la fiche suffisent. Renseignez une ligne par séance si l\'atelier se tient sur plusieurs dates ; le visiteur choisira alors sa séance à l\'inscription. Tarif et capacité par séance (capacité 0 = pas de limite).', 'opac-custom' ); ?>
        </p>
        <p class="description">
            <?php esc_html_e( 'La « Date de début » de la fiche pilote l\'affichage sur la carte et l\'onglet de période : renseignez-la même si vous détaillez des séances.', 'opac-custom' ); ?>
        </p>
        <table class="widefat opac-creneaux-editor">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Date', 'opac-custom' ); ?></th>
                    <th><?php esc_html_e( 'Début', 'opac-custom' ); ?></th>
                    <th><?php esc_html_e( 'Fin', 'opac-custom' ); ?></th>
                    <th><?php esc_html_e( 'Tarif (€)', 'opac-custom' ); ?></th>
                    <th><?php esc_html_e( 'Capacité', 'opac-custom' ); ?></th>
                    <th><?php esc_html_e( 'Note', 'opac-custom' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="opac-seances-rows">
                <?php
                $i = 0;
                foreach ( $seances as $row ) {
                    echo self::seance_row_html( (string) $i, is_array( $row ) ? $row : [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    $i++;
                }
                ?>
            </tbody>
        </table>
        <p><button type="button" class="button" id="opac-seances-add"><?php esc_html_e( '+ Ajouter une séance', 'opac-custom' ); ?></button></p>
        <script type="text/template" id="opac-seances-tpl"><?php echo self::seance_row_html( '__i__', [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
        <script>
        (function(){
            var add=document.getElementById('opac-seances-add'),
                rows=document.getElementById('opac-seances-rows'),
                tpl=document.getElementById('opac-seances-tpl');
            if(!add||!rows||!tpl){return;}
            var n=rows.children.length;
            add.addEventListener('click',function(){
                var tmp=document.createElement('tbody');
                tmp.innerHTML=tpl.innerHTML.replace(/__i__/g,'n'+(n++)).trim();
                if(tmp.firstElementChild){rows.appendChild(tmp.firstElementChild);rows.dispatchEvent(new Event('change',{bubbles:true}));}
            });
            rows.addEventListener('click',function(e){
                var b=e.target.closest('.opac-creneau-del');
                if(b){e.preventDefault();var tr=b.closest('tr');if(tr){tr.parentNode.removeChild(tr);rows.dispatchEvent(new Event('change',{bubbles:true}));}}
            });
        })();
        </script>
        <?php
    }

    /**
     * Markup d'une ligne de l'editeur de seances. Reutilise pour les lignes
     * existantes (index numerique) et le template JS (index '__i__'). Meme
     * structure que creneau_row_html mais avec une saisie de date.
     */
    private static function seance_row_html( $index, $row ) {
        $id       = isset( $row['id'] ) ? (string) $row['id'] : '';
        $date     = isset( $row['date'] ) ? (string) $row['date'] : '';
        $debut    = isset( $row['debut'] ) ? (string) $row['debut'] : '';
        $fin      = isset( $row['fin'] ) ? (string) $row['fin'] : '';
        $tarif    = isset( $row['tarif'] ) && '' !== $row['tarif'] ? (int) $row['tarif'] : '';
        $capacite = isset( $row['capacite'] ) && '' !== $row['capacite'] ? (int) $row['capacite'] : '';
        $note     = isset( $row['note'] ) ? (string) $row['note'] : '';
        $base     = 'opac_stage_seances[' . $index . ']';

        ob_start();
        ?>
        <tr>
            <td>
                <input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $id ); ?>" />
                <input type="date" name="<?php echo esc_attr( $base ); ?>[date]" value="<?php echo esc_attr( $date ); ?>" />
            </td>
            <td><input type="time" name="<?php echo esc_attr( $base ); ?>[debut]" value="<?php echo esc_attr( $debut ); ?>" /></td>
            <td><input type="time" name="<?php echo esc_attr( $base ); ?>[fin]" value="<?php echo esc_attr( $fin ); ?>" /></td>
            <td><input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( $base ); ?>[tarif]" value="<?php echo esc_attr( $tarif ); ?>" /></td>
            <td><input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( $base ); ?>[capacite]" value="<?php echo esc_attr( $capacite ); ?>" /></td>
            <td><input type="text" name="<?php echo esc_attr( $base ); ?>[note]" value="<?php echo esc_attr( $note ); ?>" /></td>
            <td><button type="button" class="button-link opac-creneau-del" aria-label="<?php esc_attr_e( 'Retirer la séance', 'opac-custom' ); ?>">&times;</button></td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Sauvegarde des seances datees. Ignore les lignes sans date, attribue un
     * id stable (prefixe 's', preserve a l'edition) pour le comptage des places.
     */
    public static function save_stage_seances( $post_id, $post ) {
        if ( ! isset( $_POST['opac_stage_seances_nonce'] )
            || ! wp_verify_nonce( wp_unslash( $_POST['opac_stage_seances_nonce'] ), 'opac_stage_seances' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $raw = ( isset( $_POST['opac_stage_seances'] ) && is_array( $_POST['opac_stage_seances'] ) )
            ? wp_unslash( $_POST['opac_stage_seances'] )
            : [];
        $clean = [];
        $used  = [];
        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $date = isset( $row['date'] ) ? sanitize_text_field( $row['date'] ) : '';
            // Une seance sans date valide (Y-m-d reel) est ignoree : c'est la date
            // qui la distingue et sert au libelle.
            if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m )
                || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
                continue;
            }
            $id = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
            if ( '' === $id || isset( $used[ $id ] ) ) {
                $id = self::next_unique_id( 's', $used );
            }
            $used[ $id ] = true;
            $clean[] = [
                'id'       => $id,
                'date'     => $date,
                'debut'    => isset( $row['debut'] ) ? sanitize_text_field( $row['debut'] ) : '',
                'fin'      => isset( $row['fin'] ) ? sanitize_text_field( $row['fin'] ) : '',
                'tarif'    => isset( $row['tarif'] ) ? absint( $row['tarif'] ) : 0,
                'capacite' => isset( $row['capacite'] ) ? absint( $row['capacite'] ) : 0,
                'note'     => isset( $row['note'] ) ? sanitize_text_field( $row['note'] ) : '',
            ];
        }

        // Tri chronologique : l'ordre de saisie n'a pas a decider de l'affichage.
        usort( $clean, static function ( $a, $b ) {
            return strcmp( $a['date'] . $a['debut'], $b['date'] . $b['debut'] );
        } );

        if ( empty( $clean ) ) {
            delete_post_meta( $post_id, 'opac_stage_seances' );
        } else {
            update_post_meta( $post_id, 'opac_stage_seances', $clean );
        }
    }
}
