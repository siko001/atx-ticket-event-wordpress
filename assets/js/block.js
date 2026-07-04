/**
 * "ATX Events" Gutenberg block — server-rendered via the shortcode callbacks,
 * so display logic exists exactly once (PHP side). No build step required.
 */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender = wp.serverSideRender;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;

	wp.blocks.registerBlockType('atx-ticketing/events', {
		title: __('ATX Events', 'atx-digital-ticketing-connect'),
		icon: 'tickets-alt',
		category: 'widgets',
		attributes: {
			eventId: { type: 'number', default: 0 },
			category: { type: 'string', default: '' },
			limit: { type: 'number', default: 12 }
		},
		edit: function (props) {
			return el(
				wp.element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Event display', 'atx-digital-ticketing-connect') },
						el(TextControl, {
							label: __('Single event ID (0 = show list)', 'atx-digital-ticketing-connect'),
							type: 'number',
							value: props.attributes.eventId,
							onChange: function (value) {
								props.setAttributes({ eventId: parseInt(value, 10) || 0 });
							}
						}),
						el(TextControl, {
							label: __('Category slug filter', 'atx-digital-ticketing-connect'),
							value: props.attributes.category,
							onChange: function (value) {
								props.setAttributes({ category: value });
							}
						}),
						el(TextControl, {
							label: __('Max events', 'atx-digital-ticketing-connect'),
							type: 'number',
							value: props.attributes.limit,
							onChange: function (value) {
								props.setAttributes({ limit: parseInt(value, 10) || 12 });
							}
						})
					)
				),
				el(ServerSideRender, {
					block: 'atx-ticketing/events',
					attributes: props.attributes
				})
			);
		},
		save: function () {
			return null; // Server-rendered.
		}
	});
})(window.wp);
