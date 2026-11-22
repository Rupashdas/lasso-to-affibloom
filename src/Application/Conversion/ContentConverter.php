<?php
/**
 * Lasso's content as Affibloom's.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\AffibloomBlocks;
use AffibloomLassoMigrator\Domain\Conversion\AffibloomTarget;
use AffibloomLassoMigrator\Domain\Conversion\Blocked;
use AffibloomLassoMigrator\Domain\Conversion\BlockRewriter;
use AffibloomLassoMigrator\Domain\Conversion\ContentWriter;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLock;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Domain\Conversion\LassoAttributes;
use AffibloomLassoMigrator\Domain\Conversion\LassoContent;
use AffibloomLassoMigrator\Domain\Conversion\LassoItem;
use Closure;

/**
 * The conversion of Lasso's content (F10 D16, AC25; Task LM2): every Lasso item of every post with what it becomes, or
 * why it stays. Lasso's block becomes Affibloom's Product box block only when the block keeps everything the box says;
 * a `[lasso …]` elsewhere becomes `[affibloom …]`, which speaks Lasso's attribute language (F31 D2). The plan writes
 * nothing. The run (LM3) writes Lasso's convertible blocks, each post once with a copy of its content before (D16);
 * bare shortcodes are LM4's. Revert puts a post's copy back exactly (D17). One run or revert at a time.
 */
final class ContentConverter {

	/**
	 * Builds the converter.
	 *
	 * @param LassoContent     $content The posts with Lasso items.
	 * @param AffibloomBlocks  $blocks  Affibloom's block.
	 * @param Closure():IdMaps $maps    The migration's maps, read once per plan or run.
	 * @param ContentWriter    $writer  Where converted posts are written, with their copies.
	 * @param ConversionLock   $lock    One run or revert at a time.
	 */
	public function __construct( private LassoContent $content, private AffibloomBlocks $blocks, private Closure $maps, private ContentWriter $writer, private ConversionLock $lock ) {}

