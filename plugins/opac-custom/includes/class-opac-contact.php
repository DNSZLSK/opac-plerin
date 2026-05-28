<?php
/**
 * OPAC Custom - Form de contact custom
 *
 * Handler du formulaire de la page Contact. Pourquoi pas Contact Form 7 ?
 * - Philosophie projet : minimum de plugins tiers
 * - 1 seul formulaire = pas besoin du framework CF7
 * - Coherence avec le futur form inscriptions M7 (meme pattern)
 *
 * Securite :
 * - Nonce WP (anti CSRF)
 * - Honeypot : champ off-screen "opac_hp_website" qui doit rester vide
 *   (bots remplissent tous les champs visibles, le honeypot piege ceux-la)
 * - Sanitize chaque champ (sanitize_text_field, sanitize_email, sanitize_textarea_field)
 *
 * Pas de rate-limit cote app (a faire par Cloudflare/.htaccess en M9 securite).
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Contact {

    const ACTION       = 'opac_contact';
    const PAGE_SLUG    = 'contact';
    const RATE_LIMIT_S = 60;

    private static function recipient() {
        return class_exists( 'OPAC_Settings' )
            ? (string) OPAC_Settings::get( 'opac_org_email' )
            : 'contact@opacplerin.fr';
    }

    public static function register() {
        add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_submit' ] );
        add_action( 'admin_post_nopriv_' . self::ACTION, [ __CLASS__, 'handle_submit' ] );
    }

    public static function handle_submit() {
        $back = self::contact_url();

        // Honeypot : si rempli, on simule un succes pour ne pas alerter le bot.
        if ( ! empty( $_POST['opac_hp_website'] ) ) {
            wp_safe_redirect( add_query_arg( 'envoye', '1', $back ) );
            exit;
        }

        // Nonce CSRF.
        if ( ! isset( $_POST['opac_contact_nonce'] )
            || ! wp_verify_nonce( wp_unslash( $_POST['opac_contact_nonce'] ), 'opac_contact_submit' ) ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'nonce', $back ) );
            exit;
        }

        $nom       = isset( $_POST['opac_nom'] )       ? sanitize_text_field( wp_unslash( $_POST['opac_nom'] ) )       : '';
        $prenom    = isset( $_POST['opac_prenom'] )    ? sanitize_text_field( wp_unslash( $_POST['opac_prenom'] ) )    : '';
        $email     = isset( $_POST['opac_email'] )     ? sanitize_email( wp_unslash( $_POST['opac_email'] ) )          : '';
        $telephone = isset( $_POST['opac_telephone'] ) ? sanitize_text_field( wp_unslash( $_POST['opac_telephone'] ) ) : '';
        $sujet     = isset( $_POST['opac_sujet'] )     ? sanitize_key( wp_unslash( $_POST['opac_sujet'] ) )             : '';
        $message   = isset( $_POST['opac_message'] )   ? sanitize_textarea_field( wp_unslash( $_POST['opac_message'] ) ) : '';

        if ( ! $nom || ! $prenom || ! $email || ! $message ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'champs', $back ) );
            exit;
        }

        if ( ! is_email( $email ) ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'email', $back ) );
            exit;
        }

        // Rate-limit transient (anti double-soumission), aligne sur l'inscription.
        $ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $rl_key = 'opac_contact_rl_' . md5( $ip . '|' . strtolower( $email ) );
        if ( get_transient( $rl_key ) ) {
            wp_safe_redirect( add_query_arg( 'erreur', 'doublon', $back ) );
            exit;
        }
        set_transient( $rl_key, 1, self::RATE_LIMIT_S );

        // Resolution label sujet via le panel admin (memes sujets que le form).
        $subjects_labels = class_exists( 'OPAC_Settings' )
            ? OPAC_Settings::contact_subjects()
            : [ 'renseignement' => 'Renseignement général', 'autre' => 'Autre' ];
        $sujet_label = $subjects_labels[ $sujet ] ?? reset( $subjects_labels );

        $subject_mail = sprintf( '[OPAC contact] %s - %s %s', $sujet_label, $prenom, $nom );

        $body  = "Message envoye depuis le formulaire de contact d'opacplerin.fr\n\n";
        $body .= "Nom : {$nom}\n";
        $body .= "Prenom : {$prenom}\n";
        $body .= "Email : {$email}\n";
        $body .= "Telephone : {$telephone}\n";
        $body .= "Sujet : {$sujet_label}\n\n";
        $body .= "Message :\n{$message}\n";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . sprintf( '%s %s <%s>', $prenom, $nom, $email ),
        ];

        $sent = wp_mail( self::recipient(), $subject_mail, $body, $headers );

        if ( ! $sent ) {
            error_log( '[OPAC contact] wp_mail failed for ' . $email );
            wp_safe_redirect( add_query_arg( 'erreur', 'envoi', $back ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'envoye', '1', $back ) );
        exit;
    }

    private static function contact_url() {
        $page = get_page_by_path( self::PAGE_SLUG );
        if ( $page ) {
            return get_permalink( $page );
        }
        return home_url( '/' . self::PAGE_SLUG . '/' );
    }
}
