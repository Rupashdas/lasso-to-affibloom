<?php
/**
 * The groups family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoGroup;
use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\GroupWriter;
use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use AffibloomLassoMigrator\Domain\Migration\ProductWriter;
use InvalidArgumentException;

/**
 * The groups family (F10 R13, AC13; Task 26F): every Lasso group becomes a group with its name, description and slug
 * (Affibloom makes the slug from the name, as Lasso does; a different one is noted), its Affibloom ID kept in the log's
 * group map; a group of the same name found in Affibloom is adopted. Its products are the records' products that are
 * published, in Lasso's order. With 26C's rule, what Affibloom already has is unchanged, and a later run keeps
 * Affibloom's differences unless --update.
 */
final class GroupsStep implements RunStep {

	/** The log's group map: Lasso term ID → Affibloom group ID. */
	public const MAP = 'group_map';

	/**
	 * Builds the step.
	 *
	 * @param LassoSource      $source   Lasso's data.
	 * @param GroupWriter      $writer   Affibloom's groups.
	 * @param MigratedProducts $migrated The products' ID map.
	 * @param ProductWriter    $products Which products are published.
	 * @param MigrationLog     $log      Whether the family ran, and the group map.
	 */
	public function __construct( private LassoSource $source, private GroupWriter $writer, private MigratedProducts $migrated, private ProductWriter $products, private MigrationLog $log ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Groups;
	}

	/**
	 * Two lines per group (the group, its products), nothing written.
	 *
	 * @param bool $update Take Lasso's where Affibloom's differs (--update).
	 * @param bool $force  Unused: --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, false );
	}

	/**
	 * The groups, then their products.
	 *
	 * @param bool $update Take Lasso's where Affibloom's differs (--update).
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
		$first   = ! $this->log->has_run( Family::Groups );
		$map     = $this->log->map( self::MAP );
		$current = array_column( $this->writer->all(), null, 'id' );
		$named   = array_column( $current, 'id', 'name' );
		$lines   = array();

		foreach ( $this->source->groups() as $group ) {
			$id = isset( $map[ $group->id ], $current[ $map[ $group->id ] ] ) ? $map[ $group->id ] : ( $named[ $group->name ] ?? 0 );
			if ( 0 === $id ) {
				try {
					$id = $write ? $this->writer->create( $group->name, $group->description ) : 0;
				} catch ( InvalidArgumentException $refused ) {
					$lines[] = self::line( $group, 'group', Action::Error, '', $refused->getMessage() );
					continue;
				}
				$lines[] = self::line( $group, 'group', Action::Create, $write ? '#' . $id : 'a new group', $this->slug_note( $group, $write ? $id : 0 ) );
			} elseif ( $current[ $id ]['name'] === $group->name && $current[ $id ]['description'] === $group->description ) {
				$lines[] = self::line( $group, 'group', Action::Unchanged, '#' . $id );
			} elseif ( $first || $update ) {
				$lines[] = $this->apply( $write, fn () => $this->writer->update( $id, $group->name, $group->description ), self::line( $group, 'group', Action::Update, '#' . $id ) );
			} else {
				$lines[] = self::line( $group, 'group', Action::Skip, '#' . $id, "Affibloom's name or description differs: kept (--update takes Lasso's)" );
			}
			if ( $write && 0 !== $id ) {
				$this->log->remember( self::MAP, $group->id, $id );
			}
			$lines[] = $this->members( $group, $id, $first || $update, $write );
		}

		return $lines;
	}

	/**
	 * The group's products: the records' published products, in Lasso's order.
	 *
	 * @param LassoGroup $group  The Lasso group.
	 * @param int        $id     Its Affibloom group, 0 while it is still to be made.
	 * @param bool       $take   First run or --update.
	 * @param bool       $write  The run, not the dry run.
	 */
	private function members( LassoGroup $group, int $id, bool $take, bool $write ): MigrationLine {
		$products = array();
		foreach ( $group->members as $lasso_id ) {
			$product = $this->migrated->for_record( $lasso_id )[0] ?? 0;
			if ( 0 !== $product && null !== $this->products->fields( $product ) && ! in_array( $product, $products, true ) ) {
				$products[] = $product;
			}
		}
		$left = count( $group->members ) - count( $products );
		$note = $left > 0 ? sprintf( '%d of its records have no published product', $left ) : '';
		$has  = 0 === $id ? array() : $this->writer->members( $id );
		if ( 0 !== $id && $has === $products ) {
			return self::line( $group, 'members', Action::Unchanged, '#' . $id, $note );
		}
		if ( ! $take && 0 !== $id ) {
			return self::line( $group, 'members', Action::Skip, '#' . $id, "Affibloom's products in it differ: kept (--update takes Lasso's)" );
		}

		return $this->apply( $write && 0 !== $id, fn () => $this->writer->set_members( $id, $products ), self::line( $group, 'members', Action::Create, 0 === $id ? '' : '#' . $id, sprintf( '%d products in Lasso\'s order', count( $products ) ) . ( '' === $note ? '' : '; ' . $note ) ) );
	}

	/**
	 * A note when Affibloom's slug for the group is not Lasso's (Lasso's addresses use it).
	 *
	 * @param LassoGroup $group The Lasso group.
	 * @param int        $id    Its Affibloom group, 0 in the dry run.
	 */
	private function slug_note( LassoGroup $group, int $id ): string {
		$slug = (string) ( array_column( $this->writer->all(), 'slug', 'id' )[ $id ] ?? '' );

		return 0 === $id || $slug === $group->slug ? '' : "its slug is $slug, Lasso's was " . $group->slug;
	}

	/**
	 * Carries out a change when writing; a refusal becomes an error line.
	 *
	 * @param bool          $write  Whether to write.
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

	/**
	 * A line for a group.
	 *
	 * @param LassoGroup $group  The Lasso group.
	 * @param string     $type   `group` or `members`.
	 * @param Action     $action What becomes of it.
	 * @param string     $target The Affibloom group.
	 * @param string     $note   Why.
	 */
	private static function line( LassoGroup $group, string $type, Action $action, string $target, string $note = '' ): MigrationLine {
		return new MigrationLine( Family::Groups, (string) $group->id, $group->name, $type, '', $action, $target, $note );
	}
}
