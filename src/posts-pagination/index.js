import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: () => {
		const blockProps = useBlockProps( {
			className: 'walkme-posts-pagination-placeholder',
		} );
		return (
			<div { ...blockProps }>
				<em>
					{ __(
						'Pagination — rendered on the frontend by the parent Posts Grid.',
						'walkme-posts-blocks'
					) }
				</em>
			</div>
		);
	},
	save: () => null, // dynamic; rendered by parent grid on the frontend.
} );
