<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\BlockRepairer;
use AffibloomLassoMigrator\Application\Conversion\ContentConverter;
use AffibloomLassoMigrator\Application\Conversion\ContentRun;
use AffibloomLassoMigrator\Application\Conversion\CssTranslator;
use AffibloomLassoMigrator\Application\Conversion\StampCleaner;
use AffibloomLassoMigrator\Application\RunLocked;
use AffibloomLassoMigrator\Domain\Conversion\ContentRunState;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Domain\Conversion\LinkTies;
use AffibloomLassoMigrator\Domain\Conversion\TieLog;
use AffibloomLassoMigrator\Domain\Migration\RunState;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeAttributeParser;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeConversionLock;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeTextStore;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM8 (F10 AC28, D13; F34 AC7): the page converts one place per request (`site`, then each post), keeps the places
 * still to do so a reload continues, refuses while another request or a command runs, and reverts one place.
 */
final class ContentRunTest extends TestCase {

	private FakeTextStore $posts;
	private FakeTextStore $widgets;
	private FakeTextStore $stamped;
	private FakeTextStore $buttons;
	private FakeTextStore $css;
	private FakeConversionLock $commands;

	/** @var array{locked: bool, run: array{places: list<string>, total: int}|null} */
	public array $page = array(
		'locked' => false,
		'run'    => null,
	);

	protected function setUp(): void {
		parent::setUp();
		$this->posts    = ( new FakeTextStore() )->add( '9', 9, '[lasso id="14722"]' )->add( '4', 4, '[lasso id="14722"]' )->add( '6', 6, '[lasso id="5"]' );
		$this->widgets  = ( new FakeTextStore( 'widget option' ) )->add( 'widget_text', 0, '[lasso id="14722"]' );
		$this->stamped  = ( new FakeTextStore( '', 'publish', array( 'data-lasso-' ) ) )->add( '4', 4, '<a href="/x/" data-lasso-id="1">x</a>' )->add( '7', 7, '<a href="/y/" data-lasso-id="2">y</a>' );
		$this->buttons  = new FakeTextStore( '', 'publish', array( 'wp:generateblocks/button' ) );
		$this->css      = ( new FakeTextStore( 'Additional CSS of', 'css', array( 'lasso-' ) ) )->add( 'child', 0, '.lasso-badge { x: 1; }' );
		$this->commands = new FakeConversionLock();
	}

	private function content(): ContentRun {
		$test  = $this;
		$lock  = new class( $test ) implements RunState {
			public function __construct( private ContentRunTest $test ) {}
			public function lock( int $now, int $ttl ): bool {
				if ( $this->test->page['locked'] ) {
					return false;
				}
				return $this->test->page['locked'] = true;
			}
			public function unlock(): void {
				$this->test->page['locked'] = false;
			}
			public function current(): ?array {
				return null;
			}
			public function save( array $families, bool $update ): void {
			}
		};
		$state = new class( $test ) implements ContentRunState {
			public function __construct( private ContentRunTest $test ) {}
			public function current(): ?array {
				return $this->test->page['run'];
			}
			public function save( array $places, int $total ): void {
				$this->test->page['run'] = array() === $places ? null : array(
					'places' => $places,
					'total'  => $total,
				);
			}
		};
		$maps  = static fn (): IdMaps => new IdMaps( array( 14722 => 22092 ) );
		$ties  = new class() implements LinkTies {
			public function places( int $post, string $url ): array {
				return array();
			}
			public function tie( int $post, string $url, int $product ): int {
				return 0;
			}
			public function untie( int $post, string $url, int $product ): int {
				return 0;
			}
		};
		$log   = new class() implements TieLog {
			public function add( int $post, string $url, int $product ): void {
			}
			public function of( int $post ): array {
				return array();
			}
			public function forget( int $post ): void {
			}
		};

		return new ContentRun(
			new ContentConverter( array( $this->posts, $this->widgets ), ContentConverterTest::blocks(), $maps, new FakeAttributeParser(), $this->commands, ContentConverterTest::no_ai_texts() ),
			new StampCleaner( $this->stamped, $maps, $ties, $log, $this->commands ),
			new BlockRepairer( $this->buttons, $this->commands ),
			new CssTranslator( $this->css, $this->commands ),
			$lock,
			$state
		);
	}

