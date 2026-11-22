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
