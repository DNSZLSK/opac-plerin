<?php
/**
 * OPAC Custom - Block Bindings sources
 *
 * Sources de binding custom pour formatter les meta fields des CPTs
 * (tarif "X EUR / an", date "Avr." + "2026", places "Places disponibles"
 * a partir du slug "ok"). Utilisees via metadata.bindings dans le block
 * markup avec source="opac/atelier-meta" ou "opac/stage-meta".
 *
 * Nativement, core/post-meta retourne la valeur brute du meta. On a
 * besoin du formatage parce que :
 * - tarif_annuel est stocke en int (335), affiche en "335 EUR / an"
 * - opac_places_dispo est stocke en slug (ok/full/few), affiche en label
 * - opac_date_debut est stocke en "2026-04-15", affiche en "Avr." + "2026"
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Bindings {

    public static function register() {
        if ( ! function_exists( 'register_block_bindings_source' ) ) {
            return; // WP < 6.5
        }

        register_block_bindings_source( 'opac/atelier-meta', [
            'label'              => __( 'OPAC Atelier Meta', 'opac-custom' ),
            'get_value_callback' => [ __CLASS__, 'get_atelier_meta' ],
            'uses_context'       => [ 'postId', 'postType' ],
        ] );

        register_block_bindings_source( 'opac/stage-meta', [
            'label'              => __( 'OPAC Stage Meta', 'opac-custom' ),
            'get_value_callback' => [ __CLASS__, 'get_stage_meta' ],
            'uses_context'       => [ 'postId', 'postType' ],
        ] );

        register_block_bindings_source( 'opac/event-meta', [
            'label'              => __( 'OPAC Event Meta', 'opac-custom' ),
            'get_value_callback' => [ __CLASS__, 'get_event_meta' ],
            'uses_context'       => [ 'postId', 'postType' ],
        ] );

        // Source globale pour lire n'importe quelle option OPAC depuis un bloc.
        // Args: { option: 'opac_home_hero_title' }
        // Usage : permet de binder un wp:heading ou wp:paragraph sur une
        // valeur du control panel admin (M10 autonomie).
        register_block_bindings_source( 'opac/site-option', [
            'label'              => __( 'OPAC Site Option', 'opac-custom' ),
            'get_value_callback' => [ __CLASS__, 'get_site_option' ],
            'uses_context'       => [],
        ] );
    }

    public static function get_site_option( $source_args, $block_instance, $attribute_name ) {
        $key = isset( $source_args['option'] ) ? sanitize_key( $source_args['option'] ) : '';
        if ( ! $key || ! class_exists( 'OPAC_Settings' ) ) {
            return '';
        }

        // Helpers computed : valeurs derivees (count, calcul d'annees).
        if ( $key === 'stats_ateliers_resolved' ) {
            return (string) OPAC_Settings::stats_ateliers();
        }
        if ( $key === 'stats_years_since_founding' ) {
            return (string) OPAC_Settings::years_since_founding();
        }
        // Badge saison : calcule (auto) ou texte manuel selon le reglage.
        if ( $key === 'opac_home_saison_badge' ) {
            return (string) OPAC_Settings::saison_badge();
        }

        // Securite : on n'autorise que les options prefixees opac_.
        if ( strpos( $key, 'opac_' ) !== 0 ) {
            return '';
        }
        $value = OPAC_Settings::get( $key );
        return is_scalar( $value ) ? (string) $value : '';
    }

    /**
     * Source binding pour les meta d'un atelier.
     * Args: { key: 'opac_tarif_annuel' | 'opac_animator' | 'opac_description_courte' | 'opac_places_dispo' }
     */
    public static function get_atelier_meta( $source_args, $block_instance, $attribute_name ) {
        $post_id = self::resolve_post_id( $block_instance );
        if ( ! $post_id ) {
            return '';
        }

        $key = isset( $source_args['key'] ) ? (string) $source_args['key'] : '';
        if ( ! $key ) {
            return '';
        }

        $value = get_post_meta( $post_id, $key, true );

        switch ( $key ) {
            case 'opac_tarif_annuel':
                return OPAC_Labels::tarif_annuel( $value );

            case 'opac_places_dispo':
                $labels = OPAC_Labels::places();
                return isset( $labels[ $value ] ) ? $labels[ $value ] : '';

            case 'opac_animator':
                return self::resolve_animator( $post_id );

            case 'opac_public':
                return self::resolve_public( $post_id );

            // Note speciale : autolink des URLs / emails. Le contenu lie d'un
            // wp:paragraph est injecte via wp_kses_post() (cf. WP_Block::replace_html),
            // donc les <a> generes sont conserves et filtres.
            // Une secretaire y colle parfois un lien : autant le rendre cliquable.
            case 'opac_notice':
                return is_scalar( $value ) ? self::autolink_notice( (string) $value ) : '';

            // Champs ajoutes en M3 : descriptifs longs pour la page single.
            // Pas de transformation, le rendu (line-breaks pour creneaux)
            // est gere cote CSS via white-space: pre-line.
            case 'opac_tagline':
            case 'opac_creneaux_text':
                return is_scalar( $value ) ? (string) $value : '';

            default:
                return is_scalar( $value ) ? (string) $value : '';
        }
    }

    /**
     * Source binding pour les meta d'un stage ephemere.
     * Args: { key: 'opac_tarif_seance' | 'opac_animator' | 'opac_description_courte'
     *       | 'opac_date_debut' | 'opac_public' }
     *       + part: 'month' | 'year' (uniquement pour opac_date_debut)
     */
    public static function get_stage_meta( $source_args, $block_instance, $attribute_name ) {
        $post_id = self::resolve_post_id( $block_instance );
        if ( ! $post_id ) {
            return '';
        }

        $key = isset( $source_args['key'] ) ? (string) $source_args['key'] : '';
        if ( ! $key ) {
            return '';
        }

        $value = get_post_meta( $post_id, $key, true );

        switch ( $key ) {
            case 'opac_tarif_seance':
                return OPAC_Labels::tarif_seance( $value );

            case 'opac_animator':
                return self::resolve_animator( $post_id );

            case 'opac_public':
                return self::resolve_public( $post_id );

            case 'opac_date_debut':
            case 'opac_date_fin':
                if ( ! $value ) {
                    return '';
                }
                $ts = strtotime( $value );
                if ( ! $ts ) {
                    return '';
                }
                $part = isset( $source_args['part'] ) ? (string) $source_args['part'] : 'month';
                if ( $part === 'year' ) {
                    return wp_date( 'Y', $ts );
                }
                // Abreviation FR forcee (independant de la locale WP qui
                // peut etre en_US par defaut sur certains serveurs).
                return OPAC_Calendar::month( (int) wp_date( 'n', $ts ), 'abbr' );

            // Plage de dates lisible (début -> fin), affichée sur la fiche
            // éphémère. Clé virtuelle : ne correspond à aucune meta stockée,
            // composée à la volée depuis opac_date_debut + opac_date_fin.
            case 'opac_date_range':
                return self::format_date_range(
                    (string) get_post_meta( $post_id, 'opac_date_debut', true ),
                    (string) get_post_meta( $post_id, 'opac_date_fin', true )
                );

            // Note speciale : autolink (cf. get_atelier_meta, meme raison). Le <a>
            // survit au wp_kses_post() applique au rendu du paragraphe lie.
            case 'opac_notice':
                return is_scalar( $value ) ? self::autolink_notice( (string) $value ) : '';

            default:
                return is_scalar( $value ) ? (string) $value : '';
        }
    }

    /**
     * Nom de l'animateur affiché. Source unique : la fiche Équipe liée
     * (opac_animator_id) fait foi et son titre est résolu à la volée, donc
     * jamais périmé si la personne est renommée. À défaut d'ID (intervenant
     * ponctuel hors équipe, ou fiche créée avant le picker), on retombe sur la
     * saisie libre opac_animator. Chaîne vide si aucun animateur.
     *
     * Publique : réutilisée par les listes rendues en PHP (blocs serveur) et la
     * colonne admin, pour une seule logique de résolution.
     */
    public static function resolve_animator( $post_id ) {
        $person_id = (int) get_post_meta( $post_id, 'opac_animator_id', true );
        if ( $person_id > 0 ) {
            $person = get_post( $person_id );
            if ( $person && 'opac_person' === $person->post_type && 'publish' === $person->post_status ) {
                return get_the_title( $person );
            }
        }
        return (string) get_post_meta( $post_id, 'opac_animator', true );
    }

    /**
     * Public affiché : catégorie (terme opac_audience) éventuellement affinée
     * par une précision d'âge libre (opac_public_precision), ex. "Enfants" +
     * "6-10 ans" => "Enfants 6-10 ans". Sans catégorie assignée, on retombe sur
     * l'ancienne saisie libre opac_public (fiches d'avant la taxonomie).
     *
     * Publique : réutilisée par les listes rendues en PHP (blocs serveur).
     */
    public static function resolve_public( $post_id ) {
        $terms = wp_get_object_terms( $post_id, 'opac_audience', [ 'fields' => 'all' ] );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            $name      = $terms[0]->name;
            $precision = trim( (string) get_post_meta( $post_id, 'opac_public_precision', true ) );
            return '' !== $precision ? $name . ' ' . $precision : $name;
        }
        return (string) get_post_meta( $post_id, 'opac_public', true );
    }

    /**
     * Formatte une plage de dates FR lisible pour un atelier éphémère, à partir
     * des meta opac_date_debut / opac_date_fin (stockées en "Y-m-d"). Mois en
     * toutes lettres, locale FR forcée (indépendante de la locale serveur).
     *
     *   début + fin même mois   : "Du 15 au 20 juin 2026"
     *   début + fin mois diff.  : "Du 28 juin au 3 juillet 2026"
     *   début + fin années diff : "Du 30 décembre 2026 au 2 janvier 2027"
     *   début seul              : "15 juin 2026"
     *   rien                    : ""
     */
    private static function format_date_range( $debut, $fin ) {
        // Mois en minuscules (milieu de phrase) derives de la source unique.
        $months_fr = array_map( 'strtolower', OPAC_Calendar::months() );
        $parse = static function ( $d ) {
            $ts = $d ? strtotime( $d ) : false;
            if ( ! $ts ) {
                return null;
            }
            return [
                'j' => (int) wp_date( 'j', $ts ),
                'm' => (int) wp_date( 'n', $ts ),
                'y' => (int) wp_date( 'Y', $ts ),
            ];
        };

        $a = $parse( $debut );
        if ( ! $a ) {
            return '';
        }
        $b = $parse( $fin );

        // Date de début seule.
        if ( ! $b ) {
            return sprintf( '%d %s %d', $a['j'], $months_fr[ $a['m'] ], $a['y'] );
        }
        // Une plage d'un seul jour s'affiche comme une date simple.
        if ( $a === $b ) {
            return sprintf( '%d %s %d', $a['j'], $months_fr[ $a['m'] ], $a['y'] );
        }
        // Même mois et même année.
        if ( $a['m'] === $b['m'] && $a['y'] === $b['y'] ) {
            return sprintf( 'Du %d au %d %s %d', $a['j'], $b['j'], $months_fr[ $b['m'] ], $b['y'] );
        }
        // Même année, mois différents.
        if ( $a['y'] === $b['y'] ) {
            return sprintf( 'Du %d %s au %d %s %d', $a['j'], $months_fr[ $a['m'] ], $b['j'], $months_fr[ $b['m'] ], $b['y'] );
        }
        // Années différentes.
        return sprintf( 'Du %d %s %d au %d %s %d', $a['j'], $months_fr[ $a['m'] ], $a['y'], $b['j'], $months_fr[ $b['m'] ], $b['y'] );
    }

    /**
     * Source binding pour les meta d'un event de l'agenda.
     * Args: { key: 'opac_date_event' | 'opac_lieu' | 'opac_description_courte' }
     *       + part: 'full' | 'month' | 'year' (uniquement pour opac_date_event)
     *
     * Format date : "15 juin 2026" (full), "Juin" (month), "2026" (year).
     * Locale FR forcee, independante de la locale serveur.
     */
    public static function get_event_meta( $source_args, $block_instance, $attribute_name ) {
        $post_id = self::resolve_post_id( $block_instance );
        if ( ! $post_id ) {
            return '';
        }

        $key = isset( $source_args['key'] ) ? (string) $source_args['key'] : '';
        if ( ! $key ) {
            return '';
        }

        $value = get_post_meta( $post_id, $key, true );

        switch ( $key ) {
            case 'opac_date_event':
                if ( ! $value ) {
                    return '';
                }
                $ts = strtotime( $value );
                if ( ! $ts ) {
                    return '';
                }
                $months_fr = array_map( 'strtolower', OPAC_Calendar::months() );
                $n = (int) wp_date( 'n', $ts );
                $part = isset( $source_args['part'] ) ? (string) $source_args['part'] : 'full';
                if ( $part === 'year' ) {
                    return wp_date( 'Y', $ts );
                }
                if ( $part === 'month' ) {
                    return isset( $months_fr[ $n ] ) ? ucfirst( $months_fr[ $n ] ) : '';
                }
                $day  = (int) wp_date( 'j', $ts );
                $year = wp_date( 'Y', $ts );
                $mois = isset( $months_fr[ $n ] ) ? $months_fr[ $n ] : '';
                return trim( $day . ' ' . $mois . ' ' . $year );

            default:
                return is_scalar( $value ) ? (string) $value : '';
        }
    }

    /**
     * Autolink d'une note libre : URLs et emails cliquables (make_clickable),
     * puis ouverture des liens http(s) dans un nouvel onglet (target="_blank"
     * + rel="noopener noreferrer" pour la securite). Les mailto: sont laisses
     * dans l'onglet courant. Le rel existant (nofollow pose par make_clickable)
     * est complete sans doublon. La sortie repasse par wp_kses_post() au rendu
     * du paragraphe lie, qui autorise a[href|rel|target] : rien n'est perdu.
     */
    private static function autolink_notice( $value ) {
        $html = make_clickable( $value );
        return preg_replace_callback(
            '/<a\s+href="([^"]*)"([^>]*)>/i',
            static function ( $m ) {
                $href  = $m[1];
                $attrs = $m[2];
                // Nouvel onglet uniquement pour les liens web (pas mailto/tel).
                if ( ! preg_match( '#^https?://#i', $href ) ) {
                    return $m[0];
                }
                if ( preg_match( '/target=/i', $attrs ) ) {
                    return $m[0]; // deja un target, on ne double pas
                }
                if ( preg_match( '/rel="([^"]*)"/i', $attrs, $rm ) ) {
                    $rel = $rm[1];
                    foreach ( [ 'noopener', 'noreferrer' ] as $token ) {
                        if ( false === strpos( $rel, $token ) ) {
                            $rel .= ' ' . $token;
                        }
                    }
                    $attrs = preg_replace( '/rel="[^"]*"/i', 'rel="' . trim( $rel ) . '"', $attrs );
                } else {
                    $attrs .= ' rel="noopener noreferrer"';
                }
                return '<a href="' . $href . '"' . $attrs . ' target="_blank">';
            },
            $html
        );
    }

    /**
     * Resolve le post ID depuis le contexte du block ou du loop courant.
     */
    private static function resolve_post_id( $block_instance ) {
        if ( isset( $block_instance->context['postId'] ) && $block_instance->context['postId'] ) {
            return (int) $block_instance->context['postId'];
        }
        $current = get_the_ID();
        return $current ? (int) $current : 0;
    }
}
