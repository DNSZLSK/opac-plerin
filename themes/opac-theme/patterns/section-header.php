<?php
/**
 * Title: En-tête de section (titre + lien)
 * Slug: opac/section-header
 * Categories: opac
 * Description: En-tête utilisé en haut de chaque section de page : titre Playfair à gauche, lien de section avec flèche à droite.
 * Keywords: section, titre, header, lien
 * Block Types: core/post-content
 */
?>
<!-- wp:group {"className":"opac-section-header","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group opac-section-header">

    <!-- wp:heading {"level":2,"className":"opac-section-title","fontFamily":"display"} -->
    <h2 class="wp-block-heading opac-section-title has-display-font-family">Titre de section</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"className":"opac-section-link","textColor":"accent"} -->
    <p class="opac-section-link has-accent-color has-text-color"><a href="#">Voir tout →</a></p>
    <!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
