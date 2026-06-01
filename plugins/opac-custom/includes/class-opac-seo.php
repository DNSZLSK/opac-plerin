<?php
/**
 * OPAC Custom - SEO layer
 *
 * Injection de meta description + Open Graph + Twitter Card + JSON-LD
 * Schema.org dans le <head> de chaque page, selon le contexte detecte
 * (home, single CPT, archive, page WP).
 *
 * Pourquoi pas Yoast / RankMath ?
 * - Philosophie projet : minimum de plugins tiers
 * - 1 site, 1 langue, 6 CPTs custom : Yoast Premium n'apporte rien ici
 * - Les meta sont deja structurees (opac_tagline, opac_date_event, etc.)
 *   donc l'extraction est triviale
 *
 * Types Schema.org utilises :
 * - Organization (toujours, persistent)
 * - WebSite (home uniquement)
 * - Course (single opac_atelier)
 * - Event (single opac_stage, single opac_event)
 * - BreadcrumbList (sur les singles avec breadcrumb HTML)
 *
 * Brand naming respecte (regle directeur) :
 * - name = "Association OPAC"
 * - alternateName = "Office Plerinais d'Action Culturelle"
 * - legalName = "Association Office Plerinais d'Action Culturelle"
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_SEO {

    const ORG_ID = '#organization';

    public static function register() {
        // Priorite 1 pour preceder la plupart des hooks WP/theme.
        add_action( 'wp_head', [ __CLASS__, 'render_head' ], 1 );

        // noindex la page /inscription/ (form sans valeur SEO).
        add_filter( 'wp_robots', [ __CLASS__, 'filter_robots' ] );

        // Exclut du sitemap les CPTs sans single template (opac_person,
        // opac_gallery_item) pour eviter les 404 crawler.
        add_filter( 'wp_sitemaps_post_types', [ __CLASS__, 'filter_sitemap_post_types' ] );
        add_filter( 'wp_sitemaps_taxonomies', [ __CLASS__, 'filter_sitemap_taxonomies' ] );
    }

    public static function render_head() {
        $ctx = self::get_context();
        if ( ! $ctx ) {
            return;
        }

        // Meta description.
        if ( ! empty( $ctx['description'] ) ) {
            printf(
                '<meta name="description" content="%s" />' . "\n",
                esc_attr( $ctx['description'] )
            );
        }

        // Resolution de l'image de partage social.
        // 1. Featured image du post courant (si singular).
        // 2. Fallback : Site Icon WP (configurable par Katell sans dev via
        //    Apparence > Personnaliser > Identite du site).
        $og_image = self::resolve_og_image();

        // Open Graph.
        $site_name = 'Association OPAC';
        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
        echo '<meta property="og:locale" content="fr_FR" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr( $ctx['og_type'] ) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $ctx['title'] ) . '" />' . "\n";
        if ( ! empty( $ctx['description'] ) ) {
            echo '<meta property="og:description" content="' . esc_attr( $ctx['description'] ) . '" />' . "\n";
        }
        echo '<meta property="og:url" content="' . esc_url( $ctx['url'] ) . '" />' . "\n";
        if ( $og_image ) {
            echo '<meta property="og:image" content="' . esc_url( $og_image ) . '" />' . "\n";
        }

        // Twitter Card : summary_large_image si on a une vraie image OG,
        // sinon summary (card texte).
        $twitter_card = $og_image ? 'summary_large_image' : 'summary';
        echo '<meta name="twitter:card" content="' . esc_attr( $twitter_card ) . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $ctx['title'] ) . '" />' . "\n";
        if ( ! empty( $ctx['description'] ) ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $ctx['description'] ) . '" />' . "\n";
        }
        if ( $og_image ) {
            echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '" />' . "\n";
        }

        // JSON-LD : toujours Organization. Plus type-specifique selon contexte.
        self::render_jsonld( self::build_organization() );

        if ( is_front_page() || is_home() ) {
            self::render_jsonld( self::build_website() );
        }

        if ( is_singular( 'opac_atelier' ) ) {
            self::render_jsonld( self::build_course( get_queried_object_id() ) );
            self::render_jsonld( self::build_breadcrumb_singular( get_queried_object_id(), 'Ateliers à l\'année', '/ateliers/' ) );
        } elseif ( is_singular( 'opac_stage' ) ) {
            self::render_jsonld( self::build_event( get_queried_object_id(), 'opac_stage' ) );
            self::render_jsonld( self::build_breadcrumb_singular( get_queried_object_id(), 'Ateliers éphémères', '/ephemeres/' ) );
        } elseif ( is_singular( 'opac_event' ) ) {
            self::render_jsonld( self::build_event( get_queried_object_id(), 'opac_event' ) );
            self::render_jsonld( self::build_breadcrumb_singular( get_queried_object_id(), 'Agenda', '/agenda/' ) );
        }
    }

    /**
     * Detecte le contexte de la page et retourne :
     * { title, description, url, og_type }
     */
    private static function get_context() {
        $url = self::current_url();

        if ( is_front_page() || is_home() ) {
            return [
                'title'       => get_bloginfo( 'name' ) . ' - ' . get_bloginfo( 'description' ),
                'description' => 'Association culturelle de Plérin fondée en 1980. Ateliers d\'expression artistique, sorties, expositions et événements ouverts à tous.',
                'url'         => $url,
                'og_type'     => 'website',
            ];
        }

        if ( is_singular( 'opac_atelier' ) ) {
            $post_id = get_queried_object_id();
            $tagline = (string) get_post_meta( $post_id, 'opac_tagline', true );
            $courte  = (string) get_post_meta( $post_id, 'opac_description_courte', true );
            return [
                'title'       => get_the_title( $post_id ),
                'description' => $tagline ?: $courte,
                'url'         => $url,
                'og_type'     => 'article',
            ];
        }

        if ( is_singular( 'opac_stage' ) ) {
            $post_id = get_queried_object_id();
            $tagline = (string) get_post_meta( $post_id, 'opac_tagline', true );
            $courte  = (string) get_post_meta( $post_id, 'opac_description_courte', true );
            return [
                'title'       => get_the_title( $post_id ),
                'description' => $tagline ?: $courte,
                'url'         => $url,
                'og_type'     => 'article',
            ];
        }

        if ( is_singular( 'opac_event' ) ) {
            $post_id = get_queried_object_id();
            $courte  = (string) get_post_meta( $post_id, 'opac_description_courte', true );
            return [
                'title'       => get_the_title( $post_id ),
                'description' => $courte,
                'url'         => $url,
                'og_type'     => 'article',
            ];
        }

        if ( is_post_type_archive( 'opac_atelier' ) ) {
            return [
                'title'       => 'Ateliers à l\'année - ' . get_bloginfo( 'name' ),
                'description' => 'Ateliers d\'expression culturelle rythmés sur le calendrier scolaire, de septembre à juillet. Inscription possible en cours de saison selon les places.',
                'url'         => $url,
                'og_type'     => 'website',
            ];
        }

        if ( is_post_type_archive( 'opac_stage' ) ) {
            return [
                'title'       => 'Ateliers éphémères - ' . get_bloginfo( 'name' ),
                'description' => 'Activités ponctuelles pendant les vacances scolaires et en cours d\'année. Enfants et adultes. Inscription à l\'accueil ou en ligne.',
                'url'         => $url,
                'og_type'     => 'website',
            ];
        }

        if ( is_post_type_archive( 'opac_event' ) ) {
            return [
                'title'       => 'Agenda - ' . get_bloginfo( 'name' ),
                'description' => 'Sorties, expositions, événements d\'association et partenariats programmés par l\'OPAC tout au long de l\'année.',
                'url'         => $url,
                'og_type'     => 'website',
            ];
        }

        if ( is_page( 'association' ) ) {
            return [
                'title'       => 'L\'association - ' . get_bloginfo( 'name' ),
                'description' => 'Association loi 1901 fondée en novembre 1980, l\'Office Plérinais d\'Action Culturelle propose toute l\'année des ateliers d\'expression artistique et culturelle.',
                'url'         => $url,
                'og_type'     => 'website',
            ];
        }

        if ( is_page( 'contact' ) ) {
            return [
                'title'       => 'Nous contacter - ' . get_bloginfo( 'name' ),
                'description' => 'Contactez l\'Association OPAC : 02 96 74 53 08, contact@opacplerin.fr, ou rendez-vous au secrétariat 10A rue fleurie, 22190 Plérin, du lundi au vendredi 14h15 à 17h45.',
                'url'         => $url,
                'og_type'     => 'website',
            ];
        }

        if ( is_page( 'inscription' ) ) {
            return [
                'title'       => 'Inscription - ' . get_bloginfo( 'name' ),
                'description' => 'Formulaire de demande d\'inscription aux ateliers à l\'année et ateliers éphémères de l\'Association OPAC.',
                'url'         => $url,
                'og_type'     => 'website',
            ];
        }

        // Fallback : titre + tagline du site.
        return [
            'title'       => wp_get_document_title(),
            'description' => get_bloginfo( 'description' ),
            'url'         => $url,
            'og_type'     => 'website',
        ];
    }

    /**
     * Schema.org Organization, 100% piloté par le control panel admin
     * (OPAC > Réglages > Coordonnées). Si l'asso change d'adresse ou
     * de telephone, le JSON-LD reflete automatiquement.
     */
    private static function build_organization() {
        $home = home_url( '/' );
        $has_settings = class_exists( 'OPAC_Settings' );

        $tel_raw   = $has_settings ? OPAC_Settings::get( 'opac_org_phone_accueil' ) : '02 96 74 53 08';
        $tel_clean = '+33-' . ltrim( preg_replace( '/[^\d]/', '-', (string) $tel_raw ), '0' );

        $founding = $has_settings ? (int) OPAC_Settings::get( 'opac_org_founding_year' ) : 1980;

        return [
            '@context'      => 'https://schema.org',
            '@type'         => 'Organization',
            '@id'           => $home . self::ORG_ID,
            'name'          => $has_settings ? OPAC_Settings::get( 'opac_org_name' ) : 'Association OPAC',
            'alternateName' => 'Office Plérinais d\'Action Culturelle',
            'legalName'     => $has_settings ? OPAC_Settings::get( 'opac_org_legal_name' ) : 'Association Office Plérinais d\'Action Culturelle',
            'url'           => $home,
            'telephone'     => $tel_clean,
            'email'         => $has_settings ? OPAC_Settings::get( 'opac_org_email' ) : 'contact@opacplerin.fr',
            'foundingDate'  => (string) $founding,
            'address'       => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $has_settings ? OPAC_Settings::get( 'opac_org_address_street' ) : '10A rue fleurie',
                'postalCode'      => $has_settings ? OPAC_Settings::get( 'opac_org_address_postal' ) : '22190',
                'addressLocality' => $has_settings ? OPAC_Settings::get( 'opac_org_address_city' )   : 'Plérin',
                'addressCountry'  => 'FR',
            ],
            'areaServed'    => $has_settings ? OPAC_Settings::get( 'opac_org_address_city' ) : 'Plérin',
        ];
    }

    private static function build_website() {
        $home = home_url( '/' );
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'WebSite',
            'url'         => $home,
            'name'        => get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'inLanguage'  => 'fr-FR',
            'publisher'   => [ '@id' => $home . self::ORG_ID ],
        ];
    }

    private static function build_course( $post_id ) {
        $tarif   = (int) get_post_meta( $post_id, 'opac_tarif_annuel', true );
        $tagline = (string) get_post_meta( $post_id, 'opac_tagline', true );
        $courte  = (string) get_post_meta( $post_id, 'opac_description_courte', true );
        $desc    = $tagline ?: $courte;
        $home    = home_url( '/' );

        $data = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Course',
            'name'        => get_the_title( $post_id ),
            'description' => $desc,
            'url'         => get_permalink( $post_id ),
            'provider'    => [ '@id' => $home . self::ORG_ID ],
            'inLanguage'  => 'fr-FR',
        ];

        if ( $tarif > 0 ) {
            $data['offers'] = [
                '@type'         => 'Offer',
                'price'         => (string) $tarif,
                'priceCurrency' => 'EUR',
                'category'      => 'Annual subscription',
                'availability'  => 'https://schema.org/InStock',
            ];
        }

        $data['hasCourseInstance'] = [
            '@type'      => 'CourseInstance',
            'courseMode' => 'Onsite',
            'location'   => [
                '@type'   => 'Place',
                'name'    => 'Association OPAC',
                'address' => [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => '10A rue fleurie',
                    'postalCode'      => '22190',
                    'addressLocality' => 'Plérin',
                    'addressCountry'  => 'FR',
                ],
            ],
        ];

        return $data;
    }

    private static function build_event( $post_id, $post_type ) {
        $home = home_url( '/' );
        $data = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Event',
            'name'        => get_the_title( $post_id ),
            'description' => (string) get_post_meta( $post_id, 'opac_description_courte', true ),
            'url'         => get_permalink( $post_id ),
            'organizer'   => [ '@id' => $home . self::ORG_ID ],
            'inLanguage'  => 'fr-FR',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
        ];

        // Date selon le post type.
        if ( $post_type === 'opac_stage' ) {
            $date_debut = (string) get_post_meta( $post_id, 'opac_date_debut', true );
            $date_fin   = (string) get_post_meta( $post_id, 'opac_date_fin', true );
            if ( $date_debut ) {
                $data['startDate'] = $date_debut;
            }
            if ( $date_fin ) {
                $data['endDate'] = $date_fin;
            }
            $tarif = (int) get_post_meta( $post_id, 'opac_tarif_seance', true );
            if ( $tarif > 0 ) {
                $data['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => (string) $tarif,
                    'priceCurrency' => 'EUR',
                    'availability'  => 'https://schema.org/InStock',
                ];
            }
        } elseif ( $post_type === 'opac_event' ) {
            $date_event = (string) get_post_meta( $post_id, 'opac_date_event', true );
            if ( $date_event ) {
                $data['startDate'] = $date_event;
            }
        }

        $lieu = (string) get_post_meta( $post_id, 'opac_lieu', true );
        if ( $lieu ) {
            $data['location'] = [
                '@type' => 'Place',
                'name'  => $lieu,
                'address' => [
                    '@type'           => 'PostalAddress',
                    'addressLocality' => 'Plérin',
                    'addressCountry'  => 'FR',
                ],
            ];
        } else {
            // Lieu par defaut = locaux OPAC.
            $data['location'] = [
                '@type'   => 'Place',
                'name'    => 'Association OPAC',
                'address' => [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => '10A rue fleurie',
                    'postalCode'      => '22190',
                    'addressLocality' => 'Plérin',
                    'addressCountry'  => 'FR',
                ],
            ];
        }

        return $data;
    }

    private static function build_breadcrumb_singular( $post_id, $parent_label, $parent_path ) {
        $home = home_url( '/' );
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => 'Accueil',
                    'item'     => $home,
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => $parent_label,
                    'item'     => home_url( $parent_path ),
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => get_the_title( $post_id ),
                    'item'     => get_permalink( $post_id ),
                ],
            ],
        ];
    }

    private static function render_jsonld( $data ) {
        $json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( $json === false ) {
            return;
        }
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /**
     * Resolution de l'image de partage social :
     * 1. Featured image du post courant si single (atelier/stage/event).
     * 2. Fallback : Site Icon WP (Apparence > Personnaliser > Identite du site).
     *    Editable par Katell sans toucher au code = scaling Katell-friendly.
     * 3. Sinon : retour vide (carte sociale texte uniquement).
     */
    private static function resolve_og_image() {
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id && has_post_thumbnail( $post_id ) ) {
                $url = get_the_post_thumbnail_url( $post_id, 'large' );
                if ( $url ) {
                    return $url;
                }
            }
        }

        $site_icon = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 512 ) : '';
        if ( $site_icon ) {
            return $site_icon;
        }

        return '';
    }

    private static function current_url() {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : parse_url( home_url(), PHP_URL_HOST );
        $uri    = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '/';
        return $scheme . '://' . $host . $uri;
    }

    public static function filter_robots( $robots ) {
        if ( is_page( 'inscription' ) ) {
            $robots['noindex'] = true;
            $robots['nofollow'] = false;
            unset( $robots['max-image-preview'] );
        }
        return $robots;
    }

    public static function filter_sitemap_post_types( $post_types ) {
        // CPTs sans single template = 404 crawler -> on les retire.
        unset( $post_types['opac_person'] );
        unset( $post_types['opac_gallery_item'] );
        return $post_types;
    }

    public static function filter_sitemap_taxonomies( $taxonomies ) {
        // Pas d'archive frontend pour ces taxonomies = on les retire.
        unset( $taxonomies['opac_person_type'] );
        unset( $taxonomies['opac_inscription_status'] );
        return $taxonomies;
    }
}
