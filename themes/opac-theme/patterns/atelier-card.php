<?php
/**
 * Title: Carte atelier (à l'année)
 * Slug: opac/atelier-card
 * Categories: opac
 * Description: Carte d'atelier à l'année avec image placeholder, nom, animateur, tag de disponibilité, tarif annuel et lien Détails. À utiliser dans une grille 3 colonnes.
 * Keywords: atelier, card, carte, ateliers
 * Block Types: core/post-content
 */
?>
<!-- wp:group {"className":"opac-card opac-atelier-card","backgroundColor":"card","layout":{"type":"default"}} -->
<div class="wp-block-group opac-card opac-atelier-card has-card-background-color has-background">

    <!-- wp:group {"className":"opac-card-cover"} -->
    <div class="wp-block-group opac-card-cover">
        <!-- wp:group {"className":"opac-card-cover-fill","style":{"color":{"background":"#f0e6dc"}}} -->
        <div class="wp-block-group opac-card-cover-fill has-background" style="background-color:#f0e6dc"></div>
        <!-- /wp:group -->
    </div>
    <!-- /wp:group -->

    <!-- wp:group {"className":"opac-card-body","style":{"spacing":{"padding":{"top":"14px","right":"16px","bottom":"10px","left":"16px"}}}} -->
    <div class="wp-block-group opac-card-body" style="padding-top:14px;padding-right:16px;padding-bottom:10px;padding-left:16px">
        <!-- wp:paragraph {"className":"opac-card-name"} -->
        <p class="opac-card-name">Céramique</p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph {"className":"opac-card-meta","textColor":"muted"} -->
        <p class="opac-card-meta has-muted-color has-text-color">7 créneaux par semaine · Anne Lemogne</p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph {"className":"opac-tag opac-tag-ok"} -->
        <p class="opac-tag opac-tag-ok">Places disponibles</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->

    <!-- wp:group {"className":"opac-card-footer","style":{"spacing":{"padding":{"top":"10px","right":"16px","bottom":"10px","left":"16px"}},"border":{"top":{"color":"#e8e5e0","width":"1px"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
    <div class="wp-block-group opac-card-footer" style="border-top-color:#e8e5e0;border-top-width:1px;padding-top:10px;padding-right:16px;padding-bottom:10px;padding-left:16px">
        <!-- wp:paragraph {"className":"opac-card-price"} -->
        <p class="opac-card-price">335 € / an</p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph {"className":"opac-card-link","textColor":"accent"} -->
        <p class="opac-card-link has-accent-color has-text-color"><a href="#">Détails →</a></p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->

</div>
<!-- /wp:group -->
