<?php
/**
 * Verification bout-en-bout du verrou de connexion, contre le VRAI WordPress du
 * site Local (pas de stubs, contrairement a test-login-lockout.php).
 *
 * Pourquoi ce harnais existe : il a attrape un defaut que le test unitaire ne
 * pouvait pas voir. check_login_attempts() avait d'abord ete accrochee en
 * priorite 1 pour repondre avant que le coeur ne calcule le hash. Les stubs
 * passaient au vert, mais en reel la connexion se faisait quand meme : avec un
 * identifiant et un mot de passe non vides, wp_authenticate_username_password()
 * ne rend PAS la main sur une WP_Error deja presente, il refait son propre
 * get_user_by() + wp_check_password() et ecrase notre erreur. D'ou la priorite
 * 30, et d'ou ce fichier : la priorite d'un filtre ne se teste qu'en integration.
 *
 * Volontairement hors du glob test-*.php de run-tests.php : il exige une base de
 * donnees demarree, donc il ne doit pas faire echouer la suite hors ligne.
 *
 * Prerequis : site Local demarre. Lancement depuis n'importe ou :
 *   <php de Local> wp-content/plugins/opac-custom/tests/e2e-login-lockout.php
 *
 * Effets de bord : pose puis efface un transient sur une IP de test (203.0.113.77,
 * plage de documentation RFC 5737, jamais celle d'un humain). Aucune ecriture
 * ailleurs, aucun cookie de connexion (wp_authenticate ne connecte pas).
 */
$_SERVER['HTTP_HOST']    = 'opac-plerin.local';
$_SERVER['REQUEST_URI']  = '/';
$_SERVER['REMOTE_ADDR']  = '203.0.113.77'; // IP de test, jamais celle d'un humain
$_SERVER['SERVER_NAME']  = 'opac-plerin.local';

require dirname( __DIR__, 4 ) . '/wp-load.php'; // wp-content/plugins/opac-custom/tests -> racine

// Garde-fou : on refuse de tourner ailleurs que sur le site local.
if ( strpos( get_option( 'siteurl' ), '.local' ) === false ) {
    exit( "ABANDON : ce script ne tourne que sur le site Local.\n" );
}

$n = 0; $fail = 0;
function check( $label, $got, $exp ) {
    global $n, $fail;
    $n++;
    $ok = ( $got === $exp );
    if ( ! $ok ) { $fail++; }
    printf( "%2d  %-4s  %-52s  obtenu=%-22s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export( $got, true ), var_export( $exp, true ) );
}

$login = 'admin';
$pass  = '1234';
$key   = 'opac_login_fail_' . md5( $_SERVER['REMOTE_ADDR'] );
delete_transient( $key );

echo "=== 1. Le plugin est bien charge ===\n";
check( 'OPAC_Security presente',        class_exists( 'OPAC_Security' ),              true );
check( 'seuil applique',                OPAC_Security::LOGIN_MAX_ATTEMPTS,            10 );
check( 'filtre authenticate priorite 30', has_filter( 'authenticate', [ 'OPAC_Security', 'check_login_attempts' ] ), 30 );

echo "\n=== 2. Mots de passe d'application ===\n";
check( 'desactives',                    wp_is_application_passwords_available(),      false );

echo "\n=== 3. Connexion normale (hors blocage) ===\n";
$u = wp_authenticate( $login, $pass );
check( 'bons identifiants -> WP_User',  $u instanceof WP_User,                        true );
if ( is_wp_error( $u ) ) {
    echo "    (note : " . $u->get_error_code() . " - mot de passe local different de '1234' ?)\n";
}

echo "\n=== 4. Blocage actif : LE cas corrige ===\n";
set_transient( $key, OPAC_Security::LOGIN_MAX_ATTEMPTS, OPAC_Security::LOGIN_WINDOW_S );
$u = wp_authenticate( $login, $pass );
check( 'BON mot de passe pendant blocage -> refuse', is_wp_error( $u ),               true );
check( 'code d erreur',                 is_wp_error( $u ) ? $u->get_error_code() : '', 'opac_too_many_attempts' );

$u = wp_authenticate( $login, 'mauvais-mot-de-passe' );
check( 'mauvais mot de passe pendant blocage',       is_wp_error( $u ),               true );

echo "\n=== 5. Retour a la normale apres expiration ===\n";
delete_transient( $key );
$u = wp_authenticate( $login, $pass );
check( 'verrou leve -> connexion possible',          $u instanceof WP_User,           true );

// Nettoyage : wp_authenticate a incremente le compteur sur les echecs ci-dessus.
delete_transient( $key );
check( 'compteur de test efface',                    get_transient( $key ),           false );

echo "\n";
printf( "%d verifications, %d FAIL\n", $n, $fail );
exit( $fail === 0 ? 0 : 1 );
