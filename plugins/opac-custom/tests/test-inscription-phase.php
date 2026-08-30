<?php
/**
 * Harnais unitaire de la phase d'inscription (priorité réinscription).
 *
 * Teste le VRAI code OPAC_Settings::inscription_phase() en isolation : les 3
 * dates du panel sont pilotées via un stub get_option, et « aujourd'hui » via
 * un stub current_time. Aucune base, aucun site.
 *
 * La phase pilote le message informatif du formulaire (avant / réinscription
 * prioritaire / ouverte / fermée). Règle : gating souple, jamais bloquant, et
 * « aucune date configurée » = toujours ouverte (comportement historique).
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-inscription-phase.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

$GLOBALS['opts']  = [];
$GLOBALS['today'] = '2026-06-15';

function get_option($k, $d = null) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function current_time($fmt) { return $GLOBALS['today']; }
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
function home_url($p = '') { return 'http://example.test' . $p; }
function get_bloginfo($k = '') { return ''; }
function wp_timezone_string() { return 'Europe/Paris'; }

require __DIR__ . '/../includes/class-opac-settings.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++; $ok = ($got === $exp); $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-52s  obtenu=%-15s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}
function set_dates($rein, $ouv, $ferm) {
    $GLOBALS['opts']['opac_insc_date_reinscription'] = $rein;
    $GLOBALS['opts']['opac_insc_date_ouverture']     = $ouv;
    $GLOBALS['opts']['opac_insc_date_fermeture']     = $ferm;
}
function phase_at($today) { $GLOBALS['today'] = $today; return OPAC_Settings::inscription_phase(); }

echo "=== A. Aucune date configurée -> toujours ouverte ===\n";
set_dates('', '', '');
check("sans dates = ouverte",              phase_at('2026-06-15'), 'ouverte');

echo "\n=== B. Cycle complet : rein 01/09, ouv 15/09, ferm 31/10 ===\n";
set_dates('2026-09-01', '2026-09-15', '2026-10-31');
check("veille de réinscription -> avant",  phase_at('2026-08-31'), 'avant');
check("jour de réinscription (inclus)",    phase_at('2026-09-01'), 'reinscription');
check("entre rein et ouv",                 phase_at('2026-09-10'), 'reinscription');
check("veille d'ouverture",                phase_at('2026-09-14'), 'reinscription');
check("jour d'ouverture (inclus)",         phase_at('2026-09-15'), 'ouverte');
check("entre ouv et ferm",                 phase_at('2026-10-15'), 'ouverte');
check("jour de fermeture (encore ouvert)", phase_at('2026-10-31'), 'ouverte');
check("lendemain de fermeture -> fermée",  phase_at('2026-11-01'), 'fermee');

echo "\n=== C. Dates partielles ===\n";
set_dates('2026-09-01', '', '');
check("rein seule, avant",                 phase_at('2026-08-20'), 'avant');
check("rein seule, après",                 phase_at('2026-09-20'), 'reinscription');
set_dates('', '2026-09-15', '');
check("ouv seule, avant",                  phase_at('2026-09-01'), 'avant');
check("ouv seule, après",                  phase_at('2026-09-20'), 'ouverte');
set_dates('', '', '2026-10-31');
check("ferm seule, avant clôture",         phase_at('2026-10-01'), 'ouverte');
check("ferm seule, après clôture",         phase_at('2026-11-05'), 'fermee');

echo "\n=== D. Config incohérente : réinscription APRÈS ouverture ===\n";
// L'admin a inversé les dates. Documenté : l'ouverture l'emporte, la phase
// 'reinscription' n'est jamais atteinte (motive un garde-fou de saisie).
set_dates('2026-10-01', '2026-09-01', '');
check("rein > ouv : ouverture l'emporte",  phase_at('2026-09-15'), 'ouverte');
check("rein > ouv : après rein, reste ouverte", phase_at('2026-10-15'), 'ouverte');

echo "\n=== E. Garde-fou : cohérence de l'ordre des dates ===\n";
function warn_count($rein, $ouv, $ferm) {
    set_dates($rein, $ouv, $ferm);
    return count(OPAC_Settings::inscription_dates_warnings());
}
check("ordre correct (rein<ouv<ferm) : 0 alerte", warn_count('2026-09-01', '2026-09-15', '2026-10-31'), 0);
check("aucune date : 0 alerte",                    warn_count('', '', ''), 0);
check("dates partielles (rein seule) : 0 alerte",  warn_count('2026-09-01', '', ''), 0);
check("rein = ouv (meme jour) : 1 alerte",         warn_count('2026-09-15', '2026-09-15', ''), 1);
check("rein > ouv : 1 alerte",                     warn_count('2026-10-01', '2026-09-01', ''), 1);
check("ouv = ferm : 1 alerte",                     warn_count('', '2026-10-31', '2026-10-31'), 1);
check("ouv > ferm : 1 alerte",                     warn_count('', '2026-11-01', '2026-10-31'), 1);
check("tout inverse (rein>ouv>ferm) : 3 alertes",  warn_count('2026-11-01', '2026-10-01', '2026-09-01'), 3);

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
