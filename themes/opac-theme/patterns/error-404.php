<?php
/**
 * Title: Page 404 (introuvable)
 * Slug: opac/error-404
 * Categories: opac
 * Description: Contenu de la page d'erreur 404 : illustration de marque, message d'erreur et liens de retour vers le site.
 * Keywords: 404, erreur, introuvable
 * Inserter: no
 *
 * Pattern PHP (et non bloc inline dans le template) pour pouvoir resoudre
 * l'URL de l'illustration livree avec le theme via get_template_directory_uri().
 * Un template FSE .html ne peut pas executer de PHP ; ce pattern est inclus
 * depuis templates/404.html par <!-- wp:pattern {"slug":"opac/error-404"} /-->.
 */
?>
<!-- wp:group {"tagName":"section","className":"opac-404","layout":{"type":"constrained","contentSize":"600px"}} -->
<section class="wp-block-group opac-404">

    <!-- Illustration decorative (alt="") : le sens "404" est porte par le titre
         texte ci-dessous, jamais uniquement par l'image (a11y + SEO). -->
    <!-- wp:image {"className":"opac-404-illus","linkDestination":"none"} -->
    <figure class="wp-block-image opac-404-illus"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/404.png' ); ?>" alt=""/></figure>
    <!-- /wp:image -->

    <!-- wp:heading {"level":1,"textAlign":"center","className":"opac-404-title","fontFamily":"display"} -->
    <h1 class="wp-block-heading has-text-align-center opac-404-title has-display-font-family">Page introuvable</h1>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"align":"center","className":"opac-404-text","textColor":"muted"} -->
    <p class="has-text-align-center opac-404-text has-muted-color has-text-color">Oups, cette page a disparu ou a été déplacée. Vérifiez l'adresse saisie, ou repartez de l'accueil.</p>
    <!-- /wp:paragraph -->

    <!-- wp:buttons {"className":"opac-404-actions","layout":{"type":"flex","justifyContent":"center"}} -->
    <div class="wp-block-buttons opac-404-actions">
        <!-- wp:button {"className":"is-style-opac-primary"} -->
        <div class="wp-block-button is-style-opac-primary"><a class="wp-block-button__link wp-element-button" href="/">Retour à l'accueil</a></div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->

    <!-- wp:paragraph {"align":"center","className":"opac-404-links"} -->
    <p class="has-text-align-center opac-404-links"><a href="/ateliers/">Ateliers</a> · <a href="/agenda/">Agenda</a> · <a href="/contact/">Contact</a></p>
    <!-- /wp:paragraph -->

</section>
<!-- /wp:group -->
