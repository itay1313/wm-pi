/**
 * Posts Filter — frontend.
 *
 * Collects current filter selections (category checkboxes, tag checkboxes,
 * search query) and broadcasts a `wm:filter-change` CustomEvent on `window`.
 * The Posts Grid view script listens and refetches.
 *
 * Multiple filters on a page are independent. If a filter has a
 * `targetQueryId`, the event carries it so only the matching grid responds.
 */

const SEARCH_DEBOUNCE_MS = 250;

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

	const searchInput = root.querySelector( 'input[data-wm-search="1"]' );
	const search = searchInput ? searchInput.value.trim() : '';

	return { categories, tags, search };
}

function emit( root ) {
	const { categories, tags, search } = readSelections( root );
	const targetQueryId = root.dataset.targetQueryId || '';
	const evt = new CustomEvent( 'wm:filter-change', {
		detail: { categories, tags, search, targetQueryId },
	} );
	window.dispatchEvent( evt );

	// Visually sync the "All" pseudo-item (highlighted when no categories selected).
	syncAllState( root, categories.length === 0 );
}

function syncAllState( root, isAllActive ) {
	const allBtn = root.querySelector( '.wm-posts-filter__nav-item[data-action="all"]' );
	if ( allBtn ) {
		allBtn.classList.toggle( 'is-active', isAllActive );
	}
}

function onChange( e ) {
	if ( e.target.matches( 'input[type="checkbox"][data-filter-type]' ) ) {
		const root = e.target.closest( '[data-wm-filter="1"]' );
		if ( root ) {
			emit( root );
		}
	}
}

function onClick( e ) {
	// "Clear" button — wipe all selections.
	const clearBtn = e.target.closest( '.wm-posts-filter__clear' );
	if ( clearBtn ) {
		const root = clearBtn.closest( '[data-wm-filter="1"]' );
		if ( root ) {
			clearAll( root );
		}
		return;
	}

	// "All" pseudo-item — clears category selections only.
	const allBtn = e.target.closest( '.wm-posts-filter__nav-item[data-action="all"]' );
	if ( allBtn ) {
		const root = allBtn.closest( '[data-wm-filter="1"]' );
		if ( root ) {
			root.querySelectorAll(
				'input[type="checkbox"][data-filter-type="categories"]'
			).forEach( ( cb ) => { cb.checked = false; } );
			emit( root );
		}
	}
}

function clearAll( root ) {
	root.querySelectorAll(
		'input[type="checkbox"][data-filter-type]'
	).forEach( ( cb ) => { cb.checked = false; } );
	const searchInput = root.querySelector( 'input[data-wm-search="1"]' );
	if ( searchInput ) {
		searchInput.value = '';
	}
	emit( root );
}

let searchTimers = new WeakMap();
function onSearch( e ) {
	if ( ! e.target.matches( 'input[data-wm-search="1"]' ) ) {
		return;
	}
	const root = e.target.closest( '[data-wm-filter="1"]' );
	if ( ! root ) {
		return;
	}
	if ( searchTimers.has( root ) ) {
		clearTimeout( searchTimers.get( root ) );
	}
	searchTimers.set(
		root,
		setTimeout( () => emit( root ), SEARCH_DEBOUNCE_MS )
	);
}

function dispatchViewChange( root, payload ) {
	const targetQueryId = root.dataset.targetQueryId || '';
	window.dispatchEvent(
		new CustomEvent( 'wm:view-change', {
			detail: { ...payload, targetQueryId },
		} )
	);
}

function onColumnsClick( e ) {
	const btn = e.target.closest( 'button[data-view="columns"]' );
	if ( ! btn ) {
		return;
	}
	const root = btn.closest( '[data-wm-filter="1"]' );
	if ( ! root ) {
		return;
	}
	const value = parseInt( btn.dataset.value, 10 );
	if ( ! [ 2, 3, 4 ].includes( value ) ) {
		return;
	}
	root.querySelectorAll( 'button[data-view="columns"]' ).forEach( ( b ) => {
		b.classList.toggle( 'is-active', b === btn );
	} );
	dispatchViewChange( root, { columns: value } );
}

function onPerPageChange( e ) {
	if ( ! e.target.matches( 'select[data-view="perPage"]' ) ) {
		return;
	}
	const root = e.target.closest( '[data-wm-filter="1"]' );
	if ( ! root ) {
		return;
	}
	const value = parseInt( e.target.value, 10 );
	if ( ! value || value < 1 ) {
		return;
	}
	dispatchViewChange( root, { perPage: value } );
}

function syncViewControlsFromGrid( root ) {
	// Find any grid this filter targets and reflect its current view state.
	const targetQueryId = root.dataset.targetQueryId || '';
	const sel = targetQueryId
		? `[data-wm-grid="1"][data-query-id="${ targetQueryId }"]`
		: '[data-wm-grid="1"]';
	const grid = document.querySelector( sel );
	if ( ! grid ) {
		return;
	}
	const cols = parseInt( grid.dataset.columns, 10 ) || 3;
	root.querySelectorAll( 'button[data-view="columns"]' ).forEach( ( b ) => {
		b.classList.toggle( 'is-active', parseInt( b.dataset.value, 10 ) === cols );
	} );
	const perPage = parseInt( grid.dataset.perPage, 10 ) || 6;
	const select = root.querySelector( 'select[data-view="perPage"]' );
	if ( select ) {
		// If the option exists, pick it; else add it.
		if ( ! Array.from( select.options ).some( ( o ) => parseInt( o.value, 10 ) === perPage ) ) {
			const opt = document.createElement( 'option' );
			opt.value = String( perPage );
			opt.textContent = String( perPage );
			select.appendChild( opt );
		}
		select.value = String( perPage );
	}
}

function init() {
	document.addEventListener( 'change', onChange );
	document.addEventListener( 'change', onPerPageChange );
	document.addEventListener( 'click', onClick );
	document.addEventListener( 'click', onColumnsClick );
	document.addEventListener( 'input', onSearch );

	document.querySelectorAll( '[data-wm-filter="1"]' ).forEach( ( root ) => {
		syncAllState( root, true );
		syncViewControlsFromGrid( root );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
