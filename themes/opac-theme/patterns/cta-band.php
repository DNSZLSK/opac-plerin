<?php
/**
 * Title: Bande CTA (inscription)
 * Slug: opac/cta-band
 * Categories: opac
 * Description: Bande d'appel à l'action plein cadre avec fond teal, titre, paragraphe descriptif et bouton blanc.
 * Keywords: cta, inscription, band, call to action
 * Block Types: core/post-content
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"opac-cta-band","backgroundColor":"teal","textColor":"card","layout":{"type":"constrained","contentSize":"1080px"}} -->
<section class="wp-block-group alignfull opac-cta-band has-card-color has-teal-background-color has-text-color has-background">

    <!-- wp:group {"className":"opac-cta-band-inner","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
    <div class="wp-block-group opac-cta-band-inner">

        <!-- wp:group {"className":"opac-cta-band-text","layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"4px"}}} -->
        <div class="wp-block-group opac-cta-band-text">
            <!-- wp:heading {"level":3,"className":"opac-cta-band-title","fontFamily":"display"} -->
            <h3 class="wp-block-heading opac-cta-band-title has-display-font-family">Inscription pour la saison 2025 / 2026</h3>
            <!-- /wp:heading -->
            <!-- wp:paragraph {"className":"opac-cta-band-desc"} -->
            <p class="opac-cta-band-desc">Inscription en ligne, paiement sur place au secrétariat. Adhésion annuelle requise.</p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->

        <!-- wp:buttons -->
        <div class="wp-block-buttons">
            <!-- wp:button {"className":"is-style-opac-on-teal"} -->
            <div class="wp-block-button is-style-opac-on-teal"><a class="wp-block-button__link wp-element-button" href="/ateliers/">S'inscrire à un atelier</a></div>
            <!-- /wp:button -->
        </div>
        <!-- /wp:buttons -->

    </div>
    <!-- /wp:group -->

</section>
<!-- /wp:group -->
