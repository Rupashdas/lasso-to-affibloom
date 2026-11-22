<?php
/**
 * Another conversion is running.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application\Conversion;

use RuntimeException;

/**
 * Another run or revert holds the conversion's lock (Task LM3): "A conversion is already running."
 */
final class ConversionLocked extends RuntimeException {
}
