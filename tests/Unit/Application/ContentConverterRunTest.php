<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\ContentConverter;
use AffibloomLassoMigrator\Application\Conversion\ConversionLocked;
use AffibloomLassoMigrator\Domain\Conversion\AffibloomBlocks;
use AffibloomLassoMigrator\Domain\Conversion\ContentWriter;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Domain\Conversion\LassoContent;
use AffibloomLassoMigrator\Domain\Conversion\LassoItem;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeContentWriter;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeConversionLock;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM3 (F10 D16, D17, AC3): the convertible Lasso blocks are written as Affibloom's, with a copy of the post from
 * before; revert puts it back exactly, refuses a post edited since unless forced; one run at a time.
 */
final class ContentConverterRunTest extends TestCase {

	private const BEFORE = "<p>A</p>\n<!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"14722\\\"]\"} /-->\n<p>[lasso id=\"14722\"]</p>\n<!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"999\\\"]\"} /-->";

	private const AFTER = "<p>A</p>\n<!-- wp:affibloom/product-box {\"type\":\"single\",\"productId\":22092} /-->\n<p>[lasso id=\"14722\"]</p>\n<!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"999\\\"]\"} /-->";

	private FakeContentWriter $writer;

	private FakeConversionLock $lock;

	protected function setUp(): void {
		parent::setUp();
		$this->writer             = new FakeContentWriter();
		$this->writer->contents[7] = self::BEFORE;
		$this->lock               = new FakeConversionLock();
	}

	private function converter(): ContentConverter {
		$writer  = $this->writer;
		$content = new class( $writer ) implements LassoContent {
			public function __construct( private FakeContentWriter $writer ) {}
			public function posts( int $post = 0 ): array {
				$text  = $this->writer->contents[7];
				$items = array();
				if ( str_contains( $text, '14722\\"]"} /-->' ) ) {
					$items[] = new LassoItem( LassoItem::BLOCK, '[lasso id="14722"]', array( 'id' => '14722' ) );
				}
				$items[] = new LassoItem( LassoItem::SHORTCODE, '[lasso id="14722"]', array( 'id' => '14722' ) );
				$items[] = new LassoItem( LassoItem::BLOCK, '[lasso id="999"]', array( 'id' => '999' ) );

				return array(
					array(
						'id'     => 7,
						'status' => 'publish',
						'title'  => 'Guide',
						'items'  => $items,
					),
				);
			}
		};
		$blocks  = new class() implements AffibloomBlocks {
			public function for_shortcode( array $attributes ): ?array {
				return array(
					'attributes' => array(
						'type'      => 'single',
						'productId' => (int) $attributes['id'],
					),
					'lost'       => array(),
				);
			}
			public function markup( array $attributes ): string {
				return '<!-- wp:affibloom/product-box ' . json_encode( $attributes ) . ' /-->';
			}
		};

		return new ContentConverter( $content, $blocks, static fn (): IdMaps => new IdMaps( array( 14722 => 22092 ) ), $this->writer, $this->lock );
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

	public function test_the_convertible_blocks_are_written_and_the_rest_left(): void {
		self::assertSame( array( 'block: convert', 'shortcode: later', 'block: blocked' ), self::actions( $this->converter()->run() ) );
		self::assertSame( self::AFTER, $this->writer->contents[7] );
		self::assertSame( self::BEFORE, $this->writer->copies[7] );
		self::assertFalse( $this->lock->held );

		// A second run has nothing to write, and the copy stays the first one.
		self::assertSame( array( 'shortcode: later', 'block: blocked' ), self::actions( $this->converter()->run() ) );
		self::assertSame( array( 7 ), $this->writer->writes );
	}

	public function test_a_post_that_does_not_save_as_written_is_an_error_and_unchanged(): void {
		$this->writer->mangle = true;

		self::assertSame( array( 'block: error', 'shortcode: later', 'block: blocked' ), self::actions( $this->converter()->run() ) );
		self::assertSame( self::BEFORE, $this->writer->contents[7] );
	}

	public function test_revert_puts_the_post_back_and_refuses_one_edited_since(): void {
		$this->converter()->run();
		$this->writer->contents[7] .= ' edited';

		self::assertSame( array( 7 => ContentWriter::EDITED ), $this->converter()->revert( 0, false ) );
		self::assertSame( array( 7 => ContentWriter::REVERTED ), $this->converter()->revert( 7, true ) );
		self::assertSame( self::BEFORE, $this->writer->contents[7] );
		self::assertSame( array( 7 => ContentWriter::NONE ), $this->converter()->revert( 7, false ) );
	}

	public function test_one_run_at_a_time(): void {
		$this->lock->held = true;

		$this->expectException( ConversionLocked::class );
		$this->converter()->run();
	}
}
