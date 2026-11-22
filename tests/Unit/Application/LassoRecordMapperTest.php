<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\LassoRecordMapper;
use AffibloomLassoMigrator\Domain\Lasso\LassoRecord;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * F10 D4, R9, R10 (Task 25A): a record's fields are what Lasso's box showed.
 */
final class LassoRecordMapperTest extends TestCase {

	private LassoRecordMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		$this->mapper = new LassoRecordMapper( 'https://volleyballvault.local' );
	}

	/**
	 * A record with the given parts.
	 *
	 * @param array<string, string> $meta   Post meta.
	 * @param array<string, mixed>  $parts  Other constructor arguments by name.
	 */
	private static function record( array $meta = array(), array $parts = array() ): LassoRecord {
		return new LassoRecord(
			...array_merge(
				array(
					'id'    => 7,
					'title' => 'Mizuno Wave',
					'meta'  => $meta,
				),
				$parts
			)
		);
	}

	public function test_a_record_with_no_link_has_no_fields(): void {
		self::assertNull( $this->mapper->fields( self::record() ) );
	}

	public function test_the_link_is_url_details_then_the_custom_redirect_then_the_final_url(): void {
		self::assertSame( 'https://a.example/', $this->mapper->fields( self::record( array( 'lasso_final_url' => 'https://c.example/' ), array( 'redirect_url' => 'https://a.example/' ) ) )['url'] );
		self::assertSame(
			'https://b.example/',
			$this->mapper->fields(
				self::record(
					array(
						'lasso_custom_redirect' => 'https://b.example/',
						'lasso_final_url'       => 'https://c.example/',
					)
				)
			)['url']
		);
		self::assertSame( 'https://c.example/', $this->mapper->fields( self::record( array( 'lasso_final_url' => ' https://c.example/ ' ) ) )['url'] );
	}

	public function test_a_flag_is_on_only_when_it_is_1_and_show_price_left_out_means_the_setting(): void {
		$fields = $this->mapper->fields(
			self::record(
				array(
					'lasso_final_url' => 'https://c.example/',
					'open_new_tab'    => '1',
					'enable_nofollow' => 'on',
				)
			)
		);

		self::assertTrue( $fields['new_tab'] );
		self::assertFalse( $fields['nofollow'] );
		self::assertArrayNotHasKey( 'show_price', $fields );
		self::assertFalse(
			$this->mapper->fields(
				self::record(
					array(
						'lasso_final_url' => 'https://c.example/',
						'show_price'      => '0',
					)
				)
			)['show_price']
		);
	}

	public function test_an_amazon_record_takes_the_cached_link_price_prime_and_name_for_a_title_amazon(): void {
		$fields = $this->mapper->fields(
			self::record(
				array(
					'lasso_final_url' => 'https://www.amazon.com/dp/B0TEST0001',
					'price'           => '$1',
				),
				array(
					'title'        => 'Amazon',
					'product_type' => 'amazon',
					'amazon'       => array(
						'monetized_url'        => 'https://www.amazon.com/dp/B0TEST0001?tag=vv-20',
						'latest_price'         => '$89.99',
						'is_prime'             => '1',
						'default_product_name' => 'Mizuno Wave Lightning',
						'default_image'        => 'https://m.media-amazon.com/images/I/shoe.jpg',
					),
				)
			)
		);

		self::assertSame( 'https://www.amazon.com/dp/B0TEST0001?tag=vv-20', $fields['url'] );
		self::assertSame( '$89.99', $fields['price'] );
		self::assertTrue( $fields['prime'] );
		self::assertSame( 'Mizuno Wave Lightning', $fields['name'] );
		self::assertSame( 'https://m.media-amazon.com/images/I/shoe.jpg', $fields['image_url'] );
	}

	public function test_another_store_s_cached_price_fills_an_empty_own_price_only(): void {
		self::assertSame(
			'$24.00',
			$this->mapper->fields(
				self::record(
					array(
						'lasso_final_url' => 'https://walmart.example/1',
						'price'           => '<p></p>',
					),
					array(
						'product_type' => 'walmart',
						'store_price'  => '$24.00',
					)
				)
			)['price']
		);
		self::assertSame(
			'$20',
			$this->mapper->fields(
				self::record(
					array(
						'lasso_final_url' => 'https://walmart.example/1',
						'price'           => '$20',
					),
					array(
						'product_type' => 'walmart',
						'store_price'  => '$24.00',
					)
				)
			)['price']
		);
	}

	public function test_the_image_order_of_get_lasso_thumbnail(): void {
		$link = array( 'lasso_final_url' => 'https://c.example/' );

		self::assertSame(
			array( 'https://x.example/custom.jpg', 0 ),
			$this->image(
				$link + array( 'lasso_custom_thumbnail' => 'https://x.example/custom.jpg' ),
				array(
					'thumbnail_url' => 'https://x.example/f.jpg',
					'thumbnail_id'  => 5,
				)
			)
		);
		self::assertSame(
			array( 'https://x.example/f.jpg', 5 ),
			$this->image(
				$link,
				array(
					'thumbnail_url' => 'https://x.example/f.jpg',
					'thumbnail_id'  => 5,
				)
			)
		);
		self::assertSame(
			array( 'https://x.example/imported.jpg', 0 ),
			$this->image(
				$link + array(
					'lasso_thumbnail_url' => 'https://x.example/imported.jpg',
					'lasso_thumbnail_id'  => '9',
				),
				array( 'attached_url' => 'https://x.example/a.jpg' )
			)
		);
		self::assertSame( array( 'https://x.example/a.jpg', 9 ), $this->image( $link + array( 'lasso_thumbnail_id' => '9' ), array( 'attached_url' => 'https://x.example/a.jpg' ) ) );
	}

	public function test_the_placeholder_is_no_image_and_an_own_site_http_image_is_https(): void {
		$link = array( 'lasso_final_url' => 'https://c.example/' );

		self::assertSame( array( '', 0 ), $this->image( $link + array( 'lasso_custom_thumbnail' => 'https://x.example/lasso-no-thumbnail.jpg' ) ) );
		self::assertSame( 'https://volleyballvault.local/wp-content/uploads/s.jpg', $this->image( $link + array( 'lasso_custom_thumbnail' => 'http://volleyballvault.local/wp-content/uploads/s.jpg' ) )[0] );
		self::assertSame( 'http://other.example/s.jpg', $this->image( $link + array( 'lasso_custom_thumbnail' => 'http://other.example/s.jpg' ) )[0] );
	}

	public function test_a_short_description_is_hidden_when_show_description_is_off(): void {
		$link = array( 'lasso_final_url' => 'https://c.example/' );

		self::assertSame(
			'',
			$this->mapper->fields(
				self::record(
					$link + array(
						'affiliate_desc'   => 'Short one',
						'show_description' => '0',
					)
				)
			)['description']
		);
		self::assertSame(
			'A description longer than ten',
			$this->mapper->fields(
				self::record(
					$link + array(
						'affiliate_desc'   => 'A description longer than ten',
						'show_description' => '0',
					)
				)
			)['description']
		);
	}

	public function test_the_second_button_comes_when_it_has_a_link_or_text(): void {
		$link = array( 'lasso_final_url' => 'https://c.example/' );

		self::assertSame( array(), $this->mapper->fields( self::record( $link ) )['buttons'] );
		self::assertSame(
			array(
				array(
					'url'      => 'https://d.example/',
					'text'     => 'Buy at Walmart',
					'new_tab'  => true,
					'nofollow' => false,
				),
			),
			$this->mapper->fields(
				self::record(
					$link + array(
						'second_btn_url'  => ' https://d.example/ ',
						'second_btn_text' => 'Buy at Walmart',
						'open_new_tab2'   => '1',
					)
				)
			)['buttons']
		);
	}

	public function test_titles_are_decoded_and_an_empty_one_takes_the_link_s_site(): void {
		self::assertSame( 'Mizuno & Co', $this->mapper->fields( self::record( array( 'lasso_final_url' => 'https://c.example/' ), array( 'title' => 'Mizuno &amp; Co' ) ) )['name'] );
		self::assertSame( 'mizunousa.com', $this->mapper->fields( self::record( array( 'lasso_final_url' => 'https://www.mizunousa.com/shoe' ), array( 'title' => ' ' ) ) )['name'] );
	}

	/**
	 * The image URL and ID the mapper gives.
	 *
	 * @param array<string, string> $meta  Post meta.
	 * @param array<string, mixed>  $parts Other constructor arguments.
	 * @return array{0: string, 1: int}
	 */
	private function image( array $meta, array $parts = array() ): array {
		$fields = $this->mapper->fields( self::record( $meta, $parts ) );

		return array( $fields['image_url'], $fields['image_id'] );
	}
}
