<?php
/**
 * Harnais unitaire du rate-limit par IP des inscriptions :
 *   - OPAC_Inscriptions::ip_over_limit() : l'IP a-t-elle atteint le plafond ?
 *   - OPAC_Inscriptions::ip_bump()       : incremente apres une creation reelle.
 *
 * Teste le VRAI code en isolation : get_transient / set_transient / delete_transient
 * sont remplaces par des mocks a base de globals. On memorise aussi le dernier TTL
 * passe a set_transient pour verifier la fenetre glissante.
 *
 * Ce qui N'EST PAS teste ici (et pourquoi) : les proprietes de flot de
 * handle_submit() - "aucun increment sur cible/creneau invalide ou insert rate",
 * "purge de l'anti-doublon sur chaque echec aval". handle_submit() appelle exit()
 * sur chaque sortie : impossible a derouler dans un seul process de test. Ces
 * proprietes sont garanties par la structure du code (ip_bump est le seul site
 * d'increment, place apres le succes de l'insert ; delete_transient est pose sur
 * chaque chemin d'echec), verifiable par lecture, pas par ce harnais unitaire.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-inscription-ratelimit.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');
define('MINUTE_IN_SECONDS', 60);

// ---------------------------------------------------------------------------
// Mocks WordPress : store de transients + trace du dernier TTL par cle.
// ---------------------------------------------------------------------------
$GLOBALS['transients'] = [];   // cle => valeur
$GLOBALS['ttl']        = [];   // cle => dernier TTL passe a set_transient

function get_transient($k) {
    return array_key_exists($k, $GLOBALS['transients']) ? $GLOBALS['transients'][$k] : false;
}
function set_transient($k, $v, $ttl = 0) {
    $GLOBALS['transients'][$k] = $v;
    $GLOBALS['ttl'][$k]        = $ttl;
    return true;
}
function delete_transient($k) {
    unset($GLOBALS['transients'][$k]);
    return true;
}
function __($s, $d = null) { return $s; }

require __DIR__ . '/../includes/class-opac-inscriptions.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-54s  obtenu=%-6s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}

$MAX    = OPAC_Inscriptions::RL_IP_MAX;
$WINDOW = OPAC_Inscriptions::RL_IP_WINDOW_S;
$ip     = '203.0.113.7';
$key    = 'opac_insc_ip_' . md5($ip);

// ---------------------------------------------------------------------------
echo "=== A. Compteur absent -> demandes 1..MAX acceptees, MAX+1 refusee ===\n";

check("compteur absent -> pas bloque", OPAC_Inscriptions::ip_over_limit($ip), false);

// Simule MAX inscriptions creees : chacune est acceptee AVANT son bump.
for ($i = 1; $i <= $MAX; $i++) {
    check("demande $i sur $MAX : acceptee avant creation", OPAC_Inscriptions::ip_over_limit($ip), false);
    OPAC_Inscriptions::ip_bump($ip);
}

check("apres $MAX creations : compteur = MAX", (int) get_transient($key), $MAX);
check("demande MAX+1 : REFUSEE",               OPAC_Inscriptions::ip_over_limit($ip), true);

// ---------------------------------------------------------------------------
echo "\n=== B. IP inconnue : jamais bloquee, bump = no-op ===\n";

check("IP vide jamais au-dessus du plafond", OPAC_Inscriptions::ip_over_limit(''), false);
OPAC_Inscriptions::ip_bump('');
check("bump('') ne cree aucun transient",    get_transient('opac_insc_ip_' . md5('')), false);
check("bump('') n'a pas touche le store",    array_key_exists('opac_insc_ip_' . md5(''), $GLOBALS['transients']), false);

// ---------------------------------------------------------------------------
echo "\n=== C. Fenetre glissante : chaque bump repose le TTL a RL_IP_WINDOW_S ===\n";

check("TTL memorise = RL_IP_WINDOW_S", $GLOBALS['ttl'][$key], $WINDOW);

// Un bump de plus doit reposer le meme TTL (fenetre qui repart de la derniere demande).
$GLOBALS['ttl'][$key] = -1; // brouille la trace pour verifier qu'elle est bien reecrite
OPAC_Inscriptions::ip_bump($ip);
check("bump suivant : TTL repose a RL_IP_WINDOW_S", $GLOBALS['ttl'][$key], $WINDOW);
check("compteur incremente (MAX+1)",                (int) get_transient($key), $MAX + 1);

// ---------------------------------------------------------------------------
echo "\n=== D. Isolation par IP : une autre IP n'est pas affectee ===\n";

$ip2 = '198.51.100.9';
check("autre IP : pas bloquee malgre le quota de la 1ere", OPAC_Inscriptions::ip_over_limit($ip2), false);
OPAC_Inscriptions::ip_bump($ip2);
check("autre IP : compteur independant = 1", (int) get_transient('opac_insc_ip_' . md5($ip2)), 1);

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
