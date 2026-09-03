<?php
/**
 * Harnais unitaire de la table de redirections 301 (anciennes URLs -> refonte).
 *
 * Teste le VRAI code (OPAC_Redirects::resolve) en isolation totale : logique
 * pure de correspondance de chemin, aucun appel WordPress, aucune base, aucun
 * site lance. Le seul prerequis est la constante ABSPATH (garde du fichier).
 *
 * Lancement (PHP CLI de Local, la version peut changer) :
 *   php wp-content/plugins/opac-custom/tests/test-redirects.php
 *
 * Couvre : archive et fiches ateliers, correction de slug, stages -> ephemeres,
 * pages fixes, query string, casse, chemins sans correspondance. Sortie : liste
 * des cas (OK / FAIL) et code de sortie 0 si tout passe.
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

require __DIR__ . '/../includes/class-opac-redirects.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-52s  obtenu=%-28s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}
function r($path) { return OPAC_Redirects::resolve($path); }

echo "=== A. Ateliers a l'annee ===\n";
check("archive avec slash",        r('/les-ateliers/'),        '/ateliers/');
check("archive sans slash",        r('les-ateliers'),          '/ateliers/');
check("page tarifs",               r('/les-ateliers/tarifs/'), '/ateliers/');
check("fiche slug identique",      r('/les-ateliers/anglais/'),'/ateliers/anglais/');
check("fiche arts-plastiques",     r('/les-ateliers/arts-plastiques/'), '/ateliers/arts-plastiques/');
check("fiche slug corrige",        r('/les-ateliers/tapisserie-dameublement-2/'), '/ateliers/tapisserie-dameublement/');

echo "\n=== B. Ateliers ephemeres (anciens stages) ===\n";
check("archive stages",            r('/les-stages/'),                     '/ephemeres/');
check("stage vacances",            r('/les-stages/vacances-de-paques/'),  '/ephemeres/');
check("stage ete",                 r('/les-stages/vacances-dete/'),       '/ephemeres/');
check("stages-de-printemps (page fixe)", r('/stages-de-printemps/'),      '/ephemeres/');

echo "\n=== C. Pages fixes ===\n";
check("equipes -> association",    r('/les-equipes-de-lopac/'),  '/association/');
check("c'est quoi -> association", r('/lopac-cest-quoi/'),       '/association/');
check("partenaires -> association",r('/partenaires/'),           '/association/');
check("infos-pratiques -> contact",r('/infos-pratiques/'),       '/contact/');
check("acces-plan -> contact",     r('/acces-plan/'),            '/contact/');
check("telethon -> agenda",        r('/telethon/'),              '/agenda/');
check("actus -> agenda",           r('/actus/'),                 '/agenda/');

echo "\n=== D. Query string, casse ===\n";
check("query string ignoree",      r('/les-ateliers/anglais/?utm_source=google'), '/ateliers/anglais/');
check("casse normalisee",          r('/LES-ATELIERS/Anglais/'),  '/ateliers/anglais/');

echo "\n=== E. Aucune correspondance (pas de redirection) ===\n";
check("racine",                    r('/'),                       '');
check("deja nouvelle URL",         r('/ateliers/'),              '');
check("fiche nouvelle URL",        r('/ateliers/theatre-enfants/'), '');
check("slug inconnu",              r('/nawak-inexistant/'),      '');
check("chaine vide",               r(''),                        '');

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
