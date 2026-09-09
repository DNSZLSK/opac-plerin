=== OPAC Custom ===
Contributors: dnszlsk
Tags: cpt, association, opac, custom
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.2.0
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
* `opac_gallery_item` - Anciennes réalisations photographiées, conservées pour compatibilité (non public).

= Taxonomies =

* `opac_period` - Périodes des stages (Automne, Hiver, Printemps, Été).
* `opac_event_cat` - Catégories d'événements (Sortie, Expo, Association, Éphémère, Partenaire).
* `opac_person_type` - Types de membre (Pédagogique, Administrative, Bureau, CA).
* `opac_inscription_status` - Statut des inscriptions (En attente, Validée, Refusée, Liste d'attente).

= Méta fields =

Chaque CPT a ses méta fields enregistrés via `register_post_meta` avec `show_in_rest: true` pour exploitation depuis le block editor. Inscriptions : meta non exposés en REST (données personnelles).

= Modules =

* Formulaires publics contact + inscription (nonce, honeypot, rate-limit), pipeline de validation admin (Valider / Refuser / Liste d'attente) avec emails transactionnels à templates éditables.
* Créneaux structurés par atelier (jour, horaires, tarif, capacité), comptage des places, liste d'attente automatique et promotion au désistement.
* Panel « OPAC Réglages » : coordonnées, tarifs d'adhésion, saison, hero, templates d'emails, pages légales (éditeur visuel).
* Blocs serveur et block bindings pour les templates FSE (listes agenda/éphémères, grille équipe, galerie, boutons d'inscription...).
* SEO (meta description, Open Graph, JSON-LD Schema.org), PWA (manifest + service worker network-first), durcissement sécurité, conformité RGPD (purge automatique, export/effacement natifs).

== Installation ==

1. Copier le dossier `opac-custom` dans `wp-content/plugins/`.
2. Activer le plugin via le menu Extensions.
3. Les termes par défaut des taxonomies (Automne, Printemps, En attente, etc.) sont seedés automatiquement à l'activation.

== Compatibilité ==

* WordPress 6.5+
* PHP 8.0+
* Compatible avec n'importe quel thème (testé avec `opac-theme`).

== Changelog ==

= 0.2.0 =
* Ajout des photos de carrousel directement dans les fiches Atelier, Éphémère et Agenda : import multiple, retrait et réorganisation.
* Migration automatique et non destructive des anciennes galeries.

= 0.1.0 =
* Version initiale livrée pour la refonte opacplerin.fr : CPTs, taxonomies, meta fields, et l'ensemble des modules décrits ci-dessus (inscriptions, réglages, blocs serveur, SEO, PWA, sécurité, RGPD).
* L'historique détaillé des évolutions est porté par le dépôt git du projet.
