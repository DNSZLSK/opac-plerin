<?php
/**
 * Harnais unitaire de la logique d'inscription sensible :
 *   - OPAC_Inscriptions::creneau_capacite() : resolution de la capacite d'un
 *     creneau/seance par son id (sert a detecter un depassement a la validation).
 *   - OPAC_Inscriptions::dispatch_bulk_email() : envoi groupe par lots de Bcc,
 *     avec comptage succes / echec (total et partiel).
 *
 * Teste le VRAI code en isolation : les fonctions WordPress (get_post_meta,
 * get_post_type, wp_mail) sont remplacees par des mocks pilotables. Aucune base,
 * aucun site lance.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-inscriptions.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');
define('MINUTE_IN_SECONDS', 60);

// ---------------------------------------------------------------------------
// Mocks WordPress pilotables par globals.
// ---------------------------------------------------------------------------
$GLOBALS['post_types'] = [];   // id => post_type
$GLOBALS['post_meta']  = [];   // id => [ key => value ]
$GLOBALS['mail_calls'] = [];   // une entree par appel wp_mail : le tableau de headers
$GLOBALS['mail_fail']  = [];   // index (0-based) des appels wp_mail a faire echouer

function get_post_type($id) {
    return $GLOBALS['post_types'][$id] ?? false;
}
function get_post_meta($id, $key, $single = false) {
    return $GLOBALS['post_meta'][$id][$key] ?? '';
}
function wp_mail($to, $subject, $body, $headers = []) {
    $idx = count($GLOBALS['mail_calls']);
    $GLOBALS['mail_calls'][] = $headers;
    return !in_array($idx, $GLOBALS['mail_fail'], true);
}

// Fonctions referencees ailleurs dans la classe (jamais appelees ici, mais le
// linker PHP ne resout qu'a l'execution : stubs de securite pour l'inclusion).
function __($s, $d = null) { return $s; }

require __DIR__ . '/../includes/class-opac-inscriptions.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-52s  obtenu=%-6s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}

// ---------------------------------------------------------------------------
echo "=== A. creneau_capacite : resolution par id ===\n";

// Atelier a l'annee avec 2 creneaux structures.
$GLOBALS['post_types'][10] = 'opac_atelier';
$GLOBALS['post_meta'][10]['opac_creneaux'] = [
    [ 'id' => 'c-lundi',  'jour' => 'lundi',  'capacite' => 8 ],
    [ 'id' => 'c-mardi',  'jour' => 'mardi',  'capacite' => 0 ],   // 0 = illimite
    [ 'id' => 'c-nocap',  'jour' => 'jeudi' ],                     // pas de cle capacite
];
check("atelier, creneau capacite 8",        OPAC_Inscriptions::creneau_capacite(10, 'c-lundi'), 8);
check("atelier, creneau capacite 0 (illim)", OPAC_Inscriptions::creneau_capacite(10, 'c-mardi'), 0);
check("atelier, cle capacite absente -> 0",  OPAC_Inscriptions::creneau_capacite(10, 'c-nocap'), 0);
check("atelier, creneau introuvable -> 0",   OPAC_Inscriptions::creneau_capacite(10, 'c-inexistant'), 0);

// Ephemere avec seances datees.
$GLOBALS['post_types'][20] = 'opac_stage';
$GLOBALS['post_meta'][20]['opac_stage_seances'] = [
    [ 'id' => 's-1', 'date' => '2026-04-12', 'capacite' => 5 ],
];
check("stage, seance capacite 5",            OPAC_Inscriptions::creneau_capacite(20, 's-1'), 5);
check("stage, seance introuvable -> 0",      OPAC_Inscriptions::creneau_capacite(20, 's-99'), 0);

// Cas limites / robustesse.
$GLOBALS['post_types'][30] = 'opac_event'; // type non inscriptible
check("type non gere (event) -> 0",          OPAC_Inscriptions::creneau_capacite(30, 's-1'), 0);
check("id de creneau vide -> 0",             OPAC_Inscriptions::creneau_capacite(10, ''), 0);
check("cible 0 -> 0",                        OPAC_Inscriptions::creneau_capacite(0, 'c-lundi'), 0);

// Structure meta absente / corrompue (l'equipe n'a pas encore saisi de creneau).
$GLOBALS['post_types'][40] = 'opac_atelier';
check("meta creneaux absente -> 0 (pas de crash)", OPAC_Inscriptions::creneau_capacite(40, 'c-lundi'), 0);

// ---------------------------------------------------------------------------
echo "\n=== B. dispatch_bulk_email : envoi par lots (Bcc de 45) ===\n";

function mails($count) {
    $out = [];
    for ($i = 0; $i < $count; $i++) { $out[] = "u{$i}@ex.test"; }
    return $out;
}
function reset_mail() { $GLOBALS['mail_calls'] = []; $GLOBALS['mail_fail'] = []; }

// 100 destinataires -> 3 lots (45 + 45 + 10), tous OK.
reset_mail();
$r = OPAC_Inscriptions::dispatch_bulk_email(mails(100), 'Sujet', '<p>corps</p>', 'asso@ex.test', 'OPAC');
check("100 destinataires : sent",            $r['sent'], 100);
check("100 destinataires : failed",          $r['failed'], 0);
check("100 destinataires : nb d'appels wp_mail (ceil 100/45)", count($GLOBALS['mail_calls']), 3);

// Verifie qu'aucun lot ne depasse 45 Bcc et que le 3e lot en a 10.
$sizes = array_map(function ($h) {
    foreach ($h as $line) {
        if (strpos($line, 'Bcc: ') === 0) {
            return substr_count($line, '@');
        }
    }
    return 0;
}, $GLOBALS['mail_calls']);
check("tailles des lots [45,45,10]",         $sizes, [45, 45, 10]);

// 45 pile -> 1 seul lot.
reset_mail();
$r = OPAC_Inscriptions::dispatch_bulk_email(mails(45), 'S', 'B', 'a@ex.test', 'OPAC');
check("45 destinataires : 1 seul appel",     count($GLOBALS['mail_calls']), 1);

// 46 -> 2 lots.
reset_mail();
$r = OPAC_Inscriptions::dispatch_bulk_email(mails(46), 'S', 'B', 'a@ex.test', 'OPAC');
check("46 destinataires : 2 appels",         count($GLOBALS['mail_calls']), 2);

// Echec partiel : le 2e lot (index 1) echoue -> 55 envoyes, 45 en echec.
reset_mail();
$GLOBALS['mail_fail'] = [1];
$r = OPAC_Inscriptions::dispatch_bulk_email(mails(100), 'S', 'B', 'a@ex.test', 'OPAC');
check("echec partiel (2e lot) : sent",       $r['sent'], 55);
check("echec partiel (2e lot) : failed",     $r['failed'], 45);

// Echec total : tous les lots echouent.
reset_mail();
$GLOBALS['mail_fail'] = [0, 1];
$r = OPAC_Inscriptions::dispatch_bulk_email(mails(50), 'S', 'B', 'a@ex.test', 'OPAC');
check("echec total : sent 0",                $r['sent'], 0);
check("echec total : failed 50",             $r['failed'], 50);

// Liste vide -> aucun appel.
reset_mail();
$r = OPAC_Inscriptions::dispatch_bulk_email([], 'S', 'B', 'a@ex.test', 'OPAC');
check("liste vide : 0 appel",                count($GLOBALS['mail_calls']), 0);
check("liste vide : sent 0",                 $r['sent'], 0);

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
