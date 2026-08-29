<?php
/**
 * Harnais unitaire du garde-fou de conservation RGPD.
 *
 * Teste le VRAI code (OPAC_Settings::sanitize_purge_months) en isolation :
 * stubs WordPress minimalistes, aucune base, aucun site lance. Logique pure.
 *
 * Enjeu : la purge supprime DEFINITIVEMENT les inscriptions plus anciennes que
 * la duree reglee. Une valeur trop courte (fausse manip : 3 mois) effacerait
 * les inscriptions de l'annee scolaire en cours. Le garde-fou remonte toute
 * valeur 1-11 a 12 ; 0 (desactive) et 12+ passent tels quels.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-purge-months.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

function __($s, $d = null) { return $s; }
function _e($s, $d = null) { echo $s; }
function _x($s, $c = null, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_attr__($s, $d = null) { return $s; }
function esc_html($s) { return $s; }
function esc_attr($s) { return $s; }
function esc_url($s) { return $s; }
function esc_textarea($s) { return $s; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($s) { return $s; }
function sanitize_textarea_field($s) { return $s; }
function wp_kses_post($s) { return $s; }
function get_option($k, $d = null) { return $d; }
function home_url($p = '') { return 'http://example.test' . $p; }
function get_bloginfo($k = '') { return ''; }
function wp_timezone_string() { return 'Europe/Paris'; }

require __DIR__ . '/../includes/class-opac-settings.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-42s  obtenu=%-4s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}

echo "=== Garde-fou duree de conservation (mois) ===\n";
check("0 (desactive) reste 0",           OPAC_Settings::sanitize_purge_months('0'),   0);
check("1 remonte a 12",                  OPAC_Settings::sanitize_purge_months('1'),   12);
check("3 (fausse manip) remonte a 12",   OPAC_Settings::sanitize_purge_months('3'),   12);
check("11 remonte a 12",                 OPAC_Settings::sanitize_purge_months('11'),  12);
check("12 reste 12 (plancher exact)",    OPAC_Settings::sanitize_purge_months('12'),  12);
check("24 (defaut) reste 24",            OPAC_Settings::sanitize_purge_months('24'),  24);
check("60 reste 60",                     OPAC_Settings::sanitize_purge_months('60'),  60);
check("negatif -> 12 (absint puis plancher)", OPAC_Settings::sanitize_purge_months('-5'), 12);
check("texte 'abc' -> 0 (rien a conserver)",  OPAC_Settings::sanitize_purge_months('abc'), 0);
check("entier 6 (non string) remonte a 12",   OPAC_Settings::sanitize_purge_months(6),    12);

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
