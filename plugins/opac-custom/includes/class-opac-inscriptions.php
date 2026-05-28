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
    const RATE_LIMIT_S  = 60;

    private static function recipient() {
        return class_exists( 'OPAC_Settings' )
            ? (string) OPAC_Settings::get( 'opac_org_email' )
            : 'contact@opacplerin.fr';
    }

    public static function register() {
        // Form public.
        add_action( 'admin_post_' . self::ACTION_SUBMIT, [ __CLASS__, 'handle_submit' ] );
        add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, [ __CLASS__, 'handle_submit' ] );

        // Workflow admin (Valider / Refuser / Liste d'attente).
        add_action( 'admin_post_' . self::ACTION_ADMIN, [ __CLASS__, 'handle_action' ] );
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

        $nom       = isset( $_POST['opac_nom'] )       ? sanitize_text_field( wp_unslash( $_POST['opac_nom'] ) )       : '';
        $prenom    = isset( $_POST['opac_prenom'] )    ? sanitize_text_field( wp_unslash( $_POST['opac_prenom'] ) )    : '';
        $email     = isset( $_POST['opac_email'] )     ? sanitize_email( wp_unslash( $_POST['opac_email'] ) )          : '';
        $telephone = isset( $_POST['opac_telephone'] ) ? sanitize_text_field( wp_unslash( $_POST['opac_telephone'] ) ) : '';
        $creneau   = isset( $_POST['opac_creneau'] )   ? sanitize_text_field( wp_unslash( $_POST['opac_creneau'] ) )   : '';
        $mineur    = ! empty( $_POST['opac_mineur'] );
        $code_postal = isset( $_POST['opac_code_postal'] ) ? sanitize_text_field( wp_unslash( $_POST['opac_code_postal'] ) ) : '';
        $commune     = isset( $_POST['opac_commune'] )     ? sanitize_text_field( wp_unslash( $_POST['opac_commune'] ) )     : '';
        // Adhesion derivee cote serveur (plus de valeur auto-declaree) :
        // mineur -> 'mineur' ; sinon CP 22190 -> 'plerinais' ; sinon 'exterieur'.
        $adhesion  = $mineur ? 'mineur' : ( '22190' === $code_postal ? 'plerinais' : 'exterieur' );
        $message   = isset( $_POST['opac_message'] )   ? sanitize_textarea_field( wp_unslash( $_POST['opac_message'] ) ) : '';
        $rgpd      = ! empty( $_POST['opac_rgpd'] );

        // Resolution de l'atelier/stage cible : soit via hidden inputs (URL pre-remplie),
        // soit via le select unifie opac_cible=atelier:ID ou stage:ID.
        $atelier_id  = isset( $_POST['opac_atelier_id'] ) ? absint( $_POST['opac_atelier_id'] ) : 0;
        $stage_id    = isset( $_POST['opac_stage_id'] )   ? absint( $_POST['opac_stage_id'] )   : 0;
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
        $ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $rl_cible = $atelier_id ? ( 'a' . $atelier_id ) : ( 's' . $stage_id );
        $rl_sig   = strtolower( $ip . '|' . $email . '|' . $rl_cible . '|' . $creneau . '|' . $nom . '|' . $prenom );
        $rl_key   = 'opac_insc_rl_' . md5( $rl_sig );
        if ( get_transient( $rl_key ) ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'doublon', $back ) );
            exit;
        }
        set_transient( $rl_key, 1, self::RATE_LIMIT_S );

        // Verifie que l'atelier/stage existe encore (peut avoir ete supprime).
        $cible_id    = $atelier_id ? $atelier_id : $stage_id;
        $cible_post  = get_post( $cible_id );
        $cible_type  = $atelier_id ? 'opac_atelier' : 'opac_stage';
        if ( ! $cible_post || $cible_post->post_type !== $cible_type ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'atelier', $back ) );
            exit;
        }
        $cible_titre = get_the_title( $cible_post );

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
        update_post_meta( $post_id, 'opac_insc_plerinais', ( '22190' === $code_postal ) ? 1 : 0 );

        wp_set_object_terms( $post_id, [ 'en-attente' ], 'opac_inscription_status', false );

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

        wp_safe_redirect( add_query_arg( 'envoye', '1', $back ) );
        exit;
    }

    public static function handle_action() {
        $id     = isset( $_GET['id'] )     ? absint( $_GET['id'] )                            : 0;
        $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) )    : '';
        $nonce  = isset( $_GET['_wpnonce'] ) ? (string) $_GET['_wpnonce']                     : '';

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

    private static function send_admin_notification( $post_id, $data ) {
        $type_label = $data['cible_type'] === 'opac_atelier' ? 'Atelier à l\'année' : 'Atelier éphémère';
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
        $loc = trim( ( isset( $data['commune'] ) ? $data['commune'] : '' ) . ' ' . ( isset( $data['code_postal'] ) ? $data['code_postal'] : '' ) );
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

        $cible_id    = (int) get_post_meta( $post_id, 'opac_insc_atelier_id', true );
        $cible_titre = $cible_id ? get_the_title( $cible_id ) : '';

        $template_key = 'opac_email_' . str_replace( '-', '_', $status );
        $template     = class_exists( 'OPAC_Settings' ) ? (string) OPAC_Settings::get( $template_key ) : '';
        if ( ! $template ) {
            return;
        }

        // Resolution du tarif selon le type cible (atelier annuel ou stage).
        $tarif = '';
        if ( $cible_id ) {
            if ( get_post_type( $cible_id ) === 'opac_atelier' ) {
                $tarif = (int) get_post_meta( $cible_id, 'opac_tarif_annuel', true );
                $tarif = $tarif > 0 ? $tarif . ' € / an' : '';
            } elseif ( get_post_type( $cible_id ) === 'opac_stage' ) {
                $tarif = (int) get_post_meta( $cible_id, 'opac_tarif_seance', true );
                $tarif = $tarif > 0 ? $tarif . ' €' : '';
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
        ];
        $subject = $subjects_map[ $status ] ?? '[OPAC] Votre inscription';

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
}
