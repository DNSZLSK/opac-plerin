<?php
/**
 * Title: Carte personne (équipe)
 * Slug: opac/person-card
 * Categories: opac
 * Description: Carte compacte d'un membre de l'équipe avec un avatar à initiales, le nom et le rôle. À utiliser dans une grille (équipe pédagogique, bureau, etc.).
 * Keywords: person, equipe, membre, bureau, avatar
 * Block Types: core/post-content
 */
?>
<!-- wp:group {"className":"opac-card opac-person-card","backgroundColor":"card","layout":{"type":"flex","orientation":"vertical","justifyContent":"center"},"style":{"spacing":{"blockGap":"6px","padding":{"top":"16px","right":"16px","bottom":"16px","left":"16px"}}}} -->
<div class="wp-block-group opac-card opac-person-card has-card-background-color has-background" style="padding-top:16px;padding-right:16px;padding-bottom:16px;padding-left:16px">

    <!-- wp:html -->
    <div class="opac-person-avatar opac-person-avatar-1" aria-hidden="true">AL</div>
    <!-- /wp:html -->

    <!-- wp:paragraph {"className":"opac-person-name","align":"center"} -->
    <p class="opac-person-name has-text-align-center">Anne Lemogne</p>
    <!-- /wp:paragraph -->

    <!-- wp:paragraph {"className":"opac-person-role","align":"center","textColor":"muted"} -->
    <p class="opac-person-role has-text-align-center has-muted-color has-text-color">Céramique</p>
    <!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
