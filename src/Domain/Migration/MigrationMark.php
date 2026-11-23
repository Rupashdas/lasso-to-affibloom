<?php
/**
 * What a migrated product was given, and what Affibloom stored.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * What a migrated product was given and what Affibloom stored from it, as two hashes (F10 D8; analysis "Data":
 * `_affibloom_migrated`). Lasso changed the record when its fields no longer hash to `given`; Affibloom changed the
 * product when its fields no longer hash to `stored`. The two differ because Affibloom cleans what it stores. An
 * adopted product (D7) was made by Affibloom's one-time import, with that import's values, not the migration's (Task
 * 29A, deviation 161).
 */
final class MigrationMark {

	/**
	 * Builds the mark.
	 *
	 * @param string $given   The hash of the fields the migration gave.
	 * @param string $stored  The hash of the product's fields right after.
	 * @param bool   $adopted Whether the product was adopted from the one-time import.
	 */
	public function __construct( public readonly string $given, public readonly string $stored, public readonly bool $adopted = false ) {}

	/**
	 * Lasso's fields that differ from a product's, Affibloom's cleaning aside (whitespace, a true stored as "1"); the
	 * fields Affibloom keeps fresh itself (`except`) are left out.
	 *
	 * @param array<string, mixed> $lasso   The record's fields.
	 * @param array<string, mixed> $product The product's fields.
	 * @param list<string>         $except  Fields not compared.
	 * @return array<string, mixed> Lasso's values of the fields that differ.
	 */
	public static function differing( array $lasso, array $product, array $except = array() ): array {
		$plain = static function ( mixed $value ) use ( &$plain ): mixed {
			if ( is_array( $value ) ) {
				return array_map( $plain, $value );
			}
			if ( is_bool( $value ) ) {
				return $value ? '1' : '0';
			}

			return trim( (string) preg_replace( '/\s+/', ' ', (string) $value ) );
		};
		$out   = array();
		foreach ( array_diff_key( $lasso, array_flip( $except ) ) as $field => $value ) {
			if ( $plain( $value ) !== $plain( $product[ $field ] ?? null ) ) {
				$out[ $field ] = $value;
			}
		}

		return $out;
	}

	/**
	 * The hash of a set of fields: the same fields in any order give the same hash.
	 *
	 * @param array<string, mixed> $fields Fields.
	 */
	public static function hash( array $fields ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the Domain layer makes no WordPress calls.
		return md5( (string) json_encode( self::sorted( $fields ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION ) );
	}

	/**
	 * The fields with their keys sorted at every level (a list keeps its order).
	 *
	 * @param array<mixed> $fields Fields.
	 * @return array<mixed>
	 */
	private static function sorted( array $fields ): array {
		if ( ! array_is_list( $fields ) ) {
			ksort( $fields );
		}

		return array_map( static fn ( mixed $value ): mixed => is_array( $value ) ? self::sorted( $value ) : $value, $fields );
	}
}
