import { InnerBlocks } from '@wordpress/block-editor';

// Dynamic block: PHP handles the actual frontend output. We only persist
// the inner blocks (pagination) into post_content so they survive saves.
export default function save() {
	return <InnerBlocks.Content />;
}
