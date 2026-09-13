# Association OPAC, refonte du site opacplerin.fr

Refonte complète du site de l'**Association OPAC** (Office Plérinais d'Action Culturelle),
association loi 1901 à Plérin (22), qui propose des ateliers culturels et artistiques à
plus de 500 adhérents.

**En production : [opacplerin.fr](https://opacplerin.fr)**

![WordPress](https://img.shields.io/badge/WordPress-7.1-21759B?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Thème FSE](https://img.shields.io/badge/Thème-FSE%20natif-black)
![Extensions tierces](https://img.shields.io/badge/Extensions%20tierces-0-success)
![Licence](https://img.shields.io/badge/Licence-GPL--2.0-blue)

---

## Le parti pris : zéro extension tierce

Le site tourne sur le cœur de WordPress, **un thème maison et un plugin maison**. Pas de
page builder, pas de Contact Form 7, pas de Yoast, pas de plugin de sécurité.

Ce n'est pas un exercice de style, c'est une contrainte de livraison. L'association doit
pouvoir maintenir son site **en autonomie complète** après la fin du stage, sans budget de
maintenance et sans prestataire. Chaque extension tierce aurait ajouté une dépendance à
mettre à jour, une source de panne au renouvellement d'une licence, et une surface
d'attaque. Le corollaire assumé : ce qui manque, il faut l'écrire.

En pratique, ça se traduit par :

| Besoin | Réponse ici | Plutôt que |
|---|---|---|
| Référencement, JSON-LD, sitemap | `class-opac-seo.php` | Yoast / RankMath |
| Formulaires (contact, inscription) | `class-opac-contact.php`, `class-opac-inscriptions.php` | Contact Form 7 / WPForms |
| Durcissement, anti-brute-force | `class-opac-security.php` | Wordfence |
| RGPD, purge, droits des personnes | `class-opac-rgpd.php` | Complianz |
| Application installable | `class-opac-pwa.php` | SuperPWA |
| Mise en page | `theme.json` + templates FSE | Elementor / Divi |

Une nuance, pour que la formule reste exacte : la production ne charge **aucune** extension
tierce, ce qui se vérifie de l'extérieur. Les courriels partent par `wp_mail()` et le relais
de l'hébergeur. Si ce relais venait à poser problème, un plugin SMTP serait le repli, et il
serait la seule exception : la procédure est documentée dans [`DEPLOY.md`](DEPLOY.md).

Ça a aussi un effet sur la sécurité : la très grande majorité des compromissions WordPress
passe par une faille d'extension tierce. Sans extension tierce, cette surface disparaît.

---

## Architecture

La séparation est stricte, et c'est ce qui permet de faire évoluer l'un sans casser l'autre.

```
wp-content/
├── plugins/opac-custom/     ← le métier : données et logique
│   ├── includes/            18 classes, ~10 500 lignes
│   ├── assets/
│   └── tests/               16 harnais, ~1 850 lignes
├── themes/opac-theme/       ← la présentation
│   ├── theme.json           design tokens (couleurs, typo, espacements)
│   ├── templates/           19 templates FSE
│   ├── parts/               en-tête, pied de page
│   ├── patterns/            5 compositions réutilisables
│   └── assets/              CSS, JS, polices auto-hébergées
└── tools/deploy-demo.ps1    ← déploiement SFTP automatisé
```

**Le plugin porte les données**, donc il survit à un changement de thème : 6 types de contenu
(`opac_atelier`, `opac_stage`, `opac_event`, `opac_person`, `opac_gallery_item`,
`opac_inscription`), 5 taxonomies, les métadonnées, les écrans d'administration et les blocs
dynamiques.

**Le thème ne porte que l'affichage.** Le style principal vit dans `theme.json`, pour rester
modifiable depuis l'éditeur de site sans toucher au code.

---

## Fonctionnalités

- **Ateliers à l'année** et **ateliers éphémères** (stages courts), avec créneaux, capacités,
  tarifs, publics et périodes
- **Agenda** des événements, avec gestion des dates multi-jours et masquage automatique du passé
- **Inscription en ligne** : formulaire public, workflow à deux phases, liste d'attente,
  anti-doublon, et export « Copier pour Excel » qui alimente les fichiers existants du
  secrétariat au lieu de les remplacer
- **Trombinoscope** de l'équipe et du conseil d'administration
- **Galerie** photo avec visionneuse et navigation tactile
- **PWA** installable sur mobile
- **Référencement** : métadonnées, Open Graph, JSON-LD Schema.org, sitemap
- **Accessibilité** : navigation au clavier, ARIA, et comportements fonctionnels sans JavaScript

---

## Sécurité et RGPD

Le durcissement est codé, pas installé (`class-opac-security.php`) :

- En-têtes HTTP : CSP, HSTS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy`
- XML-RPC désactivé, version de WordPress masquée, listing de répertoire coupé
- **Anti-énumération d'utilisateur** : endpoint REST `users` verrouillé, archives auteur en 404
- **Anti-brute-force** : verrou par IP sur transient, refus inconditionnel pendant la fenêtre
  de blocage, mots de passe d'application désactivés
- Exécution de PHP bloquée dans `uploads/`

Côté RGPD (`class-opac-rgpd.php`) : purge automatique des inscriptions selon une durée de
conservation paramétrable, branchement sur les outils natifs d'export et d'effacement des
données personnelles, polices auto-hébergées et carte OpenStreetMap pour éviter tout transfert
d'adresse IP vers un tiers.

---

## Développement local

La racine du dépôt **est** `wp-content/`. Il ne contient que le thème et le plugin maison :
le cœur de WordPress, les `uploads/` et les thèmes par défaut sont volontairement exclus
(cf. [`.gitignore`](.gitignore)). Il se greffe donc sur une installation WordPress existante.

```bash
# Depuis un site WordPress local (Local by Flywheel, Lando, wp-env…)
cd chemin/vers/le-site/app/public/wp-content

git init
git remote add origin https://github.com/DNSZLSK/opac-plerin.git
git fetch origin
git checkout -t origin/develop     # dépose themes/opac-theme et plugins/opac-custom
```

Les médias ne sont pas versionnés : `uploads/` restera vide sur une installation neuve, et les
fiches afficheront leurs visuels de repli. Pour retrouver le site à l'identique, il faut aussi
la base de données et les `uploads/`, tous deux hors dépôt.

Puis, dans l'administration : activer le thème **OPAC Plérin** et l'extension **OPAC Custom**,
et enregistrer les permaliens une fois (`Réglages > Permaliens`) pour régénérer les règles de
réécriture.

### Tests

Les harnais testent le vrai code en isolation, avec les fonctions WordPress simulées : ni base
de données, ni site lancé, ni dépendance à installer.

```bash
php wp-content/plugins/opac-custom/tests/run-tests.php
```

16 harnais couvrent les calculs de dates, la logique d'inscription, les limitations de débit,
l'export Excel, les redirections et le verrou de connexion. Un harnais d'intégration séparé
(`tests/e2e-login-lockout.php`) s'exécute contre un vrai WordPress, parce que la priorité d'un
filtre ne se teste pas avec des simulacres.

### Déploiement

```powershell
powershell -ExecutionPolicy Bypass -File "wp-content/tools/deploy-demo.ps1"
```

Upload SFTP récursif de dossiers entiers, jamais fichier par fichier, pour qu'un fichier PHP
tronqué ne casse pas le site. Garde-fou sur la branche git, avertissement sur les branches non
fusionnées, `chmod` automatique, puis contrôle de santé sur les pages **et** les ressources
statiques. Détail complet et configuration serveur dans [`DEPLOY.md`](DEPLOY.md).

---

## Conventions

- Branche d'intégration : `develop`. Aucun commit direct dessus.
- Une branche `feature/` ou `fix/` par sujet, fusionnée en `--no-ff` pour garder la trace du
  regroupement dans l'historique.
- Messages de commit en français, qui expliquent **pourquoi** le changement est fait, pas ce
  que le diff montre déjà.

---

## Contexte

Projet réalisé pendant un stage de la formation **Concepteur Développeur d'Applications**
(AFPA), du cadrage du besoin avec l'association jusqu'à la mise en production.

Développement : **DNSZLSK** ([kewin.io](https://kewin.io))

## Licence

GPL-2.0-or-later, comme WordPress.
