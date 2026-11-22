<?php
/**
 * Lasso's records from the database.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Lasso;

use AffibloomLassoMigrator\Domain\Lasso\LassoRecord;
use AffibloomLassoMigrator\Domain\Lasso\LassoSource;

/**
 * Lasso's records from the database, with SELECTs only (ADR-2): the `lasso-urls` posts of every status and the meta
 * Lasso reads, `lasso_url_details`, `lasso_amazon_products` and `lasso_extend_products`. Works with Lasso active or not;
 * a missing Lasso table reads as empty, so a site that never had Lasso has no records. Brought over from Affibloom's
 * one-time import (LC1), which read published records only.
 */
final class WpLassoSource implements LassoSource {

	/** Lasso's post type. */
	private const POST_TYPE = 'lasso-urls';

	/** Records read per query, to keep each IN list short. */
	private const CHUNK = 100;

	/** The post meta the mapper and the products step read. */
	private const META_KEYS = array(
		'lasso_custom_redirect',
		'lasso_final_url',
		'open_new_tab',
		'enable_nofollow',
		'enable_sponsored',
		'show_disclosure',
		'buy_btn_text',
		'second_btn_url',
		'second_btn_text',
		'open_new_tab2',
		'enable_nofollow2',
		'custom_theme',
		'badge_text',
		'price',
		'show_price',
		'affiliate_desc',
		'show_description',
		'disclosure_text',
		'lasso_custom_thumbnail',
		'lasso_thumbnail_url',
		'lasso_thumbnail_id',
		'lasso_repointed_to_lasso_id',
		'_thumbnail_id',
	);

	/**
	 * Whether Lasso's tables exist, by name, asked once.
	 *
	 * @var array<string, bool>
	 */
	private array $tables = array();

	/**
	 * Other stores' cached prices, once read.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $store_prices = null;

	/**
	 * The IDs of every Lasso record, whatever its status (auto-drafts aside), oldest change first.
	 *
	 * @return list<int>
	 */
	public function ids(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-time read of Lasso's posts.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> %s ORDER BY post_modified ASC, ID ASC",
				self::POST_TYPE,
				'auto-draft'
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * These records, in the order given.
	 *
	 * @param list<int> $ids From ids().
	 * @return list<LassoRecord>
	 */
	public function records( array $ids ): array {
		$records = array();
		foreach ( array_chunk( $ids, self::CHUNK ) as $chunk ) {
			array_push( $records, ...$this->chunk( $chunk ) );
		}

		return $records;
	}

	/**
	 * One chunk of records.
	 *
	 * @param list<int> $ids Up to CHUNK IDs.
	 * @return list<LassoRecord>
	 */
	private function chunk( array $ids ): array {
		global $wpdb;

		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one-time reads of Lasso's tables; $in holds only %d placeholders, filled by prepare().
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_status, post_name, post_date, post_modified FROM {$wpdb->posts} WHERE ID IN ( $in )", ...$ids ), OBJECT_K );

		$keys = implode( ',', array_fill( 0, count( self::META_KEYS ), '%s' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ( $in ) AND meta_key IN ( $keys ) ORDER BY meta_id ASC",
				...array_merge( $ids, self::META_KEYS )
			)
		);

		$details = array();
		if ( $this->has_table( 'lasso_url_details' ) ) {
			$details = $wpdb->get_results(
				$wpdb->prepare( "SELECT lasso_id, redirect_url, product_type, product_id, country FROM {$wpdb->prefix}lasso_url_details WHERE lasso_id IN ( $in )", ...$ids ),
				OBJECT_K
			);
		}

		$asins  = array_values( array_unique( array_filter( array_map( static fn( object $row ): string => 'amazon' === (string) $row->product_type ? (string) $row->product_id : '', $details ) ) ) );
		$amazon = array();
		if ( array() !== $asins && $this->has_table( 'lasso_amazon_products' ) ) {
			$list = implode( ',', array_fill( 0, count( $asins ), '%s' ) );
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}lasso_amazon_products WHERE amazon_id IN ( $list )", ...$asins ), ARRAY_A ) as $row ) {
				$amazon[ (string) $row['amazon_id'] ][ (string) $row['country'] ] = array_map( 'strval', $row );
			}
		}

