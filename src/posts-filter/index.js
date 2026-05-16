import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, Disabled } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const { targetQueryId } = attributes;
		const blockProps = useBlockProps();
		return (
			<>
				<InspectorControls>
					<PanelBody
						title={ __( 'Filter target', 'wm-posts-blocks' ) }
					>
						<TextControl
							label={ __(
								'Target Query ID (optional)',
								'wm-posts-blocks'
							) }
							help={ __(
								'Leave blank to control every Posts Grid on the page. Set to a specific grid\'s queryId to target just that grid.',
								'wm-posts-blocks'
							) }
							value={ targetQueryId || '' }
							onChange={ ( v ) =>
								setAttributes( { targetQueryId: v } )
							}
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<Disabled>
						<ServerSideRender
							block="wm/posts-filter"
							attributes={ { targetQueryId } }
						/>
					</Disabled>
				</div>
			</>
		);
	},
	save: () => null, // dynamic block.
} );
