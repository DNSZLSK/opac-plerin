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
    const ACTION_SEND_EMAIL = 'opac_insc_send_email';
    const RATE_LIMIT_S  = 60;
    // Rate-limit par IP, en plus de l'anti-doublon par signature : plafonne le
    // nombre de soumissions VALIDES par IP sur une fenetre glissante. Volontai-
    // rement genereux (une famille inscrit plusieurs enfants, des foyers parta-
    // gent une IP en CGNAT mobile) : cible le flood automatise, pas l'affluence
    // de rentree. A resserrer si du spam reel apparait.
    const RL_IP_WINDOW_S = 600; // 10 minutes
    const RL_IP_MAX      = 12;  // max inscriptions creees par IP / fenetre
    // Taille d'un lot d'envoi groupe : un seul wp_mail par lot, les inscrits en
    // Bcc. Evite un message unique a plusieurs centaines de Bcc (rejet SMTP /
    // classement spam / plafond d'envoi de l'hebergeur) sur une grosse liste.
    const EMAIL_BCC_BATCH = 45;

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

        // Envoi d'un email groupe aux inscrits : ecran de redaction cache (sous-menu
        // masque de la liste) + handler d'envoi.
        add_action( 'admin_post_' . self::ACTION_SEND_EMAIL, [ __CLASS__, 'handle_send_email' ] );
        add_action( 'admin_menu', [ __CLASS__, 'register_email_page' ] );
    }

    /**
     * Rate-limit par IP : true si l'IP a deja atteint le plafond
     * (self::RL_IP_MAX) d'inscriptions creees sur la fenetre courante. Une IP
     * vide (inconnue) n'est jamais bloquee, pour ne pas verrouiller a l'aveugle.
     *
     * Best-effort, PAS un semaphore : le couple get_transient / set_transient
     * (dans ip_bump) n'est pas atomique. Deux requetes simultanees peuvent lire
     * la meme valeur puis ecrire le meme increment, donc un burst parallele peut
     * franchir le plafond. Frein pragmatique contre le flood sequentiel d'un bot,
     * suffisant pour ce site ; le vrai anti-abus a forte concurrence se pose en
     * amont (OVH / Cloudflare), hors PHP.
     *
     * Interne (private) : appelee seulement par handle_submit(). Le harnais la
     * teste par ReflectionMethod, comme OPAC_Admin::next_unique_id, pour ne pas
     * elargir l'API publique de la classe juste pour les tests.
     */
    private static function ip_over_limit( $ip ) {
        if ( '' === (string) $ip ) {
            return false;
        }
        $key = 'opac_insc_ip_' . md5( (string) $ip );
        return (int) get_transient( $key ) >= self::RL_IP_MAX;
    }

    /**
     * Incremente le compteur IP apres une inscription reellement creee, en
     * (re)posant le TTL a self::RL_IP_WINDOW_S : la fenetre est donc glissante
     * (elle repart de la derniere demande acceptee). No-op si l'IP est inconnue.
     *
     * Interne (private) : voir ip_over_limit(), meme raison.
     */
    private static function ip_bump( $ip ) {
        if ( '' === (string) $ip ) {
            return;
        }
        $key = 'opac_insc_ip_' . md5( (string) $ip );
        set_transient( $key, (int) get_transient( $key ) + 1, self::RL_IP_WINDOW_S );
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
        // mineur -> 'mineur' ; sinon CP de Plerin -> 'plerinais' ; sinon 'exterieur'.
        if ( $mineur ) {
            $adhesion = 'mineur';
        } elseif ( OPAC_Settings::PLERIN_POSTAL === $code_postal ) {
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

        // Anti-doublon (signature exacte) : bloque uniquement la soumission
        // strictement identique (double-clic / refresh). La cle inclut atelier/
        // stage + creneau + nom + prenom pour qu'un meme parent (meme email +
        // meme IP) puisse inscrire plusieurs enfants, ou lui-meme, voire des
        // freres au meme creneau, sans faux "doublon". Le vrai frein anti-flood
        // est le rate-limit par IP plus bas ; ici on se contente d'eviter les
        // envois en double. Le transient est pose des maintenant (protege le
        // double-clic pendant la validation), puis PURGE sur chaque chemin
        // d'echec en aval (cible/creneau invalide, insert rate, quota IP) pour
        // ne pas bloquer un retour legitime apres correction.
        if ( class_exists( 'OPAC_Security' ) ) {
            $ip = OPAC_Security::get_client_ip();
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
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
        // On exige le statut publish : un brouillon/prive/corbeille n'est pas
        // proposable au public, donc une inscription vers un tel id (connu par
        // devinette) doit etre refusee comme une cible invalide.
        if ( ! $cible_post || $cible_post->post_type !== $cible_type || 'publish' !== $cible_post->post_status ) {
            delete_transient( $rl_key );
            wp_safe_redirect( add_query_arg( 'erreur', 'atelier', $back ) );
            exit;
        }
        $cible_titre = get_the_title( $cible_post );

        // Pas d'adhésion pour les ateliers éphémères : on n'enregistre ni
        // n'affiche d'estimation d'adhésion pour une cible opac_stage.
        if ( 'opac_atelier' !== $cible_type ) {
            $adhesion = '';
        }

        // Resolution du creneau / seance structure (palier 2) : si un id est
        // soumis et que la cible le possede, on enregistre l'id + le tarif et un
        // libelle lisible. Sinon on garde le creneau texte. Atelier a l'annee =
        // creneaux hebdomadaires (opac_creneaux) ; ephemere = seances datees
        // (opac_stage_seances, modele hybride) : meme id/capacite/comptage.
        $creneau_tarif = 0;
        $auto_waitlist = false;
        if ( '' !== $creneau_id ) {
            // Check explicite par type (pas de else catch-all) : si un jour un
            // autre CPT devient inscriptible avec un creneau_id, on ne tente pas
            // de le resoudre contre une meta qu'il ne possede pas.
            $creneau_resolu = false;
            $struct         = [];
            $labeller       = null;
            if ( 'opac_atelier' === $cible_type ) {
                $struct   = get_post_meta( $cible_id, 'opac_creneaux', true );
                $labeller = [ 'OPAC_Calendar', 'creneau_label' ];
            } elseif ( 'opac_stage' === $cible_type ) {
                $struct   = get_post_meta( $cible_id, 'opac_stage_seances', true );
                $labeller = [ 'OPAC_Calendar', 'seance_label' ];
            }
            if ( $labeller && is_array( $struct ) ) {
                foreach ( $struct as $c ) {
                    if ( is_array( $c ) && isset( $c['id'] ) && (string) $c['id'] === $creneau_id ) {
                        $creneau = call_user_func( $labeller, $c );
                        if ( isset( $c['tarif'] ) ) {
                            $creneau_tarif = (int) $c['tarif'];
                        } else {
                            $creneau_tarif = 0;
                        }
                        $auto_waitlist  = self::creneau_is_full( $cible_id, $c );
                        $creneau_resolu = true;
                        break;
                    }
                }
            }
            // Un creneau_id soumis mais introuvable dans la structure de la
            // cible est forge (ou supprime entre l'affichage et l'envoi) : on
            // refuse plutot que d'enregistrer un id fantome, qui serait compte
            // comme capacite illimitee et echapperait a la liste d'attente.
            if ( ! $creneau_resolu ) {
                delete_transient( $rl_key );
                wp_safe_redirect( add_query_arg( 'erreur', 'creneau', $back ) );
                exit;
            }
        }

        // Rate-limit par IP, independant du contenu du formulaire : contrairement
        // a l'anti-doublon (signature exacte, contournable en variant un champ),
        // il plafonne les inscriptions creees depuis une meme IP. Verifie juste
        // avant l'insert + l'email (le cout reel). Voir ip_over_limit() pour la
        // reserve d'atomicite.
        if ( self::ip_over_limit( $ip ) ) {
            delete_transient( $rl_key ); // ne pas laisser un faux "doublon" par-dessus le refus
            wp_safe_redirect( add_query_arg( 'erreur', 'trop', $back ) );
            exit;
        }

        // Cree le post opac_inscription.
        $insc_title = sprintf( '[%s] %s %s', $cible_titre, $prenom, $nom );
        $post_id = wp_insert_post( [
            'post_type'   => 'opac_inscription',
            'post_status' => 'publish',
            'post_title'  => $insc_title,
        ] );
        if ( ! $post_id || is_wp_error( $post_id ) ) {
            delete_transient( $rl_key );
            wp_safe_redirect( add_query_arg( 'erreur', 'enregistrement', $back ) );
            exit;
        }

        // Inscription reellement creee : incremente le compteur IP. SEUL site
        // d'incrementation, place APRES le succes de l'insert : une cible/creneau
        // invalide ou un insert rate ne compte donc jamais dans le quota IP.
        self::ip_bump( $ip );

        update_post_meta( $post_id, 'opac_insc_nom',            $nom );
        update_post_meta( $post_id, 'opac_insc_prenom',         $prenom );
        update_post_meta( $post_id, 'opac_insc_email',          $email );
        update_post_meta( $post_id, 'opac_insc_telephone',      $telephone );
        update_post_meta( $post_id, 'opac_insc_atelier_id',     $cible_id );
        update_post_meta( $post_id, 'opac_insc_creneau',        $creneau );
        update_post_meta( $post_id, 'opac_insc_message',        $message );
        $date_submitted = current_time( 'mysql' );
        update_post_meta( $post_id, 'opac_insc_date_submitted', $date_submitted );
        update_post_meta( $post_id, 'opac_insc_source',         'form-frontend' );
        // Saison figee a la creation (cf. resolve_saison) : c'est elle qui
        // regroupe les inscriptions dans le bilan annuel, pas la date de demande.
        update_post_meta( $post_id, 'opac_insc_saison', self::resolve_saison( $cible_id, $date_submitted ) );
        if ( $adhesion ) {
            update_post_meta( $post_id, 'opac_insc_adhesion', $adhesion );
        }
        if ( $code_postal ) {
            update_post_meta( $post_id, 'opac_insc_code_postal', $code_postal );
        }
        if ( $commune ) {
            update_post_meta( $post_id, 'opac_insc_commune', $commune );
        }
        // Flag Plerinais (CP de Plerin) pour le tri prioritaire en admin.
        if ( OPAC_Settings::PLERIN_POSTAL === $code_postal ) {
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
        // On conserve la cible : la page de confirmation nomme l'atelier/stage et
        // ne reaffiche plus le formulaire generique (action terminee).
        if ( $atelier_id ) {
            $args['atelier'] = $atelier_id;
        } elseif ( $stage_id ) {
            $args['stage'] = $stage_id;
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

        if ( ! current_user_can( 'edit_others_posts' ) ) {
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

        // Filet metier : la validation manuelle ne verifie pas la capacite (c'est
        // l'admin qui tranche). Si cette validation fait passer le creneau
        // au-dela de sa capacite, on le signale par une notice d'avertissement
        // (sans bloquer), pour qu'un depassement ne passe pas inapercu.
        $redirect_args = [
            'post_type'      => 'opac_inscription',
            'opac_insc_done' => $status,
        ];
        if ( 'validee' === $status ) {
            $a_id = (int) get_post_meta( $id, 'opac_insc_atelier_id', true );
            $c_id = (string) get_post_meta( $id, 'opac_insc_creneau_id', true );
            if ( $a_id && '' !== $c_id ) {
                // Comptage brut (et non places_restantes, qui plafonne a 0 et ne
                // distingue donc pas « exactement plein » de « depasse »), inscrits
                // hors site compris : sans eux l'alerte ne partirait jamais sur un
                // creneau rempli au guichet.
                $row  = self::creneau_row( $a_id, $c_id );
                $cap  = is_array( $row ) && isset( $row['capacite'] ) ? (int) $row['capacite'] : 0;
                $hors = is_array( $row ) && isset( $row['deja_inscrits'] ) ? max( 0, (int) $row['deja_inscrits'] ) : 0;
                if ( $cap > 0 && ( $hors + self::count_validees( $a_id, $c_id ) ) > $cap ) {
                    $redirect_args['opac_insc_over'] = '1';
                }
            }
        }

        wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
        exit;
    }

    /**
     * Export CSV des inscriptions, colonnes separees (nom, prenom, email...).
     * Capability + nonce. Reprend exactement les filtres affiches dans la liste
     * admin : statut (?opac_inscription_status=slug), recherche par nom (?s) et
     * filtre mois (?m). Sans filtre, exporte toutes les inscriptions.
     * En-tete UTF-8 BOM pour une ouverture propre dans Excel.
     */
    public static function handle_export() {
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            wp_die( esc_html__( 'Permissions insuffisantes.', 'opac-custom' ) );
        }
        $nonce = isset( $_GET['_wpnonce'] ) ? (string) $_GET['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, self::ACTION_EXPORT ) ) {
            wp_die( esc_html__( 'Lien d\'export invalide ou expiré.', 'opac-custom' ) );
        }

        // Memes filtres que la liste admin (statut, atelier, recherche, mois) :
        // on exporte tout ce qui matche, pas seulement la page affichee.
        $posts = get_posts( self::query_args_from_request() );

        $adh_labels = OPAC_Labels::adhesions();

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

            fputcsv( $out, array_map( [ __CLASS__, 'csv_safe' ], [
                (string) get_post_meta( $id, 'opac_insc_nom', true ),
                (string) get_post_meta( $id, 'opac_insc_prenom', true ),
                (string) get_post_meta( $id, 'opac_insc_email', true ),
                (string) get_post_meta( $id, 'opac_insc_telephone', true ),
                (string) get_post_meta( $id, 'opac_insc_code_postal', true ),
                (string) get_post_meta( $id, 'opac_insc_commune', true ),
                $atelier,
                self::creneau_display( $id ),
                $adh_label,
                $statut,
                (string) get_post_meta( $id, 'opac_insc_date_submitted', true ),
                (string) get_post_meta( $id, 'opac_insc_message', true ),
            ] ) );
        }

        fclose( $out );
        exit;
    }

    /**
     * Neutralise l'injection de formule CSV : un champ saisi au formulaire public
     * commencant par = + - @ (ou tab / retour chariot) est interprete comme une
     * formule par Excel / LibreOffice a l'ouverture du fichier. On prefixe ces
     * valeurs d'une apostrophe : la cellule reste du texte et ne s'execute pas
     * (l'apostrophe est masquee par le tableur). Mitigation standard OWASP.
     */
    private static function csv_safe( $value ) {
        $value = (string) $value;
        if ( '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Construit une ligne "prete a coller" (cellules separees par des tabulations)
     * dans le fichier Excel de suivi que la secretaire tient a la main. La secretaire
     * garde SES classeurs : elle coche une/des inscription(s) dans la liste admin,
     * copie, puis Ctrl+V sur une ligne vide de son onglet. Excel eclate chaque
     * tabulation dans une colonne.
     *
     * Les colonnes suivent l'ordre EXACT de son gabarit (identique sur tous les
     * ateliers), A a R :
     *   A N°  B NOM  C Prenom  D n°adh  E adresse  F CP  G Ville  H portable
     *   I Mail  J Arrhes  K Recu  L adh.  M tarif  N Recu  O Autre
     *   P Reinscrip.  Q Inscrip.  R infos
     *
     * Les cellules qu'elle remplit a la main ou non collectees en ligne (N°, n°adh,
     * adresse de rue, arrhes, recu, adhesion, autre, reinscription) restent VIDES :
     * un collage sur une ligne neuve n'ecrase rien. Pre-remplies : NOM, Prenom, CP,
     * Ville, portable, Mail, tarif, date d'inscription, message (colonne infos).
     *
     * Statut "liste d'attente" : la section correspondante de son fichier ne va que
     * jusqu'au Mail (pas de paiement tant qu'on n'est pas inscrit), on s'arrete donc
     * a la colonne I pour ne pas semer de valeurs dans des cellules hors tableau.
     *
     * @param int $post_id Inscription.
     * @return string Ligne TSV (sans retour a la ligne final).
     */
    public static function excel_tsv_line( $post_id ) {
        $nom    = (string) get_post_meta( $post_id, 'opac_insc_nom', true );
        $prenom = (string) get_post_meta( $post_id, 'opac_insc_prenom', true );
        $cp     = (string) get_post_meta( $post_id, 'opac_insc_code_postal', true );
        $ville  = (string) get_post_meta( $post_id, 'opac_insc_commune', true );
        $tel    = (string) get_post_meta( $post_id, 'opac_insc_telephone', true );
        $email  = (string) get_post_meta( $post_id, 'opac_insc_email', true );

        // Bloc contact A..I. A (N°), D (n°adh) et E (adresse de rue) : remplis a la
        // main ou non demandes dans le formulaire en ligne -> vides.
        $cells = [
            '',       // A N°
            $nom,     // B NOM
            $prenom,  // C Prenom
            '',       // D n°adh
            '',       // E adresse
            $cp,      // F CP
            $ville,   // G Ville
            $tel,     // H portable
            $email,   // I Mail
        ];

        $status = wp_get_object_terms( $post_id, 'opac_inscription_status', [ 'fields' => 'slugs' ] );
        $status = is_wp_error( $status ) ? [] : (array) $status;

        // Liste d'attente : on s'arrete au Mail (section contact seule du fichier).
        if ( in_array( 'liste-attente', $status, true ) ) {
            return implode( "\t", array_map( [ __CLASS__, 'excel_cell' ], $cells ) );
        }

        // Inscrit(e) : ligne complete. Colonnes paiement (J,K,L,N,O) et Reinscrip.
        // (P) laissees vides ; tarif, date d'inscription et infos pre-remplis.
        $tarif = (int) get_post_meta( $post_id, 'opac_insc_tarif', true );
        $date  = self::excel_date( (string) get_post_meta( $post_id, 'opac_insc_date_submitted', true ) );
        $infos = (string) get_post_meta( $post_id, 'opac_insc_message', true );

        $cells = array_merge( $cells, [
            '',                                // J Arrhes
            '',                                // K Recu
            '',                                // L adh.
            $tarif > 0 ? (string) $tarif : '', // M tarif
            '',                                // N Recu
            '',                                // O Autre
            '',                                // P Reinscrip.
            $date,                             // Q Inscrip.
            $infos,                            // R infos
        ] );

        return implode( "\t", array_map( [ __CLASS__, 'excel_cell' ], $cells ) );
    }

    /**
     * Assainit une cellule destinee a un collage tableur : on retire tabulations et
     * retours a la ligne (qui casseraient le decoupage en colonnes/lignes du
     * collage), puis on neutralise l'injection de formule (cf. csv_safe).
     */
    private static function excel_cell( $value ) {
        $value = str_replace( [ "\t", "\r\n", "\r", "\n" ], ' ', (string) $value );
        return self::csv_safe( $value );
    }

    /**
     * Convertit une date stockee en 'Y-m-d H:i:s' (current_time('mysql'), heure
     * locale du site) en 'd/m/Y' pour la colonne Inscrip. du fichier. On decoupe la
     * partie date au format, sans strtotime, pour eviter tout decalage de fuseau.
     */
    private static function excel_date( $mysql ) {
        if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', trim( $mysql ), $m ) ) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }
        return '';
    }

    /**
     * Args WP_Query construits depuis les filtres de la liste admin (statut,
     * atelier, recherche par nom, mois). Partage par l'export CSV et l'ecran
     * d'envoi d'email pour garantir le meme perimetre que ce qui est affiche :
     * toutes les lignes qui matchent, pas seulement la page courante.
     */
    private static function query_args_from_request() {
        $args = [
            'post_type'      => 'opac_inscription',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];

        $status = isset( $_GET['opac_inscription_status'] ) ? sanitize_key( wp_unslash( $_GET['opac_inscription_status'] ) ) : '';
        if ( '' !== $status ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'opac_inscription_status',
                    'field'    => 'slug',
                    'terms'    => $status,
                ],
            ];
        }

        $atelier_id = isset( $_GET['opac_insc_atelier_id'] ) ? absint( $_GET['opac_insc_atelier_id'] ) : 0;
        if ( $atelier_id ) {
            $args['meta_query'] = [
                [ 'key' => 'opac_insc_atelier_id', 'value' => $atelier_id ],
            ];
        }

        // Le titre contient « [Atelier] Prenom Nom » : la recherche WP (?s)
        // couvre nom / prenom / atelier comme la liste a l'ecran.
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        if ( '' !== $search ) {
            $args['s'] = $search;
        }
        $month = isset( $_GET['m'] ) ? absint( $_GET['m'] ) : 0;
        if ( $month > 0 ) {
            $args['m'] = $month;
        }

        return $args;
    }

    /**
     * Enregistre l'ecran de redaction d'email en sous-menu CACHE de la liste des
     * inscriptions : accessible uniquement via le bouton « Envoyer un email »
     * (qui transporte les filtres + un nonce), jamais affiche dans le menu.
     */
    public static function register_email_page() {
        $parent = 'edit.php?post_type=opac_inscription';
        add_submenu_page(
            $parent,
            __( 'Envoyer un email aux inscrits', 'opac-custom' ),
            __( 'Envoyer un email', 'opac-custom' ),
            'edit_posts',
            'opac-insc-email',
            [ __CLASS__, 'render_email_page' ]
        );
        // Masque l'entree de menu : la page reste accessible par URL.
        remove_submenu_page( $parent, 'opac-insc-email' );
    }

    /**
     * Ecran de redaction : sujet pre-rempli (atelier filtre), corps en editeur
     * visuel, et liste des inscrits du filtre courant en cases a cocher (toutes
     * cochees, decocher pour exclure). L'envoi se base sur les cases cochees.
     */
    public static function render_email_page() {
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            wp_die( esc_html__( 'Permissions insuffisantes.', 'opac-custom' ) );
        }
        $nonce = isset( $_GET['_wpnonce'] ) ? (string) $_GET['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'opac_insc_email_view' ) ) {
            wp_die( esc_html__( 'Lien invalide ou expiré.', 'opac-custom' ) );
        }

        // Brouillon conserve apres un envoi en echec (one-shot) : on restaure le
        // sujet, le message et les destinataires coches, puis on consomme le
        // transient pour ne pas reproposer un brouillon fantome au rechargement.
        $draft_key = 'opac_insc_email_draft_' . get_current_user_id();
        $draft     = get_transient( $draft_key );
        $has_draft = is_array( $draft ) && isset( $draft['subject'], $draft['body'], $draft['ids'] );
        if ( is_array( $draft ) ) {
            delete_transient( $draft_key );
        }

        $atelier_id = isset( $_GET['opac_insc_atelier_id'] ) ? absint( $_GET['opac_insc_atelier_id'] ) : 0;

        // Lien retour vers la liste, filtres conserves.
        $back_args = [ 'post_type' => 'opac_inscription' ];
        if ( $atelier_id ) {
            $back_args['opac_insc_atelier_id'] = $atelier_id;
        }
        $cur_status = isset( $_GET['opac_inscription_status'] ) ? sanitize_key( wp_unslash( $_GET['opac_inscription_status'] ) ) : '';
        if ( '' !== $cur_status ) {
            $back_args['opac_inscription_status'] = $cur_status;
        }
        $back_url = add_query_arg( $back_args, admin_url( 'edit.php' ) );

        // Destinataires affiches : ceux du brouillon restaure (echec precedent),
        // sinon ceux du filtre courant. Tous coches dans les deux cas.
        if ( $has_draft ) {
            $posts = [];
            foreach ( array_map( 'absint', (array) $draft['ids'] ) as $sid ) {
                $pp = get_post( $sid );
                if ( $pp && 'opac_inscription' === $pp->post_type ) {
                    $posts[] = $pp;
                }
            }
        } else {
            // Contexte email : par defaut on ne cible que les inscrits VALIDÉS
            // (jamais refusés / en attente). Un statut explicitement choisi dans
            // le filtre de la liste est respecte (ex : liste d'attente).
            $query_args = self::query_args_from_request();
            if ( '' === $cur_status ) {
                $cur_status              = 'validee';
                $query_args['tax_query'] = [
                    [ 'taxonomy' => 'opac_inscription_status', 'field' => 'slug', 'terms' => 'validee' ],
                ];
            }
            $posts = get_posts( $query_args );
        }

        // Lignes destinataires (uniquement email valide).
        $rows = [];
        foreach ( $posts as $p ) {
            $email = (string) get_post_meta( $p->ID, 'opac_insc_email', true );
            if ( '' === $email || ! is_email( $email ) ) {
                continue;
            }
            $prenom = (string) get_post_meta( $p->ID, 'opac_insc_prenom', true );
            $nom    = (string) get_post_meta( $p->ID, 'opac_insc_nom', true );
            $terms  = wp_get_object_terms( $p->ID, 'opac_inscription_status', [ 'fields' => 'names' ] );
            $statut = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? implode( ', ', $terms ) : '';
            $rows[] = sprintf(
                '<li><label><input type="checkbox" name="opac_insc_recipients[]" value="%d" checked /> %s %s &lt;%s&gt;%s</label></li>',
                (int) $p->ID,
                esc_html( $prenom ),
                esc_html( $nom ),
                esc_html( $email ),
                '' !== $statut ? ' <span style="color:#646970">- ' . esc_html( $statut ) . '</span>' : ''
            );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Envoyer un email aux inscrits', 'opac-custom' ) . '</h1>';

        if ( $has_draft ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'L\'envoi précédent a échoué. Votre message a été conservé ci-dessous : vérifiez puis réessayez.', 'opac-custom' ) . '</p></div>';
        }

        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'Aucun inscrit avec une adresse email valide ne correspond au filtre courant.', 'opac-custom' ) . '</p>';
            printf( '<p><a class="button" href="%s">%s</a></p>', esc_url( $back_url ), esc_html__( 'Retour à la liste', 'opac-custom' ) );
            echo '</div>';
            return;
        }

        // Sujet et message : brouillon restaure si echec precedent, sinon valeurs
        // par defaut (titre de l'atelier filtre ; message vide).
        if ( $has_draft ) {
            $subject_value = (string) $draft['subject'];
            $body_value    = (string) $draft['body'];
        } elseif ( $atelier_id && get_post( $atelier_id ) ) {
            $subject_value = get_the_title( $atelier_id );
            $body_value    = '';
        } else {
            $subject_value = __( 'Information - Association OPAC', 'opac-custom' );
            $body_value    = '';
        }

        echo '<form id="opac-insc-email-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SEND_EMAIL ) . '" />';
        wp_nonce_field( self::ACTION_SEND_EMAIL );

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="opac_insc_subject">' . esc_html__( 'Sujet', 'opac-custom' ) . '</label></th><td>';
        printf(
            '<input type="text" id="opac_insc_subject" name="opac_insc_subject" value="%s" class="large-text" required />',
            esc_attr( $subject_value )
        );
        echo '</td></tr>';
        echo '<tr><th scope="row"><label>' . esc_html__( 'Message', 'opac-custom' ) . '</label></th><td>';
        wp_editor( $body_value, 'opac_insc_body', [
            'textarea_name' => 'opac_insc_body',
            'textarea_rows' => 12,
            'media_buttons' => false,
            'teeny'         => true,
        ] );
        echo '</td></tr>';
        echo '</tbody></table>';

        printf( '<h2>' . esc_html__( 'Destinataires (%d)', 'opac-custom' ) . '</h2>', count( $rows ) );
        if ( ! $has_draft ) {
            $status_term  = get_term_by( 'slug', $cur_status, 'opac_inscription_status' );
            $status_label = ( $status_term && ! is_wp_error( $status_term ) ) ? $status_term->name : $cur_status;
            printf(
                '<p>' . esc_html__( 'Statut ciblé : %s. Décochez une personne pour l\'exclure.', 'opac-custom' ) . '</p>',
                '<strong>' . esc_html( $status_label ) . '</strong>'
            );
        }
        echo '<p class="description">' . esc_html__( 'Les adresses partent en copie cachée : les destinataires ne se voient pas entre eux.', 'opac-custom' ) . '</p>';
        echo '<ul style="max-height:320px;overflow:auto;border:1px solid #dcdcde;padding:10px 14px;margin:0 0 16px;background:#fff;list-style:none">';
        echo implode( '', $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lignes deja echappees ci-dessus
        echo '</ul>';

        submit_button( __( 'Envoyer l\'email', 'opac-custom' ) );
        printf( ' <a class="button" href="%s">%s</a>', esc_url( $back_url ), esc_html__( 'Annuler', 'opac-custom' ) );
        echo '</form>';

        // Garde-fou cote navigateur : feedback instantane si message vide ou aucun
        // destinataire coche (les vraies pannes serveur sont rattrapees par le
        // transient de brouillon cote PHP, cf. email_fail_redirect).
        $js_empty = wp_json_encode( __( 'Le message est vide.', 'opac-custom' ) );
        $js_norec = wp_json_encode( __( 'Aucun destinataire sélectionné.', 'opac-custom' ) );
        echo '<script>(function(){'
            . 'var f=document.getElementById("opac-insc-email-form");if(!f){return;}'
            . 'f.addEventListener("submit",function(e){'
            . 'var b="";if(window.tinymce){var ed=tinymce.get("opac_insc_body");if(ed){b=ed.getContent({format:"text"});}}'
            . 'if(!b){var t=document.getElementById("opac_insc_body");if(t){b=t.value;}}'
            . 'var n=f.querySelectorAll(\'input[name="opac_insc_recipients[]"]:checked\').length;'
            . 'if(!b.replace(/\\s/g,"")){e.preventDefault();window.alert(' . $js_empty . ');return false;}'
            . 'if(!n){e.preventDefault();window.alert(' . $js_norec . ');return false;}'
            . '});})();</script>';
        echo '</div>';
    }

    /**
     * Traite l'envoi : valide nonce + capability, resout les emails des
     * inscriptions cochees (verif type + email valide + dedup par email), puis
     * envoie UN seul message en copie cachee (Bcc), expediteur opac_org_email.
     */
    public static function handle_send_email() {
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            wp_die( esc_html__( 'Permissions insuffisantes.', 'opac-custom' ) );
        }
        check_admin_referer( self::ACTION_SEND_EMAIL );

        $list_url = add_query_arg( [ 'post_type' => 'opac_inscription' ], admin_url( 'edit.php' ) );

        $subject = isset( $_POST['opac_insc_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['opac_insc_subject'] ) ) : '';
        $body    = isset( $_POST['opac_insc_body'] ) ? wp_kses_post( wp_unslash( $_POST['opac_insc_body'] ) ) : '';
        if ( isset( $_POST['opac_insc_recipients'] ) ) {
            $ids = array_map( 'absint', (array) wp_unslash( $_POST['opac_insc_recipients'] ) );
        } else {
            $ids = [];
        }

        // Garde-fous : sujet, corps (texte reel) et au moins un destinataire. En
        // cas d'echec, on conserve le brouillon et on revient a l'ecran de
        // redaction pre-rempli (email_fail_redirect) : aucun message perdu.
        if ( '' === $subject || '' === trim( wp_strip_all_tags( $body ) ) || empty( $ids ) ) {
            self::email_fail_redirect( $subject, $body, $ids );
        }

        // Resolution des emails : on ne fait pas confiance a l'URL, on relit
        // chaque inscription cochee (type + email valide), dedup par email
        // (un parent avec plusieurs enfants = un seul destinataire).
        $emails = [];
        foreach ( $ids as $id ) {
            if ( ! $id ) {
                continue;
            }
            $post = get_post( $id );
            if ( ! $post || 'opac_inscription' !== $post->post_type ) {
                continue;
            }
            $email = (string) get_post_meta( $id, 'opac_insc_email', true );
            if ( $email && is_email( $email ) ) {
                $emails[ strtolower( $email ) ] = $email;
            }
        }
        $emails = array_values( $emails );
        if ( empty( $emails ) ) {
            self::email_fail_redirect( $subject, $body, $ids );
        }

        $from_email = self::recipient();
        if ( class_exists( 'OPAC_Settings' ) ) {
            $from_name = (string) OPAC_Settings::get( 'opac_org_name' );
        } else {
            $from_name = '';
        }
        if ( '' === $from_name ) {
            $from_name = 'Association OPAC';
        }

        $result = self::dispatch_bulk_email( $emails, $subject, wpautop( $body ), $from_email, $from_name );

        // Echec total : on conserve le brouillon et on revient a l'ecran de
        // redaction. Echec partiel : on log mais on confirme les envois reussis
        // (ne pas reproposer un renvoi complet qui doublonnerait les lots partis).
        if ( 0 === $result['sent'] ) {
            error_log( '[OPAC inscription] bulk email wp_mail failed (' . count( $emails ) . ' destinataires)' );
            self::email_fail_redirect( $subject, $body, $ids );
        }
        if ( $result['failed'] > 0 ) {
            error_log( '[OPAC inscription] bulk email partiel : ' . $result['sent'] . ' envoyes, ' . $result['failed'] . ' echecs' );
        }
        wp_safe_redirect( add_query_arg( 'opac_insc_email_sent', (string) $result['sent'], $list_url ) );
        exit;
    }

    /**
     * Envoie le message groupe par lots : un wp_mail par tranche de
     * EMAIL_BCC_BATCH destinataires en Bcc (jamais un unique message a plusieurs
     * centaines de Bcc, cause de rejet SMTP / spam / plafond hebergeur). To :
     * l'association elle-meme a chaque lot ; les inscrits ne se voient pas entre
     * eux. Retourne [ 'sent' => n, 'failed' => n ] pour distinguer succes total,
     * partiel et echec total cote appelant. Logique isolee (aucun exit / redirect)
     * pour etre testable unitairement.
     */
    public static function dispatch_bulk_email( $emails, $subject, $html, $from_email, $from_name ) {
        $base_headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: %s <%s>', $from_name, $from_email ),
            'Reply-To: ' . $from_email,
        ];

        $sent   = 0;
        $failed = 0;
        foreach ( array_chunk( array_values( $emails ), self::EMAIL_BCC_BATCH ) as $chunk ) {
            $headers = array_merge( $base_headers, [ 'Bcc: ' . implode( ', ', $chunk ) ] );
            if ( wp_mail( $from_email, $subject, $html, $headers ) ) {
                $sent += count( $chunk );
            } else {
                $failed += count( $chunk );
            }
        }
        return [ 'sent' => $sent, 'failed' => $failed ];
    }

    /**
     * Echec d'envoi : conserve le brouillon (sujet, message, destinataires
     * coches) dans un transient propre a l'utilisateur (15 min) et renvoie vers
     * l'ecran de redaction pre-rempli, pour ne pas perdre un message deja redige.
     */
    private static function email_fail_redirect( $subject, $body, $ids ) {
        set_transient(
            'opac_insc_email_draft_' . get_current_user_id(),
            [
                'subject' => (string) $subject,
                'body'    => (string) $body,
                'ids'     => array_values( array_map( 'absint', (array) $ids ) ),
            ],
            15 * MINUTE_IN_SECONDS
        );
        wp_safe_redirect( add_query_arg(
            [
                'post_type' => 'opac_inscription',
                'page'      => 'opac-insc-email',
                '_wpnonce'  => wp_create_nonce( 'opac_insc_email_view' ),
            ],
            admin_url( 'edit.php' )
        ) );
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
                $montant = $insc_tarif > 0 ? $insc_tarif : (int) get_post_meta( $cible_id, 'opac_tarif_annuel', true );
                $tarif   = OPAC_Labels::tarif_annuel( $montant );
            } elseif ( get_post_type( $cible_id ) === 'opac_stage' ) {
                $montant = $insc_tarif > 0 ? $insc_tarif : (int) get_post_meta( $cible_id, 'opac_tarif_seance', true );
                $tarif   = OPAC_Labels::tarif_seance( $montant );
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

    /**
     * URL de la page d'inscription (par slug, repli sur l'URL canonique).
     * Public : source unique aussi pour le bouton « S'inscrire »
     * (OPAC_Blocks::render_inscription_button), qui dupliquait cette logique.
     */
    public static function inscription_url() {
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
     * Places restantes sur un creneau / une seance, ou null si le creneau n'a
     * pas de limite (capacite 0, ou id absent donc comptage impossible).
     * Jamais negatif : un depassement rend 0, pas -3.
     *
     * Trois termes, et le deuxieme est le seul non evident :
     *
     *   restant = capacite - deja_inscrits - inscriptions validees en base
     *
     * « deja_inscrits » est le nombre d'inscrits pris HORS du site : les
     * reinscriptions de l'annee precedente, les inscriptions prises au guichet
     * ou par telephone. Sans ce terme, la base part de zero a chaque refonte ou
     * chaque saison et considere pleins d'espace des creneaux qui affichent
     * complet depuis septembre. Consequences observees : aucun badge « Complet »
     * sur la fiche, et surtout la liste d'attente automatique (cf. handle_submit)
     * qui ne se declenche jamais, donc chaque demande arrive en « en attente »
     * sur un atelier sature et doit etre reclassee a la main.
     *
     * Le champ se saisit une fois en debut de saison dans le tableau des
     * creneaux ; les validations faites ensuite depuis l'admin s'ajoutent
     * toutes seules via count_validees(). Laisse vide, il vaut 0 et le calcul
     * est exactement celui d'avant : aucune fiche existante ne change d'etat.
     *
     * @param int   $cible_id Atelier a l'annee ou ephemere.
     * @param array $creneau  Creneau / seance structure (id, capacite, deja_inscrits).
     * @return int|null Places restantes, ou null si pas de limite.
     */
    public static function places_restantes( $cible_id, $creneau ) {
        if ( ! is_array( $creneau ) ) {
            return null;
        }
        $cap = isset( $creneau['capacite'] ) ? (int) $creneau['capacite'] : 0;
        $id  = isset( $creneau['id'] ) ? (string) $creneau['id'] : '';
        if ( $cap <= 0 || '' === $id ) {
            return null;
        }
        $hors_site = isset( $creneau['deja_inscrits'] ) ? (int) $creneau['deja_inscrits'] : 0;
        if ( $hors_site < 0 ) {
            $hors_site = 0;
        }
        $restant = $cap - $hors_site - self::count_validees( $cible_id, $id );
        return $restant > 0 ? $restant : 0;
    }

    /**
     * Vrai si le creneau a atteint sa capacite (capacite 0 = pas de limite).
     * $creneau = tableau structure (id, capacite, deja_inscrits, ...).
     */
    public static function creneau_is_full( $atelier_id, $creneau ) {
        $restant = self::places_restantes( $atelier_id, $creneau );
        return ( null !== $restant && $restant <= 0 );
    }

    /**
     * Retrouve un creneau / une seance structure par son id dans la cible.
     * Check explicite par type (pas de else catch-all) : on ne lit que la meta
     * que la cible possede. Null si introuvable ou type non gere.
     *
     * @return array|null Le creneau structure complet (capacite, deja_inscrits...).
     */
    public static function creneau_row( $cible_id, $creneau_id ) {
        $cible_id   = (int) $cible_id;
        $creneau_id = (string) $creneau_id;
        if ( ! $cible_id || '' === $creneau_id ) {
            return null;
        }
        $type = get_post_type( $cible_id );
        if ( 'opac_atelier' === $type ) {
            $struct = get_post_meta( $cible_id, 'opac_creneaux', true );
        } elseif ( 'opac_stage' === $type ) {
            $struct = get_post_meta( $cible_id, 'opac_stage_seances', true );
        } else {
            return null;
        }
        if ( ! is_array( $struct ) ) {
            return null;
        }
        foreach ( $struct as $c ) {
            if ( is_array( $c ) && isset( $c['id'] ) && (string) $c['id'] === $creneau_id ) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Saison POUR LAQUELLE une inscription est prise, calculee a sa creation.
     *
     * Deux chemins, et le premier est exact :
     *
     * - Ephemere : sa date de debut fait foi. Un stage d'avril 2027 appartient
     *   a la saison 2026-2027, quelle que soit la date de la demande. Aucune
     *   heuristique, aucune correction a prevoir.
     *
     * - Atelier a l'annee : la fiche n'appartient a AUCUNE saison, c'est la
     *   meme qui ressert tous les ans (lui en donner une imposerait de dupliquer
     *   les vingt ateliers chaque annee, avec autant d'URLs neuves et le
     *   referencement reparti de zero). L'information n'existe donc nulle part
     *   ailleurs que dans la demande, et on la deduit de sa date via le pivot
     *   du 1er mai (cf. OPAC_Settings::saison_for_inscription_date).
     *
     * Le resultat est STOCKE sur l'inscription plutot que recalcule a chaque
     * lecture, pour deux raisons : il ne bouge pas si l'equipe modifie les
     * reglages de saison l'annee suivante, et le cas que le pivot classe mal
     * (rejoindre un atelier en juin pour finir la saison en cours) se corrige
     * dans la fiche au lieu d'etre faux en silence.
     *
     * @return string Slug de saison ("2026-2027"), ou '' si indeterminable.
     */
    public static function resolve_saison( $cible_id, $date_ymd ) {
        $cible_id = (int) $cible_id;

        if ( $cible_id && 'opac_stage' === get_post_type( $cible_id ) ) {
            $debut = (string) get_post_meta( $cible_id, 'opac_date_debut', true );
            $s     = OPAC_Settings::saison_for_date( $debut );
            if ( is_array( $s ) ) {
                return $s['slug'];
            }
            // Ephemere sans date exploitable : on retombe sur la date de demande
            // plutot que de laisser la saison vide.
        }

        $s = OPAC_Settings::saison_for_inscription_date( $date_ymd );
        return is_array( $s ) ? $s['slug'] : '';
    }

    /**
     * Saison d'une inscription pour l'affichage et le bilan.
     *
     * Lit la valeur stockee ; a defaut (demandes anterieures a l'ajout du
     * champ) la recalcule a la volee, sans l'ecrire. Le bilan reste donc
     * exploitable sur l'historique sans migration de base.
     */
    public static function saison_of( $insc_id ) {
        $insc_id = (int) $insc_id;
        $stored  = (string) get_post_meta( $insc_id, 'opac_insc_saison', true );
        if ( '' !== $stored ) {
            return $stored;
        }
        return self::resolve_saison(
            (int) get_post_meta( $insc_id, 'opac_insc_atelier_id', true ),
            (string) get_post_meta( $insc_id, 'opac_insc_date_submitted', true )
        );
    }

    /**
     * Libelle du creneau d'une inscription, pour affichage et export.
     *
     * opac_insc_creneau est un INSTANTANE : le libelle tel qu'il se lisait au
     * moment de la demande. Il ne suit donc pas les retouches faites ensuite sur
     * la fiche (horaire corrige, rythme renseigne apres coup), et deux
     * inscriptions sur le MEME creneau peuvent s'exporter avec deux textes
     * differents selon leur anciennete. Comme l'identifiant du creneau est
     * stocke a cote, on recompose le libelle depuis la fiche quand il s'y
     * retrouve : l'export redevient homogene, et les lignes se regroupent.
     *
     * L'instantane reste le repli, et il sert dans deux cas reels : les
     * inscriptions prises sur l'ancien champ texte libre (aucun id), et celles
     * dont le creneau a ete supprime de la fiche depuis. Mieux vaut un libelle
     * date qu'une cellule vide dans un dossier de subvention.
     */
    public static function creneau_display( $insc_id ) {
        $insc_id = (int) $insc_id;
        $stored  = (string) get_post_meta( $insc_id, 'opac_insc_creneau', true );

        $cible_id   = (int) get_post_meta( $insc_id, 'opac_insc_atelier_id', true );
        $creneau_id = (string) get_post_meta( $insc_id, 'opac_insc_creneau_id', true );
        if ( ! $cible_id || '' === $creneau_id ) {
            return $stored;
        }

        $row = self::creneau_row( $cible_id, $creneau_id );
        if ( ! is_array( $row ) ) {
            return $stored;
        }

        $label = ( 'opac_stage' === get_post_type( $cible_id ) )
            ? OPAC_Calendar::seance_label( $row )
            : OPAC_Calendar::creneau_label( $row );

        return ( '' !== $label ) ? $label : $stored;
    }

    /**
     * Capacite d'un creneau/seance retrouve par son id dans la structure de la
     * cible (0 = pas de limite, ou creneau introuvable). Sert a detecter un
     * depassement au moment de la validation manuelle. Check explicite par type
     * (pas de else catch-all) : on ne lit que la meta que la cible possede.
     */
    public static function creneau_capacite( $cible_id, $creneau_id ) {
        $row = self::creneau_row( $cible_id, $creneau_id );
        if ( ! is_array( $row ) ) {
            return 0;
        }
        return isset( $row['capacite'] ) ? (int) $row['capacite'] : 0;
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
