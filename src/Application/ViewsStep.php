<?php
/**
 * The views family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use AffibloomLassoMigrator\Domain\Migration\ViewWriter;
use InvalidArgumentException;

/**
 * The views family (F10 R22, AC20; F38 ON5, ON8; Task 26M): each user's saved Dashboard views by their Lasso ID, with
 * 26C's rule for a view Affibloom has in another form (written on the first run or with --update, else kept); the
 * Dashboard tour ended in Affibloom when it was in Lasso; and Lasso's closed education boxes closed in Affibloom too.
 * Nothing here reopens a box or restarts the tour.
 */
final class ViewsStep implements RunStep {

	/** Lasso's education box → Affibloom's intro box; Lasso's keyword box has none. */
	private const BOXES = array(
		'link'    => 'links',
		'content' => 'content',
		'domain'  => 'domains',
	);

	/**
	 * Builds the step.
	 *
	 * @param LassoSource  $source Lasso's data.
	 * @param ViewsMapper  $mapper Lasso's view in Affibloom's names.
	 * @param ViewWriter   $writer Affibloom's views, tour and boxes.
	 * @param MigrationLog $log    Whether the family ran, and the group map.
	 */
	public function __construct( private LassoSource $source, private ViewsMapper $mapper, private ViewWriter $writer, private MigrationLog $log ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Views;
	}

	/**
	 * One line per view, one for the tour and one for the boxes, nothing written.
	 *
	 * @param bool $update Take Lasso's view where Affibloom's differs (--update).
	 * @param bool $force  Unused: --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, false );
	}

	/**
	 * The views, the tour and the boxes.
	 *
	 * @param bool $update Take Lasso's view where Affibloom's differs (--update).
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
		$lasso  = $this->source->views();
		$first  = ! $this->log->has_run( Family::Views );
		$groups = $this->log->map( GroupsStep::MAP );
		$lines  = array();

		foreach ( $lasso->views as $user => $rows ) {
			$have = array_column( $this->writer->views( $user ), null, 'id' );
			foreach ( $rows as $index => $row ) {
				$mapped = $this->mapper->map( $row, $groups );
				$view   = $mapped['view'];
				if ( null === $view ) {
					$lines[] = self::line( $user . ':' . $index, '', Action::Skip, 'not a saved view (no ID, name or filters): Lasso skips it too' );
					continue;
				}
				$key  = $user . ':' . $view['id'];
				$note = array() === $mapped['dropped'] ? '' : 'filters dropped: ' . implode( ', ', $mapped['dropped'] );
				$has  = $have[ $view['id'] ] ?? null;
				if ( null !== $has && $has == $view ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- the same keys and values in any order.
					$lines[] = self::line( $key, $view['name'], Action::Unchanged, $note );
					continue;
				}
				if ( null !== $has && ! $first && ! $update ) {
					$lines[] = self::line( $key, $view['name'], Action::Skip, "Affibloom's view differs: kept (--update takes Lasso's)" );
					continue;
				}
				$lines[] = $this->apply( $write, fn () => $this->writer->save( $user, $view ), self::line( $key, $view['name'], null === $has ? Action::Create : Action::Update, $note ) );
			}
		}

		$lines[] = $this->tour( $lasso->tour_done, $write );
		array_push( $lines, ...$this->boxes( $lasso->closed, $write ) );

		return $lines;
	}

	/**
	 * The tour: ended in Affibloom when Lasso's was (F38 ON8), never restarted.
	 *
	 * @param bool $done  Whether Lasso's tour was ended.
	 * @param bool $write The run, not the dry run.
	 */
	private function tour( bool $done, bool $write ): MigrationLine {
		if ( ! $done ) {
			return self::line( 'tour', 'Dashboard tour', Action::Skip, "Lasso's tour was not ended: Affibloom's is left as it is" );
		}
		if ( $this->writer->tour_done() ) {
			return self::line( 'tour', 'Dashboard tour', Action::Unchanged, 'ended in both' );
		}

		return $this->apply( $write, fn () => $this->writer->finish_tour(), self::line( 'tour', 'Dashboard tour', Action::Update, 'ended, as in Lasso' ) );
	}

	/**
	 * The education boxes Lasso closed, closed in Affibloom too (F38 ON5); a box Affibloom closed stays closed.
	 *
	 * @param list<string> $closed Lasso's closed boxes.
	 * @param bool         $write  The run, not the dry run.
	 * @return list<MigrationLine>
	 */
	private function boxes( array $closed, bool $write ): array {
		$lines = array();
		if ( in_array( 'keyword', $closed, true ) ) {
			$lines[] = self::line( 'box:keyword', 'keyword box', Action::Skip, "Lasso's keyword box: Affibloom has no keyword screen" );
		}
		$wanted = array_values( array_intersect_key( self::BOXES, array_flip( $closed ) ) );
		if ( array() === $wanted ) {
			$lines[] = self::line( 'boxes', 'intro boxes', Action::Skip, 'Lasso has no closed intro box' );

			return $lines;
		}
		$have = $this->writer->closed_boxes();
		$new  = array_values( array_diff( $wanted, $have ) );
		if ( array() === $new ) {
			$lines[] = self::line( 'boxes', 'intro boxes', Action::Unchanged, 'closed: ' . implode( ', ', $wanted ) );

			return $lines;
		}
		$all     = array_values( array_unique( array_merge( $have, $new ) ) );
		$lines[] = $this->apply( $write, fn () => $this->writer->close_boxes( $all ), self::line( 'boxes', 'intro boxes', Action::Update, 'closed: ' . implode( ', ', $new ) ) );

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
			return self::line( $line->key, $line->name, Action::Error, $refused->getMessage() );
		}

		return $line;
	}

	/**
	 * A line of this family.
	 *
	 * @param string $key    The user and view ID, or what it is about.
	 * @param string $name   The view's name.
	 * @param Action $action What happens.
	 * @param string $note   Why, or what to know.
	 */
	private static function line( string $key, string $name, Action $action, string $note ): MigrationLine {
		return new MigrationLine( Family::Views, $key, $name, 'view', '', $action, '', $note );
	}
}
