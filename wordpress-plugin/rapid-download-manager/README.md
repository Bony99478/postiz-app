# Rapid Download Manager

A self-contained WordPress plugin for running a file-download site that
needs to sustain roughly **100,000 downloads/day** without falling over.
Drop the `rapid-download-manager` folder into `wp-content/plugins/`,
activate it, and start adding downloads.

## What it does

- **Custom post type** (`Downloads`) with a file upload field, an optional
  external URL (CDN/S3) field, categories, and a title/description like any
  other WordPress content.
- **Protected storage** – uploaded files are moved to
  `wp-content/uploads/rdm-protected/`, which is locked down with
  `.htaccess` / `web.config` rules on activation so the web server refuses
  to serve them directly. The **only** way to reach a file is through the
  plugin's signed download URL.
- **Signed, expiring links** – every download URL is HMAC-signed
  (`wp_salt('auth')`-derived key) with an optional expiry, so links can't
  be guessed, tampered with, or shared indefinitely.
- **Resumable downloads** – the built-in PHP delivery mode honors HTTP
  `Range` requests, so download managers, browsers resuming an interrupted
  transfer, and video/audio players all work correctly.
- **Web-server offload** – optional `X-Sendfile` (Apache/LiteSpeed) or
  `X-Accel-Redirect` (Nginx) delivery modes hand the actual file transfer
  to the web server after WordPress does the access check, taking PHP
  entirely out of the hot path. This is what makes six-figure daily
  download volume realistic on modest hardware.
- **Batched, cache-backed counters** – a download only touches a
  transient (Redis/Memcached-backed when an object cache plugin is
  active). A WP-Cron job flushes accumulated counts into post meta every 5
  minutes, so the database sees at most one write per download post per
  flush window instead of one write per download.
- **Per-IP rate limiting** – configurable requests-per-window limit to
  blunt scraping/abuse without adding a database table.
- **Shortcode & Gutenberg block** – `[rdm_download id="123"]` or the
  "Download Button" block, both showing a live download count.
- **REST API** – `GET /wp-json/rdm/v1/downloads/{id}/count` (public) and
  `GET /wp-json/rdm/v1/stats/summary` (admin only).
- **Admin dashboard** – downloads-today figure and a top-downloads table
  under *Downloads → Stats*, plus a settings screen under
  *Downloads → Settings*.

## Installation

1. Copy `rapid-download-manager/` into `wp-content/plugins/`.
2. Activate it from **Plugins** in wp-admin.
3. Go to **Downloads → Add New**, upload a file (or paste an external
   URL), and publish.
4. Place `[rdm_download id="123"]` in any post/page, or use the
   **Download Button** block.

No build step, no Composer dependencies, no external services required to
get started.

## Scaling to ~100k downloads/day

100,000/day averages ~70 requests/minute, which is light for a normal
LAMP/LEMP stack — the real risk at that volume is a handful of design
mistakes that turn each download into an expensive operation. This plugin
avoids the common ones, and this section covers the remaining server-side
setup for a fully worry-free deployment:

### 1. Turn on an object cache

Install Redis or Memcached plus a persistent object cache plugin (e.g.
Redis Object Cache). This makes the rate limiter and pending-count buffer
sub-millisecond operations instead of `wp_options` reads/writes, and
speeds up the rest of WordPress too.

### 2. Use X-Sendfile or X-Accel-Redirect for delivery

Set **Downloads → Settings → Delivery mode**:

**Nginx (`X-Accel-Redirect`, recommended):**

```nginx
location /rdm-internal/ {
    internal;
    alias /var/www/your-site/wp-content/uploads/rdm-protected/;
}
```

Keep the setting's "internal path prefix" as `/rdm-internal/` (or match
whatever you use above). PHP-FPM only handles the access check; Nginx
streams the actual bytes.

**Apache/LiteSpeed (`X-Sendfile`):**

Enable `mod_xsendfile` (Apache) or LiteSpeed's built-in support, add:

```apache
XSendFile on
XSendFilePath /var/www/your-site/wp-content/uploads/rdm-protected/
```

and set the header name in settings (`X-Sendfile`, or `X-LiteSpeed-Location`
for older LiteSpeed setups).

Without either, the plugin falls back to a Range-aware PHP stream, which
is perfectly fine for moderate traffic or shared hosting where you can't
touch server config.

### 3. Consider offloading large/hot files to a CDN or object storage

For very large files or extreme spikes, upload the file to S3/R2/a CDN and
paste the URL into the download's **"external URL"** field instead of
uploading it. The plugin still enforces signed links, rate limiting, and
counts the download — it just 302-redirects to the CDN instead of
streaming the bytes itself, so your origin server's bandwidth is no longer
the bottleneck at all.

### 4. Cache pages, never cache the download endpoint

Full-page caching (a page cache plugin, Cloudflare, etc.) is safe and
recommended for the pages *linking to* downloads, since the shortcode/block
always generates a fresh signed URL server-side on each render. Just make
sure your cache/CDN doesn't cache `?rdm_download=...` requests themselves —
they must reach PHP to be verified, rate-limited, and counted.

## Developer hooks

- `do_action( 'rdm_before_serve_download', $post_id )` — fires right
  before a file/redirect is served; hook in your own gating (login wall,
  paywall, custom entitlement check) and call `wp_die()` to block.
- `apply_filters( 'rdm_show_download_count', true, $post_id )` — return
  `false` to hide the download counter on the shortcode/block output.

## Settings reference

| Setting | Description |
|---|---|
| Require signed links | Reject any download request without a valid HMAC signature. |
| Link expiry | Seconds before a generated link stops working (0 = never). Overridable per download. |
| Rate limit | Max requests per IP within a time window. |
| Delivery mode | `php`, `xsendfile`, or `xaccel`. |
| X-Sendfile header name | Header your web server expects (`X-Sendfile`, `X-LiteSpeed-Location`, ...). |
| X-Accel-Redirect prefix | Internal Nginx location matching the protected directory. |
| PHP stream chunk size | Bytes-per-iteration for the fallback PHP delivery mode. |

## Uninstalling

Deactivating the plugin leaves everything in place. Deleting it from the
Plugins screen removes plugin settings and scheduled cron events only —
your `Downloads` posts and uploaded files are never touched automatically.
