/**
 * Frontend behavior for the Posts Grid block.
 *
 * Inter-block contract:
 *   - The Posts Filter dispatches `wm:filter-change` on window with
 *     detail = { categories: number[], tags: number[], targetQueryId?: string }.
 *   - This script listens, refetches matching grids via the REST endpoint,
 *     and swaps inner HTML (items + pagination) in place.
 *
 * Multiple grids on one page are addressable by `data-query-id`. If the
 * filter targets a specific queryId it only updates that grid; otherwise
 * it updates all grids (broadcast).
 */

const REST_PATH = '/wp-json/wm/v1/posts';

function getRestUrl() {
	// wp-includes/js/dist/url provides addQueryArgs, but to avoid an extra
	// dependency we just build it manually.
	const root = ( window.wpApiSettings && window.wpApiSettings.root ) || '/wp-json/';
	return root.replace( /\/$/, '' ) + '/wm/v1/posts';
}

function getGrids() {
	return Array.from( document.querySelectorAll( '[data-wm-grid="1"]' ) );
}

function buildUrl( base, params ) {
	const url = new URL( base, window.location.origin );
	Object.entries( params ).forEach( ( [ k, v ] ) => {
		if ( v !== undefined && v !== null && v !== '' ) {
			url.searchParams.set( k, v );
		}
	} );
	return url.toString();
}

function readGridState( grid ) {
	return {
		queryId: grid.dataset.queryId || '',
		columns: parseInt( grid.dataset.columns, 10 ) || 3,
		perPage: parseInt( grid.dataset.perPage, 10 ) || 6,
		currentPage: parseInt( grid.dataset.currentPage, 10 ) || 1,
		categories: grid.dataset.categories || '',
		tags: grid.dataset.tags || '',
		search: grid.dataset.search || '',
	};
}

async function refreshGrid( grid, overrides = {} ) {
	const state = { ...readGridState( grid ), ...overrides };
	const url = buildUrl( getRestUrl(), {
		query_id: state.queryId,
		columns: state.columns,
		per_page: state.perPage,
		page: state.currentPage,
		categories: state.categories,
		tags: state.tags,
		search: state.search,
	} );

	grid.classList.add( 'is-loading' );

	try {
		const res = await fetch( url, { credentials: 'same-origin' } );
		if ( ! res.ok ) {
			throw new Error( 'Request failed: ' + res.status );
		}
		const data = await res.json();

		// The REST response wraps the full grid (including its outer div).
		// Replace the current grid with the new one's children.
		const tmp = document.createElement( 'div' );
		tmp.innerHTML = data.html;
		const fresh = tmp.firstElementChild;
		if ( fresh ) {
			grid.innerHTML = fresh.innerHTML;
			// Sync dataset so subsequent paginations use the new state.
			Object.entries( fresh.dataset ).forEach( ( [ k, v ] ) => {
				grid.dataset[ k ] = v;
			} );
		}
	} catch ( err ) {
		// eslint-disable-next-line no-console
		console.error( '[wm/posts-grid]', err );
	} finally {
		grid.classList.remove( 'is-loading' );
	}
}

function onPaginationClick( e ) {
	const btn = e.target.closest( '.wm-posts-pagination__page, .wm-posts-pagination__btn' );
	if ( ! btn ) {
		return;
	}
	const grid = btn.closest( '[data-wm-grid="1"]' );
	if ( ! grid ) {
		return;
	}
	e.preventDefault();

	let next = parseInt( grid.dataset.currentPage, 10 ) || 1;
	if ( btn.dataset.page ) {
		next = parseInt( btn.dataset.page, 10 );
	} else if ( btn.dataset.direction === 'next' ) {
		next += 1;
	} else if ( btn.dataset.direction === 'prev' ) {
		next -= 1;
	}
	const total = parseInt( grid.dataset.totalPages, 10 ) || 1;
	if ( next < 1 || next > total ) {
		return;
	}
	refreshGrid( grid, { currentPage: next } );
}

function onFilterChange( e ) {
	const detail = e.detail || {};
	const categories = Array.isArray( detail.categories ) ? detail.categories.join( ',' ) : '';
	const tags = Array.isArray( detail.tags ) ? detail.tags.join( ',' ) : '';
	const search = typeof detail.search === 'string' ? detail.search : '';
	const targetQueryId = detail.targetQueryId || '';

	getGrids().forEach( ( grid ) => {
		if ( targetQueryId && grid.dataset.queryId !== targetQueryId ) {
			return;
		}
		refreshGrid( grid, {
			categories,
			tags,
			search,
			currentPage: 1, // reset to first page on filter change
		} );
	} );
}

function init() {
	document.addEventListener( 'click', onPaginationClick );
	window.addEventListener( 'wm:filter-change', onFilterChange );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
