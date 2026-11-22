<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\LocationsStep;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\OriginalLinkWriter;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F22 N3, E17 (Task 26Q): Lasso's original links go to Affibloom's index; the dry run writes nothing, a link not
 * indexed yet waits, another original Affibloom already has is kept, and a second run changes nothing.
 */
final class LocationsStepTest extends TestCase {

	/** Affibloom's index: "post url" => its original. @var array<string, string> */
	public array $index = array(
		'7 https://site.example/go/shoe/' => 'https://site.example/go/shoe/',
		'7 https://site.example/go/hat/'  => 'https://shop.example/other-hat',
	);

	/** set()'s calls. @var list<string> */
	public array $written = array();

	private function step(): LocationsStep {
		$index   = &$this->index;
		$written = &$this->written;
		$writer  = new class( $index, $written ) implements OriginalLinkWriter {
			/**
			 * @param array<string, string> $index   Links.
			 * @param list<string>          $written Calls.
			 */
			public function __construct( private array &$index, private array &$written ) {}
			public function original( int $post, string $url ): ?string {
				return $this->index[ "$post $url" ] ?? null;
			}
			public function set( int $post, string $url, string $original ): int {
				$this->written[]             = "$post $url";
				$this->index[ "$post $url" ] = $original;

				return 1;
			}
		};
		$source  = new FakeLassoSource(
			original_links: array(
				array(
					'post'     => 7,
					'url'      => 'https://site.example/go/shoe/',
					'original' => 'https://www.amazon.com/dp/B0SHOE0001?tag=old-20',
				),
				array(
					'post'     => 7,
					'url'      => 'https://site.example/go/hat/',
					'original' => 'https://shop.example/hat',
				),
				array(
					'post'     => 8,
					'url'      => 'https://site.example/go/bag/',
					'original' => 'https://shop.example/bag',
				),
				array(
					'post'     => 7,
					'url'      => 'https://site.example/go/cap/',
					'original' => 'not a link',
				),
				array(
					'post'     => 7,
					'url'      => 'https://site.example/go/shoe/',
					'original' => 'https://amzn.to/second',
				),
			)
		);

		return new LocationsStep( $source, $writer );
	}

	/**
	 * Each line's action and note, by key and link.
	 *
	 * @param list<MigrationLine> $lines The lines.
	 * @return array<string, string>
	 */
	private static function actions( array $lines ): array {
		$out = array();
		foreach ( $lines as $line ) {
			$key         = $line->key . ' ' . $line->name;
			$key        .= isset( $out[ $key ] ) ? ' again' : '';
			$out[ $key ] = $line->action->value . ( '' === $line->note ? '' : ' | ' . $line->note );
		}

		return $out;
	}

	public function test_the_dry_run_writes_nothing(): void {
		$lines = self::actions( $this->step()->plan() );

		self::assertSame( 'update', $lines['post 7 https://site.example/go/shoe/'] );
		// The dry run says what the run will: one original for every place of a link in a post.
		self::assertStringStartsWith( 'skip | Lasso rewrote this link in this post from several originals', $lines['post 7 https://site.example/go/shoe/ again'] );
		self::assertSame( array(), $this->written );
		self::assertSame( Family::Locations, $this->step()->family() );
	}

	public function test_originals_are_given_once_and_the_others_are_reported(): void {
		$lines = self::actions( $this->step()->run() );

		self::assertSame( 'update', $lines['post 7 https://site.example/go/shoe/'] );
		self::assertSame( 'https://www.amazon.com/dp/B0SHOE0001?tag=old-20', $this->index['7 https://site.example/go/shoe/'] );
		self::assertSame( "skip | Affibloom has another original (https://shop.example/other-hat): kept", $lines['post 7 https://site.example/go/hat/'] );
		self::assertStringStartsWith( "skip | not in Affibloom's link index yet", $lines['post 8 https://site.example/go/bag/'] );
		self::assertSame( 'skip | the original is not a link', $lines['post 7 https://site.example/go/cap/'] );
		self::assertStringStartsWith( 'skip | Lasso rewrote this link in this post from several originals', $lines['post 7 https://site.example/go/shoe/ again'] );
		self::assertSame( array( '7 https://site.example/go/shoe/' ), $this->written );

		$again = self::actions( $this->step()->run() );
		self::assertSame( 'unchanged', $again['post 7 https://site.example/go/shoe/'] );
		self::assertStringStartsWith( 'skip | Lasso rewrote this link in this post from several originals', $again['post 7 https://site.example/go/shoe/ again'] );
		self::assertSame( array( '7 https://site.example/go/shoe/' ), $this->written );
	}
}
