# OPAC Plérin - Notes de déploiement

Ce dépôt versionne uniquement `wp-content/` (thème + plugin custom). Plusieurs hardening de sécurité doivent être appliqués à des fichiers **hors `wp-content/`** au moment du déploiement.

## 1. `.htaccess` racine du site

À la racine du site (au même niveau que `wp-config.php`), avant le bloc `# BEGIN WordPress`, ajouter :

```apache
# OPAC pre-prod hardening : bloque les endpoints sensibles WP.
<FilesMatch "^(xmlrpc\.php|readme\.html|install\.php|wp-config\.php|wp-config-sample\.php)$">
    Require all denied
</FilesMatch>

# Fallback Apache 2.2
<IfModule !mod_authz_core.c>
    <FilesMatch "^(xmlrpc\.php|readme\.html|install\.php|wp-config\.php|wp-config-sample\.php)$">
        Order allow,deny
        Deny from all
    </FilesMatch>
</IfModule>
```

**Pour nginx** (si OVH propose nginx au lieu d'Apache) :

```nginx
location ~ ^/(xmlrpc\.php|readme\.html|install\.php|wp-config\.php|wp-config-sample\.php)$ {
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

## 6. Migration BDD

Avant la mise en prod :
- Supprimer les 3 inscriptions demo seedées par M7 (`/wp-admin/edit.php?post_type=opac_inscription`)
- Vérifier que les 10 ateliers, 5 stages, 8 events, 15 personnes correspondent bien à la réalité Plérin (à valider avec Katell + Laurence)
- Supprimer le term orphelin `conseil-administration` si non utilisé

## 7. Vérifications post-déploiement

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
