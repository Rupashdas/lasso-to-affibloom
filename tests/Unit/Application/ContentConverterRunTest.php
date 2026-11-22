<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\ContentConverter;
use AffibloomLassoMigrator\Application\Conversion\ConversionLocked;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Domain\Conversion\TextStore;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeAttributeParser;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeConversionLock;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeTextStore;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Tasks LM3, LM4 (F10 D16, D17, AC3, AC26): the convertible items of each text are written in one save, the place keeping
 * its content from before; revert puts it back exactly, refusing a place edited since unless forced; one run at a time.
 */
final class ContentConverterRunTest extends TestCase {

	private const BEFORE = "<p>A [lasso id=\"14722\"]</p>\n<!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"14722\\\"]\"} /-->\n<!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"999\\\"]\"} /-->";

	private const AFTER = "<p>A [affibloom id=\"22092\"]</p>\n<!-- wp:affibloom/product-box {\"type\":\"single\",\"productId\":22092} /-->\n<!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"999\\\"]\"} /-->";

	private FakeTextStore $posts;

	private FakeTextStore $widgets;

	private FakeConversionLock $lock;

	protected function setUp(): void {
		parent::setUp();
		$this->posts   = ( new FakeTextStore() )->add( '7', 7, self::BEFORE );
		$this->widgets = ( new FakeTextStore( 'widget_text #2', 'widget' ) )->add( 'widget_text:2', 0, '<p>[lasso id="14722"]</p>' );
		$this->lock    = new FakeConversionLock();
	}

	private function converter(): ContentConverter {
		return new ContentConverter( array( $this->posts, $this->widgets ), ContentConverterTest::blocks(), static fn (): IdMaps => new IdMaps( array( 14722 => 22092 ) ), new FakeAttributeParser(), $this->lock );
	}

	/**
	 * Each line as "kind: action".
	 *
	 * @param list<ConversionLine> $lines The lines.
	 * @return list<string>
	 */
	private static function actions( array $lines ): array {
		return array_map( static fn ( ConversionLine $line ): string => "{$line->kind}: {$line->action}", $lines );
	}

	public function test_each_text_is_written_once_and_what_stays_is_left(): void {
		self::assertSame( array( 'shortcode: convert', 'block: convert', 'block: blocked', 'shortcode: convert' ), self::actions( $this->converter()->run() ) );
		self::assertSame( self::AFTER, $this->posts->texts['7'] );
		self::assertSame( self::BEFORE, $this->posts->copies['7'] );
		self::assertSame( '<p>[affibloom id="22092"]</p>', $this->widgets->texts['widget_text:2'] );
		self::assertFalse( $this->lock->held );

		// A second run writes nothing, and the copies stay the first ones.
		self::assertSame( array( 'block: blocked' ), self::actions( $this->converter()->run() ) );
		self::assertSame( array( '7' ), $this->posts->writes );
	}

	public function test_a_text_that_does_not_save_as_written_is_an_error_and_unchanged(): void {
		$this->posts->mangle = true;

		self::assertSame( array( 'shortcode: error', 'block: error', 'block: blocked', 'shortcode: convert' ), self::actions( $this->converter()->run() ) );
		self::assertSame( self::BEFORE, $this->posts->texts['7'] );
	}

	public function test_revert_puts_places_back_and_refuses_one_edited_since(): void {
		$this->converter()->run();
		$this->posts->texts['7'] .= ' edited';

		self::assertSame(
			array(
				'post 7'                   => TextStore::EDITED,
				'widget_text #2 widget_text:2' => TextStore::REVERTED,
			),
			$this->converter()->revert( 0, false )
		);
		self::assertSame( array( 'post 7' => TextStore::REVERTED ), $this->converter()->revert( 7, true ) );
		self::assertSame( self::BEFORE, $this->posts->texts['7'] );
		self::assertSame( '<p>[lasso id="14722"]</p>', $this->widgets->texts['widget_text:2'] );
		self::assertSame( array(), $this->converter()->revert( 7, false ) );
	}

	public function test_one_run_at_a_time(): void {
		$this->lock->held = true;

		$this->expectException( ConversionLocked::class );
		$this->converter()->run();
	}
}
