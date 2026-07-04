<?php
/**
 * GitHub updater configuration.
 *
 * @package AtxDigitalTicketing
 */

declare(strict_types=1);

return [
	'owner'        => 'siko001',
	'repo'         => 'atx-ticket-event',
	'slug'         => 'atx-digital-ticketing-connect',
	'zip_asset'    => 'atx-digital-ticketing-connect.zip',
	'cache_key'    => 'atx_digital_ticketing_connect_github_release',
	'name'         => 'ATX Digital Ticketing Connect',
	'author'       => 'ATX Digital',
	'requires_php' => '8.1',
	'user_agent'   => 'atx-digital-ticketing-connect-updater',
	'description'  => 'Read-only mirror of events from the ATX Digital ticketing platform, with ticket checkout hand-off to Stripe.',
];
