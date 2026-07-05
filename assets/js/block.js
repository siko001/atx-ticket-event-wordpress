/**
 * ATX Ticketing Gutenberg blocks — server-rendered via the shortcode
 * callbacks, so display logic lives once (PHP side). No build step required.
 *
 *   atx-ticketing/events         — list of events (upcoming / past / all).
 *   atx-ticketing/featured-event — one highlighted event.
 */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender = wp.serverSideRender;
	var apiFetch = wp.apiFetch;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;

	// ── Shared data loaders (published events + categories) ────────────────
	function useEvents() {
		var state = useState(null);
		var events = state[0];
		var setEvents = state[1];

		useEffect(function () {
			apiFetch({ path: '/wp/v2/atx_event?per_page=100&status=publish&orderby=title&order=asc&_fields=id,title' })
				.then(function (rows) {
					setEvents(
						(rows || []).map(function (row) {
							return {
								value: String(row.id),
								label: (row.title && row.title.rendered) ? row.title.rendered : '#' + row.id
							};
						})
					);
				})
				.catch(function () { setEvents([]); });
		}, []);

		return events;
	}

	function useCategories() {
		var state = useState(null);
		var terms = state[0];
		var setTerms = state[1];

		useEffect(function () {
			apiFetch({ path: '/wp/v2/atx_event_category?per_page=100&_fields=slug,name' })
				.then(function (rows) {
					setTerms(
						(rows || []).map(function (row) {
							return { value: row.slug, label: row.name };
						})
					);
				})
				.catch(function () { setTerms([]); });
		}, []);

		return terms;
	}

	// ── Block 1: Events list ───────────────────────────────────────────────
	wp.blocks.registerBlockType('atx-ticketing/events', {
		title: __('ATX Events', 'atx-digital-ticketing-connect'),
		description: __('A list of events — upcoming, past or all — as a grid or a slider.', 'atx-digital-ticketing-connect'),
		icon: 'tickets-alt',
		category: 'widgets',
		attributes: {
			eventId: { type: 'number', default: 0 },
			scope: { type: 'string', default: 'upcoming' },
			category: { type: 'string', default: '' },
			limit: { type: 'number', default: 12 },
			orderby: { type: 'string', default: 'date' },
			order: { type: 'string', default: '' },
			layout: { type: 'string', default: 'grid' },
			columns: { type: 'number', default: 3 }
		},
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;
			var categories = useCategories();

			var categoryOptions = [{ value: '', label: __('All categories', 'atx-digital-ticketing-connect') }]
				.concat(categories || []);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Which events', 'atx-digital-ticketing-connect') },
						el(SelectControl, {
							label: __('Show', 'atx-digital-ticketing-connect'),
							value: a.scope,
							options: [
								{ value: 'upcoming', label: __('Upcoming events', 'atx-digital-ticketing-connect') },
								{ value: 'past', label: __('Past events', 'atx-digital-ticketing-connect') },
								{ value: 'all', label: __('All events', 'atx-digital-ticketing-connect') }
							],
							onChange: function (v) { set({ scope: v }); }
						}),
						el(SelectControl, {
							label: __('Category', 'atx-digital-ticketing-connect'),
							value: a.category,
							options: categoryOptions,
							onChange: function (v) { set({ category: v }); }
						}),
						el(RangeControl, {
							label: __('How many', 'atx-digital-ticketing-connect'),
							value: a.limit,
							min: 1,
							max: 50,
							onChange: function (v) { set({ limit: v || 12 }); }
						})
					),
					el(
						PanelBody,
						{ title: __('Order & layout', 'atx-digital-ticketing-connect') },
						el(SelectControl, {
							label: __('Order by', 'atx-digital-ticketing-connect'),
							value: a.orderby,
							options: [
								{ value: 'date', label: __('Event date', 'atx-digital-ticketing-connect') },
								{ value: 'title', label: __('Title', 'atx-digital-ticketing-connect') }
							],
							onChange: function (v) { set({ orderby: v }); }
						}),
						el(SelectControl, {
							label: __('Direction', 'atx-digital-ticketing-connect'),
							value: a.order,
							options: [
								{ value: '', label: __('Default (soonest / most recent)', 'atx-digital-ticketing-connect') },
								{ value: 'ASC', label: __('Ascending', 'atx-digital-ticketing-connect') },
								{ value: 'DESC', label: __('Descending', 'atx-digital-ticketing-connect') }
							],
							onChange: function (v) { set({ order: v }); }
						}),
						el(SelectControl, {
							label: __('Layout', 'atx-digital-ticketing-connect'),
							value: a.layout,
							options: [
								{ value: 'grid', label: __('Grid', 'atx-digital-ticketing-connect') },
								{ value: 'carousel', label: __('Slider (carousel)', 'atx-digital-ticketing-connect') }
							],
							onChange: function (v) { set({ layout: v }); }
						}),
						el(RangeControl, {
							label: __('Columns', 'atx-digital-ticketing-connect'),
							value: a.columns,
							min: 1,
							max: 4,
							onChange: function (v) { set({ columns: v || 3 }); }
						})
					)
				),
				el(ServerSideRender, {
					block: 'atx-ticketing/events',
					attributes: a
				})
			);
		},
		save: function () { return null; }
	});

	// ── Block 2: Featured event ────────────────────────────────────────────
	wp.blocks.registerBlockType('atx-ticketing/featured-event', {
		title: __('ATX Featured event', 'atx-digital-ticketing-connect'),
		description: __('Highlight a single event with a banner or card.', 'atx-digital-ticketing-connect'),
		icon: 'star-filled',
		category: 'widgets',
		attributes: {
			postId: { type: 'number', default: 0 },
			layout: { type: 'string', default: 'banner' },
			showDescription: { type: 'boolean', default: true },
			showDate: { type: 'boolean', default: true },
			showVenue: { type: 'boolean', default: true },
			showButton: { type: 'boolean', default: true },
			buttonText: { type: 'string', default: '' }
		},
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;
			var events = useEvents();

			var eventOptions = [{ value: '0', label: __('Auto — next upcoming event', 'atx-digital-ticketing-connect') }]
				.concat(events || []);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Featured event', 'atx-digital-ticketing-connect') },
						el(SelectControl, {
							label: __('Event', 'atx-digital-ticketing-connect'),
							value: String(a.postId),
							options: eventOptions,
							onChange: function (v) { set({ postId: parseInt(v, 10) || 0 }); }
						}),
						el(SelectControl, {
							label: __('Layout', 'atx-digital-ticketing-connect'),
							value: a.layout,
							options: [
								{ value: 'banner', label: __('Banner (image beside text)', 'atx-digital-ticketing-connect') },
								{ value: 'card', label: __('Card (image on top)', 'atx-digital-ticketing-connect') }
							],
							onChange: function (v) { set({ layout: v }); }
						})
					),
					el(
						PanelBody,
						{ title: __('Details to show', 'atx-digital-ticketing-connect') },
						el(ToggleControl, {
							label: __('Date', 'atx-digital-ticketing-connect'),
							checked: a.showDate,
							onChange: function (v) { set({ showDate: v }); }
						}),
						el(ToggleControl, {
							label: __('Venue', 'atx-digital-ticketing-connect'),
							checked: a.showVenue,
							onChange: function (v) { set({ showVenue: v }); }
						}),
						el(ToggleControl, {
							label: __('Description', 'atx-digital-ticketing-connect'),
							checked: a.showDescription,
							onChange: function (v) { set({ showDescription: v }); }
						}),
						el(ToggleControl, {
							label: __('Button', 'atx-digital-ticketing-connect'),
							checked: a.showButton,
							onChange: function (v) { set({ showButton: v }); }
						}),
						a.showButton ? el(TextControl, {
							label: __('Button text', 'atx-digital-ticketing-connect'),
							value: a.buttonText,
							placeholder: __('Details & tickets', 'atx-digital-ticketing-connect'),
							onChange: function (v) { set({ buttonText: v }); }
						}) : null
					)
				),
				el(ServerSideRender, {
					block: 'atx-ticketing/featured-event',
					attributes: a
				})
			);
		},
		save: function () { return null; }
	});
})(window.wp);
