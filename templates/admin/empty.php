<?php
/**
 * Migrate from Lasso on a site without Lasso's data (Task 25D): the menu hides the page; its address says so.
 *
 * @package AffibloomLassoMigrator
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="affibloom-card affibloom-empty">
	<p class="affibloom-empty__message"><?php esc_html_e( 'No Lasso data found.', 'affibloom-lasso-migrator' ); ?></p>
	<p class="affibloom-empty__text"><?php esc_html_e( 'This site has no Lasso links or settings to move. You can deactivate and delete Affibloom Lasso Migrator.', 'affibloom-lasso-migrator' ); ?></p>
</div>
