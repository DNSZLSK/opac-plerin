<?php
/**
 * Title: Carte info (h3 + paragraphe)
 * Slug: opac/info-card
 * Categories: opac
 * Description: Petite carte avec titre H3 et un paragraphe descriptif. Polyvalent : infos pratiques, sidebar contact, encart association.
 * Keywords: info, card, sidebar, contact
 * Block Types: core/post-content
 */
?>
<!-- wp:group {"className":"opac-card opac-info-card","backgroundColor":"card","style":{"spacing":{"padding":{"top":"20px","right":"20px","bottom":"20px","left":"20px"},"blockGap":"8px"}}} -->
<div class="wp-block-group opac-card opac-info-card has-card-background-color has-background" style="padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px">

    <!-- wp:heading {"level":3,"className":"opac-info-card-title"} -->
    <h3 class="wp-block-heading opac-info-card-title">Adresse</h3>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"className":"opac-info-card-content","textColor":"muted"} -->
    <p class="opac-info-card-content has-muted-color has-text-color">10A rue fleurie<br>22190 Plérin</p>
    <!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
