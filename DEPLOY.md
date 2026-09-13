# OPAC Plérin - Notes de déploiement

Ce dépôt versionne uniquement `wp-content/` (thème + plugin custom). Plusieurs hardening de sécurité doivent être appliqués à des fichiers **hors `wp-content/`** au moment du déploiement.

**Nouveau sur le projet ?** Le déploiement du code est automatisé : voir la section 0 ci-dessous. Les sections 1 à 4 décrivent la configuration serveur, à faire une seule fois. Les sections 5 à 7 sont des réglages qui vivent en base de données, donc jamais transportés par le déploiement.

## 0. Déployer le code (script automatisé)

Le déploiement **n'est pas manuel** : il est fait par `tools/deploy-demo.ps1`, versionné
dans ce dépôt.

```powershell
powershell -ExecutionPolicy Bypass -File "<dépôt>/tools/deploy-demo.ps1"
powershell -ExecutionPolicy Bypass -File "<dépôt>/tools/deploy-demo.ps1" -PluginOnly
```

Le script résout ses chemins par rapport à lui-même : il fonctionne depuis n'importe
quel clone, sans rien y modifier. Prérequis : le client OpenSSH de Windows (`sftp`) et
un accès SFTP OVH. Le mot de passe est demandé à chaque exécution, rien n'est stocké
dans le fichier.

Ce qu'il fait : upload SFTP récursif de dossiers entiers (jamais fichier par fichier,
pour ne pas laisser un `.php` tronqué casser le site), garde-fou sur la branche git
courante, avertissement sur les branches non fusionnées dans `develop`, confirmation
interactive, puis contrôle de santé sur `opacplerin.fr` (pages **et** assets statiques,
car un `.htaccess` illisible met tout le statique en 403 pendant que les pages
continuent de répondre 200).

**Ce qu'il ne fait pas**, et qui reste donc à faire à la main :

- il ne migre ni la base de données, ni les `uploads/` ;
- il téléverse sans supprimer : un fichier retiré en local reste en ligne sur OVH ;
- il ne pousse que le plugin `opac-custom` et le thème `opac-theme`. Tout autre
  plugin ou thème installé en prod est ignoré.

Les cibles sont listées explicitement en tête du script : cinq dossiers poussés en
récursif (`plugins/opac-custom`, et `templates/`, `assets/`, `parts/`, `patterns/`
du thème) et quatre fichiers isolés (`wp-content/.htaccess`, plus `functions.php`,
`theme.json` et `style.css` à la racine du thème). Le dossier `tools/` n'en fait pas
partie, il n'est donc jamais téléversé.

**Historique, à lire avant d'ajouter un fichier au thème.** Cette liste a oublié des
cibles deux fois. `assets/` jusqu'au 2026-07-27 : aucune modification de CSS, de JS ou
de police n'était déployée. Puis `parts/`, `patterns/` et les trois fichiers racine du
thème jusqu'au 2026-09-13 : la prod a servi pendant neuf jours l'ancien markup du menu
« S'inscrire » alors que le CSS et le JS correspondants, eux, étaient bien partis. Le
symptôme est silencieux, le site continue de répondre 200. **Tout nouveau fichier ou
dossier ajouté au thème doit être ajouté aux cibles dans le même commit.**

### Sauvegarde avant un déploiement du thème

Le script ne sauvegarde rien. Avant un déploiement qui touche `functions.php` ou
`theme.json`, récupérer une copie de secours, car une erreur fatale dans l'un des deux
donne un écran blanc et le rollback par renommage de dossier ne marche pas pour un
thème (WordPress tomberait sur un thème absent) :

```
sftp opacpler@ftp.cluster115.hosting.ovh.net
sftp> get -r /home/opacpler/demo/wp-content/themes/opac-theme
```

### Et si la prod ne reflète toujours pas les fichiers

Un template-part modifié depuis `/wp-admin > Apparence > Éditeur` est enregistré **en
base**, et il prime alors sur le fichier du thème. Le déploiement n'y changera rien,
puisque la base n'est jamais transportée. Dans ce cas, ouvrir l'élément dans l'éditeur
de site et choisir « Effacer les personnalisations » pour revenir au fichier.

## 1. `.htaccess` racine du site

