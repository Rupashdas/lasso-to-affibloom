<?php
/**
 * Lasso's records from the database.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Lasso;

use AffibloomLassoMigrator\Domain\Lasso\LassoAmazon;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoGroup;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoRecord;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Lasso\LassoAddress;
use AffibloomLassoMigrator\Domain\Lasso\LassoIssues;
use AffibloomLassoMigrator\Domain\Lasso\LassoOpportunities;
use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Lasso\LassoViews;

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
	 * The inventory, once counted.
	 *
	 * @var LassoInventory|null
	 */
	private ?LassoInventory $inventory = null;

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
	 * Lasso's settings option and tracking-ID blacklist. The secret keys keep their place but lose their value as
	 * the option is read (F10 D10): nothing after this line can print or store them.
	 */
	public function settings(): LassoSettings {
		$stored = get_option( 'lasso_settings', array() );
		$stored = is_array( $stored ) ? $stored : array();
		foreach ( LassoSettings::SECRETS as $secret ) {
			if ( array_key_exists( $secret, $stored ) ) {
				$stored[ $secret ] = null;
			}
		}
		$blacklist = get_option( 'lasso_amazon_tracking_id_blacklist', array() );

		return new LassoSettings( $stored, array_values( array_map( 'strval', array_filter( is_array( $blacklist ) ? $blacklist : array(), 'is_scalar' ) ) ) );
	}

	/**
	 * How much of each kind of Lasso's data the site holds, counted once: every `{prefix}lasso_*` table with its rows,
	 * and the counts of analysis B, C, E, G and H. COUNT queries only.
	 */
	public function inventory(): LassoInventory {
		global $wpdb;

		if ( null !== $this->inventory ) {
			return $this->inventory;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-time counts of Lasso's data; a table name comes from SHOW TABLES and is quoted.
		$tables = array();
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'lasso_' ) . '%' ) ) as $table ) {
			$name = str_replace( '`', '', (string) $table );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- a table name from SHOW TABLES, backquoted; identifiers cannot be placeholders.
			$tables[ substr( $name, strlen( $wpdb->prefix ) ) ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$name`" );
		}
		$like   = static fn ( string $prefix ): string => $wpdb->esc_like( $prefix ) . '%';
		$counts = array(
			LassoInventory::OPTIONS     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT IN ( 'lasso_settings', 'lasso_amazon_tracking_id_blacklist' )", $like( 'lasso_' ) ) ),
			LassoInventory::JOB_OPTIONS => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $like( 'wp_lasso_' ) ) ),
			LassoInventory::TRANSIENTS  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like( '_transient_lasso_' ), $like( '_site_transient_lasso_' ) ) ),
			LassoInventory::RECORDS     => count( $this->ids() ),
			LassoInventory::OLD_SLUGS   => $this->record_meta( array( 'lasso_old_slug', '_wp_old_slug' ) ),
			LassoInventory::GROUPS      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'lasso-cat' ) ),
			LassoInventory::SAVED_VIEWS => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", 'lasso_dashboard_saved_views' ) ),
			LassoInventory::DISMISSED   => $this->record_meta( array( 'dismiss_out_of_stock', 'dismiss_broken' ) ),
			LassoInventory::CLOUD_META  => $this->record_meta( array( 'boosting_enabled', 'is_aff_plus', 'link_id', 'lasso_shortcode' ) ),
			LassoInventory::USER_STATE  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key IN ( %s, %s )", 'lasso_dashboard_tour_done', 'lasso_dismiss_amazon_bulk_sync_banner_v2' ) ),
		);
		// phpcs:enable
		$this->inventory = new LassoInventory( $tables, $counts );

		return $this->inventory;
	}

	/**
	 * Lasso's custom fields (R12): `lasso_fields`, `lasso_field_mapping` in each record's `field_order`, and the
	 * `Field_Mapping` rows of `lasso_metadata` (`show_field_name_<field>`), SELECTs only; missing tables read as empty.
	 */
	public function fields(): LassoFields {
		global $wpdb;

		$definitions = array();
		$values      = array();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time reads of Lasso's tables; no input.
		if ( $this->has_table( 'lasso_fields' ) ) {
			foreach ( $wpdb->get_results( "SELECT id, field_name, field_type, field_description FROM {$wpdb->prefix}lasso_fields ORDER BY id", ARRAY_A ) as $row ) {
				$definitions[] = array(
					'id'          => (int) $row['id'],
					'name'        => (string) $row['field_name'],
					'type'        => (string) $row['field_type'],
					'description' => (string) $row['field_description'],
				);
			}
		}
		$shown = array();
		if ( $this->has_table( 'lasso_metadata' ) ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT object_id, meta_key, meta_value FROM {$wpdb->prefix}lasso_metadata WHERE type = %s", 'Field_Mapping' ), ARRAY_A ) as $row ) {
				if ( 1 === preg_match( '/^show_field_name_(\\d+)$/', (string) $row['meta_key'], $found ) ) {
					$shown[ (int) $row['object_id'] ][ (int) $found[1] ] = in_array( strtolower( (string) $row['meta_value'] ), array( '1', 'true' ), true );
				}
			}
		}
		if ( $this->has_table( 'lasso_field_mapping' ) ) {
			foreach ( $wpdb->get_results( "SELECT lasso_id, field_id, field_value, field_visible FROM {$wpdb->prefix}lasso_field_mapping ORDER BY lasso_id, field_order, field_id", ARRAY_A ) as $row ) {
				$lasso_id              = (int) $row['lasso_id'];
				$field                 = (int) $row['field_id'];
				$values[ $lasso_id ][] = array(
					'field'     => $field,
					'value'     => (string) $row['field_value'],
					'visible'   => 1 === (int) $row['field_visible'],
					'show_name' => $shown[ $lasso_id ][ $field ] ?? false,
				);
			}
		}
		// phpcs:enable

		return new LassoFields( $definitions, $values );
	}

	/**
	 * Lasso's groups (R13): the `lasso-cat` terms, their `lasso-urls` records, ordered by `lasso_category_order`
	 * (`parent_slug`, `term_order`), the records it does not order after them, oldest first. SELECTs only.
	 *
	 * @return list<LassoGroup>
	 */
	public function groups(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time reads of Lasso's groups.
		$terms   = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, tt.description, tt.term_taxonomy_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s ORDER BY t.term_id", 'lasso-cat' ) );
		$ordered = array();
		if ( $this->has_table( 'lasso_category_order' ) ) {
			foreach ( $wpdb->get_results( "SELECT item_id, parent_slug, term_order FROM {$wpdb->prefix}lasso_category_order" ) as $row ) {
				$ordered[ (string) $row->parent_slug ][ (int) $row->item_id ] = (int) $row->term_order;
			}
		}
		$groups = array();
		foreach ( $terms as $term ) {
			$members = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT r.object_id FROM {$wpdb->term_relationships} r JOIN {$wpdb->posts} p ON p.ID = r.object_id AND p.post_type = %s WHERE r.term_taxonomy_id = %d ORDER BY p.post_date, p.ID", self::POST_TYPE, (int) $term->term_taxonomy_id ) ) );
			$rank    = $ordered[ (string) $term->slug ] ?? array();
			usort( $members, static fn ( int $a, int $b ): int => array( $rank[ $a ] ?? PHP_INT_MAX, $a ) <=> array( $rank[ $b ] ?? PHP_INT_MAX, $b ) );
			$groups[] = new LassoGroup( (int) $term->term_id, (string) $term->name, (string) $term->slug, (string) $term->description, $members );
		}
		// phpcs:enable

		return $groups;
	}

	/**
	 * Lasso's Amazon data (R17): the `lasso_amazon_products` rows a record's `lasso_url_details` names (Amazon type, its
	 * ASIN and store), the per-country tracking IDs with a value, the other-store rows, and whether the Creators
	 * credentials are set, a yes or no that never keeps their values. SELECTs only.
	 */
	public function amazon(): LassoAmazon {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time reads of Lasso's tables; no input.
		$rows = array();
		if ( $this->has_table( 'lasso_amazon_products' ) && $this->has_table( 'lasso_url_details' ) ) {
			$rows = $wpdb->get_results( "SELECT DISTINCT a.* FROM {$wpdb->prefix}lasso_amazon_products a JOIN {$wpdb->prefix}lasso_url_details d ON d.product_type = 'amazon' AND d.product_id = a.amazon_id AND d.country = a.country ORDER BY a.amazon_id, a.country", ARRAY_A );
		}
		$tracking_ids = $this->has_table( 'lasso_amazon_tracking_ids' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lasso_amazon_tracking_ids WHERE tracking_id <> ''" ) : 0;
		$other_stores = $this->has_table( 'lasso_extend_products' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lasso_extend_products" ) : 0;
		// phpcs:enable
		$settings = get_option( 'lasso_settings', array() );
		$creators = is_array( $settings ) && '' !== trim( (string) ( $settings['amazon_creators_credential_id'] ?? '' ) );
		unset( $settings );

		return new LassoAmazon( array_map( static fn ( array $row ): array => array_map( 'strval', $row ), $rows ), $tracking_ids, $other_stores, $creators );
	}

	/**
	 * Lasso's saved Dashboard views (R22): each user's `lasso_dashboard_saved_views` list; the site's
	 * `lasso_dashboard_tour_done`; and the education boxes `lasso_settings` marks `hide`.
	 */
	public function views(): LassoViews {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time read of every user's Lasso views.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s ORDER BY user_id", 'lasso_dashboard_saved_views' ) );
		// phpcs:enable
		$views = array();
		foreach ( $rows as $row ) {
			$list = maybe_unserialize( $row->meta_value );
			if ( is_array( $list ) && array() !== $list ) {
				$views[ (int) $row->user_id ] = array_values( $list );
			}
		}
		$settings = get_option( 'lasso_settings', array() );
		$closed   = array_values( array_filter( LassoViews::BOXES, static fn ( string $box ): bool => is_array( $settings ) && 'hide' === ( $settings[ $box . '-opportunities' ] ?? '' ) ) );

		$tour_done = '1' === (string) get_option( 'lasso_dashboard_tour_done', '' );

		return new LassoViews( $views, $tour_done, $closed );
	}

	/**
	 * Every record's cloaked address (R4, R8): `post_name`, `post_status`, `link_cloaking` read with intval as Lasso
	 * does (missing is off), and the old slugs in `lasso_old_slug` and `_wp_old_slug` (WordPress's `__trashed` marks
	 * left out: no address ever answered there).
	 *
	 * @return list<LassoAddress>
	 */
	public function slugs(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time reads of Lasso's records.
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_name, p.post_status, MAX( m.meta_value ) AS cloak FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'link_cloaking' WHERE p.post_type = %s GROUP BY p.ID ORDER BY p.ID", self::POST_TYPE ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = %s WHERE m.meta_key IN ( 'lasso_old_slug', '_wp_old_slug' ) AND m.meta_value <> '' AND m.meta_value NOT LIKE %s ORDER BY m.meta_id", self::POST_TYPE, '%' . $wpdb->esc_like( '__trashed' ) ) );
		// phpcs:enable
		$old = array();
		foreach ( $rows as $row ) {
			$old[ (int) $row->post_id ][] = (string) $row->meta_value;
		}

		return array_map( static fn ( object $post ): LassoAddress => new LassoAddress( (int) $post->ID, (string) $post->post_name, (string) $post->post_status, (int) $post->cloak > 0, $old[ (int) $post->ID ] ?? array() ), $posts );
	}

	/**
	 * Lasso's opportunity data (R18): `lasso_url_details.is_opportunity` per record, the `lasso_link_locations` rows
	 * marked `is_ignored` with the post they are in (`detection_id`; `post_id` is the Lasso record, F21 R1), and the
	 * `lasso_link_updates` rows.
	 */
	public function opportunities(): LassoOpportunities {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time reads of Lasso's tables; no input.
		$switches = array();
		if ( $this->has_table( 'lasso_url_details' ) ) {
			foreach ( $wpdb->get_results( "SELECT lasso_id, is_opportunity FROM {$wpdb->prefix}lasso_url_details ORDER BY lasso_id" ) as $row ) {
				$switches[ (int) $row->lasso_id ] = '0' !== (string) $row->is_opportunity;
			}
		}
		$dismissed = $this->has_table( 'lasso_link_locations' ) ? $wpdb->get_results( "SELECT DISTINCT detection_id, link_slug FROM {$wpdb->prefix}lasso_link_locations WHERE is_ignored = 1 ORDER BY detection_id" ) : array();
		$updates   = $this->has_table( 'lasso_link_updates' ) ? $wpdb->get_results( "SELECT old_product_id, old_country, source_lasso_id, target_lasso_id FROM {$wpdb->prefix}lasso_link_updates ORDER BY id" ) : array();
		// phpcs:enable

		return new LassoOpportunities(
			$switches,
			array_map(
				static fn ( object $row ): array => array(
					'post' => (int) $row->detection_id,
					'url'  => (string) $row->link_slug,
				),
				$dismissed
			),
			array_map(
				static fn ( object $row ): array => array(
					'asin'    => (string) $row->old_product_id,
					'country' => (string) $row->old_country,
					'source'  => (int) $row->source_lasso_id,
					'target'  => (int) $row->target_lasso_id,
				),
				$updates
			)
		);
	}

	/**
	 * The links Lasso's scan rewrote (Task 26Q): `lasso_link_locations` rows whose `original_link_slug` differs from
	 * `link_slug` but for a trailing slash, with the post they are in (`detection_id`; `post_id` is the Lasso record, F21
	 * R1), once per post, link and original, in the post's order.
	 *
	 * @return list<array{post: int, url: string, original: string}>
	 */
	public function original_links(): array {
		global $wpdb;

		if ( ! $this->has_table( 'lasso_link_locations' ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-time read of Lasso's table; no input.
		$rows = $wpdb->get_results( "SELECT detection_id, link_slug, original_link_slug, MIN(id) AS first FROM {$wpdb->prefix}lasso_link_locations WHERE detection_id > 0 AND original_link_slug IS NOT NULL AND original_link_slug <> '' AND TRIM(TRAILING '/' FROM link_slug) <> TRIM(TRAILING '/' FROM original_link_slug) GROUP BY detection_id, link_slug, original_link_slug ORDER BY detection_id, first" );

		return array_map(
			static fn ( object $row ): array => array(
				'post'     => (int) $row->detection_id,
				'url'      => (string) $row->link_slug,
				'original' => (string) $row->original_link_slug,
			),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Lasso's comparison tables (R14): `lasso_table_details`, its products from `lasso_table_mapping` in their order, its
	 * field groups from `lasso_table_field_group` (by their order, then as stored) and each product's value per field
	 * from `lasso_table_field_group_detail` in the table's groups.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function tables(): array {
		global $wpdb;

		if ( ! $this->has_table( 'lasso_table_details' ) ) {
			return array();
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time reads of Lasso's tables.
		$tables = array();
		foreach ( $wpdb->get_results( "SELECT id, title, style, theme, show_title, show_headers_horizontal FROM {$wpdb->prefix}lasso_table_details ORDER BY id" ) as $table ) {
			$id       = (int) $table->id;
			$products = $wpdb->get_results( $wpdb->prepare( "SELECT lasso_id, badge_text FROM {$wpdb->prefix}lasso_table_mapping WHERE table_id = %d ORDER BY `order`, id", $id ) );
			$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT field_group_id, field_id, MIN( `order` ) AS position, MIN( id ) AS first FROM {$wpdb->prefix}lasso_table_field_group WHERE table_id = %d GROUP BY field_group_id, field_id ORDER BY position, first", $id ) );
			$groups   = array();
			foreach ( $rows as $row ) {
				$groups[ (string) $row->field_group_id ][] = (int) $row->field_id;
			}
			$values = array();
			if ( array() !== $groups ) {
				$in = implode( ',', array_fill( 0, count( $groups ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $in holds only %s placeholders, filled by prepare().
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT lasso_id, field_id, field_value FROM {$wpdb->prefix}lasso_table_field_group_detail WHERE field_group_id IN ( $in ) ORDER BY id", ...array_keys( $groups ) ) ) as $cell ) {
					$values[ (int) $cell->lasso_id ][ (int) $cell->field_id ] ??= (string) $cell->field_value;
				}
			}
			$tables[] = array(
				'id'           => $id,
				'title'        => (string) $table->title,
				'style'        => (string) $table->style,
				'theme'        => (string) $table->theme,
				'show_title'   => '1' === (string) $table->show_title,
				'show_headers' => '1' === (string) $table->show_headers_horizontal,
				'products'     => array_map(
					static fn ( object $row ): array => array(
						'lasso_id' => (int) $row->lasso_id,
						'badge'    => (string) $row->badge_text,
					),
					$products
				),
				'groups'       => array_map(
					static fn ( string $group, array $fields ): array => array(
						'id'     => $group,
						'fields' => $fields,
					),
					array_map( 'strval', array_keys( $groups ) ),
					array_values( $groups )
				),
				'values'       => $values,
			);
		}
		// phpcs:enable

		return $tables;
	}

	/**
	 * Lasso's link issues (R16): every `lasso_url_issues` row, and the `dismiss_broken` / `dismiss_out_of_stock` times on
	 * its records.
	 */
	public function issues(): LassoIssues {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time reads of Lasso's data.
		$rows  = $this->has_table( 'lasso_url_issues' ) ? $wpdb->get_results( "SELECT id, issue_type, issue_slug, detection_date, issue_resolved, issue_resolved_dt FROM {$wpdb->prefix}lasso_url_issues ORDER BY id", ARRAY_A ) : array();
		$metas = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = %s WHERE m.meta_key IN ( 'dismiss_broken', 'dismiss_out_of_stock' )", self::POST_TYPE ) );
		// phpcs:enable
		$dismissals = array();
		foreach ( $metas as $meta ) {
			$dismissals[ (int) $meta->post_id ] ??= array(
				'broken'       => 0,
				'out-of-stock' => 0,
			);
			$dismissals[ (int) $meta->post_id ][ 'dismiss_broken' === $meta->meta_key ? 'broken' : 'out-of-stock' ] = (int) $meta->meta_value;
		}

		return new LassoIssues( array_map( static fn ( array $row ): array => array_map( 'strval', $row ), $rows ), $dismissals );
	}

	/**
	 * Each record's URL variants (R15): `lasso_url_variants` rows in their stored order.
	 *
	 * @return array<int, list<array{name: string, key: string, kind: string}>>
	 */
	public function variants(): array {
		global $wpdb;

		if ( ! $this->has_table( 'lasso_url_variants' ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time read of Lasso's table; no input.
		$rows     = $wpdb->get_results( "SELECT parent_lasso_id, name, variant_key, variant_kind FROM {$wpdb->prefix}lasso_url_variants ORDER BY parent_lasso_id, id" );
		$variants = array();
		foreach ( $rows as $row ) {
			$variants[ (int) $row->parent_lasso_id ][] = array(
				'name' => (string) $row->name,
				'key'  => (string) $row->variant_key,
				'kind' => (string) $row->variant_kind,
			);
		}

		return $variants;
	}

	/**
	 * How many of these meta keys Lasso's records hold with a value ('' and '0' are none).
	 *
	 * @param list<string> $keys Meta keys.
	 */
	private function record_meta( array $keys ): int {
		global $wpdb;

		$in = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- a one-time count; $in holds only %s placeholders, filled by prepare().
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = %s WHERE m.meta_value NOT IN ( '', '0' ) AND m.meta_key IN ( $in )", self::POST_TYPE, ...$keys ) );
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
