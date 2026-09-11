<?php
/** Harnais unitaire de l'historique complet des fiches métier. */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

$GLOBALS['registered_meta'] = [
    'opac_stage' => [
        'opac_date_debut' => [],
        'opac_date_fin'   => [],
        'opac_date_last'  => [],
        'opac_gallery_ids'=> [],
    ],
];
$GLOBALS['post_types'] = [ 10 => 'opac_stage' ];
$GLOBALS['post_meta'] = [
    99 => [
        '_opac_revision_taxonomies' => [
            'opac_audience' => [ 7 ],
            'opac_period'   => [ 8 ],
        ],
    ],
];
$GLOBALS['terms'] = [
    'opac_audience' => [ 1 ],
    'opac_period'   => [ 2 ],
];
$GLOBALS['stored_meta'] = [];
$GLOBALS['restored_terms'] = [];

function add_filter() {}
function add_action() {}
function get_registered_meta_keys($type, $subtype) { return $GLOBALS['registered_meta'][$subtype] ?? []; }
function wp_is_post_revision() { return false; }
function wp_is_post_autosave() { return false; }
function wp_get_object_terms($post_id, $taxonomy, $args) { return $GLOBALS['terms'][$taxonomy] ?? []; }
function is_wp_error() { return false; }
function update_post_meta($post_id, $key, $value) { $GLOBALS['stored_meta'][$post_id][$key] = $value; }
function get_post_type($post_id) { return $GLOBALS['post_types'][$post_id] ?? false; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['post_meta'][$post_id][$key] ?? ''; }
function wp_set_object_terms($post_id, $ids, $taxonomy, $append) { $GLOBALS['restored_terms'][$taxonomy] = $ids; }

class OPAC_Blocks {
    public static $recalculated = false;
    public static function store_stage_end_date($post_id) { self::$recalculated = $post_id; }
}

require __DIR__ . '/../includes/class-opac-meta.php';

$tests = 0;
$passed = 0;
function check($label, $got, $expected) {
    global $tests, $passed;
    $tests++;
    $ok = $got === $expected;
    if ($ok) { $passed++; }
    printf("%2d  %-4s  %-54s obtenu=%-20s attendu=%s\n",
        $tests, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($expected, true));
}

$keys = OPAC_Meta::revision_meta_keys([ 'deja_present' ], 'opac_stage');
check('meta metier versionnee', in_array('opac_date_debut', $keys, true), true);
check('galerie versionnee', in_array('opac_gallery_ids', $keys, true), true);
check('image mise en avant versionnee', in_array('_thumbnail_id', $keys, true), true);
check('snapshot taxonomies versionne', in_array('_opac_revision_taxonomies', $keys, true), true);
check('index derive exclu', in_array('opac_date_last', $keys, true), false);
check('autre type inchange', OPAC_Meta::revision_meta_keys([ 'x' ], 'post'), [ 'x' ]);

$post = (object) [ 'post_type' => 'opac_stage' ];
OPAC_Meta::snapshot_revision_taxonomies(10, $post);
check('taxonomies photographiees', $GLOBALS['stored_meta'][10]['_opac_revision_taxonomies'], [
    'opac_audience' => [ 1 ],
    'opac_period'   => [ 2 ],
]);

OPAC_Meta::restore_revision_taxonomies(10, 99);
check('public restaure', $GLOBALS['restored_terms']['opac_audience'], [ 7 ]);
check('periode restauree', $GLOBALS['restored_terms']['opac_period'], [ 8 ]);
check('index date recalcule', OPAC_Blocks::$recalculated, 10);

echo "\n{$passed}/{$tests} tests passes.\n";
exit($passed === $tests ? 0 : 1);
