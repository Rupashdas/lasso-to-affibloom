<?php
/**
 * The report's CSV download.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Admin;

use AffibloomLassoMigrator\Application\MigrationService;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Interface\ReportCsv;

/**
 * "Download report (CSV)" on the Migrate from Lasso page (F10 D18, AC6; Task 25D): admin-post with a nonce, for those
 * who may open the page; the dry run of every family, as `--report` writes it. Nothing is written to the site.
 */
final class ReportDownload {

	/**
	 * Builds the download.
	 *
	 * @param MigrationService $migration  The migration.
	 * @param string           $capability Who may download it (the page's capability, from Affibloom's API).
	 */
	public function __construct( private MigrationService $migration, private string $capability ) {}

	/**
	 * Hooks into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_post_' . MigrationScreen::DOWNLOAD, array( $this, 'send' ) );
	}

	/**
	 * Sends the CSV.
	 */
	public function send(): void {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to download this report.', 'affibloom-lasso-migrator' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( MigrationScreen::DOWNLOAD );

		$rows = ReportCsv::rows( $this->migration->plan( Family::cases() ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="lasso-migration-report-' . gmdate( 'Y-m-d' ) . '.csv"' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- the response body.
		$out = fopen( 'php://output', 'w' );
		if ( false !== $out ) {
			ReportCsv::write( $out, $rows );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- as above.
			fclose( $out );
		}
		exit;
	}
}
