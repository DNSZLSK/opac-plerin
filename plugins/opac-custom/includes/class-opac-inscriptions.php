<?php
/**
 * OPAC Custom - Pipeline inscriptions
 *
 * 1. handle_submit() : recoit le form public, valide, cree un post
 *    opac_inscription en statut taxonomy 'en-attente', notifie Katell.
 *
 * 2. handle_action() : recoit les clics admin row actions
 *    (Valider / Refuser / Liste d'attente), change le term de status,
 *    envoie un email a l'inscrit.
 *
 * Securite : nonce + honeypot + rate-limit transient + capability check
 * cote admin. PII non exposees en REST (declare en class-opac-meta).
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Inscriptions {

    const ACTION_SUBMIT = 'opac_inscription';
    const ACTION_ADMIN  = 'opac_insc_action';
    const ACTION_EXPORT = 'opac_insc_export';
    const RATE_LIMIT_S  = 60;

    private static function recipient() {
        if ( class_exists( 'OPAC_Settings' ) ) {
            return (string) OPAC_Settings::get( 'opac_org_email' );
        }
        return 'contact@opacplerin.fr';
    }

    public static function register() {
        // Form public.
        add_action( 'admin_post_' . self::ACTION_SUBMIT, [ __CLASS__, 'handle_submit' ] );
        add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, [ __CLASS__, 'handle_submit' ] );

        // Workflow admin (Valider / Refuser / Liste d'attente).
        add_action( 'admin_post_' . self::ACTION_ADMIN, [ __CLASS__, 'handle_action' ] );

        // Export CSV des inscriptions (bouton liste admin).
        add_action( 'admin_post_' . self::ACTION_EXPORT, [ __CLASS__, 'handle_export' ] );
    }

    public static function handle_submit() {
        $back = self::inscription_url();

        // Honeypot : silent success si rempli (bots).
        if ( ! empty( $_POST['opac_hp_website'] ) ) {
            wp_safe_redirect( add_query_arg( 'envoye', '1', $back ) );
            exit;
        }

        // Nonce.
        if ( ! isset( $_POST['opac_inscription_nonce'] )
            || ! wp_verify_nonce( wp_unslash( $_POST['opac_inscription_nonce'] ), 'opac_inscription_submit' ) ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'nonce', $back ) );
            exit;
        }

        // Chaque champ : on lit la valeur envoyee si elle existe, sinon chaine vide.
        if ( isset( $_POST['opac_nom'] ) ) {
            $nom = sanitize_text_field( wp_unslash( $_POST['opac_nom'] ) );
        } else {
            $nom = '';
        }
        if ( isset( $_POST['opac_prenom'] ) ) {
            $prenom = sanitize_text_field( wp_unslash( $_POST['opac_prenom'] ) );
        } else {
            $prenom = '';
        }
        if ( isset( $_POST['opac_email'] ) ) {
            $email = sanitize_email( wp_unslash( $_POST['opac_email'] ) );
        } else {
            $email = '';
        }
        if ( isset( $_POST['opac_telephone'] ) ) {
            $telephone = sanitize_text_field( wp_unslash( $_POST['opac_telephone'] ) );
        } else {
            $telephone = '';
        }
        if ( isset( $_POST['opac_creneau'] ) ) {
            $creneau = sanitize_text_field( wp_unslash( $_POST['opac_creneau'] ) );
        } else {
            $creneau = '';
        }
        if ( isset( $_POST['opac_creneau_id'] ) ) {
            $creneau_id = sanitize_key( wp_unslash( $_POST['opac_creneau_id'] ) );
        } else {
            $creneau_id = '';
        }
        $mineur = ! empty( $_POST['opac_mineur'] );
        if ( isset( $_POST['opac_code_postal'] ) ) {
            $code_postal = sanitize_text_field( wp_unslash( $_POST['opac_code_postal'] ) );
        } else {
            $code_postal = '';
        }
        if ( isset( $_POST['opac_commune'] ) ) {
            $commune = sanitize_text_field( wp_unslash( $_POST['opac_commune'] ) );
        } else {
            $commune = '';
        }
        // Adhesion derivee cote serveur (plus de valeur auto-declaree) :
        // mineur -> 'mineur' ; sinon CP 22190 -> 'plerinais' ; sinon 'exterieur'.
        if ( $mineur ) {
            $adhesion = 'mineur';
        } elseif ( '22190' === $code_postal ) {
            $adhesion = 'plerinais';
        } else {
            $adhesion = 'exterieur';
        }
        if ( isset( $_POST['opac_message'] ) ) {
            $message = sanitize_textarea_field( wp_unslash( $_POST['opac_message'] ) );
        } else {
            $message = '';
        }
        $rgpd = ! empty( $_POST['opac_rgpd'] );

        // Resolution de l'atelier/stage cible : soit via hidden inputs (URL pre-remplie),
        // soit via le select unifie opac_cible=atelier:ID ou stage:ID.
        if ( isset( $_POST['opac_atelier_id'] ) ) {
            $atelier_id = absint( $_POST['opac_atelier_id'] );
        } else {
            $atelier_id = 0;
        }
        if ( isset( $_POST['opac_stage_id'] ) ) {
            $stage_id = absint( $_POST['opac_stage_id'] );
        } else {
            $stage_id = 0;
        }
        if ( ! $atelier_id && ! $stage_id && isset( $_POST['opac_cible'] ) ) {
            $cible = sanitize_text_field( wp_unslash( $_POST['opac_cible'] ) );
            if ( preg_match( '/^(atelier|stage):(\d+)$/', $cible, $m ) ) {
                if ( $m[1] === 'atelier' ) {
                    $atelier_id = (int) $m[2];
                } else {
                    $stage_id = (int) $m[2];
                }
            }
        }

        if ( ! $nom || ! $prenom || ! $email ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'champs', $back ) );
            exit;
        }
        if ( ! is_email( $email ) ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'email', $back ) );
            exit;
        }
        if ( ! $atelier_id && ! $stage_id ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'atelier', $back ) );
            exit;
        }
        if ( ! $rgpd ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'rgpd', $back ) );
            exit;
        }

        // Rate-limit transient : bloque uniquement la soumission strictement
        // identique (double-clic / refresh). La cle inclut atelier/stage +
        // creneau + nom + prenom pour qu'un meme parent (meme email + meme IP)
        // puisse inscrire plusieurs enfants, ou lui-meme, voire des freres au
        // meme creneau, sans faux "doublon". L'anti-bot reste honeypot + nonce.
        if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = (string) $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '';
        }
        if ( $atelier_id ) {
            $rl_cible = 'a' . $atelier_id;
        } else {
            $rl_cible = 's' . $stage_id;
        }
        $rl_sig   = strtolower( $ip . '|' . $email . '|' . $rl_cible . '|' . $creneau . '|' . $creneau_id . '|' . $nom . '|' . $prenom );
        $rl_key   = 'opac_insc_rl_' . md5( $rl_sig );
        if ( get_transient( $rl_key ) ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'doublon', $back ) );
            exit;
        }
        set_transient( $rl_key, 1, self::RATE_LIMIT_S );

        // Verifie que l'atelier/stage existe encore (peut avoir ete supprime).
        if ( $atelier_id ) {
            $cible_id = $atelier_id;
        } else {
            $cible_id = $stage_id;
        }
        $cible_post = get_post( $cible_id );
        if ( $atelier_id ) {
            $cible_type = 'opac_atelier';
        } else {
            $cible_type = 'opac_stage';
        }
        if ( ! $cible_post || $cible_post->post_type !== $cible_type ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'atelier', $back ) );
            exit;
        }
        $cible_titre = get_the_title( $cible_post );

        // Pas d'adhésion pour les ateliers éphémères : on n'enregistre ni
        // n'affiche d'estimation d'adhésion pour une cible opac_stage.
        if ( 'opac_atelier' !== $cible_type ) {
            $adhesion = '';
        }

        // Resolution du creneau structure (palier 2) : si un id de creneau est
        // soumis et que l'atelier le possede, on enregistre l'id + le tarif du
        // creneau et un libelle lisible. Sinon on garde le creneau texte.
        $creneau_tarif = 0;
        $auto_waitlist = false;
        if ( 'opac_atelier' === $cible_type && '' !== $creneau_id ) {
            $struct = get_post_meta( $cible_id, 'opac_creneaux', true );
            if ( is_array( $struct ) ) {
                $jours = [
                    'lundi' => 'Lundi', 'mardi' => 'Mardi', 'mercredi' => 'Mercredi', 'jeudi' => 'Jeudi',
                    'vendredi' => 'Vendredi', 'samedi' => 'Samedi', 'dimanche' => 'Dimanche',
                ];
                foreach ( $struct as $c ) {
                    if ( is_array( $c ) && isset( $c['id'] ) && (string) $c['id'] === $creneau_id ) {
                        if ( isset( $jours[ $c['jour'] ?? '' ] ) ) {
                            $jlabel = $jours[ $c['jour'] ];
                        } else {
                            $jlabel = '';
                        }
                        $creneau = trim( $jlabel . ' ' . ( $c['debut'] ?? '' ) . ' - ' . ( $c['fin'] ?? '' ) );
                        if ( isset( $c['tarif'] ) ) {
                            $creneau_tarif = (int) $c['tarif'];
                        } else {
                            $creneau_tarif = 0;
                        }
                        $auto_waitlist = self::creneau_is_full( $cible_id, $c );
                        break;
                    }
                }
            }
        }

        // Cree le post opac_inscription.
        $insc_title = sprintf( '[%s] %s %s', $cible_titre, $prenom, $nom );
        $post_id = wp_insert_post( [
            'post_type'   => 'opac_inscription',
            'post_status' => 'publish',
            'post_title'  => $insc_title,
        ] );
        if ( ! $post_id || is_wp_error( $post_id ) ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'enregistrement', $back ) );
            exit;
        }

        update_post_meta( $post_id, 'opac_insc_nom',            $nom );
        update_post_meta( $post_id, 'opac_insc_prenom',         $prenom );
        update_post_meta( $post_id, 'opac_insc_email',          $email );
        update_post_meta( $post_id, 'opac_insc_telephone',      $telephone );
        update_post_meta( $post_id, 'opac_insc_atelier_id',     $cible_id );
        update_post_meta( $post_id, 'opac_insc_creneau',        $creneau );
        update_post_meta( $post_id, 'opac_insc_message',        $message );
        update_post_meta( $post_id, 'opac_insc_date_submitted', current_time( 'mysql' ) );
        update_post_meta( $post_id, 'opac_insc_source',         'form-frontend' );
        if ( $adhesion ) {
            update_post_meta( $post_id, 'opac_insc_adhesion', $adhesion );
        }
        if ( $code_postal ) {
            update_post_meta( $post_id, 'opac_insc_code_postal', $code_postal );
        }
        if ( $commune ) {
            update_post_meta( $post_id, 'opac_insc_commune', $commune );
        }
        // Flag Plerinais (code postal 22190) pour le tri prioritaire en admin.
        if ( '22190' === $code_postal ) {
            $est_plerinais = 1;
        } else {
            $est_plerinais = 0;
        }
        update_post_meta( $post_id, 'opac_insc_plerinais', $est_plerinais );
        if ( '' !== $creneau_id ) {
            update_post_meta( $post_id, 'opac_insc_creneau_id', $creneau_id );
        }
        if ( $creneau_tarif > 0 ) {
            update_post_meta( $post_id, 'opac_insc_tarif', $creneau_tarif );
        }

        // Creneau complet -> liste d'attente automatique (palier 3), sinon en-attente.
        if ( $auto_waitlist ) {
            $statut_initial = 'liste-attente';
        } else {
            $statut_initial = 'en-attente';
        }
        wp_set_object_terms( $post_id, [ $statut_initial ], 'opac_inscription_status', false );

        // Notification Katell.
        self::send_admin_notification( $post_id, [
            'nom'         => $nom,
            'prenom'      => $prenom,
            'email'       => $email,
            'telephone'   => $telephone,
            'code_postal' => $code_postal,
            'commune'     => $commune,
            'cible_titre' => $cible_titre,
            'cible_type'  => $cible_type,
            'creneau'     => $creneau,
            'adhesion'    => $adhesion,
            'message'     => $message,
        ] );

        // Bascule auto en liste d'attente : prevenir l'inscrit immediatement.
        if ( $auto_waitlist ) {
            self::send_user_notification( $post_id, 'liste-attente' );
        }

        $args = [ 'envoye' => '1' ];
        if ( $auto_waitlist ) {
            $args['attente'] = '1';
        }
        wp_safe_redirect( add_query_arg( $args, $back ) );
        exit;
    }

    public static function handle_action() {
        if ( isset( $_GET['id'] ) ) {
            $id = absint( $_GET['id'] );
        } else {
            $id = 0;
        }
        if ( isset( $_GET['status'] ) ) {
            $status = sanitize_key( wp_unslash( $_GET['status'] ) );
        } else {
            $status = '';
        }
        if ( isset( $_GET['_wpnonce'] ) ) {
            $nonce = (string) $_GET['_wpnonce'];
        } else {
            $nonce = '';
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Permissions insuffisantes.', 'opac-custom' ) );
        }
        if ( ! wp_verify_nonce( $nonce, 'opac_insc_action_' . $id . '_' . $status ) ) {
            wp_die( esc_html__( 'Lien de validation invalide ou expiré.', 'opac-custom' ) );
        }
        $valid_statuses = [ 'validee', 'refusee', 'liste-attente' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            wp_die( esc_html__( 'Statut inconnu.', 'opac-custom' ) );
        }

        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'opac_inscription' ) {
            wp_die( esc_html__( 'Inscription introuvable.', 'opac-custom' ) );
        }

        // Idempotence : si l'inscription est deja dans le statut demande, ne rien
        // refaire (evite le double email sur double-clic ou rejeu du lien row action).
        $current = wp_get_object_terms( $id, 'opac_inscription_status', [ 'fields' => 'slugs' ] );
        if ( ! is_wp_error( $current ) && in_array( $status, (array) $current, true ) ) {
            wp_safe_redirect( add_query_arg(
                [ 'post_type' => 'opac_inscription', 'opac_insc_done' => 'nochange' ],
                admin_url( 'edit.php' )
            ) );
            exit;
        }

        wp_set_object_terms( $id, [ $status ], 'opac_inscription_status', false );

        self::send_user_notification( $id, $status );

        // Desistement : si on retire une inscription deja validee, une place se
        // libere sur son creneau -> promouvoir le 1er en liste d'attente.
        $was_validee = ! is_wp_error( $current ) && in_array( 'validee', (array) $current, true );
        if ( $was_validee && 'validee' !== $status ) {
            $a_id = (int) get_post_meta( $id, 'opac_insc_atelier_id', true );
            $c_id = (string) get_post_meta( $id, 'opac_insc_creneau_id', true );
            if ( $a_id && '' !== $c_id ) {
                self::promote_waitlist( $a_id, $c_id );
            }
        }

        $redirect = add_query_arg(
            [
                'post_type'      => 'opac_inscription',
                'opac_insc_done' => $status,
            ],
            admin_url( 'edit.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Export CSV des inscriptions, colonnes separees (nom, prenom, email...).
     * Capability + nonce. Respecte le filtre de statut courant s'il est passe
     * (?opac_inscription_status=slug), sinon exporte toutes les inscriptions.
     * En-tete UTF-8 BOM pour une ouverture propre dans Excel.
     */
    public static function handle_export() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Permissions insuffisantes.', 'opac-custom' ) );
        }
        $nonce = isset( $_GET['_wpnonce'] ) ? (string) $_GET['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, self::ACTION_EXPORT ) ) {
            wp_die( esc_html__( 'Lien d\'export invalide ou expiré.', 'opac-custom' ) );
        }

        $status = isset( $_GET['opac_inscription_status'] ) ? sanitize_key( wp_unslash( $_GET['opac_inscription_status'] ) ) : '';

        $args = [
            'post_type'      => 'opac_inscription',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];
        if ( '' !== $status ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'opac_inscription_status',
                    'field'    => 'slug',
                    'terms'    => $status,
                ],
            ];
        }
        $posts = get_posts( $args );

        $adh_labels = [
            'plerinais' => __( 'Plérinais', 'opac-custom' ),
            'exterieur' => __( 'Extérieur', 'opac-custom' ),
            'mineur'    => __( 'Mineur', 'opac-custom' ),
        ];

        $filename = 'inscriptions-opac-' . current_time( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $out = fopen( 'php://output', 'w' );
        // BOM UTF-8 : Excel detecte l'encodage et affiche les accents correctement.
        fwrite( $out, "\xEF\xBB\xBF" );

        fputcsv( $out, [
            __( 'Nom', 'opac-custom' ),
            __( 'Prénom', 'opac-custom' ),
            __( 'Email', 'opac-custom' ),
            __( 'Téléphone', 'opac-custom' ),
            __( 'Code postal', 'opac-custom' ),
            __( 'Commune', 'opac-custom' ),
            __( 'Atelier', 'opac-custom' ),
            __( 'Créneau', 'opac-custom' ),
            __( 'Adhésion', 'opac-custom' ),
            __( 'Statut', 'opac-custom' ),
            __( 'Date', 'opac-custom' ),
            __( 'Message', 'opac-custom' ),
        ] );

        foreach ( $posts as $p ) {
            $id         = $p->ID;
            $atelier_id = (int) get_post_meta( $id, 'opac_insc_atelier_id', true );
            $atelier    = ( $atelier_id && get_post( $atelier_id ) ) ? get_the_title( $atelier_id ) : '';
            $adhesion   = (string) get_post_meta( $id, 'opac_insc_adhesion', true );
            $adh_label  = ( '' !== $adhesion && isset( $adh_labels[ $adhesion ] ) ) ? $adh_labels[ $adhesion ] : $adhesion;

            $status_terms = wp_get_object_terms( $id, 'opac_inscription_status', [ 'fields' => 'names' ] );
            $statut       = ( ! is_wp_error( $status_terms ) && ! empty( $status_terms ) ) ? implode( ', ', $status_terms ) : '';

            fputcsv( $out, [
                (string) get_post_meta( $id, 'opac_insc_nom', true ),
                (string) get_post_meta( $id, 'opac_insc_prenom', true ),
                (string) get_post_meta( $id, 'opac_insc_email', true ),
                (string) get_post_meta( $id, 'opac_insc_telephone', true ),
                (string) get_post_meta( $id, 'opac_insc_code_postal', true ),
                (string) get_post_meta( $id, 'opac_insc_commune', true ),
                $atelier,
                (string) get_post_meta( $id, 'opac_insc_creneau', true ),
                $adh_label,
                $statut,
                (string) get_post_meta( $id, 'opac_insc_date_submitted', true ),
                (string) get_post_meta( $id, 'opac_insc_message', true ),
            ] );
        }

        fclose( $out );
        exit;
    }

    private static function send_admin_notification( $post_id, $data ) {
        if ( $data['cible_type'] === 'opac_atelier' ) {
            $type_label = 'Atelier à l\'année';
        } else {
            $type_label = 'Atelier éphémère';
        }
        $subject = sprintf( '[OPAC inscription] %s - %s %s', $data['cible_titre'], $data['prenom'], $data['nom'] );

        $edit_link = get_edit_post_link( $post_id, '' );

        $body  = "Nouvelle demande d'inscription enregistree sur opacplerin.fr\n\n";
        $body .= "Type : {$type_label}\n";
        $body .= "Atelier / stage : {$data['cible_titre']}\n";
        if ( $data['creneau'] ) {
            $body .= "Creneau : {$data['creneau']}\n";
        }
        if ( $data['adhesion'] ) {
            $body .= "Adhesion (estimee) : {$data['adhesion']}\n";
        }
        $body .= "\n";
        $body .= "Nom : {$data['nom']}\n";
        $body .= "Prenom : {$data['prenom']}\n";
        $body .= "Email : {$data['email']}\n";
        $body .= "Telephone : {$data['telephone']}\n";
        // Commune affichee si renseignee, code postal idem (sinon chaine vide).
        if ( isset( $data['commune'] ) ) {
            $loc_commune = $data['commune'];
        } else {
            $loc_commune = '';
        }
        if ( isset( $data['code_postal'] ) ) {
            $loc_cp = $data['code_postal'];
        } else {
            $loc_cp = '';
        }
        $loc = trim( $loc_commune . ' ' . $loc_cp );
        if ( $loc ) {
            $body .= "Commune : {$loc}\n";
        }
        if ( $data['message'] ) {
            $body .= "\nMessage :\n{$data['message']}\n";
        }
        $body .= "\n--\nGerer cette inscription : {$edit_link}\n";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . sprintf( '%s %s <%s>', $data['prenom'], $data['nom'], $data['email'] ),
        ];

        $sent = wp_mail( self::recipient(), $subject, $body, $headers );
        if ( ! $sent ) {
            error_log( '[OPAC inscription] admin notification wp_mail failed for ID ' . $post_id );
        }
    }

    /**
     * Email a l'inscrit selon le statut choisi par Katell.
     * Templates lus depuis OPAC_Settings (control panel admin), avec
     * substitution des placeholders {prenom}, {nom}, {atelier}, {tarif},
     * {adresse}, {tel}, {email}, {horaires}, {nom_asso}.
     */
    private static function send_user_notification( $post_id, $status ) {
        $email = (string) get_post_meta( $post_id, 'opac_insc_email', true );
        if ( ! $email || ! is_email( $email ) ) {
            return;
        }

        $cible_id = (int) get_post_meta( $post_id, 'opac_insc_atelier_id', true );
        if ( $cible_id ) {
            $cible_titre = get_the_title( $cible_id );
        } else {
            $cible_titre = '';
        }

        $template_key = 'opac_email_' . str_replace( '-', '_', $status );
        if ( class_exists( 'OPAC_Settings' ) ) {
            $template = (string) OPAC_Settings::get( $template_key );
        } else {
            $template = '';
        }
        if ( ! $template ) {
            return;
        }

        // Resolution du tarif : tarif du creneau choisi (palier 2, stocke sur
        // l'inscription) si present, sinon tarif de l'atelier annuel ou du stage.
        $tarif = '';
        $insc_tarif = (int) get_post_meta( $post_id, 'opac_insc_tarif', true );
        if ( $cible_id ) {
            if ( get_post_type( $cible_id ) === 'opac_atelier' ) {
                if ( $insc_tarif > 0 ) {
                    $montant = $insc_tarif;
                } else {
                    $montant = (int) get_post_meta( $cible_id, 'opac_tarif_annuel', true );
                }
                if ( $montant > 0 ) {
                    $tarif = $montant . ' € / an';
                } else {
                    $tarif = '';
                }
            } elseif ( get_post_type( $cible_id ) === 'opac_stage' ) {
                if ( $insc_tarif > 0 ) {
                    $montant = $insc_tarif;
                } else {
                    $montant = (int) get_post_meta( $cible_id, 'opac_tarif_seance', true );
                }
                if ( $montant > 0 ) {
                    $tarif = $montant . ' €';
                } else {
                    $tarif = '';
                }
            }
        }

        $vars = [];
        if ( class_exists( 'OPAC_Settings' ) ) {
            $vars = [
                '{prenom}'   => (string) get_post_meta( $post_id, 'opac_insc_prenom', true ),
                '{nom}'      => (string) get_post_meta( $post_id, 'opac_insc_nom', true ),
                '{atelier}'  => $cible_titre,
                '{tarif}'    => $tarif,
                '{adresse}'  => OPAC_Settings::full_address(),
                '{tel}'      => (string) OPAC_Settings::get( 'opac_org_phone_accueil' ),
                '{email}'    => (string) OPAC_Settings::get( 'opac_org_email' ),
                '{horaires}' => (string) OPAC_Settings::get( 'opac_org_hours' ),
                '{nom_asso}' => (string) OPAC_Settings::get( 'opac_org_legal_name' ),
            ];
        }
        $body = strtr( $template, $vars );

        $subjects_map = [
            'validee'       => sprintf( '[OPAC] Votre inscription à %s est validée', $cible_titre ),
            'refusee'       => sprintf( '[OPAC] Concernant votre demande pour %s', $cible_titre ),
            'liste-attente' => sprintf( '[OPAC] Votre inscription à %s : liste d\'attente', $cible_titre ),
            'place-liberee' => sprintf( '[OPAC] Une place s\'est libérée pour %s', $cible_titre ),
        ];
        // Sujet selon le statut, avec un sujet par defaut si le statut est inconnu.
        if ( isset( $subjects_map[ $status ] ) ) {
            $subject = $subjects_map[ $status ];
        } else {
            $subject = '[OPAC] Votre inscription';
        }

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
        $sent = wp_mail( $email, $subject, $body, $headers );
        if ( ! $sent ) {
            error_log( '[OPAC inscription] user notification wp_mail failed for ' . $email );
        }
    }

    private static function inscription_url() {
        $page = get_page_by_path( 'inscription' );
        if ( $page ) {
            return get_permalink( $page );
        }
        return home_url( '/inscription/' );
    }

    /**
     * Nombre d'inscriptions VALIDEES pour un creneau donne (palier 3).
     * Determine si un creneau est complet. Cache par requete.
     */
    public static function count_validees( $atelier_id, $creneau_id ) {
        $atelier_id = (int) $atelier_id;
        $creneau_id = (string) $creneau_id;
        if ( ! $atelier_id || '' === $creneau_id ) {
            return 0;
        }
        static $cache = [];
        $key = $atelier_id . '|' . $creneau_id;
        if ( isset( $cache[ $key ] ) ) {
            return $cache[ $key ];
        }
        $q = new WP_Query( [
            'post_type'      => 'opac_inscription',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'tax_query'      => [
                [
                    'taxonomy' => 'opac_inscription_status',
                    'field'    => 'slug',
                    'terms'    => 'validee',
                ],
            ],
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'opac_insc_atelier_id', 'value' => $atelier_id ],
                [ 'key' => 'opac_insc_creneau_id', 'value' => $creneau_id ],
            ],
        ] );
        $cache[ $key ] = count( $q->posts );
        return $cache[ $key ];
    }

    /**
     * Vrai si le creneau a atteint sa capacite (capacite 0 = pas de limite).
     * $creneau = tableau structure (id, capacite, ...).
     */
    public static function creneau_is_full( $atelier_id, $creneau ) {
        if ( ! is_array( $creneau ) ) {
            return false;
        }
        if ( isset( $creneau['capacite'] ) ) {
            $cap = (int) $creneau['capacite'];
        } else {
            $cap = 0;
        }
        if ( isset( $creneau['id'] ) ) {
            $id = (string) $creneau['id'];
        } else {
            $id = '';
        }
        if ( $cap <= 0 || '' === $id ) {
            return false;
        }
        return self::count_validees( $atelier_id, $id ) >= $cap;
    }

    /**
     * Promotion au desistement : place le 1er de la liste d'attente du creneau
     * (Plerinais d'abord, puis chronologique) en "en-attente" et l'informe par
     * email qu'une place s'est liberee (paiement au prorata, cf. template).
     */
    private static function promote_waitlist( $atelier_id, $creneau_id ) {
        $ids = get_posts( [
            'post_type'      => 'opac_inscription',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'tax_query'      => [
                [
                    'taxonomy' => 'opac_inscription_status',
                    'field'    => 'slug',
                    'terms'    => 'liste-attente',
                ],
            ],
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'opac_insc_atelier_id', 'value' => (int) $atelier_id ],
                [ 'key' => 'opac_insc_creneau_id', 'value' => (string) $creneau_id ],
            ],
        ] );
        if ( empty( $ids ) ) {
            return;
        }
        // Plerinais d'abord, puis premier arrive (date de soumission croissante).
        usort( $ids, static function ( $a, $b ) {
            $pa = (int) get_post_meta( $a, 'opac_insc_plerinais', true );
            $pb = (int) get_post_meta( $b, 'opac_insc_plerinais', true );
            if ( $pa !== $pb ) {
                return $pb - $pa;
            }
            $da = (string) get_post_meta( $a, 'opac_insc_date_submitted', true );
            $db = (string) get_post_meta( $b, 'opac_insc_date_submitted', true );
            return strcmp( $da, $db );
        } );
        $promu = (int) $ids[0];
        wp_set_object_terms( $promu, [ 'en-attente' ], 'opac_inscription_status', false );
        self::send_user_notification( $promu, 'place-liberee' );
    }
}
