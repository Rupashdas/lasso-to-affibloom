<?php
/**
 * POST affibloom-lasso-migrator/v1/finish.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Rest;

use AffibloomLassoMigrator\Application\Finish;
use AffibloomLassoMigrator\Application\NotClean;
use AffibloomLassoMigrator\Application\RunLocked;
use Closure;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The page's Finish migration (Task LM10): only with `confirmed`, the dialog's answer; 409 while LM9's check finds
 * anything or another migration or conversion runs. For those who may change Affibloom's settings; the REST nonce
 * guards it.
 */
final class FinishController {

	/**
	 * Builds the controller.
	 *
	 * @param Closure():Finish $finish     Finish, built when a request needs it.
	 * @param string           $capability Affibloom's settings capability.
	 */
	public function __construct( private Closure $finish, private string $capability ) {}

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
			MigrationController::NAMESPACE,
			'/finish',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'finish' ),
				'permission_callback' => fn (): bool => current_user_can( $this->capability ),
				'args'                => array(
					'confirmed' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Finishes the migration.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array{confirmed: bool}> $request
	 */
	public function finish( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $request['confirmed'] ) {
			return new WP_Error( 'affibloom_lasso_finish_unconfirmed', __( 'Confirm that the way back is your database backup only.', 'affibloom-lasso-migrator' ), array( 'status' => 400 ) );
		}
		try {
			$removed = ( $this->finish )()->finish( time() );
		} catch ( NotClean ) {
			return new WP_Error( 'affibloom_lasso_not_clean', __( 'Convert or remove the items listed under Check first.', 'affibloom-lasso-migrator' ), array( 'status' => 409 ) );
		} catch ( RunLocked ) {
			return new WP_Error( 'affibloom_lasso_migration_running', __( 'Another migration or conversion is running.', 'affibloom-lasso-migrator' ), array( 'status' => 409 ) );
		}

		return new WP_REST_Response( array( 'data' => array( 'removed' => $removed ) ) );
	}
}
