<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\LeftoverCheck;
use AffibloomLassoMigrator\Domain\Conversion\AffibloomRecords;
use AffibloomLassoMigrator\Domain\Conversion\ContentPlaces;
use AffibloomLassoMigrator\Domain\Conversion\Leftover;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM9: one leftover in each kind of place is found, with its place and link, Lasso's before the boxes; a box that names nothing Affibloom
 * has is found; converted content finds nothing.
 */
final class LeftoverCheckTest extends TestCase {

	/**
	 * @param list<array{title: string, edit: string, text: string, css: bool}> $places The places.
	 */
	private static function check( array $places ): LeftoverCheck {
		$content = new class( $places ) implements ContentPlaces {
			public function __construct( private array $list ) {}
			public function places(): iterable {
				return $this->list;
			}
		};
		$records = new class() implements AffibloomRecords {
			public function exists( string $kind, string $ref ): bool {
				return in_array( "$kind $ref", array( 'product 22092', 'group best-shoes', 'table 3' ), true );
			}
		};

		return new LeftoverCheck( $content, $records );
	}

	private static function place( string $title, string $text, bool $css = false ): array {
		return array(
			'title' => $title,
			'edit'  => "https://site.example/edit/{$title}",
			'text'  => $text,
			'css'   => $css,
		);
	}

	public function test_one_leftover_in_each_kind_of_place_is_found(): void {
		$found = self::check(
			array(
				self::place( 'A converted post', '<!-- wp:affibloom/product-box {"type":"single","productId":22092} /-->[affibloom type="grid" group="best-shoes"][affibloom type="table" id="9"]' ),
				self::place( 'A post', '<p>[lasso id="7"]</p>' ),
				self::place( 'A reusable block', '<!-- wp:affiliate-plugin/lasso {"short_code":""} /-->' ),
				self::place( 'Elementor data of A page', '[{"settings":{"html":"<a data-lasso-id=\"4\" href=\"/x/\">x</a>"}}]' ),
				self::place( 'A post, field review', '<span class="lasso-badge">Best</span>' ),
				self::place( 'Widget widget_text #3', '[lasso-attr id="7" attr_name="x"]' ),
				self::place( 'Additional CSS (child)', '.lasso-badge { x: 1; }', true ),
			)
		)->check();

		self::assertSame(
			array(
				'A post: [lasso x1',
				'A reusable block: wp:affiliate-plugin/lasso x1',
				'Elementor data of A page: data-lasso- x1',
				'A post, field review: lasso- class x1',
				'Widget widget_text #3: [lasso-attr x1',
				'Additional CSS (child): .lasso- x1',
				'A converted post: a box names table 9, which Affibloom does not have x1',
			),
			array_map( static fn ( Leftover $one ): string => "{$one->title}: {$one->what} x{$one->count}", $found )
		);
		self::assertSame( 'https://site.example/edit/A post', $found[0]->edit );
	}

	public function test_converted_content_leaves_nothing(): void {
		self::assertSame(
			array(),
			self::check(
				array(
					self::place( 'A post', '<!-- wp:affibloom/product-box {"type":"single","productId":22092} /--><a class="affibloom-button-1" href="/x/">x</a>' ),
					self::place( 'Additional CSS (child)', '.affibloom-badge { x: 1; }', true ),
				)
			)->check()
		);
	}
}
