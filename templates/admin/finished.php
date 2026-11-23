<?php
/**
 * Migrate from Lasso once the migration is finished (Task LM10): nothing can run again (the migration's map is gone),
 * so the page says when it finished and that the plugin can go.
 *
 * @package AffibloomLassoMigrator
 * @var array{finished: int, plugins: string} $view
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="affibloom-card affibloom-empty">
	<p class="affibloom-empty__message">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: the date and time the migration was finished. */
				__( 'The migration was finished on %s.', 'affibloom-lasso-migrator' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $view['finished'] )
			)
		);
		?>
	</p>
	<p class="affibloom-empty__text"><?php esc_html_e( "Affibloom holds this site's links, settings and content. Deactivate and delete Affibloom Lasso Migrator; deleting it removes anything it left. Lasso's own data stays until you delete Lasso.", 'affibloom-lasso-migrator' ); ?></p>
	<a class="affibloom-button affibloom-button--primary" href="<?php echo esc_url( $view['plugins'] ); ?>"><?php esc_html_e( 'Go to Plugins', 'affibloom-lasso-migrator' ); ?></a>
</div>
