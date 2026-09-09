<?php
/** Harnais unitaire du formatage des plages de dates d'un ephemere. */
error_reporting(E_ALL & ~E_DEPRECATED);
date_default_timezone_set('UTC');

define('ABSPATH', '/');

function wp_date($format, $timestamp = null) {
    return date($format, $timestamp ?? time());
}
function __($text, $domain = null) {
    return $text;
}

require __DIR__ . '/../includes/class-opac-calendar.php';
require __DIR__ . '/../includes/class-opac-bindings.php';

$method = new ReflectionMethod('OPAC_Bindings', 'format_date_range');

$tests = 0;
$passed = 0;
function check($label, $got, $expected) {
    global $tests, $passed;
    $tests++;
    $ok = $got === $expected;
    if ($ok) {
        $passed++;
    }
    printf("%2d  %-4s  %-40s obtenu=%-38s attendu=%s\n",
        $tests, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($expected, true));
}

check('meme jour', $method->invoke(null, '2026-04-15', '2026-04-15'), '15 avril 2026');
check('debut seul', $method->invoke(null, '2026-06-15', ''), '15 juin 2026');
check('plage meme mois', $method->invoke(null, '2026-06-15', '2026-06-20'), 'Du 15 au 20 juin 2026');
check('plage mois differents', $method->invoke(null, '2026-06-28', '2026-07-03'), 'Du 28 juin au 3 juillet 2026');
check('plage annees differentes', $method->invoke(null, '2026-12-30', '2027-01-02'), 'Du 30 décembre 2026 au 2 janvier 2027');
check('debut absent', $method->invoke(null, '', '2026-06-20'), '');

echo "\n{$passed}/{$tests} tests passes.\n";
exit($passed === $tests ? 0 : 1);
