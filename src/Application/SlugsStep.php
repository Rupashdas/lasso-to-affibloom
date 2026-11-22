<?php
/**
 * The slugs family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoAddress;
use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\AddressWriter;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use InvalidArgumentException;

/**
 * The slugs family (F10 R4, R8, AC14; Task 26G): each migrated record's cloaked address carries over so the links in
 * posts keep answering. Lasso's slug becomes the product's slug, the one Affibloom gave it stays as an old slug (it
 * answers too), Lasso's old slugs are added and its cloaking switch is copied; with 26C's rule, a later run changes
 * an address Affibloom holds otherwise only with --update. A slug another product answers at is a collision: an error
 * line, nothing changed. The prefix and its restriction are settings (26C).
 */
final class SlugsStep implements RunStep {

	/** The slug a product holds for a moment while it gives its own to the record whose Lasso address it is. */
	private const TEMP = 'affibloom-tmp-';

	/**
	 * Lasso slug => the record whose address it is, for the run.
	 *
	 * @var array<string, int>
	 */
	private array $owners = array();

	/**
	 * The addresses of the products released in this run, by product.
	 *
	 * @var array<int, array{slug: string, old_slugs: list<string>, cloak: bool}>
	 */
	private array $released = array();

	/**
	 * Builds the step.
	 *
	 * @param LassoSource      $source   Lasso's data.
	 * @param MigratedProducts $migrated The products' ID map.
	 * @param AddressWriter    $writer   Affibloom's addresses.
	 * @param MigrationLog     $log      Whether the family ran.
	 */
	public function __construct( private LassoSource $source, private MigratedProducts $migrated, private AddressWriter $writer, private MigrationLog $log ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Slugs;
	}

	/**
	 * One line per record, nothing written.
	 *
	 * @param bool $update Take Lasso's address where Affibloom's differs (--update).
	 * @param bool $force  Unused: --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, false );
	}

	/**
	 * The addresses.
	 *
	 * @param bool $update Take Lasso's address where Affibloom's differs (--update).
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
		$first     = ! $this->log->has_run( Family::Slugs );
		$all       = $this->source->slugs();
		$addresses = array_values( array_filter( $all, static fn ( LassoAddress $lasso ): bool => 'trash' !== $lasso->status ) );
		// Who each Lasso slug and old slug belongs to.
		$this->owners = array();
		foreach ( $addresses as $lasso ) {
			foreach ( array_merge( array( $lasso->slug ), $lasso->old_slugs ) as $slug ) {
				$this->owners[ $slug ] ??= $lasso->id;
			}
		}
		$this->released = array();
		if ( $write && ( $first || $update ) ) {
			$this->release( $addresses );
		}

		$lines = array();
		foreach ( $all as $lasso ) {
			$lines[] = 'trash' === $lasso->status
				? new MigrationLine( Family::Slugs, (string) $lasso->id, $lasso->slug, 'address', $lasso->status, Action::Skip, '', "in Lasso's trash: its address does not answer there either" )
				: $this->line_for( $lasso, $first, $update, $write );
		}
		$lines[] = new MigrationLine( Family::Slugs, 'prefix', '', 'note', '', Action::Skip, '', 'rewrite_slug and restrict_prefix are settings: the settings family moves them' );

		return $lines;
	}

	/**
	 * Frees the slugs products hold that are another record's Lasso address (a product the one-time import named after
	 * another record, or two products holding each other's slugs): each moves to a slug of its own for the moment and
	 * takes its Lasso slug in the pass that follows.
	 *
	 * @param list<LassoAddress> $addresses The records' addresses.
	 */
	private function release( array $addresses ): void {
		foreach ( $addresses as $lasso ) {
			$product = $this->migrated->for_record( $lasso->id )[0] ?? 0;
			$has     = 0 === $product ? null : $this->writer->address( $product );
			$owner   = null === $has ? $lasso->id : ( $this->owners[ $has['slug'] ] ?? $lasso->id );
			// Only for a record whose product can take it (a retired or trashed one cannot).
			$taker = $this->migrated->for_record( $owner )[0] ?? 0;
			if ( null === $has || $owner === $lasso->id || 0 === $taker || null === $this->writer->address( $taker ) ) {
				continue;
			}
			try {
				$this->writer->set( $product, self::TEMP . $product, $has['old_slugs'], $has['cloak'] );
				$this->released[ $product ] = $has;
			} catch ( InvalidArgumentException ) {
				continue; // The pass that follows reports it.
			}
		}
	}

