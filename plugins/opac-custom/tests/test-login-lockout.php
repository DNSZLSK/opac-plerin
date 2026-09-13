<?php
/**
 * Harnais unitaire de OPAC_Security::check_login_attempts().
 *
 * Ce que ce test verrouille, et pourquoi il existe : la version precedente
 * n'entrait dans le test de quota que si $user etait deja une WP_Error. Elle
 * rejetait donc les mauvais essais (qui l'auraient ete de toute facon) mais
 * laissait passer le BON mot de passe des que l'attaquant tombait dessus, y
 * compris pendant le blocage. Autrement dit, elle ne limitait pas le nombre de
 * mots de passe testables, soit exactement ce qu'un anti-brute-force doit
 * faire. Le cas 6 ci-dessous est la non-regression de ce defaut : il echoue sur
 * l'ancien code et passe sur le nouveau.
 *
 * Ce que ce harnais ne peut PAS couvrir : la priorite du filtre. Avec des stubs,
 * check_login_attempts() est appelee en direct, donc elle passe meme accrochee
 * au mauvais endroit. Or elle doit rester en priorite 30, APRES les handlers du
 * coeur, sinon wp_authenticate_username_password() ecrase notre WP_Error et la
 * connexion passe malgre le blocage (piege constate en conditions reelles). Cet
 * aspect-la est verifie par tests/e2e-login-lockout.php, contre le vrai
 * WordPress.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-login-lockout.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

function __($s, $d = null) { return $s; }

/* --- Stubs WordPress -------------------------------------------------- */

// Store transient en memoire. On ignore volontairement l'expiration : la
// fenetre temporelle est une constante WP (set_transient), pas notre logique.
$GLOBALS['transients'] = [];
function get_transient($key)             { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $val, $exp) { $GLOBALS['transients'][$key] = $val; return true; }
function delete_transient($key)          { unset($GLOBALS['transients'][$key]); return true; }

class WP_Error {
    public $code;
    public $message;
    public function __construct($code = '', $message = '') {
        $this->code    = $code;
        $this->message = $message;
    }
    public function get_error_code() { return $this->code; }
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }

// WP_User minimal : seule son identite d'objet compte pour ces assertions.
class WP_User {
    public $ID;
    public function __construct($id = 1) { $this->ID = $id; }
}

require __DIR__ . '/../includes/class-opac-security.php';

/* --- Helpers ---------------------------------------------------------- */

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-58s  obtenu=%-15s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}

// Etat de depart : une IP donnee, un compteur pose a $attempts.
function seed($ip, $attempts) {
    $GLOBALS['transients'] = [];
    unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    if ($ip !== null) {
        $_SERVER['REMOTE_ADDR'] = $ip;
        if ($attempts > 0) {
            $GLOBALS['transients']['opac_login_fail_' . md5($ip)] = $attempts;
        }
    }
}

// Ce que retourne le filtre, exprime en un mot pour des assertions lisibles.
function outcome($result) {
    if ($result instanceof WP_Error) {
        return $result->get_error_code() === 'opac_too_many_attempts' ? 'bloque' : 'erreur-coeur';
    }
    if ($result instanceof WP_User) {
        return 'connecte';
    }
    return 'inchange';
}

$max   = OPAC_Security::LOGIN_MAX_ATTEMPTS;
$ip    = '203.0.113.7';
$good  = new WP_User(1);                                   // identifiants valides
$bad   = new WP_Error('incorrect_password', 'Mot de passe incorrect.'); // refus du coeur

printf("Seuil teste : LOGIN_MAX_ATTEMPTS = %d, fenetre = %d s\n\n", $max, OPAC_Security::LOGIN_WINDOW_S);

echo "=== A. Sous le quota : le filtre est transparent ===\n";

seed($ip, 0);
check("aucune tentative, identifiants valides",      outcome(OPAC_Security::check_login_attempts($good)), 'connecte');

seed($ip, 0);
check("aucune tentative, mauvais mot de passe",      outcome(OPAC_Security::check_login_attempts($bad)),  'erreur-coeur');

seed($ip, $max - 1);
check("derniere tentative autorisee, valides",       outcome(OPAC_Security::check_login_attempts($good)), 'connecte');

seed($ip, $max - 1);
check("derniere tentative autorisee, mauvais",       outcome(OPAC_Security::check_login_attempts($bad)),  'erreur-coeur');

// authenticate() est appele avec null tant qu'aucun handler n'a resolu de user
// (ex. mot de passe vide rejete plus haut) : on ne doit rien inventer.
seed($ip, 0);
check("null en entree sous le quota -> inchange",    outcome(OPAC_Security::check_login_attempts(null)),  'inchange');

echo "\n=== B. Au-dela du quota : refus inconditionnel ===\n";

// LE cas qui echoue sur l'ancien code : le bon mot de passe passait le verrou.
seed($ip, $max);
check("quota atteint, identifiants VALIDES -> bloque", outcome(OPAC_Security::check_login_attempts($good)), 'bloque');

seed($ip, $max);
check("quota atteint, mauvais mot de passe",           outcome(OPAC_Security::check_login_attempts($bad)),  'bloque');

seed($ip, $max + 50);
check("quota tres depasse, identifiants valides",      outcome(OPAC_Security::check_login_attempts($good)), 'bloque');

seed($ip, $max);
check("quota atteint, null en entree",                 outcome(OPAC_Security::check_login_attempts(null)),  'bloque');

echo "\n=== C. Cloisonnement et cas limites ===\n";

// Le compteur est par IP : le blocage d'un attaquant ne doit pas fermer le site
// a tout le monde.
seed($ip, $max);
$_SERVER['REMOTE_ADDR'] = '198.51.100.4';
check("autre IP non concernee par le blocage",       outcome(OPAC_Security::check_login_attempts($good)), 'connecte');

// Sans IP resolvable, on ne peut ni compter ni bloquer : comportement du coeur
// preserve, jamais de blocage aveugle qui verrouillerait tout le monde.
seed(null, 0);
check("aucune IP, identifiants valides",             outcome(OPAC_Security::check_login_attempts($good)), 'connecte');

seed(null, 0);
check("aucune IP, mauvais mot de passe",             outcome(OPAC_Security::check_login_attempts($bad)),  'erreur-coeur');

echo "\n=== D. Compteur : increment et remise a zero ===\n";

seed($ip, 0);
$key = 'opac_login_fail_' . md5($ip);
OPAC_Security::increment_failed_login();
OPAC_Security::increment_failed_login();
check("2 echecs -> compteur a 2",                    (int) get_transient($key), 2);

// Une connexion reussie doit liberer l'IP, sinon un utilisateur legitime reste
// penalise par ses propres fautes de frappe.
OPAC_Security::reset_login_attempts();
check("connexion reussie -> compteur efface",        get_transient($key), false);

// Boucle complete : on sature le compteur comme le ferait un bot, puis on
// verifie que le verrou a bien claque.
seed($ip, 0);
for ($i = 0; $i < $max; $i++) {
    OPAC_Security::increment_failed_login();
}
check("apres {$max} echecs consecutifs -> bloque",   outcome(OPAC_Security::check_login_attempts($good)), 'bloque');

echo "\n";
printf("%d tests, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
