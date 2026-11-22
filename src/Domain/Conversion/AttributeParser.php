<?php
/**
 * A shortcode's attributes as WordPress reads them.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * A shortcode's attribute text read as WordPress's shortcode_parse_atts() reads it (Task LM4), named attributes only.
 */
interface AttributeParser {

	/**
	 * The named attributes.
	 *
	 * @param string $text The attributes' text (` id="1" badge="Top"`).
	 * @return array<string, string>
	 */
	public function parse( string $text ): array;
}
