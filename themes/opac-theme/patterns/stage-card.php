<?php
/**
 * Title: Carte stage éphémère (horizontale)
 * Slug: opac/stage-card
 * Categories: opac
 * Description: Carte horizontale pour un stage éphémère avec badge date à gauche, corps central (nom, description, tags) et bouton S'inscrire à droite.
 * Keywords: stage, ephemere, card, horizontal
 * Block Types: core/post-content
 */
?>
<!-- wp:group {"className":"opac-card opac-stage-card","backgroundColor":"card","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group opac-card opac-stage-card has-card-background-color has-background">

    <!-- wp:group {"className":"opac-stage-date","backgroundColor":"accent","textColor":"card","layout":{"type":"flex","orientation":"vertical","justifyContent":"center","verticalAlignment":"center"}} -->
    <div class="wp-block-group opac-stage-date has-card-color has-accent-background-color has-text-color has-background">
        <!-- wp:paragraph {"className":"opac-stage-day","align":"center"} -->
        <p class="opac-stage-day has-text-align-center">Avr.</p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph {"className":"opac-stage-month","align":"center"} -->
        <p class="opac-stage-month has-text-align-center">2026</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->

    <!-- wp:group {"className":"opac-stage-body","layout":{"type":"flex","orientation":"vertical","justifyContent":"center"},"style":{"spacing":{"blockGap":"6px","padding":{"top":"16px","right":"20px","bottom":"16px","left":"20px"}}}} -->
    <div class="wp-block-group opac-stage-body" style="padding-top:16px;padding-right:20px;padding-bottom:16px;padding-left:20px">
        <!-- wp:paragraph {"className":"opac-stage-name"} -->
        <p class="opac-stage-name">Sortie Bréhat &amp; aquarelle</p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph {"className":"opac-stage-desc","textColor":"muted"} -->
        <p class="opac-stage-desc has-muted-color has-text-color">Journée artistique sur l'île de Bréhat. Peinture en plein air.</p>
        <!-- /wp:paragraph -->
        <!-- wp:group {"className":"opac-stage-tags","layout":{"type":"flex","flexWrap":"wrap"},"style":{"spacing":{"blockGap":"6px"}}} -->
        <div class="wp-block-group opac-stage-tags">
            <!-- wp:paragraph {"className":"opac-tag opac-tag-neutral"} -->
            <p class="opac-tag opac-tag-neutral">Adultes</p>
            <!-- /wp:paragraph -->
            <!-- wp:paragraph {"className":"opac-tag opac-tag-ok"} -->
            <p class="opac-tag opac-tag-ok">Sur inscription</p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->
    </div>
    <!-- /wp:group -->

    <!-- wp:group {"className":"opac-stage-action","layout":{"type":"flex","verticalAlignment":"center"},"style":{"spacing":{"padding":{"right":"20px","left":"10px"}}}} -->
    <div class="wp-block-group opac-stage-action" style="padding-right:20px;padding-left:10px">
        <!-- wp:buttons -->
        <div class="wp-block-buttons">
            <!-- wp:button {"className":"is-style-opac-primary"} -->
            <div class="wp-block-button is-style-opac-primary"><a class="wp-block-button__link wp-element-button" href="#">S'inscrire</a></div>
            <!-- /wp:button -->
        </div>
        <!-- /wp:buttons -->
    </div>
    <!-- /wp:group -->

</div>
<!-- /wp:group -->
