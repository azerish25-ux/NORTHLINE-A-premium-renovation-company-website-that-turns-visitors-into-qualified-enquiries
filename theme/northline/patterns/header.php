<?php
/**
 * Title: NORTHLINE studio navigation
 * Slug: northline/header
 * Categories: northline, header
 * Block Types: core/template-part/header
 */
?>
<!-- wp:group {"className":"nl-header nl-shell","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"nowrap"}} -->
<div class="wp-block-group nl-header nl-shell">
<!-- wp:paragraph {"className":"nl-wordmark"} --><p class="nl-wordmark"><a href="<?php echo esc_url(home_url('/')); ?>" aria-label="NORTHLINE home"><span class="nl-north" aria-hidden="true">↗</span> NORTHLINE</a></p><!-- /wp:paragraph -->
<!-- wp:navigation {"overlayMenu":"mobile","className":"nl-navigation","layout":{"type":"flex","justifyContent":"right"}} -->
<!-- wp:navigation-link {"label":"The studio","url":"<?php echo esc_url(home_url('/studio/')); ?>","kind":"custom"} /-->
<!-- wp:navigation-link {"label":"Our approach","url":"<?php echo esc_url(home_url('/approach/')); ?>","kind":"custom"} /-->
<!-- wp:navigation-link {"label":"Projects","url":"<?php echo esc_url(home_url('/projects/')); ?>","kind":"custom"} /-->
<!-- wp:navigation-link {"label":"Plan your renovation ↗","url":"<?php echo esc_url(home_url('/plan-your-renovation/')); ?>","kind":"custom","className":"nl-nav-cta"} /-->
<!-- /wp:navigation -->
</div><!-- /wp:group -->
