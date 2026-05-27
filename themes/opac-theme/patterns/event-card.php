<?php
/**
 * Title: Carte événement (agenda)
 * Slug: opac/event-card
 * Categories: opac
 * Description: Ligne d'événement pour l'agenda avec bande colorée à gauche (catégorie), date, badge de catégorie, nom et description.
 * Keywords: event, evenement, agenda, timeline
 * Block Types: core/post-content
 */
?>
<!-- wp:group {"className":"opac-card opac-event-card","backgroundColor":"card","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group opac-card opac-event-card has-card-background-color has-background">

    <!-- wp:group {"className":"opac-event-stripe opac-event-stripe-sortie","backgroundColor":"teal"} -->
    <div class="wp-block-group opac-event-stripe opac-event-stripe-sortie has-teal-background-color has-background"></div>
    <!-- /wp:group -->

    <!-- wp:group {"className":"opac-event-body","layout":{"type":"flex","orientation":"vertical","justifyContent":"center"},"style":{"spacing":{"blockGap":"4px","padding":{"top":"14px","right":"18px","bottom":"14px","left":"18px"}}}} -->
    <div class="wp-block-group opac-event-body" style="padding-top:14px;padding-right:18px;padding-bottom:14px;padding-left:18px">
        <!-- wp:group {"className":"opac-event-top","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"10px"}}} -->
        <div class="wp-block-group opac-event-top">
            <!-- wp:paragraph {"className":"opac-event-date","textColor":"muted"} -->
            <p class="opac-event-date has-muted-color has-text-color">Mai 2026</p>
            <!-- /wp:paragraph -->
            <!-- wp:paragraph {"className":"opac-tag opac-tag-sortie"} -->
            <p class="opac-tag opac-tag-sortie">Sortie</p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->
        <!-- wp:paragraph {"className":"opac-event-name"} -->
        <p class="opac-event-name">Sortie culturelle de printemps</p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph {"className":"opac-event-desc","textColor":"muted"} -->
        <p class="opac-event-desc has-muted-color has-text-color">Sortie groupe organisée par l'association. Détails à venir.</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->

</div>
<!-- /wp:group -->
