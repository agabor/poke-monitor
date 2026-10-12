# Poke Monitor — Development Path

Planned improvements after the 1.0.0 wp.org review. Items are ordered by priority. The **1.1** items fix cases where the log can be silently wrong or harmful; the rest are quality improvements.

---

## 1.1 — Correctness and safety

### 1. Warn when the plugin is blind behind a local reverse proxy

**Problem.** `Classifier::is_loopback_request()` skips every request whose `REMOTE_ADDR` is `127.0.0.1`, `::1` or equals `SERVER_ADDR`. On hosts with a local reverse proxy in front of PHP (nginx → Apache, Varnish, many managed hosts) and no real-IP module, *every* request looks like loopback, so nothing is logged and the user never finds out.

**Plan.**
- On the admin page, check the current request: if `REMOTE_ADDR` is loopback (or equals `SERVER_ADDR`) while the browser is clearly remote, show a prominent warning card. The card should explain that the server is not passing the real client IP and that Poke Monitor cannot log anything until it does (`mod_remoteip`, nginx `real_ip`, or the host's setting).
- Optionally store a "last seen non-loopback request" timestamp so the warning can also say "no external request seen since …".
- Consider narrowing the skip: only skip loopback requests that are WordPress's own (e.g. `wp_doing_cron()`, the site-health loopback User-Agent, or requests carrying the `doing_wp_cron` query arg) instead of every request from a loopback address.

**Done when.** A site behind an unconfigured local proxy shows the warning on the Poke Monitor page, and the readme's "What Poke Monitor cannot see" section mentions the case.

### 2. Verify Cloudflare by IP range, not just by headers

**Problem.** "Came through Cloudflare" is decided by the presence of `CF-Ray`, `CF-IPCountry` and `CF-Visitor`. This has two consequences:
- **Whitelist evasion:** a request carrying forged CF headers plus `CF-Connecting-IP: <whitelisted IP>` is treated as that whitelisted IP and suppressed. An attacker only needs to know a whitelisted address; uptime-monitor ranges are often public.
- **False bypasses:** `CF-IPCountry` is only sent when *IP Geolocation* is enabled in Cloudflare. If it is turned off while bypass detection is on, every visitor is logged as a Cloudflare Bypass.

**Plan.**
- Ship Cloudflare's published IPv4/IPv6 ranges (https://www.cloudflare.com/ips/) as a static list in the plugin, filterable via `poke_monitor_cloudflare_ranges`.
- Define "trusted Cloudflare request" as: `REMOTE_ADDR` inside a Cloudflare range **and** `CF-Ray` present. Drop the requirement for `CF-IPCountry` and `CF-Visitor`; keep storing them when present.
- Bypass rule: bypass detection is on **and** `REMOTE_ADDR` is not in a Cloudflare range. This also catches forged headers, which closes a gap the readme currently documents as a limitation.
- Only honour `CF-Connecting-IP` for client IP resolution when the request is trusted (`Ip_Resolver::resolve_client_ip()`).
- Show a new "headers present but not from a Cloudflare IP" state in the Cloudflare card's live note.
- Note in the readme that the list is updated with plugin releases; mention the filter for sites behind another proxy layer.
- Keep `delete_requests_by_ip()` consistent with the new trust definition (store a `via_cloudflare` flag on the request row, rather than re-deriving it from header columns).

**Done when.** Forged headers are logged as bypass, whitelist matching cannot be spoofed via `CF-Connecting-IP`, and turning off IP Geolocation no longer floods the log.

### 3. Throttle logging under load

**Problem.** Every matched request costs two upserts and two inserts. A scanner sending thousands of probes, or a misconfigured bypass switch, multiplies DB writes exactly when the server is under pressure. The plugin becomes part of the load it reports.

**Plan.**
- Add a per-IP, per-type rate cap (e.g. at most N rows per IP per type per minute; default ~30, filterable via `poke_monitor_rate_limit`). Track counts in the object cache or a short-lived transient.
- When the cap is hit, stop inserting rows and instead increment a counter: either a `suppressed_count` column on the last row for that IP/type, or a small aggregate table (`ip`, `error_type_id`, `minute`, `count`).
- Show suppressed totals in the UI ("+ 1,240 similar requests from this IP"), so the log stays truthful.
- Add a global safety valve: if total writes in the last minute exceed a high threshold, log one summary row and pause logging for the rest of that minute.

**Done when.** A 10,000-request scan from one IP produces a bounded number of DB writes and still shows the true total in the admin screen.

### 4. Document what nginx hides

**Problem.** Typical nginx configs answer missing `*.php` files with `try_files $uri =404` without touching WordPress. On those servers, the Direct PHP Access tab and every `.php` probe pattern (`phpinfo.php`, `shell.php`, `adminer.php`, …) never fire.

**Plan.**
- Add an explicit bullet to "What Poke Monitor cannot see" naming this case.
- Optionally detect it: if `$_SERVER['SERVER_SOFTWARE']` contains `nginx`, show a one-line note on the Direct PHP Access tab.

**Done when.** A user on nginx understands why that tab may stay empty.

### 5. Fix the self-healing cron schedule

**Problem.** `Cron::register_hooks()` is called from `Plugin::boot()`, which itself runs on `plugins_loaded` at priority 10. It then adds `ensure_scheduled()` to `plugins_loaded` at the same priority. WordPress iterates the callbacks for the current priority by value, so a callback added mid-iteration is not run; `ensure_scheduled()` is most likely never executed. The activator still schedules the event, so this only matters if the event is lost later (cron table reset, migration, object-cache-backed cron issues).

**Plan.** Hook `ensure_scheduled()` to `init` (or call it directly from `boot()`). Add a quick test, or check with Query Monitor, that it runs.

**Done when.** Deleting the `poke_monitor_purge` event and loading any page re-creates it.

---

## 1.2 — Quality and maintainability

### 6. Make failures visible in debug mode
`Logger::handle()` and `Cron::run()` swallow every `\Throwable`. Keep that behaviour in production, but when `WP_DEBUG` is on, `error_log()` the message with a `[Poke Monitor]` prefix.

### 7. Tidy the write transaction
In `Logger::write_log_entry()`, the two upserts run before `START TRANSACTION`, and there is no `ROLLBACK` on failure. Either move the upserts inside the transaction and roll back in a `catch`, or drop the transaction altogether. Orphan cleanup already handles partial writes, and the transaction is a no-op on MyISAM anyway.

### 8. Reduce probe-pattern false positives
`backup`, `dump`, `/console/` and `config\.json$` match anywhere in a 404 path, so a mistyped slug like `/how-to-backup-wordpress` is logged as probing. Anchor these to a path segment or file extension, e.g. `/backup(s)?(/|\.(zip|tar|gz|sql)$)`, `/dump\.(sql|gz)$`, `^/console/`.

### 9. Configurable retention
Retention is hard-coded to 30 days (`Cron::KEEP_DAYS`). Add a `poke_monitor_keep_days` filter now, and a setting later if users ask for it. Update the readme FAQ and privacy section to mention it.

### 10. Constant naming
Rename `poke_monitor_VERSION`, `poke_monitor_PLUGIN_FILE`, etc. to the WordPress convention `POKE_MONITOR_VERSION`, `POKE_MONITOR_PLUGIN_FILE`, … Keep the old names defined for one release if anything external might rely on them.

### 11. Tests and CI
- The rule classes (`Probe_Rule`, `Suspicious_Ua_Rule`, `Direct_Php_Rule`, `Xmlrpc_Rule`), `Request_Context::normalize_path()`, `Whitelist::sanitize_input()` and the planned Cloudflare range check are pure or nearly pure, so they are ideal for unit tests.
- Add a GitHub Actions workflow running PHPCS (WordPress standard), Plugin Check, and the unit tests on PHP 7.4 and the latest PHP.

---

## Later / ideas

- **Multisite support:** network activation, per-site tables or a shared table with `blog_id`.
- **Export:** CSV download of the current tab, for writing firewall rules elsewhere.
- **Suggested rules:** for a selected IP or path, show a ready-to-copy Cloudflare WAF expression or nginx/Apache snippet. This stays true to "a log, not a firewall".
- **Summary notifications:** an optional daily email digest when bypasses or Direct PHP Access hits appear.