À la racine du site (au même niveau que `wp-config.php`), avant le bloc `# BEGIN WordPress`, ajouter :

```apache
# Domaine canonique : HTTPS sans www. La condition sur le host evite de
# rediriger un environnement local qui reutiliserait ce fichier.
<IfModule mod_rewrite.c>
    RewriteEngine On

    # www -> domaine canonique, quel que soit le schema entrant.
    RewriteCond %{HTTP_HOST} ^www\.opacplerin\.fr$ [NC]
    RewriteRule ^ https://opacplerin.fr%{REQUEST_URI} [R=301,L]

    # HTTP -> HTTPS. Le second test evite une boucle si le TLS est termine
    # par un reverse proxy qui transmet X-Forwarded-Proto=https a Apache.
    RewriteCond %{HTTP_HOST} ^opacplerin\.fr$ [NC]
    RewriteCond %{HTTPS} !=on
    RewriteCond %{HTTP:X-Forwarded-Proto} !https [NC]
    RewriteRule ^ https://opacplerin.fr%{REQUEST_URI} [R=301,L]
</IfModule>

# OPAC pre-prod hardening : bloque les endpoints sensibles WP.
<FilesMatch "^(xmlrpc\.php|readme\.html|license\.txt|install\.php|wp-config\.php|wp-config-sample\.php)$">
    Require all denied
</FilesMatch>

# Fallback Apache 2.2
<IfModule !mod_authz_core.c>
    <FilesMatch "^(xmlrpc\.php|readme\.html|license\.txt|install\.php|wp-config\.php|wp-config-sample\.php)$">
        Order allow,deny
        Deny from all
    </FilesMatch>
</IfModule>
```

