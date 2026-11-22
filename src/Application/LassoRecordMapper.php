<?php
/**
 * A Lasso record as Affibloom's product fields.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoRecord;

/**
 * A Lasso record → Affibloom's product fields (the product page's, `Api::check_product()`), as Lasso reads the record to
 * show its box (F10 D4; R9, R10; Lasso_Affiliate_Link::get_lasso_url() and get_lasso_thumbnail()), so a migrated box
 * shows what Lasso showed. Brought over from Affibloom's one-time import (LC1 removed it from core).
 */
final class LassoRecordMapper {

	/** Lasso's "no thumbnail" image (LASSO_DEFAULT_THUMBNAIL), which is not the product's own. */
	private const PLACEHOLDER_IMAGE = 'lasso-no-thumbnail';

	/**
	 * Builds the mapper.
	 *
	 * @param string $site_url The site's address (site_url()): an own-site image on http is served on https when it is.
	 */
	public function __construct( private string $site_url ) {}

	/**
	 * The product's fields, or null when the record has no link at all (D6).
	 *
	 * @param LassoRecord $record The record.
	 * @return array<string, mixed>|null
	 */
	public function fields( LassoRecord $record ): ?array {
		$url = $this->link( $record );
		if ( '' === $url ) {
			return null;
		}

		$on     = static fn( string $key ): bool => 1 === (int) $record->meta( $key );
		$cached = $this->is_amazon( $record ) && null !== $record->amazon;
		$image  = $this->image( $record );
		$fields = array(
			'name'            => $this->name( $record, $url ),
			'url'             => $url,
			'new_tab'         => $on( 'open_new_tab' ),
			'nofollow'        => $on( 'enable_nofollow' ),
			'sponsored'       => $on( 'enable_sponsored' ),
			'show_disclosure' => $on( 'show_disclosure' ),
			'button_text'     => $record->meta( 'buy_btn_text' ),
			'buttons'         => array(),
			'theme'           => $record->meta( 'custom_theme' ),
			'badge'           => $record->meta( 'badge_text' ),
			'price'           => $this->price( $record, $cached ),
			'description'     => $this->description( $record ),
			'disclosure'      => $record->meta( 'disclosure_text' ),
			// Lasso's box shows the Prime logo for every Amazon product while its setting is on, whatever the cached row
			// says (`is_prime => enable_amazon_prime && amazon_id`); the setting moves with the settings family (26P).
			'prime'           => $this->is_amazon( $record ) && '' !== trim( $record->product_id ),
			'image_url'       => $image[0],
			'image_id'        => $image[1],
		);
		// Lasso's second button (F29 B8): the first entry of the buttons after the primary one, when it has a link or text.
		$second = trim( $record->meta( 'second_btn_url' ) );
		if ( '' !== $second || '' !== $record->meta( 'second_btn_text' ) ) {
			$fields['buttons'][] = array(
				'url'      => $second,
				'text'     => $record->meta( 'second_btn_text' ),
				'new_tab'  => $on( 'open_new_tab2' ),
				'nofollow' => $on( 'enable_nofollow2' ),
			);
		}
		// '' means "the setting's", which the product says by leaving Show Price out.
		if ( '' !== $record->meta( 'show_price' ) ) {
			$fields['show_price'] = $on( 'show_price' );
		}

		return $fields;
	}

	/**
	 * Whether Lasso treats the record as an Amazon product.
	 *
	 * @param LassoRecord $record The record.
	 */
	private function is_amazon( LassoRecord $record ): bool {
		return 'amazon' === $record->product_type;
	}

	/**
	 * The destination (R9): the cached Amazon product's monetized link (or its base link), else url_details' redirect,
	 * healed from the custom redirect, then the final URL.
	 *
	 * @param LassoRecord $record The record.
	 */
	private function link( LassoRecord $record ): string {
		$candidates = array(
			$this->is_amazon( $record ) ? $record->amazon( 'monetized_url' ) : '',
			$this->is_amazon( $record ) ? $record->amazon( 'base_url' ) : '',
			$record->redirect_url,
			$record->meta( 'lasso_custom_redirect' ),
			$record->meta( 'lasso_final_url' ),
		);
		foreach ( $candidates as $candidate ) {
			if ( '' !== trim( $candidate ) ) {
				return trim( $candidate );
			}
		}

		return '';
	}

