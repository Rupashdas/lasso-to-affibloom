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
use AffibloomLassoMigrator\Domain\Conversion\AttributeParser;
use AffibloomLassoMigrator\Domain\Conversion\Blocked;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLock;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Domain\Conversion\LassoAttributes;
use AffibloomLassoMigrator\Domain\Conversion\LassoItem;
use AffibloomLassoMigrator\Domain\Conversion\LassoMarkup;
use AffibloomLassoMigrator\Domain\Conversion\TextStore;
use Closure;

/**
 * The conversion of Lasso's content (F10 D16, D17, AC3, AC25, AC26; Tasks LM2-LM4): every Lasso item in the stores'
 * texts (posts, widgets, Elementor data) with what it becomes, or why it stays. Lasso's block, and a Shortcode block
 * holding one `[lasso …]`, become Affibloom's Product box block when it keeps everything the box says, else `[affibloom …]`
 * in a Shortcode block (deviation 159); a bare `[lasso …]` becomes `[affibloom …]`, which speaks Lasso's attribute
 * language (F31 D2); `[lasso-attr …]` is reported. The plan writes nothing; the run writes each text once, its place
 * keeping a copy of its content before; revert puts places back exactly. One run or revert at a time.
 */
final class ContentConverter {

	/**
	 * Builds the converter.
	 *
	 * @param list<TextStore>  $stores  Where Lasso's markup is: posts, widgets, Elementor data.
	 * @param AffibloomBlocks  $blocks  Affibloom's block.
	 * @param Closure():IdMaps $maps    The migration's maps, read once per plan or run.
	 * @param AttributeParser  $parser  Reads a shortcode's attributes as WordPress does.
	 * @param ConversionLock   $lock    One run or revert at a time.
	 */
	public function __construct( private array $stores, private AffibloomBlocks $blocks, private Closure $maps, private AttributeParser $parser, private ConversionLock $lock ) {}

	/**
	 * One line per item, nothing written.
	 *
	 * @param int $post One post's texts; 0 for every text.
	 * @return list<ConversionLine>
	 */
	public function plan( int $post = 0 ): array {
		$maps  = ( $this->maps )();
		$lines = array();
		foreach ( $this->stores as $store ) {
			foreach ( $store->texts( $post ) as $text ) {
				$lines = array_merge( $lines, $this->convert( $text, $maps )['lines'] );
			}
		}

		return $lines;
	}

	/**
	 * Converts every text, or one post's: one line per item, `convert` for an item written, `blocked` with its reason,
	 * `error` for a text that did not store as written (its place is left as it was).
	 *
	 * @param int $post One post's texts; 0 for every text.
	 * @return list<ConversionLine>
	 * @throws ConversionLocked When another run or revert is going on.
	 */
	public function run( int $post = 0 ): array {
		$this->take();
		try {
			$maps  = ( $this->maps )();
			$lines = array();
			foreach ( $this->stores as $store ) {
				foreach ( $store->texts( $post ) as $text ) {
					$lines = array_merge( $lines, $this->write( $store, $text, $maps ) );
				}
			}

			return $lines;
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * Puts every converted place, or one post's, back as it was before its first conversion.
	 *
	 * @param int  $post  One post's places; 0 for every converted place.
	 * @param bool $force Revert places changed after their conversion too.
	 * @return array<string, string> Each place's result (TextStore's REVERTED, EDITED, NONE or FAILED), by a label.
	 * @throws ConversionLocked When another run or revert is going on.
	 */
	public function revert( int $post, bool $force ): array {
		$this->take();
		try {
			$results = array();
			foreach ( $this->stores as $store ) {
				foreach ( $store->converted( $post ) as $place ) {
					$results[ $store->name() . ' ' . $place ] = $store->revert( $place, $force );
				}
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
	 * One text's lines, its convertible items written in one save.
	 *
	 * @param TextStore                                                                  $store The text's store.
	 * @param array{key: string, post: int, place: string, status: string, text: string} $text  The text.
	 * @param IdMaps                                                                     $maps  The migration's maps.
	 * @return list<ConversionLine>
	 */
	private function write( TextStore $store, array $text, IdMaps $maps ): array {
		$converted = $this->convert( $text, $maps );
		$lines     = $converted['lines'];
		$written   = array_keys( array_filter( $lines, static fn ( ConversionLine $line ): bool => ConversionLine::CONVERT === $line->action ) );
		if ( array() === $written ) {
			return $lines;
		}

		$after = LassoMarkup::rewrite( $text['text'], $converted['replacements'] );
		if ( null !== $after && $store->write( $text['key'], $text['text'], $after ) ) {
			return $lines;
		}
		$reason = null === $after ? "the text's Lasso items could not be matched; it was left as it was" : 'it did not save as written; it was left as it was';
		foreach ( $written as $index ) {
			$line            = $lines[ $index ];
			$lines[ $index ] = new ConversionLine( $line->post, $line->place, $line->status, $line->kind, $line->lasso, ConversionLine::ERROR, '', $reason );
		}

		return $lines;
	}

	/**
	 * A text's items: one line each, and the replacement of each (null where it stays).
	 *
	 * @param array{key: string, post: int, place: string, status: string, text: string} $text The text.
	 * @param IdMaps                                                                     $maps The migration's maps.
	 * @return array{lines: list<ConversionLine>, replacements: list<string|null>}
	 */
	private function convert( array $text, IdMaps $maps ): array {
		$lines        = array();
		$replacements = array();
		foreach ( LassoMarkup::scan( $text['text'] ) as $found ) {
			$item   = new LassoItem( $found['kind'], LassoItem::BLOCK === $found['kind'] ? (string) ( $found['block']['short_code'] ?? '' ) : $found['markup'], $this->parser->parse( $found['attributes'] ), $found['block'] );
			$target = $this->target( $item, $maps );
			if ( $target instanceof Blocked ) {
				$lines[]        = new ConversionLine( $text['post'], $text['place'], $text['status'], $item->kind, $item->markup, ConversionLine::BLOCKED, '', $target->reason );
				$replacements[] = null;
				continue;
			}
			$lines[]        = new ConversionLine( $text['post'], $text['place'], $text['status'], $item->kind, $item->markup, ConversionLine::CONVERT, $target->text(), '' );
			$replacements[] = AffibloomTarget::BLOCK === $target->kind ? $this->blocks->markup( $target->attributes ) : $target->text();
		}

		return array(
			'lines'        => $lines,
			'replacements' => $replacements,
		);
	}

	/**
	 * What an item becomes, or why it stays.
	 *
	 * @param LassoItem $item The item.
	 * @param IdMaps    $maps The migration's maps.
	 */
	private function target( LassoItem $item, IdMaps $maps ): AffibloomTarget|Blocked {
		if ( LassoItem::ATTR === $item->kind ) {
			return new Blocked( "[lasso-attr] prints Lasso's legacy attributes, which Lasso no longer writes (F31 R3): remove it by hand" );
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

		// Lasso's block, or a Shortcode block of one `[lasso …]`: Affibloom's block when it keeps the whole box, else
		// `[affibloom …]` in a Shortcode block, which reads every attribute (deviation 159).
		$block = $this->blocks->for_shortcode( $attributes );
		if ( null === $block || array() !== $block['lost'] ) {
			return AffibloomTarget::shortcode_block( $attributes );
		}

		return AffibloomTarget::block( $block['attributes'] );
	}
}
