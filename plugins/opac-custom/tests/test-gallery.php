<?php
/** Harnais unitaire de la migration et de la validation des galeries. */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

$GLOBALS['post_types'] = [
    10 => 'opac_atelier',
    11 => 'opac_stage',
    12 => 'opac_event',
    101 => 'attachment',
    102 => 'attachment',
    103 => 'attachment',
    104 => 'attachment',
    105 => 'attachment',
    999 => 'opac_atelier',
];
$GLOBALS['images'] = [ 101 => true, 102 => true, 103 => true, 104 => true, 105 => false ];
$GLOBALS['meta'] = [
    10 => [ 'opac_gallery_ids' => [ 101, 101, 999 ] ],
];
$GLOBALS['legacy'] = [
    10 => [ (object) [ 'ID' => 201 ], (object) [ 'ID' => 202 ] ],
    11 => [ (object) [ 'ID' => 203 ] ],
];
$GLOBALS['thumbs'] = [ 201 => 102, 202 => 103, 203 => 104 ];
$GLOBALS['meta'][201] = [ 'opac_gallery_caption' => 'Première légende' ];
$GLOBALS['options'] = [];

function absint($value) { return abs((int) $value); }
function get_post_type($id) { return $GLOBALS['post_types'][$id] ?? false; }
function wp_attachment_is_image($id) { return ! empty($GLOBALS['images'][$id]); }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function metadata_exists($type, $id, $key) { return array_key_exists($key, $GLOBALS['meta'][$id] ?? []); }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function get_post_thumbnail_id($id) { return $GLOBALS['thumbs'][$id] ?? 0; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; }
function get_posts($args) {
    if (($args['post_type'] ?? '') === 'opac_gallery_item') {
        return $GLOBALS['legacy'][(int) ($args['meta_value'] ?? 0)] ?? [];
    }
    return [ 10, 11, 12 ];
}

require __DIR__ . '/../includes/class-opac-gallery.php';

$tests = 0;
$passed = 0;
function check($label, $got, $expected) {
    global $tests, $passed;
    $tests++;
    $ok = $got === $expected;
    if ($ok) {
        $passed++;
    }
    printf("%2d  %-4s  %-52s obtenu=%-24s attendu=%s\n",
        $tests, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($expected, true));
}

check(
    'validation: uniques, images uniquement, ordre conserve',
    OPAC_Gallery::sanitize_attachment_ids([ '102', 101, 102, 105, 999, 0 ]),
    [ 102, 101 ]
);
check('repli legacy avant migration', OPAC_Gallery::attachment_ids(11), [ 104 ]);

$GLOBALS['meta'][12]['opac_gallery_ids'] = [];
check('liste vide explicite fait autorite', OPAC_Gallery::attachment_ids(12), []);

check('legende legacy conservee', OPAC_Gallery::legacy_captions(10), [ 102 => 'Première légende' ]);

OPAC_Gallery::maybe_migrate();
check('migration fusionne direct puis legacy', $GLOBALS['meta'][10]['opac_gallery_ids'], [ 101, 102, 103 ]);
check('migration copie les photos legacy', $GLOBALS['meta'][11]['opac_gallery_ids'], [ 104 ]);
check('migration materialise aussi une liste vide', $GLOBALS['meta'][12]['opac_gallery_ids'], []);
check('version de migration enregistree', $GLOBALS['options']['opac_gallery_db_version'], 1);

$snapshot = $GLOBALS['meta'];
$GLOBALS['legacy'][11][] = (object) [ 'ID' => 204 ];
$GLOBALS['thumbs'][204] = 102;
OPAC_Gallery::maybe_migrate();
check('migration versionnee ne rejoue pas', $GLOBALS['meta'], $snapshot);

echo "\n{$passed}/{$tests} tests passes.\n";
exit($passed === $tests ? 0 : 1);
