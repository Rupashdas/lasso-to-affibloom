<?php
namespace AffibloomLassoMigrator\Tests\Unit\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\TextStore;
use AffibloomLassoMigrator\Infrastructure\WordPress\ElementorTexts;
use AffibloomLassoMigrator\Infrastructure\WordPress\WidgetTexts;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Task LM4 (F10 AC26): widgets' text and Elementor's data with Lasso's markup, written with a copy and reverted exactly;
 * Lasso's Elementor widget becomes Affibloom's.
 */
final class WidgetAndElementorTextsTest extends TestCase {

	/** @var array<string, mixed> Options. */
	private array $options = array();

	/** @var array<int, array<string, string>> Post meta, as stored (unslashed). */
	private array $meta = array();

	protected function setUp(): void {
		parent::setUp();
		$options = &$this->options;
		$meta    = &$this->meta;
		Functions\when( 'get_option' )->alias(
			static function ( string $name, mixed $fallback = false ) use ( &$options ): mixed {
				return $options[ $name ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $name, mixed $value ) use ( &$options ): bool {
				$options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( string $name ) use ( &$options ): bool {
				unset( $options[ $name ] );
				return true;
			}
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post, string $key ) use ( &$meta ): string {
				return $meta[ $post ][ $key ] ?? '';
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post, string $key, string $value ) use ( &$meta ): bool {
				$meta[ $post ][ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_post_meta' )->alias(
			static function ( int $post, string $key ) use ( &$meta ): bool {
				unset( $meta[ $post ][ $key ] );
				return true;
			}
		);
	}

	public function test_a_widget_s_text_is_written_and_its_option_reverted(): void {
		$this->options['widget_text']  = array(
			2              => array( 'text' => 'Plain' ),
			3              => array( 'text' => '<p>[lasso id="1"]</p>' ),
			'_multiwidget' => 1,
		);
		$this->options['widget_block'] = array( 4 => array( 'content' => '<!-- wp:paragraph --><p>none</p><!-- /wp:paragraph -->' ) );
		$before                        = $this->options['widget_text'];
		$widgets                       = new WidgetTexts();

		self::assertSame( array( array( 'widget_text:3', 0, 'widget_text #3', '<p>[lasso id="1"]</p>' ) ), array_map( static fn ( array $text ): array => array( $text['key'], $text['post'], $text['place'], $text['text'] ), $widgets->texts() ) );
		self::assertSame( array(), $widgets->texts( 9 ) );
		self::assertTrue( $widgets->write( 'widget_text:3', '<p>[lasso id="1"]</p>', '<p>[affibloom id="9"]</p>' ) );
		self::assertSame( '<p>[affibloom id="9"]</p>', $this->options['widget_text'][3]['text'] );
		self::assertFalse( $widgets->write( 'widget_text:3', 'stale', 'x' ) );
		self::assertSame( array( 'widget_text' ), $widgets->converted() );

		$this->options['widget_text'][2]['text'] = 'Edited';
		self::assertSame( TextStore::EDITED, $widgets->revert( 'widget_text', false ) );
		self::assertSame( TextStore::REVERTED, $widgets->revert( 'widget_text', true ) );
		self::assertSame( $before, $this->options['widget_text'] );
		self::assertArrayNotHasKey( WidgetTexts::COPY, $this->options );
	}

	public function test_lassos_elementor_widget_becomes_affibloom_s_and_the_data_is_reverted(): void {
		global $wpdb;
		$wpdb = new class() {
			public string $postmeta = 'wp_postmeta';
			public function prepare( string $query, mixed ...$args ): string {
				return $query;
			}
			/** @return list<string> */
			public function get_col( string $query ): array {
				return str_contains( $query, 'LIKE' ) || str_contains( $query, '_affibloom_lasso' ) ? array( '9' ) : array();
			}
		};
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		$data                                = array(
			array(
				'id'       => 's1',
				'elType'   => 'section',
				'elements' => array(
					array(
						'id'         => 'w1',
						'widgetType' => 'lasso_shortcode',
						'settings'   => array( 'lasso_shortcode' => '[lasso id="1"]' ),
					),
					array(
						'id'         => 'w2',
						'widgetType' => 'text-editor',
						'settings'   => array(
							'editor' => '<p>See [lasso id="1"]</p>',
							'align'  => 'left',
						),
					),
				),
			),
		);
		$raw                                 = json_encode( $data );
		$this->meta[9]['_elementor_data']    = $raw;
		$this->meta[9]['_elementor_css']     = 'cache';
		$elementor                           = new ElementorTexts();

		self::assertSame( array( '9|w1|lasso_shortcode', '9|w2|editor' ), array_column( $elementor->texts(), 'key' ) );
		self::assertTrue( $elementor->write( '9|w1|lasso_shortcode', '[lasso id="1"]', '[affibloom id="22092"]' ) );
		$widget = json_decode( $this->meta[9]['_elementor_data'], true )[0]['elements'][0];
		self::assertSame( array( 'affibloom', array( 'affibloom_shortcode' => '[affibloom id="22092"]' ) ), array( $widget['widgetType'], $widget['settings'] ) );
		self::assertArrayNotHasKey( '_elementor_css', $this->meta[9] );
		self::assertTrue( $elementor->write( '9|w2|editor', '<p>See [lasso id="1"]</p>', '<p>See [affibloom id="22092"]</p>' ) );
		self::assertSame( $raw, $this->meta[9][ ElementorTexts::COPY ] );

		self::assertSame( TextStore::REVERTED, $elementor->revert( '9', false ) );
		self::assertSame( $raw, $this->meta[9]['_elementor_data'] );
		self::assertArrayNotHasKey( ElementorTexts::COPY, $this->meta[9] );
	}
}
