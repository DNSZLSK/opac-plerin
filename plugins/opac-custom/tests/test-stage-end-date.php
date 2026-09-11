<?php
/**
 * Harnais unitaire de la date de fin effective des ephemeres.
 *
 * Teste la regle metier : date valide la plus tardive parmi les seances et la
 * plage generale. Verifie aussi le statut passe et l'index derive.
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

$GLOBALS['post_types'] = [];
$GLOBALS['post_meta']  = [];
$GLOBALS['today']      = '2026-09-09';

function get_post_type($id) {
    return $GLOBALS['post_types'][$id] ?? false;
}
function get_post_meta($id, $key, $single = false) {
    return $GLOBALS['post_meta'][$id][$key] ?? '';
}
function update_post_meta($id, $key, $value) {
    $GLOBALS['post_meta'][$id][$key] = $value;
}
function delete_post_meta($id, $key) {
    unset($GLOBALS['post_meta'][$id][$key]);
}
function current_time($format) {
    return $GLOBALS['today'];
}

require __DIR__ . '/../includes/class-opac-blocks.php';

$tests = 0;
$passed = 0;
function check($label, $got, $expected) {
    global $tests, $passed;
    $tests++;
    $ok = $got === $expected;
    if ($ok) {
        $passed++;
    }
    printf("%2d  %-4s  %-58s obtenu=%-12s attendu=%s\n",
        $tests, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($expected, true));
}

foreach (range(1, 8) as $id) {
    $GLOBALS['post_types'][$id] = 'opac_stage';
}

$GLOBALS['post_meta'][1] = [
    'opac_stage_seances' => [
        [ 'date' => '2026-09-10' ],
        [ 'date' => '2026-09-03' ],
        [ 'date' => '2026-09-07' ],
    ],
    'opac_date_fin'   => '2026-09-11',
    'opac_date_debut' => '2026-09-03',
];
check('plage finissant apres les debuts de seance', OPAC_Blocks::stage_end_date(1), '2026-09-11');
check('encore actif avant la fin de la plage', OPAC_Blocks::stage_is_past(1), false);

$GLOBALS['post_meta'][2] = [
    'opac_stage_seances' => [ [ 'date' => 'date-invalide' ] ],
    'opac_date_fin'      => '2026-09-12',
    'opac_date_debut'    => '2026-09-01',
];
check('seances sans date valide, fin conservee', OPAC_Blocks::stage_end_date(2), '2026-09-12');

$GLOBALS['post_meta'][3] = [ 'opac_date_debut' => '2026-09-08' ];
check('repli sur debut', OPAC_Blocks::stage_end_date(3), '2026-09-08');
check('debut seul revolu', OPAC_Blocks::stage_is_past(3), true);

$GLOBALS['post_meta'][4] = [
    'opac_date_fin'   => '2026-02-30',
    'opac_date_debut' => '2026-09-09',
];
check('date impossible ignoree', OPAC_Blocks::stage_end_date(4), '2026-09-09');
check('date du jour non passee', OPAC_Blocks::stage_is_past(4), false);

$GLOBALS['post_meta'][5] = [];
check('aucune date', OPAC_Blocks::stage_end_date(5), '');
check('sans date traite actif', OPAC_Blocks::stage_is_past(5), false);

$GLOBALS['post_meta'][5] = [
    'opac_stage_seances' => [ [ 'date' => '2026-09-15' ] ],
    'opac_date_fin'      => '2026-09-11',
    'opac_date_debut'    => '2026-09-07',
];
check('seance plus tardive que la plage', OPAC_Blocks::stage_end_date(5), '2026-09-15');

$GLOBALS['post_meta'][6] = [ 'opac_date_fin' => '2026-10-01' ];
OPAC_Blocks::store_stage_end_date(6);
check('index derive ecrit', $GLOBALS['post_meta'][6]['opac_date_last'] ?? '', '2026-10-01');

$GLOBALS['post_meta'][7] = [ 'opac_date_last' => '2026-10-01' ];
OPAC_Blocks::store_stage_end_date(7);
check('index derive supprime sans source', isset($GLOBALS['post_meta'][7]['opac_date_last']), false);

$GLOBALS['post_types'][8] = 'opac_event';
check('autre type jamais passe', OPAC_Blocks::stage_is_past(8), false);

$GLOBALS['post_meta'][8] = [
    'opac_date_event'     => '2026-09-08',
    'opac_date_event_fin' => '2026-09-12',
];
check('agenda multijour reste actif jusqu a sa fin', OPAC_Blocks::event_end_date(8), '2026-09-12');

$GLOBALS['post_types'][9] = 'opac_event';
$GLOBALS['post_meta'][9] = [ 'opac_date_event' => '2026-09-10' ];
check('agenda un jour se replie sur son debut', OPAC_Blocks::event_end_date(9), '2026-09-10');

$GLOBALS['post_types'][10] = 'opac_event';
$GLOBALS['post_meta'][10] = [
    'opac_date_event'     => '2026-09-10',
    'opac_date_event_fin' => 'date-invalide',
];
check('agenda ignore une fin invalide', OPAC_Blocks::event_end_date(10), '2026-09-10');

$query = OPAC_Blocks::filter_query_loop_vars([ 'post_type' => 'opac_stage' ]);
check('Query Loop filtre sur la fin effective', $query['meta_query']['opac_last']['key'], 'opac_date_last');
check('Query Loop inclut la date du jour', $query['meta_query']['opac_last']['value'], '2026-09-09');
check('Query Loop trie encore par le debut', $query['orderby'], [ 'opac_debut' => 'ASC' ]);

echo "\n{$passed}/{$tests} tests passes.\n";
exit($passed === $tests ? 0 : 1);
