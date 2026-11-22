<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\ContentConverter;
use AffibloomLassoMigrator\Domain\Conversion\AffibloomBlocks;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Domain\Conversion\LassoContent;
use AffibloomLassoMigrator\Domain\Conversion\LassoItem;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM2 (F10 AC25, D16): every Lasso item of a post with what it becomes, or why it stays; nothing is written.
 */
final class ContentConverterTest extends TestCase {

	private function converter(): ContentConverter {
		$content = new class() implements LassoContent {
			public function posts( int $post = 0 ): array {
				$all = array(
					array(
						'id'     => 2622,
						'status' => 'publish',
						'title'  => 'Best adidas shoes',
						'items'  => array(
							new LassoItem( LassoItem::BLOCK, '[lasso badge="Best" id="14722" link_id="13137"]', array( 'badge' => 'Best', 'id' => '14722', 'link_id' => '13137' ), array( 'schema_review' => false, 'schema_price' => '130.84', 'schema_review_author' => 'Harvey Meale' ) ),
							new LassoItem( LassoItem::BLOCK, '[lasso id="14722" rating="4.5"]', array( 'id' => '14722', 'rating' => '4.5' ) ),
							new LassoItem( LassoItem::SHORTCODE, '[lasso id="14722" rating="4.5"]', array( 'id' => '14722', 'rating' => '4.5' ) ),
						),
					),
					array(
						'id'     => 9492,
						'status' => 'draft',
						'title'  => 'Tables',
						'items'  => array(
							new LassoItem( LassoItem::BLOCK, '[lasso id="5"]', array( 'id' => '5' ) ),
							new LassoItem( LassoItem::ATTR, '[lasso-attr id="14722" field="2"]', array( 'id' => '14722', 'field' => '2' ) ),
							new LassoItem( LassoItem::BLOCK, '', array() ),
						),
					),
				);

				return 0 === $post ? $all : array_values( array_filter( $all, static fn ( array $one ): bool => $one['id'] === $post ) );
			}
		};
		// Affibloom's block keeps badge and schemaPrice, not a rating's number.
		$blocks = new class() implements AffibloomBlocks {
			public function for_shortcode( array $attributes ): ?array {
				$lost = array_values( array_diff( array_keys( $attributes ), array( 'id', 'badge', 'schema_price' ) ) );
				$out  = array(
					'type'      => 'single',
					'productId' => (int) $attributes['id'],
					'overrides' => array_intersect_key( $attributes, array( 'badge' => '' ) ),
				);
				if ( isset( $attributes['schema_price'] ) ) {
					$out['schemaPrice'] = $attributes['schema_price'];
				}

				return array(
					'attributes' => $out,
					'lost'       => $lost,
				);
			}
			public function markup( array $attributes ): string {
				return '';
			}
		};

		return new ContentConverter( $content, $blocks, static fn (): IdMaps => new IdMaps( array( 14722 => 22092 ) ), new \AffibloomLassoMigrator\Tests\Unit\Fakes\FakeContentWriter(), new \AffibloomLassoMigrator\Tests\Unit\Fakes\FakeConversionLock() );
	}

	/**
	 * Each line as "post kind: action | target or reason".
	 *
	 * @param list<ConversionLine> $lines The lines.
	 * @return list<string>
	 */
	private static function lines( array $lines ): array {
		return array_map( static fn ( ConversionLine $line ): string => "{$line->post} {$line->kind}: {$line->action} | " . ( '' === $line->target ? $line->reason : $line->target ), $lines );
	}

	public function test_every_item_is_converted_or_blocked_with_its_reason(): void {
		self::assertSame(
			array(
				'2622 block: convert | <!-- wp:affibloom/product-box {"type":"single","productId":22092,"overrides":{"badge":"Best"},"schemaPrice":"130.84"} /-->',
				"2622 block: blocked | Affibloom's block cannot hold rating (F10 D16)",
				'2622 shortcode: convert | [affibloom id="22092" rating="4.5"]',
				'9492 block: blocked | Lasso record 5 has no Affibloom product',
				'9492 lasso-attr: blocked | [lasso-attr] is converted with the bare shortcodes (Task LM4)',
				"9492 block: blocked | Lasso's block is empty: no display was chosen in it",
			),
			self::lines( $this->converter()->plan() )
		);
	}

	public function test_one_post(): void {
		self::assertCount( 3, $this->converter()->plan( 9492 ) );
	}
}
