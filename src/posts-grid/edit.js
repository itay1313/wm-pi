import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import {
	useBlockProps,
	InspectorControls,
	InnerBlocks,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	Disabled,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const ALLOWED_BLOCKS = [ 'walkme/posts-pagination' ];
const TEMPLATE = [ [ 'walkme/posts-pagination' ] ];

/**
 * Stable, short, deterministic ID derived from the clientId.
 * Persists per-block instance (saved to attributes on first render).
 */
function generateQueryId( clientId ) {
	return 'q-' + clientId.replace( /-/g, '' ).slice( 0, 10 );
}

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { queryId, columns, postsPerPage } = attributes;

	useEffect( () => {
		if ( ! queryId ) {
			setAttributes( { queryId: generateQueryId( clientId ) } );
		}
	}, [ queryId, clientId, setAttributes ] );

	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Grid settings', 'walkme-posts-blocks' ) }>
					<SelectControl
						label={ __( 'Columns', 'walkme-posts-blocks' ) }
						value={ String( columns ) }
						options={ [
							{ label: '2', value: '2' },
							{ label: '3', value: '3' },
							{ label: '4', value: '4' },
						] }
						onChange={ ( v ) =>
							setAttributes( { columns: parseInt( v, 10 ) } )
						}
					/>
					<RangeControl
						label={ __( 'Posts per page', 'walkme-posts-blocks' ) }
						value={ postsPerPage }
						onChange={ ( v ) =>
							setAttributes( { postsPerPage: v } )
						}
						min={ 1 }
						max={ 24 }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<Disabled>
					<ServerSideRender
						block="walkme/posts-grid"
						attributes={ {
							queryId: queryId || generateQueryId( clientId ),
							columns,
							postsPerPage,
						} }
					/>
				</Disabled>

				<div className="walkme-posts-grid__inner-blocks">
					<InnerBlocks
						allowedBlocks={ ALLOWED_BLOCKS }
						template={ TEMPLATE }
						templateLock="all"
					/>
				</div>
			</div>
		</>
	);
}
