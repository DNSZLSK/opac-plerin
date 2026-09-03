<?php
/**
 * Harnais unitaire de OPAC_Inscriptions::excel_tsv_line() : la ligne "prete a
 * coller" dans le fichier Excel de suivi de la secretaire (bouton « Copier pour
 * Excel » de la liste admin des inscriptions).
 *
 * On verifie l'ordre exact des colonnes (gabarit A..R), le pre-remplissage
 * (contact + tarif + date + infos), les cellules laissees vides (paiement),
 * le cas "liste d'attente" (ligne contact courte), la conversion de date
 * Y-m-d -> d/m/Y, la neutralisation d'injection de formule et le nettoyage des
 * tabulations/retours a la ligne.
 *
 * Teste le VRAI code en isolation : get_post_meta / wp_get_object_terms sont
 * mockes. Aucune base, aucun site lance.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-excel-copy.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');
define('MINUTE_IN_SECONDS', 60);

// ---------------------------------------------------------------------------
// Mocks WordPress pilotables par globals.
// ---------------------------------------------------------------------------
$GLOBALS['post_meta']   = [];   // id => [ key => value ]
$GLOBALS['post_status'] = [];   // id => [ slug, ... ]

function get_post_meta($id, $key, $single = false) {
    return $GLOBALS['post_meta'][$id][$key] ?? '';
}
function wp_get_object_terms($id, $taxonomy, $args = []) {
    return $GLOBALS['post_status'][$id] ?? [];
}
function is_wp_error($thing) {
    return ($thing instanceof WP_Error);
}
class WP_Error {}
function __($s, $d = null) { return $s; }

require __DIR__ . '/../includes/class-opac-inscriptions.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %s\n", $n, $ok ? 'OK' : 'FAIL', $label);
    if (!$ok) {
        printf("        obtenu  = %s\n        attendu = %s\n",
            var_export($got, true), var_export($exp, true));
    }
}

// Colonnes du gabarit, pour construire les attendus lisiblement.
//   0 A N°  1 B NOM  2 C Prenom  3 D n°adh  4 E adresse  5 F CP  6 G Ville
//   7 H portable  8 I Mail  9 J Arrhes  10 K Recu  11 L adh.  12 M tarif
//   13 N Recu  14 O Autre  15 P Reinscrip.  16 Q Inscrip.  17 R infos
function tsv($cells) { return implode("\t", $cells); }

// ---------------------------------------------------------------------------
echo "=== A. Inscrit(e) validee : ligne complete A..R ===\n";

$GLOBALS['post_meta'][1] = [
    'opac_insc_nom'            => 'DUPONT',
    'opac_insc_prenom'         => 'Camille',
    'opac_insc_code_postal'    => '22190',
    'opac_insc_commune'        => 'PLERIN',
    'opac_insc_telephone'      => '06 39 98 12 34',
    'opac_insc_email'          => 'camille.dupont@example.org',
    'opac_insc_tarif'          => 190,
    'opac_insc_date_submitted' => '2026-06-17 10:30:00',
    'opac_insc_message'        => 'chèque arrhes',
];
$GLOBALS['post_status'][1] = ['validee'];

$attendu = tsv([
    '', 'DUPONT', 'Camille', '', '', '22190', 'PLERIN', '06 39 98 12 34',
    'camille.dupont@example.org', '', '', '', '190', '', '', '', '17/06/2026', 'chèque arrhes',
]);
check("ligne complete, ordre + valeurs", OPAC_Inscriptions::excel_tsv_line(1), $attendu);
check("18 colonnes (17 tabulations)", substr_count(OPAC_Inscriptions::excel_tsv_line(1), "\t"), 17);

// ---------------------------------------------------------------------------
echo "\n=== B. Liste d'attente : ligne contact courte A..I ===\n";

$GLOBALS['post_meta'][2] = [
    'opac_insc_nom'         => 'MARTIN',
    'opac_insc_prenom'      => 'Laurence',
    'opac_insc_code_postal' => '22190',
    'opac_insc_commune'     => 'PLERIN',
    'opac_insc_telephone'   => '06 39 98 56 78',
    'opac_insc_email'       => 'dominique.martin@example.org',
    // tarif/date/message ignores en liste d'attente.
    'opac_insc_tarif'          => 190,
    'opac_insc_date_submitted' => '2026-08-01 09:00:00',
    'opac_insc_message'        => 'ne doit pas apparaitre',
];
$GLOBALS['post_status'][2] = ['liste-attente'];

$attendu = tsv([
    '', 'MARTIN', 'Laurence', '', '', '22190', 'PLERIN', '06 39 98 56 78',
    'dominique.martin@example.org',
]);
check("ligne d'attente s'arrete au Mail", OPAC_Inscriptions::excel_tsv_line(2), $attendu);
check("9 colonnes (8 tabulations)", substr_count(OPAC_Inscriptions::excel_tsv_line(2), "\t"), 8);

// ---------------------------------------------------------------------------
echo "\n=== C. Statut en-attente : traite comme ligne complete ===\n";

$GLOBALS['post_meta'][3] = [
    'opac_insc_nom'    => 'BERNARD',
    'opac_insc_prenom' => 'Sylvie',
];
$GLOBALS['post_status'][3] = ['en-attente'];
check("en-attente -> 17 tabulations (ligne complete)",
    substr_count(OPAC_Inscriptions::excel_tsv_line(3), "\t"), 17);

// ---------------------------------------------------------------------------
echo "\n=== D. Cellules vides / valeurs manquantes ===\n";

$GLOBALS['post_meta'][4] = [
    'opac_insc_nom'    => 'PETIT',
    'opac_insc_prenom' => 'Claude',
    // pas de CP, tel, email, tarif, date, message.
];
$GLOBALS['post_status'][4] = ['validee'];
$attendu = tsv([
    '', 'PETIT', 'Claude', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
]);
check("champs absents -> cellules vides, pas de crash",
    OPAC_Inscriptions::excel_tsv_line(4), $attendu);

// tarif 0 -> colonne M vide.
$GLOBALS['post_meta'][5] = [
    'opac_insc_nom'   => 'X',
    'opac_insc_tarif' => 0,
];
$GLOBALS['post_status'][5] = ['validee'];
$cols = explode("\t", OPAC_Inscriptions::excel_tsv_line(5));
check("tarif 0 -> M (index 12) vide", $cols[12], '');

// ---------------------------------------------------------------------------
echo "\n=== E. Conversion de date Y-m-d H:i:s -> d/m/Y ===\n";

$GLOBALS['post_meta'][6] = [
    'opac_insc_nom'            => 'A',
    'opac_insc_date_submitted' => '2026-05-27 00:00:00',
];
$GLOBALS['post_status'][6] = ['validee'];
$cols = explode("\t", OPAC_Inscriptions::excel_tsv_line(6));
check("date -> Q (index 16) = 27/05/2026", $cols[16], '27/05/2026');

$GLOBALS['post_meta'][7] = [ 'opac_insc_nom' => 'A', 'opac_insc_date_submitted' => 'bidon' ];
$GLOBALS['post_status'][7] = ['validee'];
$cols = explode("\t", OPAC_Inscriptions::excel_tsv_line(7));
check("date invalide -> Q vide", $cols[16], '');

// ---------------------------------------------------------------------------
echo "\n=== F. Securite : injection de formule + nettoyage tab/retour ===\n";

$GLOBALS['post_meta'][8] = [
    'opac_insc_nom'     => '=SOMME(A1:A9)',
    'opac_insc_prenom'  => '+33bidon',
    'opac_insc_message' => "ligne1\tavec tab\r\net retour",
];
$GLOBALS['post_status'][8] = ['validee'];
$cols = explode("\t", OPAC_Inscriptions::excel_tsv_line(8));
check("nom en =... -> prefixe apostrophe", $cols[1], "'=SOMME(A1:A9)");
check("prenom en +... -> prefixe apostrophe", $cols[2], "'+33bidon");
check("message : tabs/retours remplaces par espace", $cols[17], 'ligne1 avec tab et retour');
check("aucune tabulation parasite injectee (17)",
    substr_count(OPAC_Inscriptions::excel_tsv_line(8), "\t"), 17);

// ---------------------------------------------------------------------------
echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
