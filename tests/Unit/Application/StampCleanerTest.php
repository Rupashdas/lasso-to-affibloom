<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\ConversionLocked;
use AffibloomLassoMigrator\Application\Conversion\StampCleaner;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Domain\Conversion\LinkTies;
use AffibloomLassoMigrator\Domain\Conversion\StampLine;
use AffibloomLassoMigrator\Domain\Conversion\TextStore;
use AffibloomLassoMigrator\Domain\Conversion\TieLog;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeConversionLock;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeTextStore;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM6 (F34 AC6; F21 R3, R37): Lasso's stamps go; a link Affibloom ties by its address to another product, or to
 * none, is tied to the product Lasso's stamp named; an editor's choice in Affibloom is kept; a post whose tie cannot be
 * kept keeps its stamps; revert puts the post back and clears the ties made.
 */
final class StampCleanerTest extends TestCase {

	private const AMAZON = 'https://www.amazon.com/dp/B0D2JJ5WGT?tag=vbvault-20';
	private const CLOAKED = 'http://volleyballvault.local/aircat/';

	/** @var array<string, list<array{product: int, assigned: int, original: bool}>> The index's places, by "post url". */
	public array $index = array();

	private function ties(): LinkTies {
		$test = $this;
		return new class( $test ) implements LinkTies {
			public function __construct( private StampCleanerTest $test ) {}
			public function places( int $post, string $url ): array {
				return $this->test->index[ "$post $url" ] ?? array();
			}
			public function tie( int $post, string $url, int $product ): int {
				$count = 0;
				foreach ( $this->test->index[ "$post $url" ] ?? array() as $i => $place ) {
					if ( 0 === $place['assigned'] && ! $place['original'] && $place['product'] !== $product ) {
						$this->test->index[ "$post $url" ][ $i ]['assigned'] = $product;
						++$count;
					}
				}
				return $count;
			}
			public function untie( int $post, string $url, int $product ): int {
				$count = 0;
				foreach ( $this->test->index[ "$post $url" ] ?? array() as $i => $place ) {
					if ( $product === $place['assigned'] ) {
						$this->test->index[ "$post $url" ][ $i ]['assigned'] = 0;
						++$count;
					}
				}
				return $count;
			}
		};
	}

	private function log(): TieLog {
		return new class() implements TieLog {
			/** @var array<int, list<array{url: string, product: int}>> */
			public array $ties = array();
			public function add( int $post, string $url, int $product ): void {
				$this->ties[ $post ][] = array( 'url' => $url, 'product' => $product );
			}
			public function of( int $post ): array {
				return $this->ties[ $post ] ?? array();
			}
			public function forget( int $post ): void {
				unset( $this->ties[ $post ] );
			}
		};
	}

	private static function posts(): FakeTextStore {
		return new FakeTextStore( '', 'publish', array( 'data-lasso-', 'lasso-scan-ignore' ) );
	}

	private static function place( int $product, int $assigned = 0, bool $original = false ): array {
		return array(
			'product'  => $product,
			'assigned' => $assigned,
			'original' => $original,
		);
	}

	private function cleaner( FakeTextStore $posts, TieLog $log, ?FakeConversionLock $lock = null ): StampCleaner {
		return new StampCleaner( $posts, static fn (): IdMaps => new IdMaps( array( 9701 => 22097, 17064 => 17064 ) ), $this->ties(), $log, $lock ?? new FakeConversionLock() );
	}

	/**
	 * @param list<StampLine> $lines The lines.
	 * @return list<string>
	 */
	private static function lines( array $lines ): array {
		return array_map( static fn ( StampLine $line ): string => trim( "{$line->post} {$line->action}: {$line->item} | {$line->detail}" ), $lines );
	}

	public function test_stamps_go_and_a_link_tied_elsewhere_is_tied_to_lasso_s_product(): void {
		$before = '<p><a href="' . self::AMAZON . '" data-lasso-name="Mikasa" data-lasso-id="1" data-lasso-lid="9701">a</a> <a href="' . self::CLOAKED . '" data-lasso-id="2" data-lasso-lid="17064">b</a> <a href="/about/" data-lasso-id="3">c</a></p>';
		$posts  = self::posts()->add( '3297', 3297, $before );
		$posts->add( '5', 5, '<p>No stamps.</p>' );
		$this->index = array(
			'3297 ' . self::AMAZON  => array( self::place( 21221 ), self::place( 21221 ) ),
			'3297 ' . self::CLOAKED => array( self::place( 0 ), self::place( 17064 ) ),
		);
		$log = $this->log();

		$plan = self::lines( $this->cleaner( $posts, $log )->plan() );
		self::assertSame(
			array(
				'3297 remove: 3 data-lasso-id, 2 data-lasso-lid, 1 data-lasso-name |',
				'3297 tie: ' . self::AMAZON . ' | product 22097 (Lasso record 9701, Mikasa); by its address Affibloom ties it to product 21221',
				'3297 tie: ' . self::CLOAKED . ' | product 17064 (Lasso record 17064); by its address Affibloom ties it to no product',
			),
			$plan
		);
		self::assertSame( array(), $posts->writes );
		self::assertSame( 0, $this->index[ '3297 ' . self::AMAZON ][0]['assigned'] );

		self::assertSame( $plan, self::lines( $this->cleaner( $posts, $log )->run() ) );
		self::assertSame( '<p><a href="' . self::AMAZON . '">a</a> <a href="' . self::CLOAKED . '">b</a> <a href="/about/">c</a></p>', $posts->texts['3297'] );
		self::assertSame( array( 22097, 22097 ), array_column( $this->index[ '3297 ' . self::AMAZON ], 'assigned' ) );
		self::assertSame( array( 17064, 0 ), array_column( $this->index[ '3297 ' . self::CLOAKED ], 'assigned' ) );
		self::assertSame( array( array( 'url' => self::AMAZON, 'product' => 22097 ), array( 'url' => self::CLOAKED, 'product' => 17064 ) ), $log->of( 3297 ) );
		self::assertSame( array(), $this->cleaner( $posts, $log )->plan(), 'A second run finds nothing.' );

		self::assertSame( array( 'post 3297' => TextStore::REVERTED ), $this->cleaner( $posts, $log )->revert( 0, false ) );
		self::assertSame( $before, $posts->texts['3297'] );
		self::assertSame( array( 0, 0 ), array_column( $this->index[ '3297 ' . self::AMAZON ], 'assigned' ) );
		self::assertSame( array( 0, 0 ), array_column( $this->index[ '3297 ' . self::CLOAKED ], 'assigned' ) );
		self::assertSame( array(), $log->of( 3297 ) );
	}

