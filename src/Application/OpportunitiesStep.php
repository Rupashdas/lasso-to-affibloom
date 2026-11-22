<?php
/**
 * The opportunities family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use AffibloomLassoMigrator\Domain\Migration\OpportunityWriter;
use AffibloomLassoMigrator\Domain\Migration\ProductWriter;
use InvalidArgumentException;

/**
 * The opportunities family (F10 R18, AC21; Task 26N): each product's Detect Opportunities switch (26C's rule for a
 * later run), and every link an editor dismissed, dismissed in Affibloom too (only ever dismissed, never brought back;
 * a link Affibloom's index does not hold yet is a skip line: index the post, then run again). Lasso's retired-ASIN
 * replacements are reported: one without a replacement product resolves nothing in Lasso either; one with a product
 * has no Affibloom counterpart and is an error line to check by hand. The scan, opportunity, auto-detect and Analytics
 * settings move with the settings family (26C), which reports none waiting.
 */
final class OpportunitiesStep implements RunStep {

	/**
	 * Builds the step.
	 *
	 * @param LassoSource       $source   Lasso's data.
	 * @param MigratedProducts  $migrated The products' ID map.
	 * @param ProductWriter     $products Affibloom's products.
	 * @param OpportunityWriter $writer   Affibloom's dismissed opportunities.
	 * @param MigrationLog      $log      Whether the family ran.
	 */
	public function __construct( private LassoSource $source, private MigratedProducts $migrated, private ProductWriter $products, private OpportunityWriter $writer, private MigrationLog $log ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Opportunities;
	}

	/**
	 * One line per switch, dismissed link and replacement, nothing written.
	 *
	 * @param bool $update Take Lasso's switch where Affibloom's differs (--update).
	 * @param bool $force  Unused: --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, false );
	}

	/**
	 * The switches, the dismissals and the replacements.
	 *
	 * @param bool $update Take Lasso's switch where Affibloom's differs (--update).
	 * @param bool $force  Unused, as in plan().
	 * @return list<MigrationLine>
	 */
	public function run( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, true );
	}

	/**
	 * Decides each line and, when writing, carries it out.
	 *
	 * @param bool $update With --update.
	 * @param bool $write  The run, not the dry run.
	 * @return list<MigrationLine>
	 */
	private function work( bool $update, bool $write ): array {
		$lasso = $this->source->opportunities();
		$first = ! $this->log->has_run( Family::Opportunities );
		$lines = array();

		foreach ( $lasso->switches as $id => $on ) {
			$product = $this->migrated->for_record( $id )[0] ?? 0;
			$fields  = 0 === $product ? null : $this->products->fields( $product );
			$line    = static fn ( Action $action, string $note ): MigrationLine => new MigrationLine( Family::Opportunities, (string) $id, 'Detect Opportunities ' . ( $on ? 'on' : 'off' ), 'switch', '', $action, 0 === $product ? '' : (string) $product, $note );
			if ( null === $fields ) {
				$lines[] = $line( Action::Skip, 0 === $product ? 'no product: the products family did not copy this record' : "its product is in Affibloom's trash, retired or deleted" );
				continue;
			}
			if ( (bool) ( $fields['detect_opportunities'] ?? true ) === $on ) {
				$lines[] = $line( Action::Unchanged, '' );
				continue;
			}
			if ( ! $first && ! $update ) {
				$lines[] = $line( Action::Skip, "Affibloom's switch differs: kept (--update takes Lasso's)" );
				continue;
			}
			$lines[] = $this->apply( $write, fn () => $this->products->update( $product, array( 'is_opportunity' => $on ) ), $line( Action::Update, '' ) );
		}

		foreach ( $lasso->dismissed as $link ) {
			$line = static fn ( Action $action, string $note ): MigrationLine => new MigrationLine( Family::Opportunities, 'post ' . $link['post'], $link['url'], 'dismissed', '', $action, '', $note );
			$has  = $this->writer->dismissed( $link['post'], $link['url'] );
			if ( null === $has ) {
				$lines[] = $line( Action::Skip, "not in Affibloom's link index yet: index the post, then run again" );
			} elseif ( $has ) {
				$lines[] = $line( Action::Unchanged, '' );
			} else {
				$lines[] = $this->apply( $write, fn () => $this->writer->dismiss( $link['post'], $link['url'] ), $line( Action::Update, '' ) );
			}
		}

		foreach ( $lasso->replacements as $row ) {
			$lines[] = new MigrationLine(
				Family::Opportunities,
				$row['asin'] . ':' . $row['country'],
				'retired ASIN',
				'replacement',
				'',
				0 === $row['target'] ? Action::Skip : Action::Error,
				'',
				0 === $row['target'] ? 'no replacement product: Lasso resolves nothing either' : 'Affibloom has no retired-ASIN replacement: link the old ASIN to Lasso record ' . $row['target'] . "'s product by hand"
			);
		}
		$lines[] = new MigrationLine( Family::Opportunities, 'settings', '', 'note', '', Action::Skip, '', 'the scan, opportunity, auto-detect and Analytics settings move with the settings family' );

		return $lines;
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
			return new MigrationLine( $line->family, $line->key, $line->name, $line->type, '', Action::Error, $line->target, $refused->getMessage() );
		}

		return $line;
	}
}
