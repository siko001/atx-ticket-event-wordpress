=== ATX Digital Ticketing Connect ===
Contributors: atxdigital
Tags: events, tickets, stripe
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays events managed in the ATX Digital ticketing platform (Laravel) and hands ticket purchases to Stripe Checkout.

== Description ==

This plugin is the WordPress half of the ATX Digital ticketing system. The Laravel
application is the source of truth for all event and ticketing data; WordPress
mirrors published events read-only for public display.

* Receives signed webhooks from Laravel and mirrors events into an `atx_event`
  custom post type (with `atx_event_category` taxonomy).
* `[atx_events]` shortcode and "ATX Events" block for event listings
  (optional `category` and `limit` attributes).
* `[atx_event id="123"]` shortcode / block for a single event with a full
  ticket purchase form (ticket types, registration questions, discount code).
* Purchases are proxied server-side to the Laravel checkout API, which returns
  a Stripe Checkout URL — no payment data ever touches WordPress.
* Templates are theme-overridable: copy files from the plugin's `templates/`
  directory into `your-theme/atx-ticketing/`.

== Installation ==

1. Upload the plugin and activate it.
2. Go to Settings → ATX Ticketing and set the Laravel API base URL and the
   webhook shared secret (the same value as `TICKETING_WP_WEBHOOK_SECRET` in
   the Laravel `.env`).
3. Copy the webhook endpoint shown on that screen into the Laravel `.env` as
   `TICKETING_WP_WEBHOOK_URL`.
4. Publish an event in the Laravel admin — it appears in WordPress within
   seconds. Run `php artisan ticketing:push-events` on the Laravel side to
   resync everything at any time.

== Frequently Asked Questions ==

= Why can't I edit event content in WordPress? =

Laravel owns the event data. Anything you change locally would be overwritten
by the next sync, so the editor is disabled by design. Use the "Edit in ATX
admin" button instead.

= Does deactivating the plugin delete my events? =

No. Deactivation only flushes rewrite rules. Mirrored events remain in the
database.

== Changelog ==

= 1.0.0 =
* Initial release: webhook mirror, shortcodes, block, checkout proxy,
  settings screen, theme-overridable templates.
