<?php
/**
 * Harnais unitaire du calcul des places et des libelles de creneau.
 *
 *   - OPAC_Inscriptions::places_restantes() : capacite - inscrits hors site -
 *     inscriptions validees en base, jamais negatif, null si pas de limite.
 *   - OPAC_Inscriptions::creneau_is_full() : le declencheur de la liste
 *     d'attente automatique, qui doit desormais tenir compte des inscrits pris
 *     hors du site.
 *   - OPAC_Calendar::creneau_label() / choice_label() : deux creneaux de meme
 *     jour et meme horaire (deux groupes en alternance) doivent produire deux
 *     libelles DIFFERENTS, sans quoi ils donnent deux <option> identiques et le
 *     choix se fait au hasard.
 *
 * Le contexte : la base part de zero (aucune inscription en ligne avant la
 * refonte). Sans le terme « deja_inscrits », count_validees() vaut 0 partout,
 * donc aucun creneau n'est jamais complet, aucun badge ne s'affiche et la liste
 * d'attente automatique ne se declenche jamais, sur des ateliers pourtant
 * pleins depuis septembre. C'est ce que verifie le volet B.
 *
 * Teste le VRAI code en isolation : WP_Query et get_post_meta sont des mocks
 * pilotables. Aucune base, aucun site lance.
 *
 * Attention en ajoutant des cas : count_validees() memoise par
 * « atelier|creneau » pour la duree du process. Chaque scenario utilise donc
 * son propre couple d'identifiants.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-places-restantes.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');
define('MINUTE_IN_SECONDS', 60);

// ---------------------------------------------------------------------------
// Mocks WordPress pilotables par globals.
// ---------------------------------------------------------------------------
$GLOBALS['post_types'] = [];   // id => post_type
$GLOBALS['post_meta']  = [];   // id => [ key => value ]
$GLOBALS['validees']   = [];   // "atelierId|creneauId" => nombre d'inscriptions validees

function __($s, $d = null) { return $s; }
function get_post_type($id) { return $GLOBALS['post_types'][$id] ?? false; }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['post_meta'][$id][$key] ?? ''; }

/**
 * Stub de WP_Query : relit le meta_query construit par count_validees() pour
 * retrouver le couple atelier / creneau interroge, et rend autant de faux posts
 * que $GLOBALS['validees'] en declare.
 */
class WP_Query {
    public $posts = [];
    public function __construct($args) {
        $a = ''; $c = '';
        foreach ($args['meta_query'] ?? [] as $clause) {
            if (!is_array($clause) || !isset($clause['key'])) {
                continue;
            }
            if ('opac_insc_atelier_id' === $clause['key']) { $a = (string) $clause['value']; }
            if ('opac_insc_creneau_id' === $clause['key']) { $c = (string) $clause['value']; }
        }
        $n = $GLOBALS['validees'][$a . '|' . $c] ?? 0;
        $this->posts = array_fill(0, $n, 1);
    }
}

require __DIR__ . '/../includes/class-opac-calendar.php';
require __DIR__ . '/../includes/class-opac-inscriptions.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-56s  obtenu=%-22s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}

/** Fabrique un creneau structure et declare ses inscriptions validees. */
function creneau($id, array $extra = [], $validees = 0) {
    $GLOBALS['validees']['10|' . $id] = $validees;
    return array_merge([ 'id' => $id, 'jour' => 'jeudi', 'debut' => '14:00', 'fin' => '16:00' ], $extra);
}

$GLOBALS['post_types'][10] = 'opac_atelier';

// ---------------------------------------------------------------------------
echo "=== A. places_restantes : les trois termes ===\n";

check("capacite 0 -> pas de limite (null)",
    OPAC_Inscriptions::places_restantes(10, creneau('c-a1', [ 'capacite' => 0 ])), null);

check("id absent -> comptage impossible (null)",
    OPAC_Inscriptions::places_restantes(10, [ 'capacite' => 12 ]), null);

check("non-tableau -> null",
    OPAC_Inscriptions::places_restantes(10, 'pas-un-creneau'), null);

check("12 places, personne -> 12",
    OPAC_Inscriptions::places_restantes(10, creneau('c-a2', [ 'capacite' => 12 ])), 12);

check("cle deja_inscrits absente (fiche existante) -> 12",
    OPAC_Inscriptions::places_restantes(10, creneau('c-a3', [ 'capacite' => 12 ], 0)), 12);

check("12 places, 3 validees en ligne -> 9",
    OPAC_Inscriptions::places_restantes(10, creneau('c-a4', [ 'capacite' => 12 ], 3)), 9);