	public function test_an_editor_s_choice_in_affibloom_is_kept(): void {
		$posts       = self::posts()->add( '7', 7, '<a href="' . self::AMAZON . '" data-lasso-lid="9701">a</a><img class="x lasso-scan-ignore">' );
		$this->index = array( '7 ' . self::AMAZON => array( self::place( 9, 9 ), self::place( 0, 0, true ), self::place( 22097 ) ) );

		self::assertSame(
			array(
				'7 remove: 1 data-lasso-lid, 1 lasso-scan-ignore (becomes affibloom-scan-ignore) |',
				'7 keep: ' . self::AMAZON . ' | an editor assigned it to product 9 in Affibloom, not product 22097 (Lasso record 9701): kept',
				'7 keep: ' . self::AMAZON . ' | Affibloom turns it back to its original URL, not product 22097 (Lasso record 9701): kept',
			),
			self::lines( $this->cleaner( $posts, $this->log() )->run() )
		);
		self::assertSame( '<a href="' . self::AMAZON . '">a</a><img class="x affibloom-scan-ignore">', $posts->texts['7'] );
		self::assertSame( array( 9, 0, 0 ), array_column( $this->index[ '7 ' . self::AMAZON ], 'assigned' ) );
	}

	public function test_a_post_edited_since_keeps_its_ties_on_revert(): void {
		$posts       = self::posts()->add( '6', 6, '<a href="' . self::AMAZON . '" data-lasso-lid="9701">a</a>' );
		$this->index = array( '6 ' . self::AMAZON => array( self::place( 21221 ) ) );
		$log         = $this->log();
		$this->cleaner( $posts, $log )->run();
		$posts->texts['6'] .= '<p>Edited.</p>';

		self::assertSame( array( 'post 6' => TextStore::EDITED ), $this->cleaner( $posts, $log )->revert( 6, false ) );
		self::assertSame( 22097, $this->index[ '6 ' . self::AMAZON ][0]['assigned'] );
		self::assertCount( 1, $log->of( 6 ) );
	}

	public function test_a_post_whose_tie_cannot_be_kept_keeps_its_stamps(): void {
		$posts       = self::posts()->add( '8', 8, '<a href="https://a.example/" data-lasso-lid="5">a</a><a href="' . self::AMAZON . '" data-lasso-lid="9701">b</a><a href="' . self::CLOAKED . '" data-lasso-lid="17064">c</a><a href="' . self::CLOAKED . '" data-lasso-lid="9701">d</a>' );
		$this->index = array();

		self::assertSame(
			array(
				'8 blocked: https://a.example/ | Lasso record 5 has no Affibloom product: migrate the products first',
				'8 blocked: ' . self::AMAZON . " | Affibloom's link index does not have this link in this post yet: let Affibloom index the post, then run again",
				'8 blocked: ' . self::CLOAKED . ' | Lasso tied this link to several records in this post (17064, 9701): assign each place on Opportunities, then run again',
			),
			self::lines( $this->cleaner( $posts, $this->log() )->run() )
		);
		self::assertSame( array(), $posts->writes );
	}

	public function test_a_post_that_does_not_save_is_left_with_its_ties_undone(): void {
		$posts         = self::posts()->add( '9', 9, '<a href="' . self::AMAZON . '" data-lasso-lid="9701">a</a>' );
		$posts->mangle = true;
		$this->index   = array( '9 ' . self::AMAZON => array( self::place( 21221 ) ) );
		$log           = $this->log();

		$lines = $this->cleaner( $posts, $log )->run();

		self::assertSame( array( StampLine::ERROR, StampLine::ERROR ), array_map( static fn ( StampLine $line ): string => $line->action, $lines ) );
		self::assertSame( 0, $this->index[ '9 ' . self::AMAZON ][0]['assigned'] );
		self::assertSame( array(), $log->of( 9 ) );
	}

	public function test_one_run_at_a_time(): void {
		$lock       = new FakeConversionLock();
		$lock->held = true;

		$this->expectException( ConversionLocked::class );
		$this->cleaner( self::posts(), $this->log(), $lock )->run();
	}
}
