<?php
/**
 * The `wp affibloom-lasso verify` command.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Cli;

use AffibloomLassoMigrator\Application\Conversion\LeftoverCheck;
use AffibloomLassoMigrator\Domain\Conversion\Leftover;
use WP_CLI;
use WP_CLI\Utils;

/**
 * `wp affibloom-lasso verify [--format=<format>]` (Task LM9): whether anything of Lasso is left in the content, place by
 * place with a link to change it, and every Affibloom box that names a product, group or table Affibloom does not have.
 * It only reads; it ends with an error status while anything is left, so a script can wait for zero.
 */
final class VerifyCommand {

	/**
	 * Builds the command.
	 *
	 * @param LeftoverCheck $check The check.
	 */
	public function __construct( private LeftoverCheck $check ) {}

	/**
	 * Adds the command when WP-CLI is running.
	 */
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'affibloom-lasso verify', $this );
		}
	}

	/**
	 * Checks that nothing of Lasso is left in the site's content.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : How the findings are printed.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp affibloom-lasso verify
	 *
	 * @param list<string>          $args       Unused.
	 * @param array<string, string> $assoc_args The options.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );
		$found = $this->check->check();
		if ( array() === $found ) {
			WP_CLI::success( 'Nothing of Lasso is left in your content.' );
			return;
		}
		Utils\format_items(
			$assoc_args['format'] ?? 'table',
			array_map(
				static fn ( Leftover $one ): array => array(
					'place' => $one->title,
					'what'  => $one->what,
					'count' => $one->count,
					'edit'  => $one->edit,
				),
				$found
			),
			array( 'place', 'what', 'count', 'edit' )
		);
		$places = count( array_unique( array_map( static fn ( Leftover $one ): string => $one->title . $one->edit, $found ) ) );
		WP_CLI::warning( sprintf( '%d findings in %d places: Lasso is still needed by this content.', count( $found ), $places ) );
		WP_CLI::halt( 1 );
	}
}
