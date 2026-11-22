<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Migration\ProductRules;
use AffibloomLassoMigrator\Domain\Migration\ProductWriter;
use InvalidArgumentException;

/**
 * Affibloom's products in memory: what the migration wrote, and one link it refuses (as its Save rules would).
 */
final class FakeAffibloom implements ProductRules, ProductWriter {

	/**
	 * The products by ID.
	 *
	 * @var array<int, array{status: string, fields: array<string, mixed>, to: int, created: string}>
	 */
	public array $products = array();

	/** A link Affibloom refuses, '' for none. */
	public string $refuse_url = '';

	/** Fields refused by check(), for the dry run. @var array<string, string> */
	public array $refusals = array();

	private int $next = 100;

	public function refusals( array $fields ): array {
		return $this->refusals;
	}

	public function import( array $fields, string $created, string $modified ): int {
		if ( '' !== $this->refuse_url && $fields['url'] === $this->refuse_url ) {
			throw new InvalidArgumentException( 'url: invalid_url' );
		}
		$id                    = $this->next++;
		$this->products[ $id ] = array(
			'status'  => 'publish',
			'fields'  => $fields,
			'to'      => 0,
			'created' => $created,
		);

		return $id;
	}

	public function fields( int $product ): ?array {
		// Affibloom reads published products only.
		return 'publish' === ( $this->products[ $product ]['status'] ?? '' ) ? $this->products[ $product ]['fields'] : null;
	}

	public function update( int $product, array $fields ): void {
		$this->products[ $product ]['fields'] = array_merge( $this->products[ $product ]['fields'], $fields );
	}

	public function retire( int $product, int $to ): void {
		$this->products[ $product ]['status'] = 'retired';
		$this->products[ $product ]['to']     = $to;
	}

	public function date( int $product, string $created, string $modified ): void {
		$this->products[ $product ]['created']  = $created;
		$this->products[ $product ]['modified'] = $modified;
	}

	public function trash( int $product ): void {
		$this->products[ $product ]['status'] = 'trash';
	}
}