check("12 places, 10 hors site -> 2",
    OPAC_Inscriptions::places_restantes(10, creneau('c-a5', [ 'capacite' => 12, 'deja_inscrits' => 10 ])), 2);

check("12 places, 10 hors site + 2 validees -> 0",
    OPAC_Inscriptions::places_restantes(10, creneau('c-a6', [ 'capacite' => 12, 'deja_inscrits' => 10 ], 2)), 0);

check("depassement saisi (15 sur 12) -> 0, jamais negatif",
    OPAC_Inscriptions::places_restantes(10, creneau('c-a7', [ 'capacite' => 12, 'deja_inscrits' => 15 ])), 0);

// Depassement STRICT avec les deux termes actifs : 10 + 5 = 15 sur 12 places.
// Le cas reel du secretariat qui valide une inscription de trop sur un creneau
// deja rempli au guichet. Le calcul brut donnerait -3.
check("depassement par les deux termes (10 + 5 sur 12) -> 0",
    OPAC_Inscriptions::places_restantes(10, creneau('c-a10', [ 'capacite' => 12, 'deja_inscrits' => 10 ], 5)), 0);

check("deja_inscrits negatif traite comme 0",
    OPAC_Inscriptions::places_restantes(10, creneau('c-a8', [ 'capacite' => 12, 'deja_inscrits' => -3 ])), 12);

check("deja_inscrits saisi en texte -> caste",
    OPAC_Inscriptions::places_restantes(10, creneau('c-a9', [ 'capacite' => 12, 'deja_inscrits' => '4' ])), 8);

// ---------------------------------------------------------------------------
echo "\n=== B. creneau_is_full : le declencheur de la liste d'attente ===\n";

check("sans capacite -> jamais complet",
    OPAC_Inscriptions::creneau_is_full(10, creneau('c-b1', [ 'capacite' => 0, 'deja_inscrits' => 99 ])), false);

// Le cas annee 1 : atelier plein de reinscrits, zero inscription en base.
check("REGRESSION annee 1 : 12/12 hors site -> complet",
    OPAC_Inscriptions::creneau_is_full(10, creneau('c-b2', [ 'capacite' => 12, 'deja_inscrits' => 12 ])), true);

check("11 sur 12 hors site -> pas complet",
    OPAC_Inscriptions::creneau_is_full(10, creneau('c-b3', [ 'capacite' => 12, 'deja_inscrits' => 11 ])), false);

check("10 hors site + 2 validees -> complet",
    OPAC_Inscriptions::creneau_is_full(10, creneau('c-b4', [ 'capacite' => 12, 'deja_inscrits' => 10 ], 2)), true);

// Le creneau adapte reste ouvert quand les autres sont pleins : c'est tout
// l'interet du calcul par creneau et non par fiche.
check("creneau adapte ouvert pendant que les autres saturent",
    OPAC_Inscriptions::creneau_is_full(10, creneau('c-b5', [ 'capacite' => 8, 'deja_inscrits' => 2 ])), false);

// ---------------------------------------------------------------------------
echo "\n=== C. creneau_label : le rythme fait partie du libelle ===\n";

$base = [ 'jour' => 'jeudi', 'debut' => '14:00', 'fin' => '16:00' ];

check("sans rythme",
    OPAC_Calendar::creneau_label($base), 'Jeudi 14h - 16h');

check("rythme hebdomadaire n'ajoute rien",
    OPAC_Calendar::creneau_label($base + [ 'rythme' => 'chaque' ]), 'Jeudi 14h - 16h');

check("semaines paires",
    OPAC_Calendar::creneau_label($base + [ 'rythme' => 'paires' ]), 'Jeudi 14h - 16h, semaines paires');

check("semaines impaires",
    OPAC_Calendar::creneau_label($base + [ 'rythme' => 'impaires' ]), 'Jeudi 14h - 16h, semaines impaires');

check("rythme inconnu ignore",
    OPAC_Calendar::creneau_label($base + [ 'rythme' => 'n-importe-quoi' ]), 'Jeudi 14h - 16h');

// Le point de tout l'exercice : deux lignes de meme horaire restent lisibles.
$paires   = $base + [ 'rythme' => 'paires' ];
$impaires = $base + [ 'rythme' => 'impaires' ];
check("deux groupes en alternance -> libelles DIFFERENTS",
    OPAC_Calendar::creneau_label($paires) !== OPAC_Calendar::creneau_label($impaires), true);

