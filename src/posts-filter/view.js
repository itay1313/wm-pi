/**
 * Posts Filter — frontend.
 *
 * On any checkbox change inside a filter root, collect the current
 * selected term IDs for both category and tag groups, and broadcast
 * a `wm:filter-change` CustomEvent on `window`. The Posts Grid
 * view script listens and refetches.
 *
 * Multiple filters on a page are independent: each filter owns its
 * own DOM and emits its own event. If a filter has `targetQueryId`,
 * the event carries it so only the matching grid responds.
 */

function readSelections( root ) {
	const categories = Array.from(
		root.querySelectorAll(
			'input[type="checkbox"][data-filter-type="categories"]:checked'
		)
	).map( ( el ) => parseInt( el.value, 10 ) ).filter( Boolean );

	const tags = Array.from(
		root.querySelectorAll(
			'input[type="checkbox"][data-filter-type="tags"]:checked'
		)
	).map( ( el ) => parseInt( el.value, 10 ) ).filter( Boolean );

	return { categories, tags };
}

function emit( root ) {
	const { categories, tags } = readSelections( root );
	const targetQueryId = root.dataset.targetQueryId || '';
	const evt = new CustomEvent( 'wm:filter-change', {
		detail: { categories, tags, targetQueryId },
	} );
	window.dispatchEvent( evt );
}

function onChange( e ) {
	if ( e.target.matches( 'input[type="checkbox"][data-filter-type]' ) ) {
		const root = e.target.closest( '[data-wm-filter="1"]' );
		if ( root ) {
			emit( root );
		}
	}
}

function onClear( e ) {
	const btn = e.target.closest( '.wm-posts-filter__clear' );
	if ( ! btn ) {
		return;
	}
	const root = btn.closest( '[data-wm-filter="1"]' );
	if ( ! root ) {
		return;
	}
	root.querySelectorAll(
		'input[type="checkbox"][data-filter-type]'
	).forEach( ( cb ) => {
		cb.checked = false;
	} );
	emit( root );
}

function init() {
	document.addEventListener( 'change', onChange );
	document.addEventListener( 'click', onClear );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