	/**
	 * One record's line.
	 *
	 * @param LassoAddress $lasso  The record's address.
	 * @param bool         $first  The family's first run.
	 * @param bool         $update With --update.
	 * @param bool         $write  The run, not the dry run.
	 */
	private function line_for( LassoAddress $lasso, bool $first, bool $update, bool $write ): MigrationLine {
		$line    = static fn ( Action $action, string $note, int $product = 0 ): MigrationLine => new MigrationLine( Family::Slugs, (string) $lasso->id, $lasso->slug, 'address', $lasso->status, $action, 0 === $product ? '' : (string) $product, $note );
		$product = $this->migrated->for_record( $lasso->id )[0] ?? 0;
		if ( 0 === $product ) {
			return $line( Action::Skip, 'no product: the products family did not copy this record' );
		}
		$has = $this->writer->address( $product );
		if ( null === $has ) {
			return $line( Action::Skip, "its product is in Affibloom's trash, retired or deleted: its address stays as it is", $product );
		}

		// Lasso's old slugs another product answers at stay with it.
		$old       = array();
		$elsewhere = array();
		foreach ( array_diff( array_unique( $lasso->old_slugs ), array( $lasso->slug ) ) as $slug ) {
			$holder = $this->writer->holder( $slug );
			if ( 0 !== $holder && $holder !== $product ) {
				$elsewhere[] = 'old slug ' . $slug . ' stays with product ' . $holder;
				continue;
			}
			$old[] = $slug;
		}
		$missing = array_values( array_diff( $old, $has['old_slugs'] ) );
		$temp    = str_starts_with( $has['slug'], self::TEMP );
		if ( $has['slug'] === $lasso->slug && $has['cloak'] === $lasso->cloak && array() === $missing ) {
			return $line( Action::Unchanged, implode( '; ', $elsewhere ), $product );
		}
		if ( ! $first && ! $update && ! $temp ) {
			return $line( Action::Skip, "Affibloom's address differs: kept (--update takes Lasso's)", $product );
		}
		// Another product at this slug is a collision, unless it holds it only until the release (the dry run).
		$holder = $this->writer->holder( $lasso->slug );
		if ( 0 !== $holder && $holder !== $product ) {
			$borrowed = ! $write && ( $this->writer->address( $holder )['slug'] ?? '' ) === $lasso->slug;
			if ( ! $borrowed ) {
				return $this->restored( $product, $line( Action::Error, 'collision: product ' . $holder . ' answers at ' . $lasso->slug, $product ) );
			}
		}

		// Affibloom's own slug keeps answering, unless it is another Lasso record's address, which that record takes.
		$other = ( $this->owners[ $has['slug'] ] ?? $lasso->id ) !== $lasso->id;
		$keep  = $temp || $other || $has['slug'] === $lasso->slug ? array() : array( $has['slug'] );
		$notes = array();
		if ( $has['slug'] !== $lasso->slug ) {
			$notes[] = 'slug adopted' . ( $temp ? '' : ( $other ? ' (' . $has['slug'] . " left to Lasso's record " . $this->owners[ $has['slug'] ] . ')' : ' (' . $has['slug'] . ' kept as an old slug)' ) );
		}
		if ( $has['cloak'] !== $lasso->cloak ) {
			$notes[] = 'cloaking ' . ( $lasso->cloak ? 'on' : 'off' );
		}
		if ( array() !== $missing ) {
			$notes[] = count( $missing ) . ' old slug' . ( 1 === count( $missing ) ? '' : 's' ) . ' added';
		}
		$slugs = array_values( array_unique( array_merge( $has['old_slugs'], $keep, $old ) ) );
		$done  = $line( Action::Update, implode( '; ', array_merge( $notes, $elsewhere ) ), $product );
		if ( ! $write ) {
			return $done;
		}
		try {
			$this->writer->set( $product, $lasso->slug, $slugs, $lasso->cloak );
		} catch ( InvalidArgumentException $refused ) {
			return $this->restored( $product, $line( Action::Error, 'collision: ' . $refused->getMessage(), $product ) );
		}

		return $done;
	}

	/**
	 * A product released for this run that could not take its own slug gets its address back, so it keeps answering.
	 *
	 * @param int           $product The product.
	 * @param MigrationLine $error   The collision.
	 */
	private function restored( int $product, MigrationLine $error ): MigrationLine {
		$before = $this->released[ $product ] ?? null;
		if ( null !== $before ) {
			try {
				$this->writer->set( $product, $before['slug'], $before['old_slugs'], $before['cloak'] );
			} catch ( InvalidArgumentException ) {
				return $error; // Its slug went to the record whose address it is.
			}
		}

		return $error;
	}
}
