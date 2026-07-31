/**
 * "Community Supporters Directory" block editor script.
 *
 * Dynamic block: `save` returns null and the server renders the markup via
 * COMSUP_Block::render(). The editor shows a live server-side preview.
 *
 * Written against the global `wp` runtime so it needs no build step.
 */
( function ( wp ) {
	'use strict';

	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var __                = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var c                 = wp.components;
	var ServerSideRender  = wp.serverSideRender;

	var BLOCK_NAME = 'community-supporters/supporters';

	registerBlockType( BLOCK_NAME, {
		apiVersion: 3,
		title: __( 'Community Supporters Directory', 'community-supporters' ),
		description: __( 'Display the supporter directory from Airtable as a card grid or table.', 'community-supporters' ),
		icon: 'groups',
		category: 'widgets',
		keywords: [ 'airtable', 'supporters', 'sponsored' ],
		supports: {
			html: false,
			align: [ 'wide', 'full' ]
		},
		attributes: {
			layout: { type: 'string', default: 'grid' },
			columns: { type: 'number', default: 3 },
			limit: { type: 'number', default: 0 },
			country: { type: 'string', default: '' },
			language: { type: 'string', default: '' },
			search: { type: 'string', default: '' },
			fields: { type: 'string', default: '' },
			view: { type: 'string', default: '' },
			photos: { type: 'boolean', default: true },
			photoSize: { type: 'number', default: 116 },
			filters: { type: 'boolean', default: true }
		},

		edit: function ( props ) {
			var a          = props.attributes;
			var setA       = props.setAttributes;
			var blockProps = useBlockProps();

			var layoutPanel = el(
				c.PanelBody,
				{ title: __( 'Layout', 'community-supporters' ), initialOpen: true },
				el( c.SelectControl, {
					label: __( 'Layout', 'community-supporters' ),
					value: a.layout,
					options: [
						{ label: __( 'Card grid', 'community-supporters' ), value: 'grid' },
						{ label: __( 'Table', 'community-supporters' ), value: 'table' }
					],
					onChange: function ( v ) { setA( { layout: v } ); }
				} ),
				'grid' === a.layout ? el( c.RangeControl, {
					label: __( 'Columns', 'community-supporters' ),
					value: a.columns,
					min: 1,
					max: 6,
					onChange: function ( v ) { setA( { columns: v } ); }
				} ) : null,
				el( c.RangeControl, {
					label: __( 'Limit (0 = show all)', 'community-supporters' ),
					value: a.limit,
					min: 0,
					max: 100,
					onChange: function ( v ) { setA( { limit: v } ); }
				} ),
				el( c.ToggleControl, {
					label: __( 'Show profile photos', 'community-supporters' ),
					checked: a.photos,
					onChange: function ( v ) { setA( { photos: v } ); }
				} ),
				a.photos ? el( c.RangeControl, {
					label: __( 'Photo size (px)', 'community-supporters' ),
					value: a.photoSize,
					min: 48,
					max: 512,
					step: 8,
					onChange: function ( v ) { setA( { photoSize: v } ); }
				} ) : null,
				el( c.ToggleControl, {
					label: __( 'Show filter bar (Sponsorship / Language / Country)', 'community-supporters' ),
					checked: a.filters,
					onChange: function ( v ) { setA( { filters: v } ); }
				} )
			);

			var filterPanel = el(
				c.PanelBody,
				{ title: __( 'Filters', 'community-supporters' ), initialOpen: false },
				el( c.TextControl, {
					label: __( 'Country', 'community-supporters' ),
					value: a.country,
					onChange: function ( v ) { setA( { country: v } ); }
				} ),
				el( c.TextControl, {
					label: __( 'Language', 'community-supporters' ),
					value: a.language,
					onChange: function ( v ) { setA( { language: v } ); }
				} ),
				el( c.TextControl, {
					label: __( 'Search', 'community-supporters' ),
					value: a.search,
					onChange: function ( v ) { setA( { search: v } ); }
				} ),
				el( c.TextControl, {
					label: __( 'Fields (comma separated)', 'community-supporters' ),
					help: __( 'Leave blank to show all fields.', 'community-supporters' ),
					value: a.fields,
					onChange: function ( v ) { setA( { fields: v } ); }
				} ),
				el( c.TextControl, {
					label: __( 'View ID or name', 'community-supporters' ),
					value: a.view,
					onChange: function ( v ) { setA( { view: v } ); }
				} )
			);

			return el(
				Fragment,
				{},
				el( InspectorControls, {}, layoutPanel, filterPanel ),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: BLOCK_NAME,
						attributes: a
					} )
				)
			);
		},

		// Dynamic block — rendered on the server.
		save: function () {
			return null;
		}
	} );
} )( window.wp );
