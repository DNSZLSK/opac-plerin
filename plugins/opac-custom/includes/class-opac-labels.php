<?php
/**
 * OPAC Custom - Helpers libelles metier (places, adhesion, tarifs)
 *
 * Source UNIQUE des libelles et formats metier autrefois dupliques dans les
 * blocs serveur, l'admin et les bindings : disponibilite des places, types
 * d'adhesion, formatage des tarifs. Une seule definition par libelle / format.
 *
 * @package OPAC\Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OPAC_Labels {

    /** Disponibilite des places : slug => libelle (ordre = select admin). */
    public static function places() {
        return [
            'ok'   => __( 'Places disponibles', 'opac-custom' ),
            'few'  => __( 'Quelques places', 'opac-custom' ),
            'full' => __( 'Complet', 'opac-custom' ),
        ];
    }

    /** Types d'adhesion : slug => libelle. */
    public static function adhesions() {
        return [
            'plerinais' => __( 'Plérinais', 'opac-custom' ),
            'exterieur' => __( 'Extérieur', 'opac-custom' ),
            'mineur'    => __( 'Mineur', 'opac-custom' ),
        ];
    }

    /** Slugs d'adhesion valides (validation des saisies). */
    public static function adhesion_keys() {
        return array_keys( self::adhesions() );
    }

    /** Montant en euros formate : "12 €" (vide si <= 0). Base des tarifs. */
    public static function euros( $montant ) {
        $montant = (int) $montant;
        return $montant > 0 ? number_format_i18n( $montant, 0 ) . ' €' : '';
    }

    /** Tarif annuel formate : "335 € / an" (vide si <= 0). */
    public static function tarif_annuel( $montant ) {
        $e = self::euros( $montant );
        return '' !== $e ? $e . ' / an' : '';
    }

    /** Tarif a la seance formate : "12 €" (vide si <= 0). */
    public static function tarif_seance( $montant ) {
        return self::euros( $montant );
    }
}
