<?php
/**
 * Lasso's stamps out of the content, its ties kept.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\ConversionLock;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Domain\Conversion\LassoStamps;
use AffibloomLassoMigrator\Domain\Conversion\LinkTies;
use AffibloomLassoMigrator\Domain\Conversion\StampLine;
use AffibloomLassoMigrator\Domain\Conversion\TextStore;
use AffibloomLassoMigrator\Domain\Conversion\TieLog;
use Closure;

/**
 * Lasso's stamps out of the posts (Task LM6; F34 R18, D2, D4, AC6; F21 R3, R37): each post's `data-lasso-*` attributes
 * and AAWP's class go and `lasso-scan-ignore` becomes Affibloom's, every other byte kept (LassoStamps). Before a
 * `data-lasso-lid` goes, Affibloom's link index is asked which product the link is tied to by its own address; where
 * that is not the product Lasso's record became, the link is tied to it (an assignment, kept across a rescan), so it
 * stays tied. A link an editor assigned in Affibloom, or turned back to its original URL, keeps that. A post whose tie
 * cannot be kept (a record with no product, a link the index does not have yet, a link Lasso tied to several records)
 * keeps its stamps. Each post keeps its content from before in a copy and a revision; revert puts it back exactly and
 * clears the ties made. One run or revert at a time, with the conversion's lock.
 */
final class StampCleaner {

	/**
	 * Builds the cleaner.
	 *
	 * @param TextStore        $posts The posts with Lasso's stamps, with their own copy.
	 * @param Closure():IdMaps $maps  The migration's maps, read once per plan or run.
	 * @param LinkTies         $ties  Affibloom's link index.
	 * @param TieLog           $log   The ties made, for revert.
	 * @param ConversionLock   $lock  One run or revert at a time.
	 */
	public function __construct( private TextStore $posts, private Closure $maps, private LinkTies $ties, private TieLog $log, private ConversionLock $lock ) {}

	/**
	 * What would change in every post, or one; nothing written.
	 *
	 * @param int $post One post; 0 for every post.
	 * @return list<StampLine>
	 */
	public function plan( int $post = 0 ): array {
		$maps  = ( $this->maps )();
		$lines = array();
		foreach ( $this->posts->texts( $post ) as $text ) {
			$lines = array_merge( $lines, $this->look( $text, $maps )['lines'] );
		}

		return $lines;
	}

