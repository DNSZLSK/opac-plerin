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

    /**
     * Version du schéma de termes. Incrémenter quand on ajoute une taxonomie
     * seedée ou qu'on change un libellé par défaut : maybe_upgrade() rejoue
     * alors le seed (idempotent) sur les installs déjà activées, sans exiger
     * une réactivation manuelle du plugin.
     */
    const DB_VERSION = 2;

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

        // Public visé (Tous publics, Adultes, Ados, Enfants, Familles), partagé
        // par les ateliers à l'année et les éphémères. Vocabulaire contrôlé,
        // extensible par l'équipe : remplace la saisie libre (source de "Adultes"
        // / "adulte" / "ADULTES" incohérents). La tranche d'âge précise reste
        // libre via la meta opac_public_precision, composée à l'affichage.
        register_taxonomy( 'opac_audience', [ 'opac_atelier', 'opac_stage' ], [
            'labels' => [
                'name' => __( 'Publics', 'opac-custom' ),
                'singular_name' => __( 'Public', 'opac-custom' ),
                'add_new_item' => __( 'Ajouter un public', 'opac-custom' ),
                'menu_name' => __( 'Publics', 'opac-custom' ),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'show_admin_column' => true,
        ] );

        // Types de personne (Animateurs, Administrative, Bureau, CA).
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
                'pedagogique' => 'Animateurs',
                'administrative' => 'Équipe administrative',
                'bureau' => 'Bureau',
                'conseil-administration' => 'Conseil d\'administration',
            ],
            'opac_audience' => [
                'tous-publics' => 'Tous publics',
                'adultes' => 'Adultes',
                'ados' => 'Ados',
                'enfants' => 'Enfants',
                'familles' => 'Familles',
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

    /**
     * Migration légère jouée sur admin_init : met à niveau les installs déjà
     * activées quand DB_VERSION change (nouveaux termes seedés, libellés par
     * défaut modifiés), sans réactivation manuelle. Idempotente et sans effet
     * une fois à jour (option opac_tax_db_version).
     */
    public static function maybe_upgrade() {
        if ( (int) get_option( 'opac_tax_db_version', 0 ) >= self::DB_VERSION ) {
            return;
        }

        // Crée les termes manquants (ex. opac_audience introduit en v2).
        self::seed_default_terms();

        // Renomme l'ancien défaut "Équipe pédagogique" en "Animateurs" (v2),
        // uniquement s'il porte encore l'ancien libellé : on n'écrase pas un
        // renommage volontaire de l'équipe.
        $term = get_term_by( 'slug', 'pedagogique', 'opac_person_type' );
        if ( $term && ! is_wp_error( $term ) && 'Équipe pédagogique' === $term->name ) {
            wp_update_term( $term->term_id, 'opac_person_type', [ 'name' => 'Animateurs' ] );
        }

        update_option( 'opac_tax_db_version', self::DB_VERSION );
    }
}
