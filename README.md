# ATX Digital Ticketing Connect (WordPress)

WordPress companion plugin for [`atx-digital/ticketing`](../atx-ticketing). **Laravel is the
source of truth** — this plugin mirrors published events read-only, renders them with
shortcodes/blocks/theme templates, and proxies ticket purchases to the Laravel checkout
API (which responds with a Stripe Checkout URL).

The webhook payload and checkout contracts are documented in the Laravel package's
[ARCHITECTURE.md](../atx-ticketing/ARCHITECTURE.md) — that file is the single reference
for both sides.

## How it works

1. **Sync in** — Laravel POSTs signed JSON (`event.published|updated|cancelled|deleted`)
   to `/wp-json/atx-ticketing/v1/webhook`. `Rest\WebhookController` verifies the HMAC
   (timestamp + body, 5-minute tolerance, constant-time compare) and `Sync\EventUpserter`
   upserts the `atx_event` post, structured meta (`_atx_event_id`, `_atx_starts_at`,
   `_atx_status`, `_atx_requires_attendee_details`, …), the `_atx_payload` JSON blob and
   `atx_event_category` terms — idempotently, keyed on the Laravel event id.
   `Sync\MediaSideloader` downloads the event's main image/video and gallery into the
   media library (deduplicated by source URL); a `connection.mode` envelope flips this
   site's test mode remotely.
2. **Display** — shortcodes and two Gutenberg blocks share one PHP render path:
   - `[atx_events]` / **ATX Events** block — a list of events. Options: `scope`
     (`upcoming` | `past` | `all`), `category` (slug), `limit`, `orderby`
     (`date` | `title`), `order` (`ASC` | `DESC`), `layout` (`grid` | `carousel`)
     and `columns` (1–4). The carousel is a dependency-free slider
     (`assets/js/events-carousel.js`).
   - `[atx_event id="…"]` — a single event by Laravel event id.
   - `[atx_featured_event post_id="…" layout="banner|card"]` / **ATX Featured
     event** block — highlights one event (post id, or the next upcoming when
     `0`) with per-detail toggles (date/venue/description/button + button text).

   The `/events/` archive,
   category archives and single event permalinks also render through the plugin
   templates by default (Events → Settings → Display), so themes with minimal
   generic templates still show full event details; both toggles there —
   plugin templates and plugin styling — can be switched off. Templates ship in
   `templates/` and can be overridden in `your-theme/atx-ticketing/`.
3. **Checkout out** — the vanilla-JS ticket form posts to
   `/wp-json/atx-ticketing/v1/checkout` (REST-nonce protected); `Frontend\CheckoutProxy`
   sanitises and forwards it server-side via `wp_remote_post` and the browser is
   redirected to the returned Stripe URL. When the event has **named tickets**
   (`requires_attendee_details`), the form renders a name (+ optional email) field per
   selected ticket and sends them along — Laravel validates server-side either way.
4. **Test mode** — two-way synced flag: the Connection-tab checkbox notifies Laravel
   (signed `POST /api/ticketing/wp/mode`); toggling it on the Laravel Connections
   screen arrives instantly as a webhook. While on: a warning notice across wp-admin
   and a striped TEST MODE banner on the buy form.
5. **Logs** — Events → Settings → Logs: incoming webhooks, syncs, mode changes and
   checkout activity (capped ring buffer, clearable); the Laravel admin keeps its own
   copy under System → Logs.

## Setup

1. Activate the plugin.
2. **Events → Settings** (tabbed screen):
   - **Connection** — Laravel API base URL, webhook shared secret (with a
     **Generate** button — generate, save, copy into the Laravel `.env`), the ATX
     admin URL, and a **Test connection** button that verifies both reachability
     and the secret against `GET /api/ticketing/wp/ping`.
   - **Pages** — checkout success/cancel pages, plus a **Create default pages**
     button that creates an Events listing page (`[atx_events]`) and the two
     checkout pages and selects them automatically.
   - **Connection** also holds the **Test mode** checkbox (synced with the ATX admin).
   - **Display** — use the plugin's event templates and/or styling, or hand both to the theme.
   - **Tools** — **Sync now** pulls every published event from
     `GET /api/ticketing/wp/events` (first install / after downtime).
   - **Logs** — sync + checkout activity on this site.
3. In the Laravel `.env`:
   ```dotenv
   TICKETING_WP_WEBHOOK_URL=https://your-site.example/wp-json/atx-ticketing/v1/webhook
   TICKETING_WP_WEBHOOK_SECRET=<same secret as in WP settings>
   ```
4. Publish an event in Laravel, click **Sync now** in WP, or run
   `php artisan ticketing:push-events` on the Laravel side.

