<?php
/**
 * Lasso's custom fields.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * Lasso's custom fields (F10 R12; analysis A T4-T6): the definitions in `lasso_fields` and each record's values in
 * `lasso_field_mapping`, in Lasso's order, with "show field name" from `lasso_metadata`. Read only.
 */
final class LassoFields {

	/**
	 * Builds the fields.
	 *
	 * @param list<array{id: int, name: string, type: string, description: string}>              $definitions The definitions, by ID.
	 * @param array<int, list<array{field: int, value: string, visible: bool, show_name: bool}>> $values      Each record's rows, in Lasso's order.
	 */
	public function __construct( public readonly array $definitions = array(), public readonly array $values = array() ) {}
}