	/**
	 * Cleans every post, or one: its ties made, then its content written in one save.
	 *
	 * @param int $post One post; 0 for every post.
	 * @return list<StampLine>
	 * @throws ConversionLocked When another run or revert is going on.
	 */
	public function run( int $post = 0 ): array {
		$this->take();
		try {
			$maps  = ( $this->maps )();
			$lines = array();
			foreach ( $this->posts->texts( $post ) as $text ) {
				$lines = array_merge( $lines, $this->clean( $text, $maps ) );
			}

			return $lines;
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * Puts every cleaned post, or one, back as it was, and clears the ties made in it.
	 *
	 * @param int  $post  One post; 0 for every cleaned post.
	 * @param bool $force Revert posts changed after their cleaning too.
	 * @return array<string, string> Each post's result (TextStore's REVERTED, EDITED, NONE or FAILED), by a label.
	 * @throws ConversionLocked When another run or revert is going on.
	 */
	public function revert( int $post, bool $force ): array {
		$this->take();
		try {
			$results = array();
			foreach ( $this->posts->converted( $post ) as $place ) {
				// The ties go first, so the revert's save rescans each link by its own address; back on a post kept.
				$ties = $this->log->of( (int) $place );
				foreach ( $ties as $tie ) {
					$this->ties->untie( (int) $place, $tie['url'], $tie['product'] );
				}
				$result = $this->posts->revert( $place, $force );
				if ( TextStore::REVERTED === $result ) {
					$this->log->forget( (int) $place );
				} else {
					foreach ( $ties as $tie ) {
						$this->ties->tie( (int) $place, $tie['url'], $tie['product'] );
					}
				}
				$results[ $this->posts->name() . ' ' . $place ] = $result;
			}

			return $results;
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * Takes the lock.
	 *
	 * @throws ConversionLocked When another run or revert holds it.
	 */
	private function take(): void {
		if ( ! $this->lock->take() ) {
			throw new ConversionLocked( 'A conversion is already running.' );
		}
	}

	/**
	 * One post cleaned: its ties made, its content written; a post that did not save is left as it was, its ties undone.
	 *
	 * @param array{key: string, post: int, place: string, status: string, text: string} $text The post.
	 * @param IdMaps                                                                     $maps The migration's maps.
	 * @return list<StampLine>
	 */
	private function clean( array $text, IdMaps $maps ): array {
		$look = $this->look( $text, $maps );
		if ( null === $look['after'] ) {
			return $look['lines'];
		}

		$tied = array();
		foreach ( $look['tie'] as [ $url, $product ] ) {
			if ( $this->ties->tie( $text['post'], $url, $product ) > 0 ) {
				$tied[] = array( $url, $product );
			}
		}
		if ( $this->posts->write( $text['key'], $text['text'], $look['after'] ) ) {
			foreach ( $tied as [ $url, $product ] ) {
				$this->log->add( $text['post'], $url, $product );
			}

			return $look['lines'];
		}

		foreach ( $tied as [ $url, $product ] ) {
			$this->ties->untie( $text['post'], $url, $product );
		}

		return array_map( static fn ( StampLine $line ): StampLine => new StampLine( $line->post, $line->status, StampLine::ERROR, $line->item, 'it did not save as written; it was left as it was' ), $look['lines'] );
	}

	/**
	 * A post's lines, its content without the stamps, and the ties to make; no content when it keeps its stamps.
	 *
	 * @param array{key: string, post: int, place: string, status: string, text: string} $text The post.
	 * @param IdMaps                                                                     $maps The migration's maps.
	 * @return array{lines: list<StampLine>, after: string|null, tie: list<array{string, int}>}
	 */
	private function look( array $text, IdMaps $maps ): array {
		$found = LassoStamps::clean( $text['text'] );
		if ( $found['text'] === $text['text'] ) {
			return array(
				'lines' => array(),
				'after' => null,
				'tie'   => array(),
			);
		}
		$line = static fn ( string $action, string $item, string $detail ): StampLine => new StampLine( $text['post'], $text['status'], $action, $item, $detail );

		// Lasso's record for each link, by its URL (each place of a link shares the index's identity).
		$records = array();
		foreach ( $found['ties'] as $tie ) {
			$records[ $tie['url'] ][ $tie['lasso'] ] = $tie['name'];
		}
		$lines   = array();
		$blocked = array();
		$tie     = array();
		foreach ( $records as $url => $lasso ) {
			$url = (string) $url;
			if ( count( $lasso ) > 1 ) {
				$blocked[] = $line( StampLine::BLOCKED, $url, 'Lasso tied this link to several records in this post (' . implode( ', ', array_keys( $lasso ) ) . '): assign each place on Opportunities, then run again' );
				continue;
			}
			$record  = (int) array_key_first( $lasso );
			$product = $maps->products[ $record ] ?? 0;
			if ( 0 === $product ) {
				$blocked[] = $line( StampLine::BLOCKED, $url, "Lasso record {$record} has no Affibloom product: migrate the products first" );
				continue;
			}
			$places = $this->ties->places( $text['post'], $url );
			if ( array() === $places ) {
				$blocked[] = $line( StampLine::BLOCKED, $url, "Affibloom's link index does not have this link in this post yet: let Affibloom index the post, then run again" );
				continue;
			}

			$named = sprintf( 'product %d (Lasso record %d%s)', $product, $record, '' === $lasso[ $record ] ? '' : ', ' . $lasso[ $record ] );
			$other = array();
			$kept  = array();
			foreach ( $places as $place ) {
				if ( $place['original'] ) {
					$kept[] = 'Affibloom turns it back to its original URL';
				} elseif ( $place['assigned'] > 0 && $place['assigned'] !== $product ) {
					$kept[] = "an editor assigned it to product {$place['assigned']} in Affibloom";
				} elseif ( 0 === $place['assigned'] && $place['product'] !== $product ) {
					$other[] = 0 === $place['product'] ? 'no product' : 'product ' . $place['product'];
				}
			}
			if ( array() !== $other ) {
				$tie[]   = array( $url, $product );
				$lines[] = $line( StampLine::TIE, $url, "{$named}; by its address Affibloom ties it to " . implode( ', ', array_unique( $other ) ) );
			}
			foreach ( array_unique( $kept ) as $why ) {
				$lines[] = $line( StampLine::KEEP, $url, "{$why}, not {$named}: kept" );
			}
		}
		if ( array() !== $blocked ) {
			return array(
				'lines' => $blocked,
				'after' => null,
				'tie'   => array(),
			);
		}
		array_unshift( $lines, $line( StampLine::REMOVE, self::counts( $found['counts'] ), '' ) );

		return array(
			'lines' => $lines,
			'after' => $found['text'],
			'tie'   => $tie,
		);
	}

	/**
	 * "3 data-lasso-id, 1 data-lasso-lid, …": the stamps a post has.
	 *
	 * @param array<string, int> $counts LassoStamps' counts.
	 */
	private static function counts( array $counts ): string {
		$parts = array();
		foreach ( array_filter( $counts ) as $stamp => $count ) {
			$parts[] = LassoStamps::IGNORE === $stamp ? "{$count} {$stamp} (becomes affibloom-scan-ignore)" : "{$count} {$stamp}";
		}

		return implode( ', ', $parts );
	}
}