## Updates (GitHub Releases)

The plugin self-updates from GitHub Releases via the vendored
`Support\GitHubPluginUpdater` (config in `config/github-updater.php`, currently
`siko001/atx-ticket-event-wordpress`). A **Force update check** link appears on the Plugins
screen. Releasing: push a `vX.Y.Z` tag (or run the *Release WordPress Plugin*
workflow) — `.github/workflows/release-plugin.yml` builds
`atx-digital-ticketing-connect.zip` with the version stamped into the plugin
header and attaches it to a GitHub Release. The Laravel package deliberately has
no updater — it is deployed with the app via Composer.

## For developers — building custom displays

Build your own blocks, sliders or parallax layouts on the synced data without
editing plugin files or synced posts:

```php
// Full payload for one event (occurrences, ticket_types, speakers, sponsors,
// registration_questions, venue, image_url, gallery_urls, checkout_url, post_id…).
$event = atx_ticketing_get_event();          // current post, or pass a post/ID

// A custom loop (scope: upcoming|past|all, category slug, limit, orderby, order).
$q = atx_ticketing_get_events( [ 'scope' => 'past', 'limit' => 6 ] );
while ( $q->have_posts() ) { $q->the_post(); /* … */ }
wp_reset_postdata();
```

Filters & actions:

| Hook | Type | Use |
|---|---|---|
| `atx_ticketing_event_payload` | filter `($payload, $post_id)` | add/tweak event data |
| `atx_ticketing_before_single_event` / `_after_single_event` | action `($event, $post)` | inject markup around a single event (e.g. a parallax hero) |
| `atx_ticketing_before_events` / `_after_events` | action `($query, $scope)` | inject markup around a list |

For a custom Gutenberg block, register your own block and call
`atx_ticketing_get_event()` / `atx_ticketing_get_events()` in its
`render_callback` — the data shape is identical to what the built-in blocks use.
Structured meta (`_atx_starts_at`, `_atx_starts_at_ts`, `_atx_venue_name`,
`_atx_status`, …) is also readable directly with `get_post_meta()`.

Events **and** their categories are one-way mirrors: both are read-only in
wp-admin (categories are viewable but not creatable/editable) — the ATX platform
owns them and overwrites local changes on sync. After a plugin update the mirror
re-derives its display data automatically, so no manual "Sync now" is needed.

## Pausing (hibernation) & uninstalling

All event data — including **past events, sponsors, speakers, locations, media
and categories** — is stored locally in WordPress (the `_atx_payload` meta, post
meta, the media library and `atx_event_category` terms). **Displaying events
never calls Laravel** — only syncing new changes, testing the connection, and
ticket checkout do.

- **Hibernation (pausing to save costs):** keep the plugin **active** and simply
  switch off / stop paying for the Laravel backend. The site keeps showing every
  synced event from its local copy for as long as you like; when you bring
  Laravel back, run **Tools → Sync now** to catch up. (Note: while Laravel is
  down, new syncs and live ticket purchases won't work — display is unaffected.)
- **Deactivating** never deletes anything.
- **Deleting the plugin** honours **Settings → Tools → Data & uninstall**:
  *Keep all data* (default — safe to reinstall/resume) or *Delete everything*
  (removes mirrored events, categories, downloaded media, logs and settings).
  A prompt with the same choice also appears when you click *Delete* on the
  Plugins screen. No custom database tables are ever created, so nothing is
  orphaned either way.
- **Removing the blocks** is safe: the ATX blocks are server-rendered, so if the
  plugin is gone they simply render nothing rather than breaking the page.
  (Shortcodes, if you used any, would show as literal text — prefer the blocks.)

## Development

```bash
composer install
composer lint     # php-parallel-lint
composer phpcs    # WordPress-Extra coding standards
```

No build step: the block uses `ServerSideRender` with plain-JS editor controls, the
frontend uses vanilla JS (no jQuery).

## Releasing a new version

A GitHub Release (built automatically from a tag) is what installed sites see as an update.

```bash
# from this plugin's directory, after committing your changes:
git tag -a v1.1.1 -m "v1.1.1"        # next semver: fix = patch, feature = minor, breaking = major
git push origin main --tags           # pushes the branch AND the tag

# the "Release WordPress Plugin" workflow then builds the zip, stamps the
# version into the plugin header and publishes the GitHub Release.
# On any installed site: Plugins → "Force update check" → update.
```

Forgot which tags exist? `git tag` (local) / `git ls-remote --tags origin` (GitHub).
Tagged the wrong commit? `git tag -d v1.1.1 && git push origin :refs/tags/v1.1.1`, then re-tag.
