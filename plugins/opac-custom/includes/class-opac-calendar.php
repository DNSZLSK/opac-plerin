<?php
/**
 * OPAC Custom - Helpers calendrier (jours, mois, horaires, creneaux)
 *
 * Source UNIQUE des donnees de calendrier FR, autrefois dupliquees dans
 * plusieurs blocs serveur, l'admin et les bindings (jours de la semaine, noms de
 * mois complets / abreges, format des heures, libelle d'un creneau). Centraliser
 * evite qu'une retouche a un endroit oublie les copies.
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Calendar {

    /** Jours de la semaine : slug => libelle FR. */
    public static function jours() {
        return [
            'lundi'    => __( 'Lundi', 'opac-custom' ),
            'mardi'    => __( 'Mardi', 'opac-custom' ),
            'mercredi' => __( 'Mercredi', 'opac-custom' ),
            'jeudi'    => __( 'Jeudi', 'opac-custom' ),
            'vendredi' => __( 'Vendredi', 'opac-custom' ),
            'samedi'   => __( 'Samedi', 'opac-custom' ),
            'dimanche' => __( 'Dimanche', 'opac-custom' ),
        ];
    }

    /** Slugs des jours valides (validation des saisies). */
    public static function jours_keys() {
        return array_keys( self::jours() );
    }

    /** Mois en toutes lettres (capitalises) : 1..12 => libelle. */
    public static function months() {
        return [
            1 => 'Janvier',   2  => 'Février', 3  => 'Mars',     4 => 'Avril',
            5 => 'Mai',       6  => 'Juin',    7  => 'Juillet',  8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
    }

    /** Mois abreges : 1..12 => libelle court. */
    public static function months_abbr() {
        return [
            1 => 'Janv.', 2  => 'Févr.', 3  => 'Mars',  4 => 'Avr.',
            5 => 'Mai',   6  => 'Juin',  7  => 'Juil.', 8 => 'Août',
            9 => 'Sept.', 10 => 'Oct.',  11 => 'Nov.',  12 => 'Déc.',
        ];
    }

    /**
     * Libelle d'un mois. $form : 'full' (Janvier), 'abbr' (Janv.) ou 'lower'
     * (janvier, pour un milieu de phrase). Vide si $n est hors 1..12.
     *
     * Note : strtolower suffit pour la forme 'lower' car seule l'initiale des
     * mois est en ASCII majuscule (les accents sont deja en minuscule).
     */
    public static function month( $n, $form = 'full' ) {
        $n = (int) $n;
        if ( 'abbr' === $form ) {
            $m = self::months_abbr();
            return isset( $m[ $n ] ) ? $m[ $n ] : '';
        }
        $m    = self::months();
        $name = isset( $m[ $n ] ) ? $m[ $n ] : '';
        return ( 'lower' === $form ) ? strtolower( $name ) : $name;
    }

    /** "14:30" => "14h30", "14:00" => "14h", "" => "". */
    public static function format_heure( $t ) {
        $t = trim( (string) $t );
        if ( '' === $t ) {
            return '';
        }
        $parts = explode( ':', $t );
        $h = (int) $parts[0];
        $m = isset( $parts[1] ) ? (int) $parts[1] : 0;
        return $m > 0 ? sprintf( '%dh%02d', $h, $m ) : $h . 'h';
    }

    /** "14:30" + "17:30" => "14h30 - 17h30" (gere une borne vide). */
    public static function format_horaire( $debut, $fin ) {
        $d = self::format_heure( $debut );
        $f = self::format_heure( $fin );
        if ( '' !== $d && '' !== $f ) {
            return $d . ' - ' . $f;
        }
        return $d . $f;
    }

    /**
     * Libelle lisible d'un creneau structure : "Lundi 14h30 - 17h30".
     * $c = tableau (jour, debut, fin). Jour inconnu : ucfirst du slug.
     */
    public static function creneau_label( $c ) {
        if ( ! is_array( $c ) ) {
            return '';
        }
        $jours = self::jours();
        $slug  = isset( $c['jour'] ) ? (string) $c['jour'] : '';
        $jour  = isset( $jours[ $slug ] ) ? $jours[ $slug ] : ucfirst( $slug );
        $h     = self::format_horaire(
            isset( $c['debut'] ) ? $c['debut'] : '',
            isset( $c['fin'] ) ? $c['fin'] : ''
        );
        return trim( $jour . ' ' . $h );
    }

    /**
     * Libelle lisible d'une seance datee d'un atelier ephemere :
     * "12 avril 2026, 14h30 - 17h30". $s = tableau (date en Y-m-d, debut, fin).
     * Mois en minuscules (milieu de phrase). Date absente ou invalide : on
     * retombe sur les seuls horaires (jamais de date fantaisie).
     *
     * Parse la date par decoupage direct de la chaine Y-m-d (pas de strtotime),
     * pour rester pur et independant du fuseau : un libelle de date n'a pas a
     * subir de conversion horaire.
     */
    public static function seance_label( $s ) {
        if ( ! is_array( $s ) ) {
            return '';
        }
        $date       = isset( $s['date'] ) ? (string) $s['date'] : '';
        $date_label = '';
        if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
            $mois       = self::month( (int) $m[2], 'lower' );
            $date_label = trim( (int) $m[3] . ' ' . $mois . ' ' . $m[1] );
        }
        $h = self::format_horaire(
            isset( $s['debut'] ) ? $s['debut'] : '',
            isset( $s['fin'] ) ? $s['fin'] : ''
        );
        if ( '' === $date_label ) {
            return $h;
        }
        return '' !== $h ? $date_label . ', ' . $h : $date_label;
    }
}
