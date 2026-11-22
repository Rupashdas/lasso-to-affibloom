<?php
/**
 * What the migration does with one item.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * What the migration does with one item (F10 D18).
 */
enum Action: string {

	/** A new Affibloom item. */
	case Create = 'create';

	/** Changed in Lasso since its migration: its product takes the new values (D8; Task 26B). */
	case Update = 'update';

	/** Already migrated: nothing to do (D7). */
	case Unchanged = 'unchanged';

	/** Not migrated, with the reason in the note (a record with no link, D6). */
	case Skip = 'skip';

	/** Lasso retired it: a retired product pointing at its target's (D5). */
	case Retire = 'retire';

	/** In Lasso's trash: a product in the trash (D5). */
	case Trash = 'trash';

	/** Its family's migration task is not built yet: counted only (Task 25C). */
	case Waiting = 'waiting';

	/** Affibloom refuses a value: reported, the rest continues (D19). */
	case Error = 'error';
}
