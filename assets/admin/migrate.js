/**
 * Migrate from Lasso: Run migration and Update from Lasso (F10 D13, AC22; Task 26O). Each press posts to the run
 * route until every family has run, one family per request, and shows the progress; a second tab gets the route's
 * "A migration is already running."; a run cut off continues on the next press. At the end the page reloads with the
 * new report.
 */
( function () {
	const root = document.querySelector( '[data-affibloom-migrate-run]' );
	if ( ! root ) {
		return;
	}
	const config = JSON.parse( root.getAttribute( 'data-affibloom-migrate-run' ) );
	const backup = root.querySelector( '[data-affibloom-migrate-backup]' );
	const buttons = root.querySelectorAll( '[data-affibloom-migrate-start]' );
	const status = root.querySelector( '[data-affibloom-migrate-status]' );

	const setBusy = ( busy ) => {
		buttons.forEach( ( button ) => {
			button.disabled = busy || ! backup.checked;
		} );
	};

	const say = ( text, error ) => {
		status.textContent = text;
		status.classList.toggle( 'affibloom-migrate__status--error', !! error );
	};

	const step = async ( update ) => {
		const response = await fetch( config.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( { update } ),
		} );
		const body = await response.json();
		if ( ! response.ok ) {
			throw new Error( body.message || config.texts.failed );
		}
		return body.data;
	};

	const run = async ( update ) => {
		setBusy( true );
		try {
			let done = false;
			while ( ! done ) {
				const result = await step( update );
				say(
					config.texts.progress
						.replace( '%1$s', config.names[ result.family ] || result.family )
						.replace( '%2$d', result.position )
						.replace( '%3$d', result.total )
				);
				done = result.done;
			}
			say( config.texts.done );
			window.location.reload();
		} catch ( error ) {
			say( error.message, true );
			setBusy( false );
		}
	};

	backup.addEventListener( 'change', () => setBusy( false ) );
	buttons.forEach( ( button ) => {
		button.addEventListener( 'click', () =>
			run( 'update' === button.getAttribute( 'data-affibloom-migrate-start' ) )
		);
	} );
	setBusy( false );
} )();

/**
 * Migrate from Lasso: the Content card (F10 AC28, F34 AC7; Task LM8). Convert posts to the convert route until every
 * place is done, one place per request, and shows "N of M"; a run cut off continues on the next press (the route keeps
 * the places). Revert puts one place back; Revert All goes through the list, one place per request. The routes answer
 * 409 while another migration, conversion or command runs.
 */
( function () {
	const root = document.querySelector( '[data-affibloom-migrate-content]' );
	if ( ! root ) {
		return;
	}
	const config = JSON.parse( root.getAttribute( 'data-affibloom-migrate-content' ) );
	const backup = root.querySelector( '[data-affibloom-content-backup]' );
	const convert = root.querySelector( '[data-affibloom-content-convert]' );
	const revertAll = root.querySelector( '[data-affibloom-content-revert-all]' );
	const status = root.querySelector( '[data-affibloom-content-status]' );
	const nothing = convert.hasAttribute( 'data-affibloom-nothing' );
	let busy = false;

	const setBusy = ( value ) => {
		busy = value;
		convert.disabled = busy || nothing || ! backup.checked;
		root.querySelectorAll( '[data-affibloom-content-revert], [data-affibloom-content-revert-all]' ).forEach( ( button ) => {
			button.disabled = busy;
		} );
	};

	const say = ( text, error ) => {
		status.textContent = text;
		status.classList.toggle( 'affibloom-migrate__status--error', !! error );
	};

	const post = async ( url, body ) => {
		const response = await fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( body ),
		} );
		const answer = await response.json();
		if ( ! response.ok ) {
			throw new Error( answer.message || config.texts.failed );
		}
		return answer.data;
	};

	convert.addEventListener( 'click', async () => {
		setBusy( true );
		try {
			let done = false;
			let errors = 0;
			while ( ! done ) {
				const result = await post( config.convert, { backup_confirmed: backup.checked } );
				errors += result.errors;
				say( config.texts.progress.replace( '%1$d', result.position ).replace( '%2$d', result.total ) );
				done = result.done;
			}
			say( errors > 0 ? config.texts.errors : config.texts.done, errors > 0 );
			window.setTimeout( () => window.location.reload(), errors > 0 ? 4000 : 600 );
		} catch ( error ) {
			say( error.message, true );
			setBusy( false );
		}
	} );

	// One place back; the row goes when it is back, and stays (with a word why) when it was changed since.
	const revert = async ( item ) => {
		const title = item.getAttribute( 'data-affibloom-content-title' );
		const result = await post( config.revert, { place: item.getAttribute( 'data-affibloom-content-place' ) } );
		if ( result.kept > 0 ) {
			say( config.texts.kept.replace( '%s', title ), true );
			return false;
		}
		item.remove();
		say( config.texts.reverted.replace( '%s', title ) );
		return true;
	};

	root.querySelectorAll( '[data-affibloom-content-revert]' ).forEach( ( button ) => {
		button.addEventListener( 'click', async () => {
			setBusy( true );
			try {
				await revert( button.closest( '[data-affibloom-content-place]' ) );
			} catch ( error ) {
				say( error.message, true );
			}
			setBusy( false );
		} );
	} );

	if ( revertAll ) {
		revertAll.addEventListener( 'click', async () => {
			// eslint-disable-next-line no-alert -- a plain confirmation before every place is put back.
			if ( ! window.confirm( config.texts.confirm ) ) {
				return;
			}
			setBusy( true );
			try {
				for ( const item of root.querySelectorAll( '[data-affibloom-content-place]' ) ) {
					await revert( item );
				}
				window.location.reload();
			} catch ( error ) {
				say( error.message, true );
				setBusy( false );
			}
		} );
	}

	backup.addEventListener( 'change', () => setBusy( busy ) );
	setBusy( false );
} )();
