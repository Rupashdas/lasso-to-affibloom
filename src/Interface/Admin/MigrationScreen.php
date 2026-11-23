<?php
/**
 * The Migrate from Lasso page.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Admin;

use AffibloomLassoMigrator\Application\Conversion\ContentRun;
use AffibloomLassoMigrator\Application\Conversion\LeftoverCheck;
use AffibloomLassoMigrator\Application\Finish;
use AffibloomLassoMigrator\Domain\Conversion\Leftover;
use AffibloomLassoMigrator\Application\MigrationService;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use Closure;
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
	 * @param MigrationService               $migration The migration.
	 * @param LassoInstall                   $lasso     Whether the site has Lasso's data, and its version.
	 * @param string                         $templates The migrator's templates/ folder.
	 * @param string                         $css_url   The page's stylesheet.
	 * @param string                         $version   The migrator's version, to bust caches.
	 * @param string                         $js_url    The page's script (Run migration, Task 26O); '' for none.
	 * @param MigrationLog|null              $log     Whether the products family ran, for "Update from Lasso".
	 * @param (Closure():ContentRun)|null    $content The content conversion (Task LM8), built when the page prints.
	 * @param (Closure():LeftoverCheck)|null $check The check that nothing of Lasso is left (Task LM9).
	 * @param (Closure():Finish)|null        $finish Finish migration (Task LM10), built when the page prints.
	 */
	public function __construct( private MigrationService $migration, private LassoInstall $lasso, private string $templates, private string $css_url, private string $version, private string $js_url = '', private ?MigrationLog $log = null, private ?Closure $content = null, private ?Closure $check = null, private ?Closure $finish = null ) {}

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
		// Task LM10: once finished, nothing can run again; the page says so and points to the plugins.
		$finished = null === $this->finish ? 0 : ( $this->finish )()->finished();
		if ( $finished > 0 ) {
			$this->template(
				'finished',
				array(
					'finished' => $finished,
					'plugins'  => admin_url( 'plugins.php' ),
				)
			);
			return;
		}
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
				'content'       => null === $this->content ? null : $this->content_view( ( $this->content )() ),
				'check'         => null === $this->check ? null : self::check_view( ( $this->check )()->check() ),
				'finish_url'    => null === $this->finish ? null : rest_url( MigrationController::NAMESPACE . '/finish' ),
			)
		);
	}

	/**
	 * The Content card (Task LM8; F10 AC28): the dry run's figures, the blocked items with a link to each post, a run cut
	 * off, the converted places with their names and links, and the routes.
	 *
	 * @param ContentRun $run The content conversion.
	 * @return array{plan: array{boxes: int, blocked: list<ConversionLine>, stamps: int, ties: int, buttons: int, css: int, places: list<string>}, blocked: list<array{title: string, edit: string, reason: string}>, progress: array{position: int, total: int}|null, converted: list<array{place: string, title: string, edit: string, view: string}>, convert_url: string, revert_url: string}
	 */
	private function content_view( ContentRun $run ): array {
		$plan    = $run->plan();
		$blocked = array();
		foreach ( array_slice( $plan['blocked'], 0, 20 ) as $line ) {
			$blocked[] = array(
				'title'  => $line->post > 0 ? get_the_title( $line->post ) : $line->place,
				'edit'   => $line->post > 0 ? (string) get_edit_post_link( $line->post, 'raw' ) : admin_url( 'widgets.php' ),
				'reason' => $line->reason,
			);
		}
		$converted = array();
		foreach ( $run->converted() as $place ) {
			$post        = ContentRun::SITE === $place ? 0 : (int) substr( $place, 5 );
			$converted[] = array(
				'place' => $place,
				'title' => 0 === $post ? __( 'Widgets and Additional CSS', 'affibloom-lasso-migrator' ) : get_the_title( $post ),
				'edit'  => 0 === $post ? admin_url( 'widgets.php' ) : (string) get_edit_post_link( $post, 'raw' ),
				'view'  => 0 === $post ? '' : (string) get_permalink( $post ),
			);
		}

		return array(
			'plan'        => $plan,
			'blocked'     => $blocked,
			'progress'    => $run->progress(),
			'converted'   => $converted,
			'convert_url' => rest_url( MigrationController::NAMESPACE . '/convert' ),
			'revert_url'  => rest_url( MigrationController::NAMESPACE . '/convert/revert' ),
		);
	}

	/**
	 * The Check card (Task LM9): the findings by place, each place with its link, in the order found.
	 *
	 * @param list<Leftover> $found The findings.
	 * @return list<array{title: string, edit: string, items: list<array{what: string, count: int}>}>
	 */
	private static function check_view( array $found ): array {
		$places = array();
		foreach ( $found as $one ) {
			$key                       = $one->title . '
' . $one->edit;
			$places[ $key ]          ??= array(
				'title' => $one->title,
				'edit'  => $one->edit,
				'items' => array(),
			);
			$places[ $key ]['items'][] = array(
				'what'  => $one->what,
				'count' => $one->count,
			);
		}

		return array_values( $places );
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
