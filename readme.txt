=== Poke Monitor ===
Contributors: gaborangyal
Tags: security, cloudflare, log, 404, xmlrpc
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See who is poking your site: Cloudflare bypasses, secret-file scans, backdoor access, XML-RPC abuse and scanner user agents.

== Description ==

Poke Monitor shows you who is poking at your site and how. It records suspicious requests in your own database and lists them in a simple admin screen, one tab per type.

If your site is behind Cloudflare, Poke Monitor also tells you something few other tools do: whether anyone is reaching your server **directly**, skipping Cloudflare and its firewall altogether.

**Poke Monitor is a log, not a firewall.** It does not block, rate-limit or challenge anything. It tells you what is happening so you can decide what to do — for example, add a firewall rule at your host or in Cloudflare.

= What it logs =

* **Cloudflare Bypass** — your site is behind Cloudflare, but a request reached your server without the headers Cloudflare always adds (`CF-Ray`, `CF-IPCountry`, `CF-Visitor`). This usually means someone found your server's real IP address and is skipping Cloudflare and its firewall.
* **Probing** — requests that end in a 404 and look for files scanners hunt for: `.env`, `backup.sql`, `.git/config`, `wp-config.php.bak`, `phpinfo.php` and similar. Take these events seriously, as they can drain your system resources!
* **Direct PHP Access** — requests for `.php` files inside `wp-includes` or your uploads folder. Visitors never need these; they are typically attempts to reach a planted backdoor.
* **XML-RPC** — every request to `xmlrpc.php` (except calls that log in successfully). The endpoint is mostly used for password guessing; the log shows the method called and the number of failed logins.
* **Suspicious User Agent** — requests with a missing or malformed User-Agent, or one from an HTTP library or a known scanner (`curl`, `python-requests`, `Go-http-client`, `sqlmap`, `nikto` and others).

One request can match several types — for example a probe for `.env` sent with `curl`. It is stored once and appears in each matching tab, and in the **All** tab.

For each logged request Poke Monitor stores the time, method, path, status code, client IP address, the address that actually connected to your server, User-Agent, and the Cloudflare headers if present.

= Features =

* Tabs for All and each error type, with request counts.
* Cloudflare card: shows whether your current request came through Cloudflare, and a switch to turn bypass detection on or off.
* Cloudflare IP check: a separate switch that, when on, treats a request as coming through Cloudflare only if its connecting address is a Cloudflare IP address.
* Daily update of the probing patterns from the plugin's GitHub repository, with the built-in list as a fallback.
* Daily update of the Cloudflare IP ranges from Cloudflare.
* Allowlist by exact IP address or exact User-Agent, for all types or selected ones — for example your uptime monitor or office IP. Each log row has buttons to allowlist its IP or User-Agent in one click.
* Clear one type, or clear the whole log.
* Automatic clean-up: entries older than 30 days are deleted daily.
* Requests from logged-in users are never logged.

= What Poke Monitor cannot see =

Please read this before relying on the log.

* **Only requests that reach WordPress are logged.** Requests for files that exist on disk, requests your web server blocks itself, and pages served from a full-page cache (for example Cloudflare APO, Varnish or a static cache plugin) never reach WordPress, so they are not in the log.
* **An existing backdoor is invisible.** If a malicious PHP file actually exists in `wp-includes` or uploads, your web server runs it directly and Poke Monitor never sees the request. Blocking PHP execution in those folders at the web server is the real protection; see the FAQ.
* **Forged Cloudflare headers are not detected by default.** Poke Monitor checks whether the Cloudflare headers are present. With the Cloudflare IP check turned on, requests that carry Cloudflare headers from an address outside Cloudflare's published ranges are logged as a bypass. Without it, an attacker who sends fake headers directly to your server is not logged as a bypass. To close that gap, let your server accept web traffic only from Cloudflare's IP ranges, or use Cloudflare's Authenticated Origin Pulls.
* **The client IP of a bypass can be fake.** On requests that did not come through Cloudflare, the `CF-Connecting-IP` header is set by whoever sent the request. When the client IP differs from the address that actually connected, the log shows both; trust the connecting address when you write a firewall rule.
* **Logged-in users are never logged**, including requests made with a stolen account.
* **Some legitimate tools will show up** as Suspicious User Agent or XML-RPC — uptime monitors, server cron jobs that call `wp-cron.php` with `wget` or `curl`, integrations using HTTP libraries, desktop blogging apps. Add them to the allowlist.

