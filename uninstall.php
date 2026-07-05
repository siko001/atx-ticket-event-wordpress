<?php
/**
 * Uninstall handler — runs when the plugin is deleted from the Plugins screen.
 *
 * Behaviour is governed by the "atx_ticketing_delete_data_on_uninstall" option,
 * set from Events → Settings → Tools (or the prompt shown when deleting):
 *   - falsy (default): keep everything — safe for hibernation / reinstalling.
 *   - truthy: remove all plugin data (mirrored events, their media, the event
 *     categories, and every plugin option). The plugin creates NO custom
 *     database tables, so nothing else is left behind.
 *
 * @package AtxDigitalTicketing
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Purge all plugin data for the current site.
 */
function atx_ticketing_uninstall_site(): void {
	if ( ! get_option( 'atx_ticketing_delete_data_on_uninstall' ) ) {
		return; // Keep data.
	}

	// The plugin is not loaded during uninstall, so register just enough of the
	// post type and taxonomy for core's cleanup functions to work fully.
	register_post_type( 'atx_event', [ 'public' => false ] );
	register_taxonomy( 'atx_event_category', 'atx_event' );

	$event_ids = get_posts(
		[
			'post_type'        => 'atx_event',
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		]
	);

	foreach ( $event_ids as $event_id ) {
		// Remove media the plugin downloaded for this event.
		$main = (int) get_post_meta( $event_id, '_atx_main_media_id', true );
		if ( $main > 0 ) {
			wp_delete_attachment( $main, true );
		}

		$gallery = get_post_meta( $event_id, '_atx_gallery_ids', true );
		if ( is_array( $gallery ) ) {
			foreach ( $gallery as $attachment_id ) {
				if ( (int) $attachment_id > 0 ) {
					wp_delete_attachment( (int) $attachment_id, true );
				}
			}
		}

		wp_delete_post( $event_id, true ); // Also clears its post meta.
	}

	$terms = get_terms(
		[
			'taxonomy'   => 'atx_event_category',
			'hide_empty' => false,
		]
	);

	if ( is_array( $terms ) ) {
		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, 'atx_event_category' );
		}
	}

	$options = [
		'atx_ticketing_settings',
		'atx_ticketing_events_page_id',
		'atx_ticketing_indexed_version',
		'atx_ticketing_last_sync',
		'atx_ticketing_logs',
		'atx_ticketing_delete_data_on_uninstall',
		'atx_ticketing_start_ts_backfilled', // legacy (pre-1.3.11).
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites( [ 'fields' => 'ids' ] );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		atx_ticketing_uninstall_site();
		restore_current_blog();
	}
} else {
	atx_ticketing_uninstall_site();
}
