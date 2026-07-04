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
   `_atx_status`, …), the `_atx_payload` JSON blob and `atx_event_category` terms —
   idempotently, keyed on the Laravel event id.
2. **Display** — `[atx_events category="…" limit="…"]`, `[atx_event id="…"]`, or the
   "ATX Events" block (all share one PHP render path). The `/events/` archive,
   category archives and single event permalinks also render through the plugin
   templates by default (Events → Settings → Display), so themes with minimal
   generic templates still show full event details; both toggles there —
   plugin templates and plugin styling — can be switched off. Templates ship in
   `templates/` and can be overridden in `your-theme/atx-ticketing/`.
3. **Checkout out** — the vanilla-JS ticket form posts to
   `/wp-json/atx-ticketing/v1/checkout` (REST-nonce protected); `Frontend\CheckoutProxy`
   sanitises and forwards it server-side via `wp_remote_post` and the browser is
   redirected to the returned Stripe URL.

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
   - **Tools** — **Sync now** pulls every published event from
     `GET /api/ticketing/wp/events` (first install / after downtime).
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
`siko001/atx-ticket-event`). A **Force update check** link appears on the Plugins
screen. Releasing: push a `vX.Y.Z` tag (or run the *Release WordPress Plugin*
workflow) — `.github/workflows/release-plugin.yml` builds
`atx-digital-ticketing-connect.zip` with the version stamped into the plugin
header and attaches it to a GitHub Release. The Laravel package deliberately has
no updater — it is deployed with the app via Composer.

## Development

```bash
composer install
composer lint     # php-parallel-lint
composer phpcs    # WordPress-Extra coding standards
```

No build step: the block uses `ServerSideRender` with plain-JS editor controls, the
frontend uses vanilla JS (no jQuery).
