<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationMark;

/**
 * The ID map and marks in memory.
 */
final class FakeIdMap implements MigratedProducts {

	/** Products by Lasso ID. @var array<int, list<int>> */
	public array $map = array();

	/** Marks by product. @var array<int, MigrationMark> */
	public array $marks = array();

	public function for_record( int $lasso_id ): array {
		return $this->map[ $lasso_id ] ?? array();
	}

	public function link( int $product, int $lasso_id, MigrationMark $mark ): void {
		if ( ! in_array( $product, $this->map[ $lasso_id ] ?? array(), true ) ) {
			$this->map[ $lasso_id ][] = $product;
		}
		$this->marks[ $product ] = $mark;
	}

	public function mark( int $product ): ?MigrationMark {
		return $this->marks[ $product ] ?? null;
	}
}