**Pour nginx** (si OVH propose nginx au lieu d'Apache) :

```nginx
if ($host = www.opacplerin.fr) {
    return 301 https://opacplerin.fr$request_uri;
}

# A placer dans le server HTTP port 80.
return 301 https://opacplerin.fr$request_uri;

location ~ ^/(xmlrpc\.php|readme\.html|license\.txt|install\.php|wp-config\.php|wp-config-sample\.php)$ {
    deny all;
    return 403;
}
```

## 2. `wp-config.php`

Juste après `$table_prefix = 'wp_';` et avant le commentaire `/* That's all, stop editing! */`, ajouter :

```php
define( 'DISALLOW_FILE_EDIT', true );
define( 'FORCE_SSL_ADMIN', true );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
```

Justification :
- `DISALLOW_FILE_EDIT` : bloque l'éditeur PHP dans `/wp-admin > Apparence > Éditeur de thème` et `/wp-admin > Extensions > Éditeur de plugin`. Empêche qu'un attaquant qui a compromis un compte admin puisse exécuter du code arbitraire.
- `FORCE_SSL_ADMIN` : force HTTPS sur `/wp-admin` et `/wp-login.php`. Empêche les sessions admin en clair.
- `WP_AUTO_UPDATE_CORE = 'minor'` : auto-update seulement sur les versions mineures (`6.x.Y`), pas les majeures (`6.x → 7.x`) qui peuvent casser le thème/plugin custom.

## 3. PHP runtime (à demander à l'hébergeur OVH)

Dans `php.ini` (ou interface OVH) :

```
expose_php = Off
```

Supprime le header HTTP `X-Powered-By: PHP/X.Y.Z` qui leakait la version PHP exacte (utile pour les exploits ciblés).

## 4. WP Mail SMTP (configurer après accès OVH)

Les 2 formulaires custom (Contact + Inscription) envoient via `wp_mail()`. OVH bloque souvent le relay sans authentification.

Installer le plugin `WP Mail SMTP` (officiel, validé en P0 du projet), configurer avec :
- SMTP host : `ssl0.ovh.net` (ou similaire selon hébergement OVH)
- Port : 465 (SSL) ou 587 (TLS)
- From : `contact@opacplerin.fr`
- Username : `contact@opacplerin.fr`
- Password : (à fournir par l'admin mail OVH)

Tester en envoyant un message via `/contact/` après config.

## 5. Permaliens

Après import du thème + plugin OPAC :
- `/wp-admin > Réglages > Permaliens` → choisir "Nom de l'article"
- Cliquer "Enregistrer" pour flusher les rewrite rules

Vérifier que `/ateliers/`, `/ephemeres/`, `/agenda/`, `/association/`, `/contact/`, `/inscription/`, `/mentions-legales/`, `/politique-de-confidentialite/` répondent toutes en 200.

## 5 bis. Thèmes par défaut à supprimer en prod

Action **manuelle en prod**, à faire une fois : `/wp-admin > Apparence`, supprimer
tout thème `twentytwenty*` encore présent. Le site tourne sur `opac-theme`, qui est
autonome (pas de thème parent) : aucun de ces thèmes n'est utilisé, mais un thème
inactif reste du code exécutable le jour où une faille y est publiée.

Pourquoi ce n'est pas automatique : ces dossiers sont exclus du dépôt
(cf. `.gitignore`), donc les supprimer en local ne les retire pas d'OVH, et le
déploiement SFTP téléverse sans supprimer.

## 6. Migration BDD

Avant la mise en prod :
- Supprimer les 3 inscriptions demo seedées par M7 (`/wp-admin/edit.php?post_type=opac_inscription`)
- Vérifier que les 10 ateliers, 5 stages, 8 events, 15 personnes correspondent bien à la réalité Plérin (à valider avec Katell + Laurence)
## 7. Référencement et indexation

Le SEO technique est codé dans le plugin (`OPAC_SEO`, `class-opac-seo.php`) : meta description, Open Graph, Twitter Card, JSON-LD Schema.org, sitemap. Rien à installer. Restent 3 réglages qui vivent en base (donc non transportés par SFTP) ou hors site, à faire une fois en prod :

1. **Indexation activée** : dans `Réglages > Lecture`, la case « Visibilité par les moteurs de recherche » doit être **décochée**. Si elle est cochée, tout le site passe en `noindex` et n'apparaît jamais sur Google. Piège classique après une phase de préparation.

2. **Titre du site** : dans `Réglages > Général`, le « Titre du site » construit le `<title>` et l'`og:title` de l'accueil. Vérifier qu'il contient « OPAC » et « Plérin » (le mot Plérin distingue l'association des OPAC bailleurs sociaux, qui dominent la recherche « OPAC » seule). Exemple : titre « Association OPAC », slogan « Association culturelle à Plérin depuis 1980 ».

3. **Google Search Console** : ajouter la propriété **domaine** `opacplerin.fr`, vérifiée par un enregistrement **DNS TXT** dans la zone DNS OVH du domaine. Méthode volontairement hors du code et hors admin : c'est une action webmaster ponctuelle (une seule fois), pas une donnée à gérer par l'association. Une fois la propriété vérifiée, soumettre le sitemap `https://opacplerin.fr/wp-sitemap.xml`. Accélère l'indexation et fournit les statistiques de recherche. La vérification par balise HTML n'est pas retenue : elle mettrait un code technique soit dans le code, soit dans l'admin de l'association, sans bénéfice (ce n'est pas un facteur de classement).

### Visibilité locale (levier principal pour « OPAC » cherché autour de Plérin)

- **Fiche Google Business Profile** : créer ou revendiquer la fiche établissement de l'Association OPAC (10A rue fleurie, 22190 Plérin). C'est ce qui fait apparaître l'association sur Google Maps et dans le bloc local des résultats. Gratuit, et c'est le levier le plus fort en local.
- **Backlinks locaux** : obtenir un lien depuis le site de la Ville de Plérin et s'inscrire dans les annuaires d'associations. Construit l'autorité du domaine sur le nom.
- **Redirections** : si l'ancien site utilisait d'autres URLs, mettre en place des redirections 301 des anciennes vers les nouvelles pour conserver le référencement déjà acquis.

## 8. Vérifications post-déploiement

```bash
# Vérifier les security headers présents
curl -I https://opacplerin.fr/

# XMLRPC bloqué (devrait retourner 403)
curl -I https://opacplerin.fr/xmlrpc.php

# readme bloqué (devrait retourner 403)
curl -I https://opacplerin.fr/readme.html

# PHP version masquée (X-Powered-By absent)
curl -I https://opacplerin.fr/ | grep -i powered

# Sitemap accessible
curl https://opacplerin.fr/wp-sitemap.xml
```
