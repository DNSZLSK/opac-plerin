<?php
/**
 * Title: Hero (page d'accueil)
 * Slug: opac/hero
 * Categories: opac
 * Description: Bloc hero plein cadre avec badge saison, titre Playfair, paragraphe intro, deux CTAs et trois statistiques.
 * Keywords: hero, accueil, intro, saison, banner
 * Block Types: core/post-content
 * Viewport Width: 1180
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"opac-hero","backgroundColor":"hero","textColor":"card","layout":{"type":"constrained","contentSize":"720px","justifyContent":"left"}} -->
<section class="wp-block-group alignfull opac-hero has-card-color has-hero-background-color has-text-color has-background">

    <!-- wp:paragraph {"className":"opac-hero-badge"} -->
    <p class="opac-hero-badge">Saison 2025 / 2026</p>
    <!-- /wp:paragraph -->

    <!-- wp:heading {"className":"opac-hero-title","fontFamily":"display"} -->
    <h1 class="wp-block-heading opac-hero-title has-display-font-family">La culture au bout des doigts</h1>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"className":"opac-hero-intro"} -->
    <p class="opac-hero-intro">Ateliers d'expression culturelle, activités éphémères et sorties pour tous les âges. Association OPAC, asso loi 1901 à Plérin-sur-Mer.</p>
    <!-- /wp:paragraph -->

    <!-- wp:buttons {"className":"opac-hero-actions","layout":{"type":"flex","flexWrap":"wrap"}} -->
    <div class="wp-block-buttons opac-hero-actions">
        <!-- wp:button {"className":"is-style-opac-primary"} -->
        <div class="wp-block-button is-style-opac-primary"><a class="wp-block-button__link wp-element-button" href="/ateliers/">Découvrir les ateliers</a></div>
        <!-- /wp:button -->
        <!-- wp:button {"className":"is-style-opac-ghost"} -->
        <div class="wp-block-button is-style-opac-ghost"><a class="wp-block-button__link wp-element-button" href="/ephemeres/">Nos éphémères</a></div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->

    <!-- wp:group {"className":"opac-hero-stats","layout":{"type":"flex","flexWrap":"wrap"}} -->
    <div class="wp-block-group opac-hero-stats">

        <!-- wp:group {"className":"opac-stat","layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"2px"}}} -->
        <div class="wp-block-group opac-stat">
            <!-- wp:paragraph {"className":"opac-stat-num","fontFamily":"display"} -->
            <p class="opac-stat-num has-display-font-family">10</p>
            <!-- /wp:paragraph -->
            <!-- wp:paragraph {"className":"opac-stat-label"} -->
            <p class="opac-stat-label">Ateliers à l'année</p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->

        <!-- wp:group {"className":"opac-stat","layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"2px"}}} -->
        <div class="wp-block-group opac-stat">
            <!-- wp:paragraph {"className":"opac-stat-num","fontFamily":"display"} -->
            <p class="opac-stat-num has-display-font-family">200+</p>
            <!-- /wp:paragraph -->
            <!-- wp:paragraph {"className":"opac-stat-label"} -->
            <p class="opac-stat-label">Adhérents</p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->

        <!-- wp:group {"className":"opac-stat","layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"2px"}}} -->
        <div class="wp-block-group opac-stat">
            <!-- wp:paragraph {"className":"opac-stat-num","fontFamily":"display"} -->
            <p class="opac-stat-num has-display-font-family">46</p>
            <!-- /wp:paragraph -->
            <!-- wp:paragraph {"className":"opac-stat-label"} -->
            <p class="opac-stat-label">Années d'activité</p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->

    </div>
    <!-- /wp:group -->

</section>
<!-- /wp:group -->
