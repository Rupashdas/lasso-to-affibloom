<?php
/**
 * POST affibloom-lasso-migrator/v1/convert and /convert/revert.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Rest;

use AffibloomLassoMigrator\Application\Conversion\ContentRun;
use AffibloomLassoMigrator\Application\RunLocked;
use Closure;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The admin page's content conversion (Task LM8; F10 AC28, D13; F34 AC7): each POST to `/convert` converts the next
 * place and answers with the progress, only with `backup_confirmed`; each POST to `/convert/revert` puts one place back.
 * A request while another, or a command, runs is 409 "Another migration or conversion is running." For those who may
 * change Affibloom's settings; the REST nonce guards it.
 */
final class ContentController {

	/**
	 * Builds the controller.
	 *
	 * @param Closure():ContentRun $run        The content run, built when a request needs it.
	 * @param string               $capability Affibloom's settings capability.
	 */
	public function __construct( private Closure $run, private string $capability ) {}

	/**
	 * Registers the routes on rest_api_init.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * The routes.
	 */
	public function routes(): void {
		$permission = fn (): bool => current_user_can( $this->capability );
		register_rest_route(
			MigrationController::NAMESPACE,
			'/convert',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'convert' ),
				'permission_callback' => $permission,
				'args'                => array(
					'backup_confirmed' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			MigrationController::NAMESPACE,
			'/convert/revert',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'revert' ),
				'permission_callback' => $permission,
				'args'                => array(
					'place' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^(site|post:\d+)$',
					),
				),
			)
		);
	}

	/**
	 * Converts the next place.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array{backup_confirmed: bool}> $request
	 */
	public function convert( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $request['backup_confirmed'] ) {
			return new WP_Error( 'affibloom_lasso_backup_needed', __( 'Confirm that you have a database backup from today first.', 'affibloom-lasso-migrator' ), array( 'status' => 400 ) );
		}
		try {
			return new WP_REST_Response( array( 'data' => ( $this->run )()->step( time() ) ) );
		} catch ( RunLocked ) {
			return self::locked();
		}
	}

	/**
	 * Puts one place back.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array{place: string}> $request
	 */
	public function revert( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$results = ( $this->run )()->revert( (string) $request['place'], time() );
		} catch ( RunLocked ) {
			return self::locked();
		}

		return new WP_REST_Response(
			array(
				'data' => array(
					'place'   => (string) $request['place'],
					'results' => $results,
					'kept'    => count( array_filter( $results, static fn ( string $result ): bool => 'reverted' !== $result && 'none' !== $result ) ),
				),
			)
		);
	}

	/**
	 * The answer while something else runs.
	 */
	private static function locked(): WP_Error {
		return new WP_Error( 'affibloom_lasso_migration_running', __( 'Another migration or conversion is running.', 'affibloom-lasso-migrator' ), array( 'status' => 409 ) );
	}
}
