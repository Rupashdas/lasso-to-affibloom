<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Conversion\AttributeParser;

/**
 * `name="value"` pairs, as shortcode_parse_atts() reads the site's shortcodes.
 */
final class FakeAttributeParser implements AttributeParser {

	public function parse( string $text ): array {
		preg_match_all( '/([\w-]+)="([^"]*)"/', $text, $pairs, PREG_SET_ORDER );
		$named = array();
		foreach ( $pairs as $pair ) {
			$named[ strtolower( $pair[1] ) ] = $pair[2];
		}

		return $named;
	}
}
