<?php
/**
 * Harnais unitaire de la liste blanche REST (OPAC_Security::is_public_rest_route).
 *
 * L'API REST est fermee aux visiteurs anonymes, sauf les routes listees dans
 * REST_PUBLIC_PREFIXES. Cette fonction decide donc, a chaque requete anonyme,
 * ce qui passe et ce qui rend 401 : une erreur de comparaison de prefixe y
 * ouvrirait silencieusement toute l'API, ou casserait les apercus oEmbed chez
 * les tiers qui partagent un lien vers le site.
 *
 * Le piege verifie ici est la comparaison naive par str_starts_with : sans
 * exiger le separateur, une route « /oembed/1.0-nimporte-quoi » passerait pour
 * publique. Le test la refuse explicitement.
 *
 * Fonction pure, aucun appel WordPress : le harnais n'a besoin que d'ABSPATH.
 *
 * Lancement (PHP CLI de Local) :
 *   php wp-content/plugins/opac-custom/tests/test-rest-allowlist.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', '/');

require __DIR__ . '/../includes/class-opac-security.php';

$n = 0; $pass = 0; $fail = 0;
function check($label, $got, $exp) {
    global $n, $pass, $fail;
    $n++;
    $ok = ($got === $exp);
    $ok ? $pass++ : $fail++;
    printf("%2d  %-4s  %-52s  obtenu=%-7s attendu=%s\n",
        $n, $ok ? 'OK' : 'FAIL', $label, var_export($got, true), var_export($exp, true));
}
function pub($route) { return OPAC_Security::is_public_rest_route($route); }

// ---------------------------------------------------------------------------
echo "=== A. Routes laissees ouvertes ===\n";

check("prefixe exact",                    pub('/oembed/1.0'),          true);
check("route sous le prefixe",            pub('/oembed/1.0/embed'),    true);
check("sans slash initial",               pub('oembed/1.0/embed'),     true);
check("proxy oembed",                     pub('/oembed/1.0/proxy'),    true);

// ---------------------------------------------------------------------------
echo "\n=== B. Routes fermees ===\n";

check("contenus",                         pub('/wp/v2/posts'),         false);
check("medias (fuite d'ID d'auteur)",     pub('/wp/v2/media'),         false);
check("utilisateurs",                     pub('/wp/v2/users'),         false);
check("ateliers",                         pub('/wp/v2/opac_atelier'),  false);
check("commentaires",                     pub('/wp/v2/comments'),      false);
check("abilities",                        pub('/wp-abilities/v1/abilities'), false);
check("index de l'API",                   pub('/'),                    false);
check("route vide",                       pub(''),                     false);

// ---------------------------------------------------------------------------
echo "\n=== C. Le prefixe doit s'arreter sur un separateur ===\n";

// Sans cette exigence, n'importe quelle route commencant par les memes
// caracteres passerait pour publique.
check("prefixe colle a d'autres caracteres", pub('/oembed/1.0-nimporte-quoi/x'), false);
check("prefixe sans separateur",             pub('/oembed/1.0abc'),             false);
check("prefixe en milieu de route",          pub('/wp/v2/oembed/1.0/embed'),    false);
check("prefixe partiel",                     pub('/oembed'),                    false);
check("autre version d'oembed",              pub('/oembed/2.0/embed'),          false);

echo "\n";
printf("RESULTAT : %d cas, %d OK, %d FAIL\n", $n, $pass, $fail);
exit($fail === 0 ? 0 : 1);
