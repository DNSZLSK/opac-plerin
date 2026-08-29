<?php
/**
 * Lanceur de tous les harnais unitaires du plugin.
 *
 * Chaque test-*.php definit ses propres stubs WordPress globaux (check(), __(),
 * absint()...), donc ils ne peuvent pas cohabiter dans un meme process : ce
 * lanceur execute chacun dans un process PHP separe (PHP_BINARY = le meme
 * interpreteur que celui qui lance ce script) et agrege les codes de sortie.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/run-tests.php
 *
 * Code de sortie 0 si tous les harnais passent, 1 sinon.
 */
$dir   = __DIR__;
$files = glob($dir . '/test-*.php');
sort($files);

$php     = PHP_BINARY;
$failed  = [];

foreach ($files as $file) {
    $name = basename($file);
    echo str_repeat('=', 72) . "\n";
    echo ">>> {$name}\n";
    echo str_repeat('=', 72) . "\n";

    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($file);
    passthru($cmd, $code);
    echo "\n";
    if ($code !== 0) {
        $failed[] = $name;
    }
}

echo str_repeat('#', 72) . "\n";
if (empty($failed)) {
    printf("TOUS LES HARNAIS PASSENT (%d fichiers).\n", count($files));
    exit(0);
}
printf("ECHEC : %d/%d harnais en echec -> %s\n", count($failed), count($files), implode(', ', $failed));
exit(1);
