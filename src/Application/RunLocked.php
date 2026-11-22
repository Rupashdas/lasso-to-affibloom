<?php
/**
 * Another request is running the migration.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use RuntimeException;

/**
 * Another request holds the migration's lock (Task 26O): "A migration is already running."
 */
final class RunLocked extends RuntimeException {
}
