<?php
/**
 * Harnais unitaire de la resolution de cible publique du formulaire /
 * de la confirmation d'inscription :
 *   - OPAC_Blocks::resolve_public_target() : depuis ?atelier=ID / ?stage=ID,
 *     ne garde qu'UNE cible publiee et du bon type ; ecarte tout id invalide
 *     (brouillon, prive, corbeille, inexistant, mauvais type) et impose
 *     l'exclusivite (atelier prioritaire si les deux sont valides).
 *
 * Methode privee : appelee par ReflectionMethod (comme test-creneaux-ids /
 * test-inscription-ratelimit). get_post_type / get_post_status / absint sont
 * mockes ; on pilote la cible via $_GET. Aucun WordPress, aucune base.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-inscription-target.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

// ---------------------------------------------------------------------------
// Mocks WordPress pilotables par globals.
// ---------------------------------------------------------------------------
$GLOBALS['post_types']  = [];   // id => post_type
$GLOBALS['post_status'] = [];   // id => post_status

function absint($v) { return abs((int) $v); }
function get_post_type($id) { return $GLOBALS['post_types'][$id] ?? false; }
function get_post_status($id) { return $GLOBALS['post_status'][$id] ?? false; }
function __($s, $d = null) { return $s; }

require __DIR__ . '/../includes/class-opac-blocks.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-52s  obtenu=%-10s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, json_encode($got), json_encode($exp));
}

// Jeu de contenus : id => [type, statut].
$GLOBALS['post_types']  = [
    10 => 'opac_atelier', 11 => 'opac_atelier', 12 => 'opac_atelier', 13 => 'opac_atelier',
    20 => 'opac_stage',   21 => 'opac_stage',
    30 => 'opac_event',
];
$GLOBALS['post_status'] = [
    10 => 'publish', 11 => 'draft', 12 => 'private', 13 => 'trash',
    20 => 'publish', 21 => 'draft',
    30 => 'publish',
];

// Acces a la methode privee par reflexion.
$ref = new ReflectionMethod('OPAC_Blocks', 'resolve_public_target');
$ref->setAccessible(true);
function resolve($get) {
    global $ref;
    $_GET = $get;
    return $ref->invoke(null);
}

// ---------------------------------------------------------------------------
echo "=== A. Cas nominaux ===\n";
check("aucun parametre -> [0,0]",              resolve([]),                       [0, 0]);
check("atelier publie valide -> [10,0]",       resolve(['atelier' => '10']),      [10, 0]);
check("stage publie valide -> [0,20]",         resolve(['stage' => '20']),        [0, 20]);

echo "\n=== B. Cibles invalides ecartees (-> [0,0]) ===\n";
check("atelier brouillon",                     resolve(['atelier' => '11']),      [0, 0]);
check("atelier prive",                         resolve(['atelier' => '12']),      [0, 0]);
check("atelier corbeille",                     resolve(['atelier' => '13']),      [0, 0]);
check("id inexistant",                         resolve(['atelier' => '999']),     [0, 0]);
check("mauvais type (event en ?atelier)",      resolve(['atelier' => '30']),      [0, 0]);
check("mauvais type (atelier en ?stage)",      resolve(['stage' => '10']),        [0, 0]);
check("stage brouillon",                       resolve(['stage' => '21']),        [0, 0]);
check("valeur non scalaire ?atelier[]=10",     resolve(['atelier' => ['10']]),    [0, 0]);
check("valeur non scalaire ?stage[]=20",       resolve(['stage' => ['20']]),      [0, 0]);

echo "\n=== C. Exclusivite : au plus une cible, atelier prioritaire ===\n";
check("atelier invalide + stage valide -> stage", resolve(['atelier' => '11', 'stage' => '20']), [0, 20]);
check("atelier valide + stage valide -> atelier", resolve(['atelier' => '10', 'stage' => '20']), [10, 0]);
check("deux valides, ordre inverse dans l'URL -> atelier", resolve(['stage' => '20', 'atelier' => '10']), [10, 0]);

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
