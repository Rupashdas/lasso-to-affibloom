<?php
/**
 * One Lasso record.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * One Lasso record (a `lasso-urls` post, any status) with what Lasso reads to show it (F10 R4-R10): its post meta,
 * its `lasso_url_details` row, and its cached store row (`lasso_amazon_products` for Amazon, `lasso_extend_products`
 * for another store). Read only.
 */
final class LassoRecord {

	/**
	 * Builds a record.
	 *
	 * @param int                        $id            The Lasso post ID.
	 * @param string                     $title         post_title, as stored (HTML entities not decoded).
	 * @param string                     $status        post_status, such as `publish`, `trash` or `lasso_delete`.
	 * @param string                     $slug          post_name.
	 * @param string                     $date          post_date.
	 * @param string                     $modified      post_modified.
	 * @param array<string, string>      $meta          The post meta Lasso reads, key => value; a missing key is ''.
	 * @param string                     $redirect_url  lasso_url_details.redirect_url.
	 * @param string                     $product_type  lasso_url_details.product_type (`amazon`, another store, or '').
	 * @param string                     $product_id    lasso_url_details.product_id (an ASIN, or the store's ID).
	 * @param string                     $country       lasso_url_details.country.
	 * @param array<string, string>|null $amazon        The lasso_amazon_products row, column => value; null when none.
	 * @param string                     $store_price   The other store's cached price (lasso_extend_products.latest_price).
	 * @param string                     $thumbnail_url The featured image's "large" URL; '' when the post has none.
	 * @param int                        $thumbnail_id  The featured image's attachment ID; 0 when none.
	 * @param string                     $attached_url  The URL of the attachment `lasso_thumbnail_id`; '' when none.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $status = 'publish',
		public readonly string $slug = '',
		public readonly string $date = '',
		public readonly string $modified = '',
		public readonly array $meta = array(),
		public readonly string $redirect_url = '',
		public readonly string $product_type = '',
		public readonly string $product_id = '',
		public readonly string $country = '',
		public readonly ?array $amazon = null,
		public readonly string $store_price = '',
		public readonly string $thumbnail_url = '',
		public readonly int $thumbnail_id = 0,
		public readonly string $attached_url = '',
	) {}

	/**
	 * A post meta value, '' when Lasso stored none (get_post_meta( …, true ) gives '' then too).
	 *
	 * @param string $key The meta key.
	 */
	public function meta( string $key ): string {
		return $this->meta[ $key ] ?? '';
	}

	/**
	 * A column of the Amazon row, '' when there is no row or no value.
	 *
	 * @param string $column The column.
	 */
	public function amazon( string $column ): string {
		return null === $this->amazon ? '' : (string) ( $this->amazon[ $column ] ?? '' );
	}

	/**
	 * The record Lasso retired this one into (`lasso_repointed_to_lasso_id`), 0 when none.
	 */
	public function repointed_to(): int {
		return (int) $this->meta( 'lasso_repointed_to_lasso_id' );
	}
}
