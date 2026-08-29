<?php
/**
 * Harnais unitaire de l'unicite des identifiants de creneaux / seances.
 *
 * Exerce le VRAI code de sauvegarde (OPAC_Admin::save_atelier_creneaux et
 * save_stage_seances) via des mocks WordPress (nonce, capability, $_POST,
 * update_post_meta capture). Verifie la correction de robustesse : deux lignes
 * nouvelles (ou deux lignes portant le meme id soumis) ne peuvent JAMAIS
 * partager un identifiant, sinon leur comptage de places se melangerait.
 *
 * Un second volet stresse directement next_unique_id (via Reflection) : 2000
 * generations doivent donner 2000 ids distincts.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-creneaux-ids.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

// --- Mocks WordPress -------------------------------------------------------
$GLOBALS['saved']   = [];   // key => value (dernier update_post_meta)
$GLOBALS['deleted'] = [];   // liste des cles supprimees

function __($s, $d = null) { return $s; }
function wp_verify_nonce($nonce, $action) { return 1; }
function wp_unslash($v) { return $v; }
function current_user_can($cap, $id = null) { return true; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($s) { return is_string($s) ? trim($s) : ''; }
// Reproduit le comportement reel de sanitize_key : minuscules + [a-z0-9_-]
// uniquement (donc le point de uniqid(..., true) "c66f.1234" est retire).
function sanitize_key($k) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $k)); }
function update_post_meta($id, $key, $val) { $GLOBALS['saved'][$key] = $val; return true; }
function delete_post_meta($id, $key) { $GLOBALS['deleted'][] = $key; return true; }

require __DIR__ . '/../includes/class-opac-calendar.php';
require __DIR__ . '/../includes/class-opac-admin.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-54s  obtenu=%-8s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}
function check_true($label, $cond) { check($label, (bool) $cond, true); }

/** Lance save_atelier_creneaux avec un jeu de lignes et retourne le tableau nettoye. */
function save_creneaux(array $rows) {
    $GLOBALS['saved'] = []; $GLOBALS['deleted'] = [];
    $_POST = [ 'opac_atelier_creneaux_nonce' => 'x', 'opac_creneaux' => $rows ];
    OPAC_Admin::save_atelier_creneaux(1, null);
    return $GLOBALS['saved']['opac_creneaux'] ?? null;
}
function save_seances(array $rows) {
    $GLOBALS['saved'] = []; $GLOBALS['deleted'] = [];
    $_POST = [ 'opac_stage_seances_nonce' => 'x', 'opac_stage_seances' => $rows ];
    OPAC_Admin::save_stage_seances(1, null);
    return $GLOBALS['saved']['opac_stage_seances'] ?? null;
}
function ids_of($clean) { return array_map(fn($r) => $r['id'], $clean ?? []); }

// ---------------------------------------------------------------------------
echo "=== A. Creneaux d'atelier : unicite des ids ===\n";

// 5 nouvelles lignes ajoutees d'un coup (id vide) : le cas de collision uniqid.
$clean = save_creneaux([
    [ 'jour' => 'lundi',    'debut' => '14:00', 'fin' => '16:00' ],
    [ 'jour' => 'mardi',    'debut' => '14:00', 'fin' => '16:00' ],
    [ 'jour' => 'mercredi', 'debut' => '10:00', 'fin' => '12:00' ],
    [ 'jour' => 'jeudi',    'debut' => '10:00', 'fin' => '12:00' ],
    [ 'jour' => 'vendredi', 'debut' => '17:00', 'fin' => '18:00' ],
]);
$ids = ids_of($clean);
check("5 lignes -> 5 creneaux",              count($ids), 5);
check("5 lignes -> 5 ids DISTINCTS",         count(array_unique($ids)), 5);
check_true("tous les ids commencent par 'c'", count(array_filter($ids, fn($i) => str_starts_with($i, 'c'))) === 5);
check_true("aucun id vide",                   !in_array('', $ids, true));

