<?php
/**
 * The blocks Lasso's scan rewrote, as their plugin saves them.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\ConversionLock;
use AffibloomLassoMigrator\Domain\Conversion\GenerateBlocksButtons;
use AffibloomLassoMigrator\Domain\Conversion\StampLine;
use AffibloomLassoMigrator\Domain\Conversion\TextStore;

/**
 * The repair of the GenerateBlocks buttons Lasso left with settings and HTML that disagree (Task LM6A, deviation 160),
 * so the block editor no longer flags them once Lasso is off: each post's buttons in one save, what readers get kept
 * (GenerateBlocksButtons). Each post keeps its content from before in a copy and a revision; revert puts it back
 * exactly. One run or revert at a time, with the conversion's lock.
 */
final class BlockRepairer {

	/**
	 * Builds the repair.
	 *
	 * @param TextStore      $posts The posts with GenerateBlocks buttons, with their own copy.
	 * @param ConversionLock $lock  One run or revert at a time.
	 */
	public function __construct( private TextStore $posts, private ConversionLock $lock ) {}

	/**
	 * What would change in every post, or one; nothing written.
	 *
	 * @param int $post One post; 0 for every post.
	 * @return list<StampLine>
	 */
	public function plan( int $post = 0 ): array {
		$lines = array();
		foreach ( $this->posts->texts( $post ) as $text ) {
			$lines = array_merge( $lines, $this->look( $text )['lines'] );
		}

		return $lines;
	}

	/**
	 * Repairs every post, or one.
	 *
	 * @param int $post One post; 0 for every post.
	 * @return list<StampLine>
	 * @throws ConversionLocked When another run or revert is going on.
	 */
	public function run( int $post = 0 ): array {
		$this->take();
		try {
			$lines = array();
			foreach ( $this->posts->texts( $post ) as $text ) {
				$look = $this->look( $text );
				if ( null !== $look['after'] && ! $this->posts->write( $text['key'], $text['text'], $look['after'] ) ) {
					$look['lines'] = array_map( static fn ( StampLine $line ): StampLine => StampLine::REPAIR === $line->action ? new StampLine( $line->post, $line->status, StampLine::ERROR, $line->item, 'it did not save as written; it was left as it was' ) : $line, $look['lines'] );
				}
				$lines = array_merge( $lines, $look['lines'] );
			}

			return $lines;
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * Puts every repaired post, or one, back as it was.
	 *
	 * @param int  $post  One post; 0 for every repaired post.
	 * @param bool $force Revert posts changed after their repair too.
	 * @return array<string, string> Each post's result (TextStore's REVERTED, EDITED, NONE or FAILED), by a label.
	 * @throws ConversionLocked When another run or revert is going on.
	 */
	public function revert( int $post, bool $force ): array {
		$this->take();
		try {
			$results = array();
			foreach ( $this->posts->converted( $post ) as $place ) {
				$results[ $this->posts->name() . ' ' . $place ] = $this->posts->revert( $place, $force );
			}

			return $results;
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * The repaired posts, with a copy to revert to.
	 *
	 * @return list<string>
	 */
	public function converted(): array {
		return $this->posts->converted();
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
	 * A post's lines and its repaired content; no content when nothing changes.
	 *
	 * @param array{key: string, post: int, place: string, status: string, text: string} $text The post.
	 * @return array{lines: list<StampLine>, after: string|null}
	 */
	private function look( array $text ): array {
		$repaired = GenerateBlocksButtons::repair( $text['text'] );
		$lines    = array();
		if ( $repaired['repaired'] > 0 ) {
			$lines[] = new StampLine( $text['post'], $text['status'], StampLine::REPAIR, sprintf( '%d GenerateBlocks buttons', $repaired['repaired'] ), '' );
		}
		foreach ( $repaired['skipped'] as $settings ) {
			$lines[] = new StampLine( $text['post'], $text['status'], StampLine::BLOCKED, 'a GenerateBlocks button', "its settings would not be written back exactly as WordPress writes them, so it is left: repair it in the editor ({$settings})" );
		}

		return array(
			'lines' => $lines,
			'after' => $repaired['repaired'] > 0 ? $repaired['text'] : null,
		);
	}
}
