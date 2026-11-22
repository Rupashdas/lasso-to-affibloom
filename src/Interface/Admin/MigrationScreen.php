<?php
/**
 * The Migrate from Lasso page.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Admin;

/**
 * The Migrate from Lasso page's body (Task LM1), under Affibloom's menu and in its design (Affibloom prints the header,
 * title and footer). Empty until the dry run's report (Task 25D).
 */
final class MigrationScreen {

	/** The page's slug. */
	public const SLUG = 'affibloom-lasso-migrator';

	/**
	 * Prints the page's body.
	 */
	public function render(): void {
		?>
		<div class="affibloom-card affibloom-empty">
			<p class="affibloom-empty__message"><?php esc_html_e( 'Run the dry run first.', 'affibloom-lasso-migrator' ); ?></p>
			<p class="affibloom-empty__text"><?php esc_html_e( 'The dry run lists everything that will move from Lasso, and changes nothing.', 'affibloom-lasso-migrator' ); ?></p>
		</div>
		<?php
	}
}
