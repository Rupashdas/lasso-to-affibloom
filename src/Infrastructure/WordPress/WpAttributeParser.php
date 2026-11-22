<?php
/**
 * A shortcode's attributes, read by WordPress.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\AttributeParser;

/**
 * A shortcode's attribute text through WordPress's shortcode_parse_atts() (Task LM4), named attributes only.
 */
final class WpAttributeParser implements AttributeParser {

	/**
	 * The named attributes.
	 *
	 * @param string $text The attributes' text.
	 * @return array<string, string>
	 */
	public function parse( string $text ): array {
		$named = array();
		foreach ( shortcode_parse_atts( trim( $text ) ) as $name => $value ) {
			if ( is_string( $name ) && is_scalar( $value ) ) {
				$named[ $name ] = (string) $value;
			}
		}

		return $named;
	}
}
