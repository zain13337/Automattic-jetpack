/**
 * External dependencies
 */
import { renderMarkdownFromHTML, renderHTMLFromMarkdown } from '@automattic/jetpack-ai-client';
import { rawHandler, getBlockContent } from '@wordpress/blocks';
import { select, dispatch } from '@wordpress/data';
/**
 * Types
 */
import type { BlockEditorDispatch, BlockEditorSelect } from './types';
import type { Block, RenderHTMLRules } from '@automattic/jetpack-ai-client';

export function getMarkdown( html: string ) {
	return renderMarkdownFromHTML( { content: html } );
}

export function renderHTMLContent( markdown: string, rules: RenderHTMLRules = [] ) {
	return renderHTMLFromMarkdown( { content: markdown, rules } );
}

export class BlockHandler {
	public clientId: string;
	public renderRules: RenderHTMLRules = [];
	public firstUpdate: boolean = true;

	constructor( clientId: string, renderRules: RenderHTMLRules = [] ) {
		this.clientId = clientId;
		this.renderRules = renderRules;
	}

	public getBlock(): Block {
		const { getBlock } = select( 'core/block-editor' ) as BlockEditorSelect;

		return getBlock( this.clientId );
	}

	public getContent() {
		const block = this.getBlock();

		return getMarkdown( getBlockContent( block ) );
	}

	public renderContent( markdown: string ) {
		return renderHTMLContent( markdown, this.renderRules );
	}

	public onSuggestion( suggestion: string ): void {
		// Ignore an empty suggestion
		if ( ! suggestion ) {
			return;
		}

		const HTML = this.renderContent( suggestion );

		this.replaceBlockContent( HTML );
	}

	public onDone(): void {
		this.firstUpdate = true;
	}

	public replaceBlockContent( newContent: string ): void {
		// Create a new block with the raw HTML content.
		const [ newBlock ] = rawHandler( { HTML: newContent } );

		if ( ! newBlock ) {
			return;
		}

		const { updateBlockAttributes, __unstableMarkNextChangeAsNotPersistent } = dispatch(
			'core/block-editor'
		) as BlockEditorDispatch;

		if ( ! this.firstUpdate ) {
			// Mark the change as not persistent so we can undo all the changes in one step.
			__unstableMarkNextChangeAsNotPersistent();
		} else {
			this.firstUpdate = false;
		}

		// Replace the original block attributes with the new block attributes.
		updateBlockAttributes( this.clientId, newBlock.attributes );
	}
}
