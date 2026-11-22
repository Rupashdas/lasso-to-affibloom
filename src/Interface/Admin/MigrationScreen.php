<?php
/**
 * The Migrate from Lasso page.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Admin;

use AffibloomLassoMigrator\Application\MigrationService;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\DataItem;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use AffibloomLassoMigrator\Interface\Rest\MigrationController;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationReport;
use AffibloomLassoMigrator\Infrastructure\Lasso\LassoInstall;

/**
 * The Migrate from Lasso page's body (F10 D13, D18, AC6; Task 25D), under Affibloom's menu and in its design (Affibloom
 * prints the header, title and footer): the dry run's report as a card per family, Lasso's data with its verdicts, and
 * "Download report (CSV)". Opening it writes nothing. No Run button yet (26O). Without Lasso's data the menu hides it
 * and it says so.
 */
final class MigrationScreen {

	/** The page's slug. */
	public const SLUG = 'affibloom-lasso-migrator';

	/** The download's admin-post action and nonce action. */
	public const DOWNLOAD = 'affibloom_lasso_migration_report';

	/**
	 * Builds the screen.
	 *
	 * @param MigrationService  $migration The migration.
	 * @param LassoInstall      $lasso     Whether the site has Lasso's data, and its version.
	 * @param string            $templates The migrator's templates/ folder.
	 * @param string            $css_url   The page's stylesheet.
	 * @param string            $version   The migrator's version, to bust caches.
	 * @param string            $js_url    The page's script (Run migration, Task 26O); '' for none.
	 * @param MigrationLog|null $log      Whether the products family ran, for "Update from Lasso".
	 */
	public function __construct( private MigrationService $migration, private LassoInstall $lasso, private string $templates, private string $css_url, private string $version, private string $js_url = '', private ?MigrationLog $log = null ) {}

	/**
	 * Hooks into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * The page's stylesheet, after Affibloom's admin styles, on this page only.
	 *
	 * @param string $hook_suffix The admin page's hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( str_ends_with( $hook_suffix, self::SLUG ) ) {
			wp_enqueue_style( 'affibloom-lasso-migrate', $this->css_url, array( 'affibloom-admin' ), $this->version );
			if ( '' !== $this->js_url ) {
				wp_enqueue_script( 'affibloom-lasso-migrate', $this->js_url, array(), $this->version, true );
			}
		}
	}

	/**
	 * Prints the page's body.
	 */
	public function render(): void {
		if ( ! $this->lasso->has_data() ) {
			$this->template( 'empty', array() );
			return;
		}
		$report = $this->migration->plan( Family::cases() );
		$this->template(
			'migrate',
			array(
				'lasso_version' => $this->lasso->version(),
				'records'       => count( $report->lines_of( Family::Products ) ),
				'download'      => wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DOWNLOAD ), self::DOWNLOAD ),
				'run_url'       => rest_url( MigrationController::NAMESPACE . '/run' ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'ran'           => null !== $this->log && $this->log->has_run( Family::Products ),
				'cards'         => array_map( fn ( Family $family ): array => $this->card( $report, $family ), $report->families ),
				'data'          => $report->data,
				'left'          => array_values( array_filter( $report->data, static fn ( DataItem $item ): bool => $item->is_left() ) ),
			)
		);
	}

	/**
	 * One family's card: its numbers, or what it found while its task is to come, and its error lines.
	 *
	 * @param MigrationReport $report The report.
	 * @param Family          $family The family.
	 * @return array{family: Family, waiting: string, stats: list<array{0: string, 1: int, 2: bool}>, errors: list<MigrationLine>}
	 */
	private function card( MigrationReport $report, Family $family ): array {
		$lines   = $report->lines_of( $family );
		$waiting = $report->count( $family, Action::Waiting ) > 0 ? $lines[0]->target : '';
		$stats   = array();
		if ( '' !== $waiting ) {
			foreach ( $report->data as $item ) {
				if ( $item->family === $family ) {
					$stats[] = array( $item->name, $item->rows, false );
				}
			}
		} elseif ( Family::Products === $family ) {
			$stats = array(
				array( __( 'Records', 'affibloom-lasso-migrator' ), count( $lines ), true ),
				array( __( 'To create', 'affibloom-lasso-migrator' ), $report->count( $family, Action::Create ), false ),
				array( __( 'Already migrated', 'affibloom-lasso-migrator' ), $report->count( $family, Action::Unchanged ), false ),
				array( __( 'Skipped', 'affibloom-lasso-migrator' ), $report->count( $family, Action::Skip ), false ),
				array( __( 'To retire', 'affibloom-lasso-migrator' ), $report->count( $family, Action::Retire ), false ),
				array( __( 'To trash', 'affibloom-lasso-migrator' ), $report->count( $family, Action::Trash ), false ),
			);
		} else {
			$stats = array(
				array( __( 'Keys', 'affibloom-lasso-migrator' ), count( $lines ), true ),
				array( __( 'To import', 'affibloom-lasso-migrator' ), $report->count( $family, Action::Create ), false ),
				array( __( 'Left', 'affibloom-lasso-migrator' ), $report->count( $family, Action::Skip ), false ),
			);
		}

		return array(
			'family'  => $family,
			'waiting' => $waiting,
			'stats'   => $stats,
			'errors'  => array_values( array_filter( $lines, static fn ( MigrationLine $line ): bool => Action::Error === $line->action ) ),
		);
	}

	/**
	 * Prints one of the migrator's templates.
	 *
	 * @param string               $name The template, without ".php".
	 * @param array<string, mixed> $view What it prints; it escapes.
	 */
	private function template( string $name, array $view ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the template reads $view.
		include $this->templates . '/admin/' . $name . '.php';
	}
}