// Deux lignes portant le MEME id soumis : la 2e doit etre re-generee.
$clean = save_creneaux([
    [ 'id' => 'c-dup', 'jour' => 'lundi', 'debut' => '14:00', 'fin' => '16:00' ],
    [ 'id' => 'c-dup', 'jour' => 'mardi', 'debut' => '14:00', 'fin' => '16:00' ],
]);
$ids = ids_of($clean);
check("2 ids dupliques -> 2 ids distincts",  count(array_unique($ids)), 2);
check("1er id preserve = 'c-dup'",           $ids[0], 'c-dup');
check_true("2e id different de 'c-dup'",      $ids[1] !== 'c-dup');

// Ids existants distincts (edition) : preserves tels quels.
$clean = save_creneaux([
    [ 'id' => 'c-aaa', 'jour' => 'lundi', 'debut' => '14:00', 'fin' => '16:00' ],
    [ 'id' => 'c-bbb', 'jour' => 'mardi', 'debut' => '14:00', 'fin' => '16:00' ],
]);
check("ids existants preserves",             ids_of($clean), ['c-aaa', 'c-bbb']);

// Lignes vides (ni debut ni fin) ignorees.
$clean = save_creneaux([
    [ 'jour' => 'lundi', 'debut' => '14:00', 'fin' => '16:00' ],
    [ 'jour' => 'mardi', 'debut' => '',      'fin' => '' ],
]);
check("ligne sans horaire ignoree",          count($clean), 1);

// Aucune ligne exploitable -> suppression de la meta.
$clean = save_creneaux([ [ 'jour' => 'lundi', 'debut' => '', 'fin' => '' ] ]);
check_true("tout vide -> delete_post_meta",  in_array('opac_creneaux', $GLOBALS['deleted'], true));

// ---------------------------------------------------------------------------
echo "\n=== B. Seances d'ephemere : unicite + tri + dates ===\n";

$clean = save_seances([
    [ 'date' => '2026-04-14', 'debut' => '10:00', 'fin' => '12:00' ],
    [ 'date' => '2026-04-12', 'debut' => '14:00', 'fin' => '16:00' ],
    [ 'date' => 'pas-une-date', 'debut' => '09:00', 'fin' => '10:00' ], // ignoree
    [ 'date' => '2026-02-31', 'debut' => '09:00', 'fin' => '10:00' ],   // 31 fev inexistant -> ignoree
    [ 'date' => '2026-04-13', 'debut' => '09:00', 'fin' => '10:00' ],
]);
$ids   = ids_of($clean);
$dates = array_map(fn($r) => $r['date'], $clean ?? []);
check("3 dates valides retenues (2 rejetees)", count($clean), 3);
check("ids de seance DISTINCTS",             count(array_unique($ids)), 3);
check_true("tous les ids commencent par 's'", count(array_filter($ids, fn($i) => str_starts_with($i, 's'))) === 3);
check("tri chronologique",                   $dates, ['2026-04-12', '2026-04-13', '2026-04-14']);

// ---------------------------------------------------------------------------
echo "\n=== C. Stress next_unique_id (Reflection) : 2000 ids distincts ===\n";

$ref = new ReflectionMethod('OPAC_Admin', 'next_unique_id');
$ref->setAccessible(true);
$used = [];
for ($i = 0; $i < 2000; $i++) {
    $id = $ref->invoke(null, 'c', $used);
    $used[$id] = true;
}
check("2000 generations -> 2000 ids uniques", count($used), 2000);

// Verifie que le do/while ferme bien une collision pre-existante : on passe un
// $used deja peuple, l'id rendu ne doit pas s'y trouver.
$seed = $used;
$next = $ref->invoke(null, 'c', $seed);
check_true("id rendu absent d'un \$used pre-rempli", !isset($seed[$next]));

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
