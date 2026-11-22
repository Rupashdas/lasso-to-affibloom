<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\ContentConverter;
use AffibloomLassoMigrator\Domain\Conversion\AiTexts;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeAttributeParser;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeConversionLock;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeTextStore;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM5 (F10 AC27; F31 A6-A13, A44-A51, R15): Lasso's other display types become Affibloom's of that type, with the
 * group or table mapped; what Affibloom's block cannot draw is `[affibloom …]` in a Shortcode block (deviation 159); an
 * AI text box keeps its cached text, taken from Lasso when Affibloom has none, else is reported.
 */
final class ContentConverterTypesTest extends TestCase {

	/** @var array<string, string> Affibloom's AI texts, by "format prompt". */
	public array $affibloom = array();

	/** @var array<string, string> Lasso's cached AI texts, by "format prompt". */
	public array $lasso = array( 'html Best shoes?' => '<p>These.</p>' );

	private function converter( FakeTextStore $posts ): ContentConverter {
		$test = $this;
		$ai   = new class( $test ) implements AiTexts {
			public function __construct( private ContentConverterTypesTest $test ) {}
			public function affibloom( string $prompt, string $format ): ?string {
				return $this->test->affibloom[ ( '' === $format ? 'html' : $format ) . ' ' . $prompt ] ?? null;
			}
			public function lasso( string $prompt, string $format ): ?string {
				return $this->test->lasso[ ( '' === $format ? 'html' : $format ) . ' ' . $prompt ] ?? null;
			}
			public function store( string $prompt, string $format, string $text ): void {
				$this->test->affibloom[ ( '' === $format ? 'html' : $format ) . ' ' . $prompt ] = $text;
			}
		};

		return new ContentConverter( array( $posts ), ContentConverterTest::blocks(), static fn (): IdMaps => new IdMaps( array( 14722 => 22092 ), array( 'shoes' => 'best-shoes' ), array( 2 => 22101 ) ), new FakeAttributeParser(), new FakeConversionLock(), $ai );
	}

	/**
	 * Each line as "action | target or reason".
	 *
	 * @param list<ConversionLine> $lines The lines.
	 * @return list<string>
	 */
	private static function lines( array $lines ): array {
		return array_map( static fn ( ConversionLine $line ): string => "{$line->action} | " . ( '' === $line->target ? $line->reason : $line->target ), $lines );
	}

	public function test_each_display_type_becomes_affibloom_s(): void {
		$posts = ( new FakeTextStore() )->add(
			'9',
			9,
			'[lasso id="14722" type="button" button_type="secondary"][lasso id="14722" type="image-link"][lasso id="14722" type="link" label="Get it"]'
			. '[lasso type="grid" category="shoes" columns="3"][lasso type="list" categories="shoes" bullets="square"][lasso type="gallery" category="shoes"]'
			. '[lasso type="table" id="2"][lasso id="14722" sitestripe="true"][lasso amazon_url="https://www.amazon.com/dp/B0SHOE0001"]'
		);

		self::assertSame(
			array(
				'convert | [affibloom id="22092" type="button" button_type="secondary"]',
				'convert | [affibloom id="22092" type="image-link"]',
				'convert | [affibloom id="22092" type="link" label="Get it"]',
				'convert | [affibloom type="grid" columns="3" group="best-shoes"]',
				'convert | [affibloom type="list" bullets="square" group="best-shoes"]',
				'convert | [affibloom type="gallery" group="best-shoes"]',
				'convert | [affibloom type="table" id="22101"]',
				'convert | [affibloom id="22092" sitestripe="true"]',
				'convert | [affibloom amazon_url="https://www.amazon.com/dp/B0SHOE0001"]',
			),
			self::lines( $this->converter( $posts )->plan() )
		);
	}

	public function test_a_display_the_block_cannot_draw_becomes_a_shortcode_block(): void {
		$posts = ( new FakeTextStore() )->add( '9', 9, '<!-- wp:affiliate-plugin/lasso {"short_code":"[lasso id=\"14722\" type=\"link\"]"} /-->' );

		self::assertSame( array( "convert | <!-- wp:shortcode -->\n[affibloom id=\"22092\" type=\"link\"]\n<!-- /wp:shortcode -->" ), self::lines( $this->converter( $posts )->plan() ) );
	}

	public function test_an_ai_text_box_keeps_lassos_cached_text_or_is_reported(): void {
		$posts = ( new FakeTextStore() )->add( '9', 9, '[lasso prompt="Best shoes?"][lasso prompt="Gone?" format="text"]' );

		self::assertSame(
			array(
				'convert | [affibloom prompt="Best shoes?"]',
				"blocked | its AI text has no cached text, in Lasso or in Affibloom, and Affibloom never asks a service for one (F30 D14): write it as text, or remove it",
			),
			self::lines( $this->converter( $posts )->plan() )
		);
		self::assertSame( array(), $this->affibloom, 'The plan writes nothing.' );

		$this->converter( $posts )->run();
		self::assertSame( array( 'html Best shoes?' => '<p>These.</p>' ), $this->affibloom );
		self::assertSame( '[affibloom prompt="Best shoes?"][lasso prompt="Gone?" format="text"]', $posts->texts['9'] );
	}
}
