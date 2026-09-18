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

    /**
     * Rythme d'un creneau hebdomadaire : slug => libelle FR.
     *
     * Pourquoi ce champ existe : un meme atelier peut tenir deux groupes au
     * MEME jour et au MEME horaire, en alternance (semaines paires / impaires).
     * Ce sont deux creneaux distincts, avec chacun sa capacite et ses inscrits,
     * mais « Jeudi 14h - 16h » ne permet pas de les distinguer dans une liste
     * deroulante : le visiteur comme le secretariat choisiraient au hasard.
     * Le rythme fait donc partie du LIBELLE du creneau (cf. creneau_label),
     * contrairement a la note qui reste editoriale.
     *
     * 'chaque' est le defaut et n'ajoute rien au libelle : la grande majorite
     * des creneaux sont hebdomadaires, les afficher tous en « toutes les
     * semaines » alourdirait les fiches sans rien apprendre a personne.
     */
    public static function rythmes() {
        return [
            'chaque'   => __( 'Toutes les semaines', 'opac-custom' ),
            'paires'   => __( 'Semaines paires', 'opac-custom' ),
            'impaires' => __( 'Semaines impaires', 'opac-custom' ),
        ];
    }

    /** Slugs de rythme valides (validation des saisies). */
    public static function rythmes_keys() {
        return array_keys( self::rythmes() );
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
     * Libelle lisible d'un creneau structure : "Lundi 14h30 - 17h30", suffixe
     * du rythme quand il n'est pas hebdomadaire : "Jeudi 14h - 16h, semaines
     * paires". $c = tableau (jour, debut, fin, rythme). Jour inconnu : ucfirst
     * du slug, rythme inconnu ou absent : aucun suffixe (cf. rythmes()).
     *
     * Note : lcfirst suffit pour la mise en minuscule du rythme en milieu de
     * phrase, seule l'initiale des libelles etant en ASCII majuscule (meme
     * raisonnement que la forme 'lower' des mois).
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
        $label = trim( $jour . ' ' . $h );

        $rythmes = self::rythmes();
        $rythme  = isset( $c['rythme'] ) ? (string) $c['rythme'] : '';
        if ( '' !== $label && 'chaque' !== $rythme && isset( $rythmes[ $rythme ] ) ) {
            $label .= ', ' . lcfirst( $rythmes[ $rythme ] );
        }

        return $label;
    }

    /**
     * Libelle d'un creneau/seance pour une liste deroulante : le libelle de
     * base, suivi entre parentheses de la note puis du complement eventuel
     * (tarif), separes par une virgule.
     *
     *   "Jeudi 14h - 16h, semaines paires (atelier adapté, 335 €)"
     *
     * Pourquoi la note ici et pas dans creneau_label : sur la fiche publique
     * elle est rendue dans son propre <span> (style distinct), alors qu'une
     * <option> ne peut porter que du texte plat. Sans elle, deux creneaux de
     * meme horaire donnent deux options identiques, seule difference visible
     * entre eux : c'est le cas qui rend le choix impossible.
     *
     * @param string $label Libelle de base (creneau_label / seance_label).
     * @param array  $c     Creneau ou seance structure (lu : note).
     * @param string $extra Complement facultatif deja formate (ex : "335 €").
     */
    public static function choice_label( $label, $c, $extra = '' ) {
        $label = trim( (string) $label );
        if ( '' === $label ) {
            return '';
        }
        $bits = [];
        $note = ( is_array( $c ) && isset( $c['note'] ) ) ? trim( (string) $c['note'] ) : '';
        if ( '' !== $note ) {
            $bits[] = $note;
        }
        $extra = trim( (string) $extra );
        if ( '' !== $extra ) {
            $bits[] = $extra;
        }
        if ( empty( $bits ) ) {
            return $label;
        }
        return $label . ' (' . implode( ', ', $bits ) . ')';
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
