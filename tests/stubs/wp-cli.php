<?php
/**
 * The WP-CLI methods the migrator calls, for PHPStan only: php-stubs/wp-cli-stubs needs php-stubs/wordpress-stubs 6 or
 * older, and szepeviktor/phpstan-wordpress brings 7. Never loaded at run time.
 *
 * @package AffibloomLassoMigrator
 */

// phpcs:ignoreFile -- a signature stub, not plugin code.

namespace {
	class WP_CLI {
		/**
		 * @param callable|object|string $callable
		 * @param array<string, mixed>   $args
		 */
		public static function add_command( string $name, $callable, array $args = array() ): bool {}

		public static function success( string $message ): void {}

		public static function warning( string $message ): void {}

		public static function line( string $message = '' ): void {}

		/**
		 * @param array<string, mixed> $assoc_args
		 */
		public static function confirm( string $question, array $assoc_args = array() ): void {}

		/**
		 * @return never
		 */
		public static function halt( int $return_code ) {}

		/**
		 * @param string|\WP_Error|\Exception|\Throwable $message
		 * @param bool|int                               $exit
		 * @return never
		 */
		public static function error( $message, $exit = true ) {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * Prints items as a table, JSON, CSV, YAML, IDs or a count.
	 *
	 * @param string                           $format Format to use: table, json, csv, yaml, ids, count.
	 * @param array<int, array<string, mixed>> $items  The items.
	 * @param array<int, string>|string        $fields The fields to show.
	 */
	function format_items( $format, $items, $fields ): void {}
}