		// phpcs:enable
		$stores = $this->store_prices();

		// get_post_meta( …, true ) gives the first value of a key, so the first row of each wins.
		$meta = array();
		foreach ( $rows as $row ) {
			$meta[ (int) $row->post_id ][ (string) $row->meta_key ] ??= (string) $row->meta_value;
		}

		$records = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $posts[ $id ] ) ) {
				continue;
			}
			$post         = $posts[ $id ];
			$detail       = $details[ $id ] ?? null;
			$type         = null === $detail ? '' : (string) $detail->product_type;
			$product_id   = null === $detail ? '' : (string) $detail->product_id;
			$country      = null === $detail ? '' : (string) $detail->country;
			$thumbnail_id = (int) ( $meta[ $id ]['_thumbnail_id'] ?? 0 );
			$thumbnail    = $thumbnail_id > 0 ? wp_get_attachment_image_url( $thumbnail_id, 'large' ) : false;
			$attached_id  = (int) ( $meta[ $id ]['lasso_thumbnail_id'] ?? 0 );
			$attached     = $attached_id > 0 ? wp_get_attachment_url( $attached_id ) : false;

			$records[] = new LassoRecord(
				id: $id,
				title: (string) $post->post_title,
				status: (string) $post->post_status,
				slug: (string) $post->post_name,
				date: (string) $post->post_date,
				modified: (string) $post->post_modified,
				meta: $meta[ $id ] ?? array(),
				redirect_url: null === $detail ? '' : (string) $detail->redirect_url,
				product_type: $type,
				product_id: $product_id,
				country: $country,
				// Lasso keeps one row per ASIN and store; the record's own store first, else the ASIN's only row.
				amazon: 'amazon' === $type ? ( $amazon[ $product_id ][ $country ] ?? ( 1 === count( $amazon[ $product_id ] ?? array() ) ? reset( $amazon[ $product_id ] ) : null ) ) : null,
				store_price: 'amazon' === $type || '' === $type ? '' : ( $stores[ $type . ':' . $product_id ] ?? '' ),
				thumbnail_url: is_string( $thumbnail ) ? $thumbnail : '',
				thumbnail_id: is_string( $thumbnail ) ? $thumbnail_id : 0,
				attached_url: is_string( $attached ) ? $attached : '',
			);
		}

		return $records;
	}

	/**
	 * Other stores' cached prices by "type:ID" (Lasso_Extend_Product::get_extend_product_by_id), read once: Lasso keeps
	 * a handful of rows (4 on the site).
	 *
	 * @return array<string, string>
	 */
	private function store_prices(): array {
		global $wpdb;

		if ( null === $this->store_prices ) {
			$this->store_prices = array();
			if ( $this->has_table( 'lasso_extend_products' ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-time read of Lasso's table; no input.
				foreach ( $wpdb->get_results( "SELECT product_type, product_id, latest_price FROM {$wpdb->prefix}lasso_extend_products", ARRAY_A ) as $row ) {
					$this->store_prices[ $row['product_type'] . ':' . $row['product_id'] ] = (string) $row['latest_price'];
				}
			}
		}

		return $this->store_prices;
	}

	/**
	 * Whether one of Lasso's tables exists on this site.
	 *
	 * @param string $name The table's name without the prefix.
	 */
	private function has_table( string $name ): bool {
		global $wpdb;

		if ( ! isset( $this->tables[ $name ] ) ) {
			$table = $wpdb->prefix . $name;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- whether Lasso's table exists.
			$this->tables[ $name ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		}

		return $this->tables[ $name ];
	}
}
