<?php
/**
 * A setting's value as the report prints it.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

/**
 * A setting's value as the report prints it: true/false, a number or text as is, a list or set as JSON, cut at 120
 * characters (custom CSS can be long).
 */
final class ValueText {

	/** The longest value printed whole. */
	private const MAX = 120;

	/** What the report prints for a secret key (F10 D10). */
	public const SECRET = '(secret: not read)';

	/**
	 * The value as text.
	 *
	 * @param mixed $value Any setting value.
	 */
	public static function of( mixed $value ): string {
		$text = match ( true ) {
			is_bool( $value )   => $value ? 'true' : 'false',
			is_scalar( $value ) => (string) $value,
			null === $value     => '',
			default             => (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		};

		return mb_strlen( $text ) > self::MAX ? mb_substr( $text, 0, self::MAX - 1 ) . '…' : $text;
	}
}
