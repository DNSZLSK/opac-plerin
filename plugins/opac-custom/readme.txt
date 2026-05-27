=== OPAC Custom ===
Contributors: dnszlsk
Tags: cpt, association, opac, custom
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin métier pour le site OPAC Plérin. Fournit les CPTs (ateliers, stages, événements, équipe, inscriptions, galerie), taxonomies, meta fields et interfaces admin custom. Indépendant du thème - porte les données et la logique métier de l'association.

== Description ==

Plugin développé spécifiquement pour `opacplerin.fr` (Association OPAC, Office Plérinais d'Action Culturelle, asso loi 1901 fondée en 1980). Pensé pour être maintenu en autonomie par l'équipe administrative non-technique depuis /wp-admin.

= Types de contenu déclarés =

* `opac_atelier` - Ateliers à l'année (céramique, sculpture, dessin, etc.). Tarif annuel.
* `opac_stage` - Ateliers éphémères / stages ponctuels. Tarif à la séance.
* `opac_event` - Événements de l'agenda (sorties, expositions, AG, forum des assos, etc.).
* `opac_person` - Membres de l'équipe (pédagogique, administrative, bureau, CA).
* `opac_inscription` - Demandes d'inscription en attente de validation manuelle (non public).
* `opac_gallery_item` - Réalisations photographiées des ateliers (non public).

= Taxonomies =

* `opac_period` - Périodes des stages (Automne, Hiver, Printemps, Été).
* `opac_event_cat` - Catégories d'événements (Sortie, Expo, Association, Éphémère, Partenaire).
* `opac_person_type` - Types de membre (Pédagogique, Administrative, Bureau, CA).
* `opac_inscription_status` - Statut des inscriptions (En attente, Validée, Refusée, Liste d'attente).

= Méta fields =

Chaque CPT a ses méta fields enregistrés via `register_post_meta` avec `show_in_rest: true` pour exploitation depuis le block editor. Inscriptions : meta non exposés en REST (données personnelles).

== Installation ==

1. Copier le dossier `opac-custom` dans `wp-content/plugins/`.
2. Activer le plugin via le menu Extensions.
3. Les termes par défaut des taxonomies (Automne, Printemps, En attente, etc.) sont seedés automatiquement à l'activation.

== Compatibilité ==

* WordPress 6.5+
* PHP 8.0+
* Compatible avec n'importe quel thème (testé avec `opac-theme`).

== Changelog ==

= 0.1.0 - 2026-05-27 =
* Squelette initial : CPTs, taxonomies, meta fields, dashboard widget « Inscriptions en attente ».
* Colonnes custom dans la liste des ateliers (animateur, tarif annuel, places) et des inscriptions (email, atelier, statut).
* Seeding automatique des termes par défaut à l'activation.

== Roadmap ==

* M3 - Templates atelier (single + archive) côté thème.
* M7 - Module Inscriptions : formulaire frontend, validation admin avec actions Valider/Refuser/Liste d'attente, emails transactionnels.
* M8 - JSON-LD Schema.org (Organization, Course, Event) injecté depuis le plugin.
