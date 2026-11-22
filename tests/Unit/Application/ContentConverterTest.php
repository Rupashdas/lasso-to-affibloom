<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\ContentConverter;
use AffibloomLassoMigrator\Domain\Conversion\AffibloomBlocks;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeAttributeParser;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeConversionLock;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeTextStore;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Tasks LM2, LM4 (F10 AC25, AC26, D16 as changed by deviation 159): every Lasso item of every text with what it becomes,
 * or why it stays; nothing is written.
 */
final class ContentConverterTest extends TestCase {

	/** Affibloom's block keeps badge and schemaPrice, not a rating's number. */
	public static function blocks(): AffibloomBlocks {
		return new class() implements AffibloomBlocks {
			public function for_shortcode( array $attributes ): ?array {
				if ( ! isset( $attributes['id'] ) ) {
					return null;
				}
				$out = array(
					'type'      => 'single',
					'productId' => (int) $attributes['id'],
				);
				if ( isset( $attributes['badge'] ) ) {
					$out['overrides'] = array( 'badge' => $attributes['badge'] );
				}
				if ( isset( $attributes['schema_price'] ) ) {
					$out['schemaPrice'] = $attributes['schema_price'];
				}

				return array(
					'attributes' => $out,
					'lost'       => array_values( array_diff( array_keys( $attributes ), array( 'id', 'badge', 'schema_price' ) ) ),
				);
			}
			public function markup( array $attributes ): string {
				return '<!-- wp:affibloom/product-box ' . json_encode( $attributes ) . ' /-->';
			}
		};
	}

	private function converter( FakeTextStore ...$stores ): ContentConverter {
		return new ContentConverter( $stores, self::blocks(), static fn (): IdMaps => new IdMaps( array( 14722 => 22092 ) ), new FakeAttributeParser(), new FakeConversionLock() );
	}

	/**
	 * Each line as "post place kind: action | target or reason".
	 *
	 * @param list<ConversionLine> $lines The lines.
	 * @return list<string>
	 */
	private static function lines( array $lines ): array {
		return array_map( static fn ( ConversionLine $line ): string => trim( "{$line->post} {$line->place}" ) . " {$line->kind}: {$line->action} | " . ( '' === $line->target ? $line->reason : $line->target ), $lines );
	}

	public function test_every_kind_in_a_post_is_converted_or_blocked_with_its_reason(): void {
		$posts = ( new FakeTextStore() )->add(
			'2622',
			2622,
			'<!-- wp:affiliate-plugin/lasso {"short_code":"[lasso badge=\"Best\" id=\"14722\" link_id=\"1\"]","schema_price":"130.84","schema_review_author":"Harvey Meale"} /-->'
			. '<!-- wp:affiliate-plugin/lasso {"short_code":"[lasso id=\"14722\" rating=\"4.5\"]"} /-->'
			. "<p>See [lasso id=\"14722\" rating=\"4.5\"] here.</p>"
			. "<!-- wp:shortcode -->\n[lasso id=\"14722\"]\n<!-- /wp:shortcode -->"
			. '<!-- wp:affiliate-plugin/lasso {"short_code":"[lasso id=\"5\"]"} /-->'
			. '[lasso-attr id="14722" attr_name="x"]'
			. '<!-- wp:affiliate-plugin/lasso {"button_text":"Add a Display"} /-->'
		);

		self::assertSame(
			array(
				'2622 block: convert | <!-- wp:affibloom/product-box {"type":"single","productId":22092,"overrides":{"badge":"Best"},"schemaPrice":"130.84"} /-->',
				// Deviation 159 (the user, 2026-11-23): nothing is lost as [affibloom] in a shortcode block.
				"2622 block: convert | <!-- wp:shortcode -->\n[affibloom id=\"22092\" rating=\"4.5\"]\n<!-- /wp:shortcode -->",
				'2622 shortcode: convert | [affibloom id="22092" rating="4.5"]',
				'2622 shortcode-block: convert | <!-- wp:affibloom/product-box {"type":"single","productId":22092} /-->',
				'2622 block: blocked | Lasso record 5 has no Affibloom product',
				"2622 lasso-attr: blocked | [lasso-attr] prints Lasso's legacy attributes, which Lasso no longer writes (F31 R3): remove it by hand",
				"2622 block: blocked | Lasso's block is empty: no display was chosen in it",
			),
			self::lines( $this->converter( $posts )->plan() )
		);
		self::assertSame( array(), $posts->writes );
	}

	public function test_widgets_and_elementor_texts_are_listed_with_their_place(): void {
		$widgets   = ( new FakeTextStore( 'widget_text #3', 'widget' ) )->add( 'widget_text:3', 0, '[lasso id="14722"]' );
		$elementor = ( new FakeTextStore( 'elementor' ) )->add( '9|a1|lasso_shortcode', 9, '[lasso id="14722"]' );

		self::assertSame(
			array(
				'0 widget_text #3 shortcode: convert | [affibloom id="22092"]',
				'9 elementor shortcode: convert | [affibloom id="22092"]',
			),
			self::lines( $this->converter( $widgets, $elementor )->plan() )
		);
		self::assertCount( 1, $this->converter( $widgets, $elementor )->plan( 9 ) );
	}
}
