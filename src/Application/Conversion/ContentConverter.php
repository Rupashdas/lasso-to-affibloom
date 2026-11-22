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
 * nothing; LM3-LM5 write.
 */
final class ContentConverter {

	/**
	 * Builds the converter.
	 *
	 * @param LassoContent     $content The posts with Lasso items.
	 * @param AffibloomBlocks  $blocks  Affibloom's block.
	 * @param Closure():IdMaps $maps    The migration's maps, read once per plan.
	 */
	public function __construct( private LassoContent $content, private AffibloomBlocks $blocks, private Closure $maps ) {}

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

		$block = $this->blocks->for_shortcode( $attributes );
		if ( null === $block ) {
			return new Blocked( "Affibloom's block cannot draw this display" );
		}
		if ( array() !== $block['lost'] ) {
			return new Blocked( "Affibloom's block cannot hold " . implode( ', ', $block['lost'] ) . ' (F10 D16)' );
		}

		return AffibloomTarget::block( $block['attributes'] );
	}
}
