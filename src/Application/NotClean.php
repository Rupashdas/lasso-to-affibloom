<?php
/**
 * Finish refused: something of Lasso is left.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use RuntimeException;

/**
 * LM9's check still finds something (Task LM10): "Convert or remove the items listed under Check first."
 */
final class NotClean extends RuntimeException {

	/**
	 * Builds the refusal.
	 *
	 * @param int $findings How many findings the check has.
	 */
	public function __construct( public readonly int $findings ) {
		parent::__construct( "{$findings} findings" );
	}
}
