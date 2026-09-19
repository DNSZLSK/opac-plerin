<?php
/**
 * Harnais unitaire de la logique de saison des ateliers éphémères.
 *
 * Teste le VRAI code (OPAC_Settings::saison_*) en isolation totale : stubs
 * WordPress minimalistes, aucune base de données, aucun site lancé. Logique
 * pure, indépendante de WordPress.
 *
 * Lancement (PHP CLI de Local, la version peut changer) :
 *   php wp-content/plugins/opac-custom/tests/test-saison.php
 *
 * Couvre : classement par saison (bascule réglable au 1er septembre), année
 * bissextile, et bornage des valeurs de configuration. Sortie : liste des cas
 * (OK / FAIL) et code de sortie 0 si tout passe.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
date_default_timezone_set('UTC'); // calcul ancré à midi, insensible au fuseau

define('ABSPATH', '/');
define('DAY_IN_SECONDS', 86400);

$GLOBALS['opts'] = [];
function get_option($k, $d = null) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function __($s, $d = null) { return $s; }
function _e($s, $d = null) { echo $s; }
function _x($s, $c = null, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_attr__($s, $d = null) { return $s; }
function esc_html($s) { return $s; }
function esc_attr($s) { return $s; }
function esc_url($s) { return $s; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($s) { return $s; }
function sanitize_textarea_field($s) { return $s; }
function home_url($p = '') { return 'http://example.test' . $p; }
function get_bloginfo($k = '') { return ''; }
function wp_timezone_string() { return 'Europe/Paris'; }
// Date du jour pilotable : current_saison() s'en sert, et donc la fenetre de
// saisons proposees dans les menus deroulants (section E).
$GLOBALS['now'] = '2026-09-19';
function current_time($format = 'Y-m-d') { return $GLOBALS['now']; }

require __DIR__ . '/../includes/class-opac-settings.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-48s  obtenu=%-13s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}
function set_boundary($day, $month) {
    $GLOBALS['opts']['opac_saison_start_day']   = $day;
    $GLOBALS['opts']['opac_saison_start_month'] = $month;
}

echo "=== A. Classement par date, bascule au 1er septembre ===\n";
set_boundary(1, 9);
check("2026-09-01 (jour de bascule, inclus)", OPAC_Settings::saison_for_date('2026-09-01')['label'], '2026 / 2027');
check("2026-08-31 (veille de bascule)",       OPAC_Settings::saison_for_date('2026-08-31')['label'], '2025 / 2026');
check("2026-09-02 (lendemain)",               OPAC_Settings::saison_for_date('2026-09-02')['label'], '2026 / 2027');
check("2026-08-30 (avant-veille)",            OPAC_Settings::saison_for_date('2026-08-30')['label'], '2025 / 2026');
check("2026-01-01 (debut annee civile)",      OPAC_Settings::saison_for_date('2026-01-01')['label'], '2025 / 2026');
check("2026-12-31 (fin annee civile)",        OPAC_Settings::saison_for_date('2026-12-31')['label'], '2026 / 2027');
check("2026-07-15 (ete, avant bascule)",      OPAC_Settings::saison_for_date('2026-07-15')['label'], '2025 / 2026');
check("2026-10-10 (automne, apres bascule)",  OPAC_Settings::saison_for_date('2026-10-10')['label'], '2026 / 2027');
check("2027-08-31",                           OPAC_Settings::saison_for_date('2027-08-31')['label'], '2026 / 2027');
check("2027-09-01",                           OPAC_Settings::saison_for_date('2027-09-01')['label'], '2027 / 2028');
check("2025-09-01",                           OPAC_Settings::saison_for_date('2025-09-01')['label'], '2025 / 2026');
check("2000-01-01 (annee lointaine)",         OPAC_Settings::saison_for_date('2000-01-01')['label'], '1999 / 2000');
check("start_year de 2026-08-31",             OPAC_Settings::saison_for_date('2026-08-31')['start_year'], 2025);

echo "\n=== B. Annee bissextile ===\n";
check("2028-02-29 (existe, avant bascule)",   OPAC_Settings::saison_for_date('2028-02-29')['label'], '2027 / 2028');
check("2028-09-01 (annee bissextile)",        OPAC_Settings::saison_for_date('2028-09-01')['label'], '2028 / 2029');
check("fin saison 2027 = veille 01/09/2028",  OPAC_Settings::saison_by_start_year(2027)['end'],   '2028-08-31');
check("fin saison 2023 = veille 01/09/2024",  OPAC_Settings::saison_by_start_year(2023)['end'],   '2024-08-31');
check("debut saison 2027 = 2027-09-01",       OPAC_Settings::saison_by_start_year(2027)['start'], '2027-09-01');
check("libelle saison 2027",                  OPAC_Settings::saison_by_start_year(2027)['label'], '2027 / 2028');

echo "\n=== C. Bornage des valeurs de configuration ===\n";
set_boundary(0, 9);   check("jour 0 -> 1",              OPAC_Settings::saison_start()['day'],   1);
set_boundary(32, 9);  check("jour 32 -> 1",             OPAC_Settings::saison_start()['day'],   1);
set_boundary(1, 13);  check("mois 13 -> 9",             OPAC_Settings::saison_start()['month'], 9);
set_boundary(1, 0);   check("mois 0 -> 9",              OPAC_Settings::saison_start()['month'], 9);
set_boundary(31, 2);  check("31 fevrier -> 29 (bissextile toleree)", OPAC_Settings::saison_start()['day'], 29);
set_boundary(31, 4);  check("31 avril -> 30",           OPAC_Settings::saison_start()['day'],   30);

// ---------------------------------------------------------------------------
echo "\n=== D. Rattachement d'une INSCRIPTION (pivot du 1er mai) ===\n";

// saison_for_date repond « dans quelle saison tombe cette date », ce qui est la
// bonne question pour un evenement et la mauvaise pour une inscription : les
// reinscriptions ouvrent en mai POUR la saison qui commence en septembre.
set_boundary(1, 9); // retour a la bascule standard apres la section C

function insc($ymd) { return OPAC_Settings::saison_for_inscription_date($ymd)['slug']; }

// Le coeur du correctif : toute la vague de mai a aout compte pour la saison
// qui s'ouvre, alors que saison_for_date la classait dans la precedente.
check("15 juin -> saison qui s'ouvre",    insc('2026-06-15'), '2026-2027');
check("(saison_for_date aurait dit)",     OPAC_Settings::saison_for_date('2026-06-15')['slug'], '2025-2026');
check("2 juillet",                        insc('2026-07-02'), '2026-2027');
check("20 aout, veille de rentree",       insc('2026-08-20'), '2026-2027');

// Bornes du pivot.
check("30 avril -> saison en cours",      insc('2026-04-30'), '2025-2026');
check("1er mai -> bascule",               insc('2026-05-01'), '2026-2027');
check("18 mai (reinscription 2026)",      insc('2026-05-18'), '2026-2027');

// Apres le 1er septembre, les deux regles doivent coincider : une inscription
// de novembre concerne la saison en cours, pas la suivante.
check("5 septembre",                      insc('2026-09-05'), '2026-2027');
check("20 decembre",                      insc('2026-12-20'), '2026-2027');
check("10 janvier",                       insc('2027-01-10'), '2026-2027');
check("accord avec saison_for_date en novembre",
    insc('2026-11-03'), OPAC_Settings::saison_for_date('2026-11-03')['slug']);

check("date illisible -> null",           OPAC_Settings::saison_for_inscription_date('pas-une-date'), null);
check("chaine vide -> null",              OPAC_Settings::saison_for_inscription_date(''), null);

// ---------------------------------------------------------------------------
echo "\n=== E. Saisons proposables dans le select ===\n";

$GLOBALS['now'] = '2026-09-19';
$choices = OPAC_Settings::saisons_choices();
check("4 saisons par defaut (2 en arriere, 1 en avant)", count($choices), 4);
check("la plus recente d'abord",          array_key_first($choices), '2027-2028');
check("la saison en cours presente",      isset($choices['2026-2027']), true);
check("la plus ancienne, bornee par la purge RGPD", array_key_last($choices), '2024-2025');
check("libelle lisible",                  $choices['2026-2027'], '2026 / 2027');
check("fenetre reglable",                 count(OPAC_Settings::saisons_choices(1, 0)), 2);

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
