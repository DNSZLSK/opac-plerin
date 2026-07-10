<?php
/**
 * OPAC Custom - Custom Post Types
 *
 * Déclare les 6 CPTs métier du site OPAC. Chaque CPT est conçu pour être
 * administrable par des utilisatrices non-techniques (Katell, Laurence)
 * depuis /wp-admin sans toucher au code.
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_CPTs {

    public static function register() {
        self::register_atelier();
        self::register_stage();
        self::register_event();
        self::register_person();
        self::register_inscription();
        self::register_gallery_item();
    }

    private static function register_atelier() {
        register_post_type( 'opac_atelier', [
            'labels' => [
                'name' => __( 'Ateliers', 'opac-custom' ),
                'singular_name' => __( 'Atelier', 'opac-custom' ),
                'add_new' => __( 'Ajouter', 'opac-custom' ),
                'add_new_item' => __( 'Ajouter un atelier', 'opac-custom' ),
                'edit_item' => __( 'Modifier l\'atelier', 'opac-custom' ),
                'new_item' => __( 'Nouvel atelier', 'opac-custom' ),
                'view_item' => __( 'Voir l\'atelier', 'opac-custom' ),
                'view_items' => __( 'Voir les ateliers', 'opac-custom' ),
                'search_items' => __( 'Rechercher un atelier', 'opac-custom' ),
                'not_found' => __( 'Aucun atelier.', 'opac-custom' ),
                'all_items' => __( 'Tous les ateliers', 'opac-custom' ),
                'menu_name' => __( 'Ateliers', 'opac-custom' ),
            ],
            'description' => __( 'Ateliers à l\'année (sept. → juillet). Tarif annuel.', 'opac-custom' ),
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => 'ateliers',
            'rewrite' => [ 'slug' => 'ateliers', 'with_front' => false ],
            // Sans 'editor' : edition via le formulaire OPAC_Meta_Boxes (pas de Gutenberg).
            'supports' => [ 'title', 'thumbnail', 'revisions' ],
            'menu_icon' => 'dashicons-art',
            'menu_position' => 21,
            'hierarchical' => false,
        ] );
    }

    private static function register_stage() {
        register_post_type( 'opac_stage', [
            'labels' => [
                'name' => __( 'Ateliers éphémères', 'opac-custom' ),
                'singular_name' => __( 'Atelier éphémère', 'opac-custom' ),
                'add_new' => __( 'Ajouter', 'opac-custom' ),
                'add_new_item' => __( 'Ajouter un atelier éphémère', 'opac-custom' ),
                'edit_item' => __( 'Modifier l\'atelier éphémère', 'opac-custom' ),
                'new_item' => __( 'Nouvel atelier éphémère', 'opac-custom' ),
                'all_items' => __( 'Tous les ateliers éphémères', 'opac-custom' ),
                'menu_name' => __( 'Ateliers éphémères', 'opac-custom' ),
            ],
            'description' => __( 'Activités ponctuelles (vacances scolaires + hors vacances). Tarif à la séance.', 'opac-custom' ),
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => 'ephemeres',
            'rewrite' => [ 'slug' => 'ephemeres', 'with_front' => false ],
            // Sans 'editor' : edition via le formulaire OPAC_Meta_Boxes (pas de Gutenberg).
            'supports' => [ 'title', 'thumbnail', 'revisions' ],
            'menu_icon' => 'dashicons-calendar-alt',
            'menu_position' => 22,
        ] );
    }

    private static function register_event() {
        register_post_type( 'opac_event', [
            'labels' => [
                'name' => __( 'Agenda', 'opac-custom' ),
                'singular_name' => __( 'Événement', 'opac-custom' ),
                'add_new' => __( 'Ajouter', 'opac-custom' ),
                'add_new_item' => __( 'Ajouter un événement', 'opac-custom' ),
                'edit_item' => __( 'Modifier l\'événement', 'opac-custom' ),
                'all_items' => __( 'Tous les événements', 'opac-custom' ),
                'menu_name' => __( 'Agenda', 'opac-custom' ),
            ],
            'description' => __( 'Sorties, expositions, AG, forum des assos, Journées du patrimoine, Téléthon, etc.', 'opac-custom' ),
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => 'agenda',
            'rewrite' => [ 'slug' => 'agenda', 'with_front' => false ],
            // Sans 'editor' : description complete editee dans le formulaire OPAC_Meta_Boxes.
            'supports' => [ 'title', 'thumbnail' ],
            'menu_icon' => 'dashicons-calendar',
            'menu_position' => 23,
        ] );
    }

    private static function register_person() {
        register_post_type( 'opac_person', [
            'labels' => [
                'name' => __( 'Équipe', 'opac-custom' ),
                'singular_name' => __( 'Personne', 'opac-custom' ),
                'add_new' => __( 'Ajouter', 'opac-custom' ),
                'add_new_item' => __( 'Ajouter une personne', 'opac-custom' ),
                'edit_item' => __( 'Modifier la personne', 'opac-custom' ),
                'all_items' => __( 'Toutes les personnes', 'opac-custom' ),
                'menu_name' => __( 'Équipe', 'opac-custom' ),
            ],
            'description' => __( 'Membres de l\'équipe : pédagogique, administrative, bureau, conseil d\'administration.', 'opac-custom' ),
            // Pas de page publique individuelle (aucun single template) : on coupe
            // la route front (le permalien menait nulle part) et on masque le lien
            // permalien dans l'editeur (is_post_type_viewable => false), tout en
            // gardant l'UI admin. Meme posture que opac_inscription plus bas.
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            // Sans 'editor' : edition via le formulaire OPAC_Meta_Boxes (pas de Gutenberg).
            'supports' => [ 'title', 'thumbnail' ],
            'menu_icon' => 'dashicons-groups',
            'menu_position' => 24,
        ] );
    }

    private static function register_inscription() {
        register_post_type( 'opac_inscription', [
            'labels' => [
                'name' => __( 'Inscriptions', 'opac-custom' ),
                'singular_name' => __( 'Inscription', 'opac-custom' ),
                'add_new' => __( 'Ajouter', 'opac-custom' ),
                'add_new_item' => __( 'Saisir une inscription', 'opac-custom' ),
                'edit_item' => __( 'Modifier l\'inscription', 'opac-custom' ),
                'all_items' => __( 'Toutes les inscriptions', 'opac-custom' ),
                'menu_name' => __( 'Inscriptions', 'opac-custom' ),
            ],
            'description' => __( 'Demandes d\'inscription en attente de validation manuelle.', 'opac-custom' ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            // Note securite : l'acces aux donnees personnelles est resserre au niveau
            // des OPERATIONS sensibles (export CSV, email groupe, validation, widget,
            // row-actions -> 'edit_others_posts', cf. OPAC_Inscriptions et OPAC_Admin),
            // et NON via les capacites du CPT : remapper celles-ci empoisonne
            // 'edit_others_posts' au niveau global (WP l'enregistre comme meta-cap) et
            // casse ce droit partout, y compris pour l'admin (verifie au harnais). Une
            // restriction complete du menu/liste demanderait un capability_type dedie
            // + attribution des caps aux roles : a faire proprement si besoin.
            // Sans 'custom-fields' : les metas sont presentees en fiche lisible
            // via OPAC_Admin::render_inscription_details_box (plus de champs bruts).
            'supports' => [ 'title' ],
            'menu_icon' => 'dashicons-yes-alt',
            'menu_position' => 25,
        ] );
    }

    private static function register_gallery_item() {
        register_post_type( 'opac_gallery_item', [
            'labels' => [
                'name' => __( 'Galerie', 'opac-custom' ),
                'singular_name' => __( 'Réalisation', 'opac-custom' ),
                'add_new' => __( 'Ajouter', 'opac-custom' ),
                'add_new_item' => __( 'Ajouter une réalisation', 'opac-custom' ),
                'menu_name' => __( 'Galerie', 'opac-custom' ),
            ],
            'description' => __( 'Photos des réalisations des ateliers, liées à un atelier.', 'opac-custom' ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'has_archive' => false,
            'supports' => [ 'title', 'thumbnail', 'custom-fields' ],
            'menu_icon' => 'dashicons-format-gallery',
            'menu_position' => 26,
        ] );
    }
}
