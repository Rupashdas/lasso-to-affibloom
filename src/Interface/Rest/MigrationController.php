<?php
/**
 * POST affibloom-lasso-migrator/v1/run.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Rest;

use AffibloomLassoMigrator\Application\MigrationRunner;
use AffibloomLassoMigrator\Application\RunLocked;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The admin page's run (F10 D13, AC22; Task 26O): each POST runs the next family and answers with the progress; a
 * second request while one runs is 409 "A migration is already running." For those who may change Affibloom's
 * settings; the REST nonce guards it.
 */
final class MigrationController {

	public const NAMESPACE = 'affibloom-lasso-migrator/v1';

	/**
	 * Builds the controller.
	 *
	 * @param MigrationRunner $runner     The run.
	 * @param string          $capability Affibloom's settings capability.
	 */
	public function __construct( private MigrationRunner $runner, private string $capability ) {}

	/**
	 * Registers the route on rest_api_init.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * The route.
	 */
	public function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run' ),
				'permission_callback' => fn (): bool => current_user_can( $this->capability ),
				'args'                => array(
					'update' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Runs the next family.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array{update: bool}> $request
	 */
	public function run( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			return new WP_REST_Response( array( 'data' => $this->runner->batch( (bool) $request['update'], time() ) ) );
		} catch ( RunLocked ) {
			return new WP_Error( 'affibloom_lasso_migration_running', __( 'A migration is already running.', 'affibloom-lasso-migrator' ), array( 'status' => 409 ) );
		}
	}
}
