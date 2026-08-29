<?php
/**
 * Harnais unitaire de OPAC_Security::get_client_ip().
 *
 * Point sensible : cette IP alimente les rate-limits (login anti-brute-force,
 * anti-doublon d'inscription). Regle de securite : par defaut on prend
 * REMOTE_ADDR (vraie IP en hebergement direct) et on IGNORE X-Forwarded-For,
 * car ce header est falsifiable tant qu'aucun proxy de confiance ne le pose ;
 * l'ecouter sans proxy devant permettrait de contourner les rate-limits en
 * variant l'IP. On ne lit X-Forwarded-For QUE si la constante OPAC_TRUST_PROXY
 * est definie (opt-in explicite, a activer seulement derriere un vrai proxy).
 *
 * Comme une constante ne peut pas etre "de-definie", le test verifie d'abord
 * tout le comportement SANS la constante (anti-spoof), puis la definit et
 * verifie le comportement proxy.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-client-ip.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

function __($s, $d = null) { return $s; }

require __DIR__ . '/../includes/class-opac-security.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-56s  obtenu=%-17s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}
function reset_server() { unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']); }

echo "=== A. Sans OPAC_TRUST_PROXY (defaut : anti-spoof) ===\n";

reset_server();
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
check("REMOTE_ADDR seul",                       OPAC_Security::get_client_ip(), '203.0.113.7');

// X-Forwarded-For present mais IGNORE (falsifiable) : on garde REMOTE_ADDR.
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
check("XFF falsifie ignore -> REMOTE_ADDR",     OPAC_Security::get_client_ip(), '203.0.113.7');

reset_server();
check("aucune IP -> chaine vide",               OPAC_Security::get_client_ip(), '');

reset_server();
$_SERVER['HTTP_X_FORWARDED_FOR'] = '9.9.9.9';
check("XFF seul sans constante -> vide",        OPAC_Security::get_client_ip(), '');

echo "\n=== B. Avec OPAC_TRUST_PROXY (derriere proxy de confiance) ===\n";

define('OPAC_TRUST_PROXY', true);

reset_server();
$_SERVER['REMOTE_ADDR']          = '10.0.0.1';         // IP du proxy
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';      // vraie IP client
check("XFF pris comme IP client",               OPAC_Security::get_client_ip(), '203.0.113.7');

// Chaine XFF (client, proxy1, proxy2) : premiere entree = client d'origine.
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 70.41.3.18, 10.0.0.1';
check("XFF multi-hop -> 1ere entree",           OPAC_Security::get_client_ip(), '203.0.113.7');

// Espaces autour de l'entree : trim.
$_SERVER['HTTP_X_FORWARDED_FOR'] = '  203.0.113.7 , 10.0.0.1';
check("XFF avec espaces -> trim",               OPAC_Security::get_client_ip(), '203.0.113.7');

// XFF vide -> repli sur REMOTE_ADDR.
$_SERVER['HTTP_X_FORWARDED_FOR'] = '';
check("XFF vide -> repli REMOTE_ADDR",          OPAC_Security::get_client_ip(), '10.0.0.1');

// XFF a une seule virgule (entrees vides) -> repli REMOTE_ADDR.
$_SERVER['HTTP_X_FORWARDED_FOR'] = ' , ';
check("XFF entrees vides -> repli REMOTE_ADDR", OPAC_Security::get_client_ip(), '10.0.0.1');

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