	/**
	 * Converts the convertible Lasso blocks of every post, or of one: one line per item, `convert` for a block written,
	 * `later` for a bare shortcode (LM4), `blocked` with its reason, `error` for a post that did not save as written
	 * (it is left as it was).
	 *
	 * @param int $post One post; 0 for every post.
	 * @return list<ConversionLine>
	 * @throws ConversionLocked When another run or revert is going on.
	 */
	public function run( int $post = 0 ): array {
		if ( ! $this->lock->take() ) {
			throw new ConversionLocked( 'A conversion is already running.' );
		}
		try {
			$maps  = ( $this->maps )();
			$lines = array();
			foreach ( $this->content->posts( $post ) as $host ) {
				$lines = array_merge( $lines, $this->convert( $host, $maps ) );
			}

			return $lines;
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * Puts every converted post, or one, back as it was before its first conversion.
	 *
	 * @param int  $post  One post; 0 for every converted post.
	 * @param bool $force Revert posts edited after their conversion too.
	 * @return array<int, string> Each post's result (ContentWriter's REVERTED, EDITED, NONE or FAILED).
	 * @throws ConversionLocked When another run or revert is going on.
	 */
	public function revert( int $post, bool $force ): array {
		if ( ! $this->lock->take() ) {
			throw new ConversionLocked( 'A conversion is already running.' );
		}
		try {
			$results = array();
			foreach ( $post > 0 ? array( $post ) : $this->writer->converted() as $one ) {
				$results[ $one ] = $this->writer->revert( $one, $force );
			}

			return $results;
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * One post's lines, its convertible blocks written in one save.
	 *
	 * @param array{id: int, status: string, title: string, items: list<LassoItem>} $host The post.
	 * @param IdMaps                                                                $maps The migration's maps.
	 * @return list<ConversionLine>
	 */
	private function convert( array $host, IdMaps $maps ): array {
		$lines        = array();
		$replacements = array();
		$written      = array();
		foreach ( $host['items'] as $item ) {
			$target = $this->target( $item, $maps );
			$line   = static fn ( string $action, string $text, string $reason ): ConversionLine => new ConversionLine( $host['id'], $host['status'], $item->kind, $item->markup, $action, $text, $reason );
			if ( $target instanceof Blocked ) {
				$lines[] = $line( ConversionLine::BLOCKED, '', $target->reason );
				if ( LassoItem::BLOCK === $item->kind ) {
					$replacements[] = null;
				}
			} elseif ( LassoItem::BLOCK !== $item->kind ) {
				$lines[] = $line( ConversionLine::LATER, $target->text(), 'bare shortcodes are converted with Task LM4' );
			} else {
				$written[]      = count( $lines );
				$replacements[] = AffibloomTarget::BLOCK === $target->kind ? $this->blocks->markup( $target->attributes ) : $target->text();
				$lines[]        = $line( ConversionLine::CONVERT, $target->text(), '' );
			}
		}
		if ( array() === $written ) {
			return $lines;
		}

		$before = $this->writer->content( $host['id'] );
		$after  = null === $before ? null : BlockRewriter::rewrite( $before, $replacements );
		$saved  = null !== $before && null !== $after && $this->writer->write( $host['id'], $before, $after );
		if ( ! $saved ) {
			$reason = null === $after ? "the post's Lasso blocks could not be matched; it was left as it was" : 'the post did not save as written; it was left as it was';
			foreach ( $written as $index ) {
				$lines[ $index ] = new ConversionLine( $lines[ $index ]->post, $lines[ $index ]->status, $lines[ $index ]->kind, $lines[ $index ]->lasso, ConversionLine::ERROR, '', $reason );
			}
		}

		return $lines;
	}

	/**
	 * One line per item, nothing written.
	 *
	 * @param int $post One post; 0 for every post.
	 * @return list<ConversionLine>
	 */
	public function plan( int $post = 0 ): array {
		$maps  = ( $this->maps )();
		$lines = array();
		foreach ( $this->content->posts( $post ) as $host ) {
			foreach ( $host['items'] as $item ) {
				$target  = $this->target( $item, $maps );
				$lines[] = $target instanceof Blocked
					? new ConversionLine( $host['id'], $host['status'], $item->kind, $item->markup, ConversionLine::BLOCKED, '', $target->reason )
					: new ConversionLine( $host['id'], $host['status'], $item->kind, $item->markup, ConversionLine::CONVERT, $target->text(), '' );
			}
		}

		return $lines;
	}

	/**
	 * What an item becomes, or why it stays.
	 *
	 * @param LassoItem $item The item.
	 * @param IdMaps    $maps The migration's maps.
	 */
	private function target( LassoItem $item, IdMaps $maps ): AffibloomTarget|Blocked {
		if ( LassoItem::ATTR === $item->kind ) {
			return new Blocked( '[lasso-attr] is converted with the bare shortcodes (Task LM4)' );
		}
		if ( LassoItem::BLOCK === $item->kind && '' === trim( $item->markup ) ) {
			return new Blocked( "Lasso's block is empty: no display was chosen in it" );
		}
		$attributes = LassoAttributes::to_affibloom( LassoItem::BLOCK === $item->kind ? $item->box() : $item->attributes, $maps );
		if ( $attributes instanceof Blocked ) {
			return $attributes;
		}
		if ( LassoItem::SHORTCODE === $item->kind ) {
			return AffibloomTarget::shortcode( $attributes );
		}

		// A box Affibloom's block cannot draw, or could not hold whole, becomes `[affibloom …]` in a shortcode block,
		// which reads every attribute: nothing is lost (deviation 158, the user, 2026-11-23; F10 D16 as changed).
		$block = $this->blocks->for_shortcode( $attributes );
		if ( null === $block || array() !== $block['lost'] ) {
			return AffibloomTarget::shortcode_block( $attributes );
		}

		return AffibloomTarget::block( $block['attributes'] );
	}
}
