<?php
/**
 * OPAC Custom - Taxonomies métier
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Taxonomies {

    public static function register() {
        // Périodes des stages (Automne, Hiver, Printemps, Été).
        register_taxonomy( 'opac_period', 'opac_stage', [
            'labels' => [
                'name' => __( 'Périodes', 'opac-custom' ),
                'singular_name' => __( 'Période', 'opac-custom' ),
                'add_new_item' => __( 'Ajouter une période', 'opac-custom' ),
                'menu_name' => __( 'Périodes', 'opac-custom' ),
            ],
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'show_admin_column' => true,
            'rewrite' => [ 'slug' => 'periode', 'with_front' => false ],
        ] );

        // Catégories d'événements (Sortie, Expo, Association, Éphémère, Partenaire).
        register_taxonomy( 'opac_event_cat', 'opac_event', [
            'labels' => [
                'name' => __( 'Catégories d\'événements', 'opac-custom' ),
                'singular_name' => __( 'Catégorie', 'opac-custom' ),
                'add_new_item' => __( 'Ajouter une catégorie', 'opac-custom' ),
                'menu_name' => __( 'Catégories', 'opac-custom' ),
            ],
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'show_admin_column' => true,
            'rewrite' => [ 'slug' => 'categorie-evenement', 'with_front' => false ],
        ] );

        // Types de personne (Pédagogique, Administrative, Bureau, CA).
        register_taxonomy( 'opac_person_type', 'opac_person', [
            'labels' => [
                'name' => __( 'Types de personne', 'opac-custom' ),
                'singular_name' => __( 'Type', 'opac-custom' ),
                'add_new_item' => __( 'Ajouter un type', 'opac-custom' ),
                'menu_name' => __( 'Types', 'opac-custom' ),
            ],
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'show_admin_column' => true,
        ] );

        // Statut des inscriptions (En attente, Validée, Refusée, Liste d'attente).
        register_taxonomy( 'opac_inscription_status', 'opac_inscription', [
            'labels' => [
                'name' => __( 'Statuts d\'inscription', 'opac-custom' ),
                'singular_name' => __( 'Statut', 'opac-custom' ),
                'add_new_item' => __( 'Ajouter un statut', 'opac-custom' ),
                'menu_name' => __( 'Statuts', 'opac-custom' ),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => false,
            'hierarchical' => false,
            'show_admin_column' => true,
        ] );
    }

    /**
     * Seed des termes par défaut. Appelé une fois à l'activation du plugin.
     * Les admins peuvent ensuite ajouter/modifier/supprimer librement.
     */
    public static function seed_default_terms() {
        $defaults = [
            'opac_period' => [
                'automne' => 'Vacances d\'automne',
                'hiver' => 'Vacances d\'hiver',
                'printemps' => 'Vacances de printemps',
                'ete' => 'Été',
            ],
            'opac_event_cat' => [
                'sortie' => 'Sortie',
                'expo' => 'Exposition',
                'association' => 'Association',
                'ephemere' => 'Éphémère',
                'partenaire' => 'Partenaire',
            ],
            'opac_person_type' => [
                'pedagogique' => 'Équipe pédagogique',
                'administrative' => 'Équipe administrative',
                'bureau' => 'Bureau',
                'conseil-administration' => 'Conseil d\'administration',
            ],
            'opac_inscription_status' => [
                'en-attente' => 'En attente',
                'validee' => 'Validée',
                'refusee' => 'Refusée',
                'liste-attente' => 'Liste d\'attente',
            ],
        ];

        foreach ( $defaults as $taxonomy => $terms ) {
            foreach ( $terms as $slug => $name ) {
                if ( ! term_exists( $slug, $taxonomy ) ) {
                    wp_insert_term( $name, $taxonomy, [ 'slug' => $slug ] );
                }
            }
        }
    }
}