= Privacy =

Poke Monitor stores IP addresses and User-Agents of the requests it logs. These are personal data under the GDPR.

* Visitor data stays in your own WordPress database. Once a day the plugin downloads the probing patterns from raw.githubusercontent.com and the Cloudflare IP ranges from cloudflare.com. No site or visitor data is sent.
* Entries are deleted automatically after 30 days.

You are responsible for mentioning the logging in your privacy policy — for example, that IP addresses and User-Agents of suspicious requests are stored for 30 days to protect the site. This is not legal advice.

== Installation ==

1. Install Poke Monitor from Plugins → Add New, or upload the `poke-monitor` folder to `/wp-content/plugins/`.
2. Activate the plugin **from your browser**, the way you normally visit your site. On activation Poke Monitor checks whether your request came through Cloudflare and turns bypass detection on or off accordingly.
3. Open **Poke Monitor** in the admin menu. Check the Cloudflare card: if the switch doesn't match your setup, change it.
4. Optionally, add your uptime monitor, office IP or other trusted tools to the allowlist.

If you activate the plugin with WP-CLI, the Cloudflare check runs the first time you open the Poke Monitor page instead.

== Frequently Asked Questions ==

= Does Poke Monitor block attacks? =

No. It only logs them. Use the log to decide what to block at your web server, your host's firewall or in Cloudflare.

= My site isn't behind Cloudflare. Is the plugin still useful? =

Yes. Probing, Direct PHP Access, XML-RPC and Suspicious User Agent logging work on any site. Only Cloudflare Bypass detection needs Cloudflare; turn it off on the Cloudflare card.

= The Cloudflare switch was set wrong. Why? =

The setting is made once, automatically, from the request that activated the plugin (or your first visit to the Poke Monitor page). If that request reached your server directly — through a VPN, a hosts-file entry or a staging domain — the plugin concluded you are not behind Cloudflare. Use the switch to correct it. Poke Monitor never changes it again on its own.

= Every visitor is logged as a Cloudflare Bypass. What's wrong? =

Most likely bypass detection is on but your site is not (or no longer) proxied through Cloudflare — for example the orange cloud is switched off in your Cloudflare DNS settings. Turn the switch off, or fix the Cloudflare setting.

= I see Cloudflare bypasses. What should I do? =

Someone knows your server's real IP address. Configure your server or host firewall to accept web traffic only from Cloudflare's published IP ranges, or turn on Cloudflare's Authenticated Origin Pulls. If the address was leaked recently (for example through an old DNS record or an email header), consider changing it.

= Why is my uptime monitor in the log? =

Many monitors and tools use HTTP library User-Agents such as `curl` or `python-requests`. Click **+ IP** or **+ UA** on its row in the log, choose which types the entry applies to, and save. Allowlist entries match exactly.

= Can I keep the log for longer than 30 days? =

Not yet. The retention period is fixed at 30 days in this version.

= Does it slow down my site? =

Requests that don't match any rule cost a few string checks and no database query. Matching requests are written to the database at the very end of the request, after the page has been generated.

= Does it work on multisite? =

Multisite is not tested or supported. Activating it on individual sites is expected to work; network activation is not recommended.

= What is the Cloudflare IP check? =

The Cloudflare IP check is a second switch on the Cloudflare card. When it is on, a request is treated as coming through Cloudflare only if its connecting address is inside Cloudflare's published IP ranges, even if it carries the Cloudflare headers. Requests with Cloudflare headers from any other address are logged as Cloudflare Bypass. Some hosts do not forward Cloudflare's address to WordPress and show their own proxy address instead. On those hosts every request would be logged as a bypass, so turn the check off there. It is set automatically in the same way as bypass detection, and you can change it at any time.

= Do the updated probing patterns replace the built-in list? =

Yes. Once a day Poke Monitor downloads the current probing patterns from its GitHub repository and uses them in place of the built-in list. The built-in list stays in the plugin and is used whenever no downloaded list is stored, for example if the site has never been able to reach GitHub, so probing detection keeps working offline. The Cloudflare IP ranges work the same way: a built-in list is used until the first successful download.

= What happens when I uninstall the plugin? =

All Poke Monitor tables and settings are deleted, including the downloaded probing patterns and Cloudflare IP ranges. Deactivating keeps your data.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.