// ---------------------------------------------------------------------------
echo "\n=== D. choice_label : la note dans une <option> ===\n";

check("ni note ni tarif",
    OPAC_Calendar::choice_label('Jeudi 14h - 16h', $base), 'Jeudi 14h - 16h');

check("tarif seul",
    OPAC_Calendar::choice_label('Jeudi 14h - 16h', $base, '335 €'), 'Jeudi 14h - 16h (335 €)');

check("note seule",
    OPAC_Calendar::choice_label('Mardi 10h - 12h', [ 'note' => 'atelier adapté' ]),
    'Mardi 10h - 12h (atelier adapté)');

check("note + tarif dans une seule parenthese",
    OPAC_Calendar::choice_label('Mardi 10h - 12h', [ 'note' => 'atelier adapté' ], '335 €'),
    'Mardi 10h - 12h (atelier adapté, 335 €)');

check("note vide ignoree",
    OPAC_Calendar::choice_label('Jeudi 14h - 16h', [ 'note' => '   ' ], '335 €'),
    'Jeudi 14h - 16h (335 €)');

check("libelle vide -> chaine vide",
    OPAC_Calendar::choice_label('', [ 'note' => 'x' ], '335 €'), '');

// Deux creneaux identiques distingues par leur seule note : le cas qui rendait
// le choix impossible dans la liste deroulante.
$a = [ 'jour' => 'jeudi', 'debut' => '14:00', 'fin' => '16:00', 'note' => 'débutants' ];
$b = [ 'jour' => 'jeudi', 'debut' => '14:00', 'fin' => '16:00', 'note' => 'confirmés' ];
check("meme horaire, notes differentes -> options DIFFERENTES",
    OPAC_Calendar::choice_label(OPAC_Calendar::creneau_label($a), $a)
        !== OPAC_Calendar::choice_label(OPAC_Calendar::creneau_label($b), $b), true);

// ---------------------------------------------------------------------------
echo "\n=== E. creneau_display : libelle recompose depuis la fiche ===\n";

// L'atelier 10 porte un creneau en semaines paires ; l'ephemere 20 une seance.
$GLOBALS['post_meta'][10]['opac_creneaux'] = [
    [ 'id' => 'c-e1', 'jour' => 'jeudi', 'debut' => '14:00', 'fin' => '16:00', 'rythme' => 'paires' ],
];
$GLOBALS['post_types'][20] = 'opac_stage';
$GLOBALS['post_meta'][20]['opac_stage_seances'] = [
    [ 'id' => 's-e1', 'date' => '2026-04-12', 'debut' => '14:30', 'fin' => '17:30' ],
];

/** Declare une inscription (instantane + cible + id de creneau). */
function inscription($id, $stored, $cible, $creneau_id) {
    $GLOBALS['post_meta'][$id] = [
        'opac_insc_creneau'    => $stored,
        'opac_insc_atelier_id' => $cible,
        'opac_insc_creneau_id' => $creneau_id,
    ];
    return $id;
}

// Le cas signale : une inscription d'avant l'ajout du rythme porte encore
// l'ancien instantane, mais s'exporte avec le libelle a jour.
check("instantane perime -> libelle a jour",
    OPAC_Inscriptions::creneau_display(inscription(100, 'Jeudi 14h - 16h', 10, 'c-e1')),
    'Jeudi 14h - 16h, semaines paires');

check("deux inscriptions du meme creneau -> meme libelle",
    OPAC_Inscriptions::creneau_display(inscription(101, 'Jeudi 14h - 16h', 10, 'c-e1'))
        === OPAC_Inscriptions::creneau_display(inscription(102, 'Jeudi 14h - 16h, semaines paires', 10, 'c-e1')),
    true);

check("creneau supprime de la fiche -> repli sur l'instantane",
    OPAC_Inscriptions::creneau_display(inscription(103, 'Lundi 9h - 11h', 10, 'c-disparu')),
    'Lundi 9h - 11h');

check("aucun id (ancien champ texte) -> repli sur l'instantane",
    OPAC_Inscriptions::creneau_display(inscription(104, 'Mercredi 14h (enfants)', 10, '')),
    'Mercredi 14h (enfants)');

check("cible absente -> repli sur l'instantane",
    OPAC_Inscriptions::creneau_display(inscription(105, 'Jeudi 14h - 16h', 0, 'c-e1')),
    'Jeudi 14h - 16h');

check("ephemere -> libelle de seance datee",
    OPAC_Inscriptions::creneau_display(inscription(106, '', 20, 's-e1')),
    '12 avril 2026, 14h30 - 17h30');

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
