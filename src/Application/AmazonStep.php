<?php
/**
 * The Amazon family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\AmazonWriter;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use InvalidArgumentException;

/**
 * The Amazon family (F10 R17, AC19; Task 26L): each cached Amazon row a record uses goes to Affibloom's store, marked
 * imported, unless Affibloom's own row is as new or newer (its refresh wins); with 26C's rule, a later run takes a newer
 * Lasso row only with --update. Lasso never stores per-country tracking IDs (C1 R58), Affibloom keeps no other-store
 * data (their prices came with the products, 26A), and Creators credentials are never copied: each has a line.
 */
final class AmazonStep implements RunStep {

	/**
	 * Builds the step.
	 *
	 * @param LassoSource  $source Lasso's data.
	 * @param AmazonWriter $writer Affibloom's Amazon store.
	 * @param MigrationLog $log    Whether the family ran.
	 */
	public function __construct( private LassoSource $source, private AmazonWriter $writer, private MigrationLog $log ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Amazon;
	}

	/**
	 * One line per cached row and one per kind of data not copied, nothing written.
	 *
	 * @param bool $update Take a newer Lasso row (--update).
	 * @param bool $force  Unused: --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, false );
	}

	/**
	 * The cached rows, then the lines for what is not copied.
	 *
	 * @param bool $update Take a newer Lasso row (--update).
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
		$amazon = $this->source->amazon();
		$first  = ! $this->log->has_run( Family::Amazon );
		$lines  = array();

		foreach ( $amazon->rows as $row ) {
			$asin    = (string) $row['amazon_id'];
			$country = (string) $row['country'];
			$line    = static fn ( Action $action, string $note = '' ): MigrationLine => new MigrationLine( Family::Amazon, $asin . ':' . $country, (string) $row['default_product_name'], 'amazon product', '', $action, '', $note, (string) $row['last_updated'] );
			$has     = $this->writer->product( $asin, $country );
			if ( null !== $has && (string) $has['last_updated'] >= (string) $row['last_updated'] ) {
				$lines[] = $line( Action::Unchanged, "Affibloom's data is as new or newer" );
				continue;
			}
			if ( null !== $has && ! $first && ! $update ) {
				$lines[] = $line( Action::Skip, "Lasso's row is newer: kept Affibloom's (--update takes Lasso's)" );
				continue;
			}
			$lines[] = $this->apply( $write, fn () => $this->writer->import( $asin, $country, self::data( $row ) ), $line( null === $has ? Action::Create : Action::Update ) );
		}

		$lines[] = 0 === $amazon->tracking_ids
			? self::note( 'tracking ids', Action::Skip, 'Lasso stores no per-country tracking IDs (it never writes them, C1 R58)' )
			: self::note( 'tracking ids', Action::Error, $amazon->tracking_ids . ' per-country tracking IDs: Affibloom has none of its own; check them by hand' );
		if ( $amazon->other_stores > 0 ) {
			$lines[] = self::note( 'other stores', Action::Skip, $amazon->other_stores . ' other-store rows: Affibloom keeps no other-store data; their prices came with the products' );
		}
		$lines[] = $amazon->creators
			? self::note( 'creators', Action::Error, 'Lasso holds Creators credentials: enter them under Settings → Amazon; the migrator copies no secret' )
			: self::note( 'creators', Action::Skip, 'Lasso holds no Creators credentials' );

		return $lines;
	}

	/**
	 * A Lasso row in the store's names.
	 *
	 * @param array<string, string> $row The row.
	 * @return array<string, mixed>
	 */
	private static function data( array $row ): array {
		$features = json_decode( (string) ( $row['features'] ?? '' ), true );

		return array(
			'name'            => $row['default_product_name'] ?? '',
			'price'           => $row['latest_price'] ?? '',
			'currency'        => $row['currency'] ?? '',
			'base_url'        => $row['base_url'] ?? '',
			'monetized_url'   => $row['monetized_url'] ?? '',
			'image'           => $row['default_image'] ?? '',
			'out_of_stock'    => '1' === (string) ( $row['out_of_stock'] ?? '' ),
			'savings_amount'  => $row['savings_amount'] ?? '',
			'savings_percent' => (int) ( $row['savings_percent'] ?? 0 ),
			'savings_basis'   => $row['savings_basis'] ?? '',
			'features'        => is_array( $features ) ? $features : array(),
			'is_prime'        => '1' === (string) ( $row['is_prime'] ?? '' ),
			'last_updated'    => $row['last_updated'] ?? '',
		);
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
			return new MigrationLine( $line->family, $line->key, $line->name, $line->type, '', Action::Error, '', $refused->getMessage(), $line->value );
		}

		return $line;
	}

	/**
	 * A line about a kind of data.
	 *
	 * @param string $key    What it is about.
	 * @param Action $action Skip or error.
	 * @param string $note   Why.
	 */
	private static function note( string $key, Action $action, string $note ): MigrationLine {
		return new MigrationLine( Family::Amazon, $key, '', 'note', '', $action, '', $note );
	}
}