	/**
	 * The title with HTML entities decoded. A record titled just "Amazon" (Lasso's name before it fetched the product)
	 * takes the cached product's name; an empty title takes the link's site, as a new product's name starts.
	 *
	 * @param LassoRecord $record The record.
	 * @param string      $url    Its link.
	 */
	private function name( LassoRecord $record, string $url ): string {
		$name = trim( html_entity_decode( $record->title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( 'Amazon' === $name && '' !== trim( $record->amazon( 'default_product_name' ) ) ) {
			return trim( $record->amazon( 'default_product_name' ) );
		}
		if ( '' === $name ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			return is_string( $host ) ? (string) preg_replace( '/^www\./', '', $host ) : '';
		}

		return $name;
	}

	/**
	 * The price (R9): an Amazon product's box shows Amazon's last price; otherwise the record's own, and when that is
	 * empty (tags aside) the other store's cached price.
	 *
	 * @param LassoRecord $record The record.
	 * @param bool        $cached An Amazon product with a cached row.
	 */
	private function price( LassoRecord $record, bool $cached ): string {
		if ( $cached ) {
			return $record->amazon( 'latest_price' );
		}
		$own = $record->meta( 'price' );

		return '' === trim( wp_strip_all_tags( $own ) ) && '' !== $record->store_price ? $record->store_price : $own;
	}

	/**
	 * The image, Lasso's get_lasso_thumbnail() (R10): the custom thumbnail; else the featured image ("large"); else the
	 * imported thumbnail URL, else the attachment `lasso_thumbnail_id`, which an Amazon product's cached image replaces.
	 * Lasso's placeholder means none; an own-site image on http is served on https when the site is.
	 *
	 * @param LassoRecord $record The record.
	 * @return array{0: string, 1: int} The image URL and its media library ID (0 when it is not one).
	 */
	private function image( LassoRecord $record ): array {
		if ( '' !== $record->meta( 'lasso_custom_thumbnail' ) ) {
			$image = array( trim( $record->meta( 'lasso_custom_thumbnail' ) ), 0 );
		} elseif ( '' !== $record->thumbnail_url ) {
			$image = array( $record->thumbnail_url, $record->thumbnail_id );
		} elseif ( $this->is_amazon( $record ) ) {
			$image = array( trim( $record->amazon( 'default_image' ) ), 0 );
		} elseif ( '' !== $record->meta( 'lasso_thumbnail_url' ) ) {
			$image = array( trim( $record->meta( 'lasso_thumbnail_url' ) ), 0 );
		} else {
			$image = array( $record->attached_url, '' === $record->attached_url ? 0 : (int) $record->meta( 'lasso_thumbnail_id' ) );
		}

		if ( str_contains( $image[0], self::PLACEHOLDER_IMAGE ) ) {
			return array( '', 0 );
		}
		if ( str_starts_with( $this->site_url, 'https://' ) && wp_parse_url( $image[0], PHP_URL_HOST ) === wp_parse_url( $this->site_url, PHP_URL_HOST ) ) {
			$image[0] = (string) preg_replace( '#^http://#', 'https://', $image[0] );
		}

		return $image;
	}

	/**
	 * The description, hidden as Lasso hides it: when "show description" is off and the text is 10 characters or
	 * fewer (a longer text is always shown; a missing switch means on).
	 *
	 * @param LassoRecord $record The record.
	 */
	private function description( LassoRecord $record ): string {
		$text = $record->meta( 'affiliate_desc' );
		if ( '0' === $record->meta( 'show_description' ) && strlen( $text ) <= 10 ) {
			return '';
		}

		return $text;
	}
}
