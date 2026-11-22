<?php
/**
 * The issues family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\IssueWriter;
use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use AffibloomLassoMigrator\Domain\Migration\ProductWriter;
use InvalidArgumentException;

/**
 * The issues family (F10 R16, D12, AC18; Task 26K): each record's link-health row becomes its product's, so the Alert
 * column and the Broken links and Out of stock counts show what Lasso showed before Affibloom's own first check; a
 * closed issue bar stays closed. Affibloom's row wins when its last check is as new or newer (its checks are the
 * truth from then on); with 26C's rule, a later run takes a newer Lasso row only with --update. Lasso's mapping (F11
 * analysis, "Data"): `issue_slug` is the URL checked, `issue_resolved_dt` the last failing check or the time it was
 * found working; `is_ignored` and `total_404_request` are not kept (F11 D3).
 */
final class IssuesStep implements RunStep {

	/** Lasso's dismissal meta → Affibloom's bar. */
	public const BARS = array(
		'broken'       => 'dismiss_broken',
		'out-of-stock' => 'dismiss_out_of_stock',
	);

	/**
	 * Builds the step.
	 *
	 * @param LassoSource      $source   Lasso's data.
	 * @param MigratedProducts $migrated The products' ID map.
	 * @param IssueWriter      $writer   Affibloom's link health.
	 * @param ProductWriter    $products Affibloom's products, to tell a published one.
	 * @param MigrationLog     $log      Whether the family ran.
	 * @param int              $now      The Unix time of the run, for expired dismissals; 0 for now.
	 */
	public function __construct( private LassoSource $source, private MigratedProducts $migrated, private IssueWriter $writer, private ProductWriter $products, private MigrationLog $log, private int $now = 0 ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Issues;
	}

	/**
	 * One line per row and per closed bar, nothing written.
	 *
	 * @param bool $update Take a newer Lasso row (--update).
	 * @param bool $force  Unused: --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, false );
	}

	/**
	 * The rows and the closed bars.
	 *
	 * @param bool $update Take a newer Lasso row (--update).
	 * @param bool $force  Unused, as in plan().
	 * @return list<MigrationLine>
	 */
	public function run( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, true );
	}

	/**
	 * A Lasso row in Affibloom's keys.
	 *
	 * @param array<string, string> $row The row.
	 * @return array{issue_type: string, checked_url: string, detection_date: string, resolved: bool, resolved_at: string, last_checked_at: string}
	 */
	public static function mapped( array $row ): array {
		$time     = static fn ( string $key ): string => isset( $row[ $key ] ) && '' !== $row[ $key ] && ! str_starts_with( $row[ $key ], '0000' ) ? $row[ $key ] : '';
		$resolved = '1' === ( $row['issue_resolved'] ?? '' );
		$last     = '' !== $time( 'issue_resolved_dt' ) ? $time( 'issue_resolved_dt' ) : $time( 'detection_date' );

		return array(
			'issue_type'      => (string) ( $row['issue_type'] ?? '' ),
			'checked_url'     => (string) ( $row['issue_slug'] ?? '' ),
			'detection_date'  => $time( 'detection_date' ),
			'resolved'        => $resolved,
			'resolved_at'     => $resolved ? $time( 'issue_resolved_dt' ) : '',
			'last_checked_at' => $last,
		);
	}

	/**
	 * Decides each line and, when writing, carries it out.
	 *
	 * @param bool $update With --update.
	 * @param bool $write  The run, not the dry run.
	 * @return list<MigrationLine>
	 */
	private function work( bool $update, bool $write ): array {
		$lasso = $this->source->issues();
		$first = ! $this->log->has_run( Family::Issues );
		$lines = array();

		foreach ( $lasso->rows as $row ) {
			$id      = (int) $row['id'];
			$product = $this->migrated->for_record( $id )[0] ?? 0;
			$wanted  = self::mapped( $row );
			$line    = static fn ( Action $action, string $note ): MigrationLine => new MigrationLine( Family::Issues, (string) $id, $wanted['issue_type'] . ( $wanted['resolved'] ? ' resolved' : ' open' ), 'issue', '', $action, 0 === $product ? '' : (string) $product, $note, $wanted['last_checked_at'] );
			$skip    = $this->unpublished( $product );
			if ( '' !== $skip ) {
				$lines[] = $line( Action::Skip, $skip );
				continue;
			}
			$has = $this->writer->issue( $product );
			if ( $has === $wanted ) {
				$lines[] = $line( Action::Unchanged, '' );
				continue;
			}
			if ( null !== $has && $has['last_checked_at'] >= $wanted['last_checked_at'] ) {
				$lines[] = $line( Action::Unchanged, "Affibloom's check is as new or newer" );
				continue;
			}
			if ( null !== $has && ! $first && ! $update ) {
				$lines[] = $line( Action::Skip, "Lasso's row is newer: kept Affibloom's (--update takes Lasso's)" );
				continue;
			}
			$lines[] = $this->apply( $write, fn () => $this->writer->import( $product, $wanted ), $line( null === $has ? Action::Create : Action::Update, '' ) );
		}

		foreach ( $lasso->dismissals as $id => $bars ) {
			$product = $this->migrated->for_record( $id )[0] ?? 0;
			foreach ( $bars as $bar => $until ) {
				if ( $until <= 0 ) {
					continue;
				}
				$line = static fn ( Action $action, string $note ): MigrationLine => new MigrationLine( Family::Issues, $id . ':' . $bar, $bar . ' bar closed', 'dismissal', '', $action, 0 === $product ? '' : (string) $product, $note, gmdate( 'Y-m-d H:i:s', $until ) );
				$skip = $until <= ( 0 === $this->now ? time() : $this->now ) ? 'expired in Lasso: the bar shows again there too' : $this->unpublished( $product );
				if ( '' !== $skip ) {
					$lines[] = $line( Action::Skip, $skip );
					continue;
				}
				if ( $this->writer->dismissed_until( $product, $bar ) >= $until ) {
					$lines[] = $line( Action::Unchanged, '' );
					continue;
				}
				$lines[] = $this->apply( $write, fn () => $this->writer->dismiss( $product, $bar, $until ), $line( Action::Update, 'closed until ' . gmdate( 'Y-m-d H:i', $until ) . ' UTC' ) );
			}
		}

		return $lines;
	}

	/**
	 * Why a record's product takes no row: none was copied, or it is not published; '' when it is.
	 *
	 * @param int $product The product, 0 for none.
	 */
	private function unpublished( int $product ): string {
		if ( 0 === $product ) {
			return 'no product: the products family did not copy this record';
		}

		return null === $this->products->fields( $product ) ? "its product is in Affibloom's trash, retired or deleted" : '';
	}

	/**
	 * Carries out a change when writing; a refusal becomes an error line.
	 *
	 * @param bool          $write  The run, not the dry run.
	 * @param callable      $change The change.
	 * @param MigrationLine $line   What it does.
	 */
	private function apply( bool $write, callable $change, MigrationLine $line ): MigrationLine {
		if ( ! $write ) {
			return $line;
		}
		try {
			$change();
		} catch ( InvalidArgumentException $refused ) {
			return new MigrationLine( $line->family, $line->key, $line->name, $line->type, '', Action::Error, $line->target, $refused->getMessage(), $line->value );
		}

		return $line;
	}
}