	public function test_the_plan_counts_each_step_and_lists_the_places(): void {
		$plan = $this->content()->plan();

		self::assertSame( 3, $plan['boxes'] );
		self::assertSame( array( '6' ), array_map( static fn ( $line ): string => (string) $line->post, $plan['blocked'] ) );
		self::assertSame( 2, $plan['stamps'] );
		self::assertSame( 1, $plan['css'] );
		self::assertSame( array( 'site', 'post:4', 'post:7', 'post:9' ), $plan['places'] );
		self::assertSame( array(), array_merge( $this->posts->writes, $this->stamped->writes, $this->css->writes ) );
	}

	public function test_one_place_per_request_and_a_reload_continues(): void {
		$first = $this->content()->step( 1000 );
		self::assertSame(
			array(
				'place'    => 'site',
				'done'     => false,
				'position' => 1,
				'total'    => 4,
				'errors'   => 0,
			),
			$first
		);
		self::assertSame( '[affibloom id="22092"]', $this->widgets->texts['widget_text'] );
		self::assertSame( '.affibloom-badge { x: 1; }', $this->css->texts['child'] );
		self::assertSame( array(), $this->posts->writes, 'No post is written with the site.' );

		// A new request (a reload) picks up where the run stopped.
		$second = $this->content()->step( 1001 );
		self::assertSame( array( 'post:4', 2 ), array( $second['place'], $second['position'] ) );
		self::assertSame( '[affibloom id="22092"]', $this->posts->texts['4'] );
		self::assertSame( '<a href="/x/">x</a>', $this->stamped->texts['4'] );
		self::assertSame( '[lasso id="14722"]', $this->posts->texts['9'] );

		$this->content()->step( 1002 );
		$last = $this->content()->step( 1003 );
		self::assertSame( array( 'post:9', true, 4 ), array( $last['place'], $last['done'], $last['position'] ) );
		self::assertNull( $this->page['run'] );
		self::assertSame( '[lasso id="5"]', $this->posts->texts['6'], 'A blocked box stays.' );
	}

	public function test_another_request_or_a_command_running_is_refused(): void {
		$this->page['locked'] = true;
		try {
			$this->content()->step( 1000 );
			self::fail( 'A second request runs.' );
		} catch ( RunLocked ) {
			self::assertTrue( $this->page['locked'], 'The other request keeps its lock.' );
		}

		$this->page['locked']   = false;
		$this->commands->held   = true;
		$this->expectException( RunLocked::class );
		try {
			$this->content()->step( 1000 );
		} finally {
			self::assertFalse( $this->page['locked'], 'The page gives its lock back.' );
		}
	}

	public function test_each_converted_place_is_listed_and_reverted_alone(): void {
		foreach ( range( 1, 4 ) as $request ) {
			$this->content()->step( 1000 + $request );
		}
		$run = $this->content();

		self::assertSame( array( 'site', 'post:4', 'post:7', 'post:9' ), $run->converted() );

		$results = $run->revert( 'post:4', 1100 );
		self::assertSame( array( 'reverted', 'reverted' ), array_values( $results ) );
		self::assertSame( '[lasso id="14722"]', $this->posts->texts['4'] );
		self::assertSame( '<a href="/x/" data-lasso-id="1">x</a>', $this->stamped->texts['4'] );
		self::assertSame( '[affibloom id="22092"]', $this->posts->texts['9'], 'Another post stays converted.' );

		$run->revert( 'site', 1101 );
		self::assertSame( '.lasso-badge { x: 1; }', $this->css->texts['child'] );
		self::assertSame( '[lasso id="14722"]', $this->widgets->texts['widget_text'] );
		self::assertSame( array( 'post:7', 'post:9' ), $run->converted() );
	}
}
