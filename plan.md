# Goal

Add a daily WordPress cron that refreshes the probing patterns from the plugin's GitHub repository and the Cloudflare IP ranges from Cloudflare. Add a separate, auto-detected and user-switchable "Cloudflare IP check" setting, built on the same logic as the existing Cloudflare bypass setting. When it is on, requests whose connecting address is not a Cloudflare address are not treated as coming through Cloudflare, even if they carry Cloudflare headers. It is separate because some hosts do not forward Cloudflare's address.

## Steps

### [x] 1. Add the seed probing patterns file

**File:** `patterns/probing.txt` (new)

- Plain text, one regular expression fragment per line, matched case-insensitively against the normalized request path.
- Lines that are empty or start with `#` are comments. Fragments must not contain the `#` character.
- Copy every pattern from `get_default_patterns()` in the linked probe rule, one per line, grouped under comment lines that reuse the existing group names (Environment / secrets, VCS / IDE, Backups / dumps, and so on).
- The file is published in the GitHub repository at `patterns/probing.txt`, so the cron fetches it from the raw URL `https://raw.githubusercontent.com/agabor/poke-monitor/main/patterns/probing.txt`.

**Context:** [includes/rules/class-probe-rule.php](includes/rules/class-probe-rule.php)

### [x] 2. Create the probing patterns updater

**File:** `includes/class-probe-patterns.php` (new)

- Namespace `Poke_Monitor`, guarded by `defined( 'ABSPATH' ) || exit;`.
- Add `class Probe_Patterns` with these constants:
  - `OPTION_NAME = 'poke_monitor_probe_patterns'`
  - `SOURCE_URL = 'https://raw.githubusercontent.com/agabor/poke-monitor/main/patterns/probing.txt'`
  - `MAX_PATTERNS = 500`
  - `MAX_PATTERN_LENGTH = 200`
- Add `public function get_stored_patterns(): array`. It returns the `patterns` list from the option, or an empty array when nothing valid is stored.
- Add `public function get_last_updated(): ?string`. It returns the stored `updated_at` GMT timestamp or `null`.
- Add `public function update(): bool`:
  - Fetch `SOURCE_URL` with `wp_safe_remote_get()` and a 15 second timeout.
  - Return `false` on a `WP_Error`, a non-200 response or an empty body.
  - Run the body through `parse()`. Return `false` when no valid pattern remains, so the stored list is never replaced by an empty one.
  - Store `array( 'patterns' => $patterns, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) )` with `update_option()` and autoload `false`, then return `true`.
- Add `public function parse( string $body ): array`. It splits the body into lines, trims them, skips blank and `#` comment lines, drops invalid and duplicate fragments, and caps the result at `MAX_PATTERNS`.
- Add `private function is_valid_pattern( string $pattern ): bool`. It rejects fragments longer than `MAX_PATTERN_LENGTH` or containing `#`, and fragments that fail to compile when tested with `@preg_match( '#' . $pattern . '#i', '' )`.

**Context:** [includes/rules/class-probe-rule.php](includes/rules/class-probe-rule.php)

### [x] 3. Make the probe rule use the downloaded patterns

**File:** [includes/rules/class-probe-rule.php](includes/rules/class-probe-rule.php)

- Add `use Poke_Monitor\Probe_Patterns;`.
- Change `public function get_patterns()`:
  - Read `( new Probe_Patterns() )->get_stored_patterns()`.
  - Use the stored list when it is not empty, otherwise `$this->get_default_patterns()`.
  - Pass the chosen list through `apply_filters( 'poke_monitor_probe_patterns', ... )` as before.
- Keep `get_default_patterns()` unchanged as the offline fallback.

**Context:** [includes/class-probe-patterns.php](includes/class-probe-patterns.php)

### [x] 4. Create the Cloudflare IP ranges class

**File:** `includes/class-cf-ip-ranges.php` (new)

- Namespace `Poke_Monitor`, guarded by `defined( 'ABSPATH' ) || exit;`.
- Add `class Cf_Ip_Ranges` with these constants:
  - `OPTION_NAME = 'poke_monitor_cf_ip_ranges'`
  - `IPV4_URL = 'https://www.cloudflare.com/ips-v4'`
  - `IPV6_URL = 'https://www.cloudflare.com/ips-v6'`
- Add a private `$ip_resolver` holding an `Ip_Resolver`, created in `public function __construct()`. Add a private static cache for the loaded ranges.
- Add `public function get_default_ranges(): array`. It returns a built-in list of the currently published Cloudflare IPv4 and IPv6 CIDR blocks, used until the first successful refresh.
- Add `public function get_ranges(): array`. It returns the stored `ranges` list, or `get_default_ranges()` when nothing valid is stored.
- Add `public function get_last_updated(): ?string`. It returns the stored `updated_at` GMT timestamp or `null`.
- Add `public function refresh(): bool`:
  - Fetch `IPV4_URL` and `IPV6_URL` with `wp_safe_remote_get()` and a 15 second timeout.
  - Return `false` unless both responses are 200 and both yield at least one valid CIDR via `parse()`.
  - Store `array( 'ranges' => $merged, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) )` with `update_option()` and autoload `true`, because it is read on every Cloudflare request. Call `flush_cache()` and return `true`.
- Add `public function parse( string $body ): array`. It splits the body into lines, trims them, and keeps only lines accepted by `is_valid_cidr()`.
- Add `public function contains( string $ip ): bool`. It converts the IP with `Ip_Resolver::to_binary( $ip )`, which also normalizes IPv4-mapped IPv6. It returns `false` for empty or invalid input and `true` when any range matches via `cidr_contains()`.
- Add `public function flush_cache(): void`.
- Add `private function is_valid_cidr( string $cidr ): bool`. The address part must be a valid IP and the prefix an integer within 0–32 for IPv4 or 0–128 for IPv6.
- Add `private function cidr_contains( string $binary_ip, string $cidr ): bool`. It converts the network address to binary, returns `false` when the byte lengths differ, and compares the first `prefix` bits using whole-byte comparison plus a bit mask for the remainder.

**Context:** [includes/class-ip-resolver.php](includes/class-ip-resolver.php)

### [x] 5. Add the Cloudflare IP check setting to the detector

**File:** [includes/class-cf-detector.php](includes/class-cf-detector.php)

- Add `const IP_OPTION_NAME = 'poke_monitor_cf_ip_status'`.
- Add `public static function request_remote_addr_is_cloudflare(): bool`. It resolves the connecting address with `( new Ip_Resolver() )->resolve_remote_addr()` and returns `self::is_cloudflare_ip( $address )`.
- Add `public static function is_cloudflare_ip( string $ip ): bool`. It returns `( new Cf_Ip_Ranges() )->contains( $ip )`.
- Add `public static function get_ip_check_status()`. It has the same shape and defaults as `get_status()` (`enabled`, `state`, `set_by`, `set_at`, `user_id`) but reads `IP_OPTION_NAME`.
- Add `public static function is_ip_check_enabled()`. It returns the `enabled` flag of `get_ip_check_status()` as a bool.
- Add `public static function set_ip_check_status( $enabled, $set_by, $user_id = null )`. It stores the same structure as `set_status()` under `IP_OPTION_NAME`.
- Add `public static function maybe_auto_set_ip_check_on_activation()`. It follows the logic of `maybe_auto_set_on_activation()`:
  - Do nothing unless the state is `unknown`.
  - Do nothing when there is no `REQUEST_METHOD`.
  - Otherwise call `set_ip_check_status( self::request_remote_addr_is_cloudflare(), 'activation' )`.
- Add `public static function maybe_auto_set_ip_check_on_first_admin_visit()`. It follows `maybe_auto_set_on_first_admin_visit()`: do nothing unless the state is `unknown`, otherwise call `set_ip_check_status( self::request_remote_addr_is_cloudflare(), 'first_admin_visit' )`.
- Add `public static function get_ip_check_live_note()`. It returns `array( 'status' => 'full'|'none', 'message' => string )`. `full` means "This request was sent from a Cloudflare IP address (%s)". `none` means "This request was not sent from a Cloudflare IP address (%s)", or a variant for an unknown address. Both messages are translatable and include the connecting address.
- Add `public static function get_ip_check_switch_hint()`. It returns `?string`:
  - Check on and the connecting address is not Cloudflare: warn that every request will be logged as a bypass, and that some hosts show their own proxy address instead of Cloudflare's.
  - Check off and the connecting address is Cloudflare: suggest turning it on.
  - Check on and bypass detection off: say the check has no effect while bypass detection is off.
  - Otherwise `null`.
- Change `public static function is_cloudflare_trusted_request()`. It must return `is_bypass_detection_enabled() && request_has_all_cf_headers() && ( ! is_ip_check_enabled() || request_remote_addr_is_cloudflare() )`.

**Context:** [includes/class-cf-ip-ranges.php](includes/class-cf-ip-ranges.php), [includes/class-ip-resolver.php](includes/class-ip-resolver.php)

### [x] 6. Use the IP check in the bypass rule

**File:** [includes/rules/class-cf-bypass-rule.php](includes/rules/class-cf-bypass-rule.php)

- Change `public function matches( Request_Context $context )`. It returns `true` when `Cf_Detector::is_bypass_detection_enabled()` and either:
  - `! $context->has_all_cf_headers()`, or
  - `Cf_Detector::is_ip_check_enabled() && ! Cf_Detector::is_cloudflare_ip( $context->get_remote_addr() )`.
- Change `public function get_detail( Request_Context $context )`. It returns `null` when the Cloudflare headers are missing, and `'headers from non-Cloudflare IP'` when all headers are present but the connecting address is outside the Cloudflare ranges.

**Context:** [includes/class-cf-detector.php](includes/class-cf-detector.php), [includes/class-request-context.php](includes/class-request-context.php)

### [x] 7. Add the daily remote update cron

**File:** [includes/class-cron.php](includes/class-cron.php)

- Add `const UPDATE_HOOK = 'poke_monitor_update_remote_data'`.
- In `public function register_hooks(): void`, also add `add_action( self::UPDATE_HOOK, array( $this, 'run_remote_update' ) )`.
- In `public function ensure_scheduled(): void`, also schedule `UPDATE_HOOK` daily with `wp_schedule_event( time(), 'daily', self::UPDATE_HOOK )` when it is not yet scheduled.
- Add `public function run_remote_update(): void`:
  - Call `( new Probe_Patterns() )->update()` and `( new Cf_Ip_Ranges() )->refresh()`.
  - Wrap each call in its own `try`/`catch ( \Throwable )`, so one failure does not block the other.
  - Keep the previously stored data on failure.
- Leave `run()` and `get_keep_days()` unchanged.

**Context:** [includes/class-probe-patterns.php](includes/class-probe-patterns.php), [includes/class-cf-ip-ranges.php](includes/class-cf-ip-ranges.php)

### [x] 8. Schedule the update and set the IP check on activation

**File:** [includes/class-activator.php](includes/class-activator.php)

- In `public static function activate()`, after `Cf_Detector::maybe_auto_set_on_activation()`, call `Cf_Detector::maybe_auto_set_ip_check_on_activation()`.
- Also schedule the update with `wp_schedule_event( time(), 'daily', Cron::UPDATE_HOOK )` when `wp_next_scheduled( Cron::UPDATE_HOOK )` is false. No network call is made during activation. The built-in default ranges are used until the first cron run.

**Context:** [includes/class-cf-detector.php](includes/class-cf-detector.php), [includes/class-cron.php](includes/class-cron.php)

### [x] 9. Unschedule the update on deactivation

**File:** [includes/class-deactivator.php](includes/class-deactivator.php)

- In `public static function deactivate()`, also call `wp_clear_scheduled_hook( Cron::UPDATE_HOOK )`.

**Context:** [includes/class-cron.php](includes/class-cron.php)

### [x] 10. Remove the new data on uninstall

**File:** [uninstall.php](uninstall.php)

- In `poke_monitor_uninstall_site()`, add these option names to `$option_names`:
  - `poke_monitor_cf_ip_status`
  - `poke_monitor_cf_ip_ranges`
  - `poke_monitor_probe_patterns`
- Also call `wp_clear_scheduled_hook( 'poke_monitor_update_remote_data' )`, because the uninstall script must not depend on the plugin autoloader.

**Context:** [includes/class-cron.php](includes/class-cron.php), [includes/class-cf-ip-ranges.php](includes/class-cf-ip-ranges.php), [includes/class-probe-patterns.php](includes/class-probe-patterns.php)

### [ ] 11. Add the Cloudflare IP check toggle endpoint

**File:** [admin/class-log-controller.php](admin/class-log-controller.php)

- In `public function register_hooks(): void`, add `add_action( 'wp_ajax_poke_monitor_toggle_cf_ip', array( $this, 'ajax_toggle_cf_ip' ) )`.
- Add `public function ajax_toggle_cf_ip(): void`:
  - Call `verify_request()` and parse the posted `enabled` value exactly as `ajax_toggle_cf()` does.
  - Call `Cf_Detector::set_ip_check_status( $enabled, 'user', get_current_user_id() )`.
  - Respond with `wp_send_json_success( array( 'enabled' => $enabled, 'set_by_text' => $admin_page->describe_cf_set_by( Cf_Detector::get_ip_check_status() ) ) )`, using a new `Admin_Page` built from `$this->repository`.

**Context:** [admin/class-admin-page.php](admin/class-admin-page.php), [includes/class-cf-detector.php](includes/class-cf-detector.php)

### [ ] 12. Expose the IP check in the admin page data

**File:** [admin/class-admin-page.php](admin/class-admin-page.php)

- In `public function on_load_page(): void`, also call `Cf_Detector::maybe_auto_set_ip_check_on_first_admin_visit()`.
- In `public function enqueue_assets( $hook_suffix ): void`, add the following to the `pokeMonitorAdmin` localized data:
  - `'requestIsFromCloudflareIp' => Cf_Detector::request_remote_addr_is_cloudflare() ? '1' : '0'`
  - Translatable strings `confirmIpCheckEnable`, `confirmIpCheckEnableWithoutCloudflareIp` and `confirmIpCheckDisable`. They explain that when the check is on, requests from non-Cloudflare addresses are logged as Cloudflare Bypass even if they carry Cloudflare headers. The "without Cloudflare IP" text warns that this request did not come from a Cloudflare address.
- In `public function get_log_view_data(): array`, add a `'cloudflare_ip'` entry:
  - `'enabled' => Cf_Detector::is_ip_check_enabled()`
  - `'live_note' => Cf_Detector::get_ip_check_live_note()`
  - `'live_note_icon' => $this->get_live_note_icon( ... )`
  - `'hint' => Cf_Detector::get_ip_check_switch_hint()`
  - `'description' => $this->describe_cf_set_by( Cf_Detector::get_ip_check_status() )`

**Context:** [includes/class-cf-detector.php](includes/class-cf-detector.php), [admin/views/log.php](admin/views/log.php)

### [ ] 13. Render the Cloudflare IP check card

**File:** [admin/views/log.php](admin/views/log.php)

- Directly after the existing Cloudflare card, add a second `poke-monitor-card poke-monitor-cf-card` that uses the `$cloudflare_ip` array. Mirror the markup and classes of the first card, escaping all output:
  - live note with `$cloudflare_ip['live_note']['status']`, `$cloudflare_ip['live_note_icon']` and `$cloudflare_ip['live_note']['message']`;
  - optional hint paragraph from `$cloudflare_ip['hint']`;
  - switch row labelled "Cloudflare IP check" with label id `poke-monitor-cf-ip-switch-label`;
  - button `poke-monitor-switch` with id `poke-monitor-cf-ip-switch`, `role="switch"`, `aria-checked` and `data-enabled` taken from `$cloudflare_ip['enabled']`;
  - footer paragraph with id `poke-monitor-cf-ip-set-by` showing `$cloudflare_ip['description']`.
- Add a short explanatory line in the card: "Also require the connecting address to be a Cloudflare IP. Turn this off if your host does not pass Cloudflare's address through."
- Leave the rest of the template unchanged.

**Context:** [admin/class-admin-page.php](admin/class-admin-page.php)

### [ ] 14. Generalize the switch script for both settings

**File:** [assets/js/admin.js](assets/js/admin.js)

- Add default strings for `confirmIpCheckEnable`, `confirmIpCheckEnableWithoutCloudflareIp` and `confirmIpCheckDisable`.
- Add `function requestIsFromCloudflareIp()`. It returns `'1' === config.requestIsFromCloudflareIp`.
- Replace `initCloudflareSwitch()` with `function initSwitch( options )`. The options object has these keys:
  - `switchId`
  - `setById`
  - `action`
  - `getConfirmMessage( turningOn )`
- `initSwitch` keeps the current behavior: confirm, disable the button, `postAjax( options.action, ... )`, call `renderSwitchState()`, update the set-by text, and handle errors.
- In `init()`, call `initSwitch` twice:
  - For the existing switch: `poke-monitor-cf-switch`, `poke-monitor-cf-set-by`, action `poke_monitor_toggle_cf`, and the existing `getSwitchConfirmMessage`.
  - For the new switch: `poke-monitor-cf-ip-switch`, `poke-monitor-cf-ip-set-by`, action `poke_monitor_toggle_cf_ip`, and a new `getIpSwitchConfirmMessage( turningOn )`. It returns `confirmIpCheckDisable` when turning off, `confirmIpCheckEnableWithoutCloudflareIp` when turning on and `! requestIsFromCloudflareIp()`, and `confirmIpCheckEnable` otherwise.
- Leave the other init functions unchanged.

**Context:** [admin/views/log.php](admin/views/log.php)

### [ ] 15. Update the readme

**File:** [readme.txt](readme.txt)

- In "Features", add:
  - the daily update of probing patterns from the plugin's GitHub repository;
  - the daily update of Cloudflare IP ranges;
  - the separate "Cloudflare IP check" switch.
- In "What Poke Monitor cannot see", change the forged Cloudflare headers bullet. It now says that with the Cloudflare IP check turned on, requests carrying Cloudflare headers from a non-Cloudflare address are logged as a bypass. Without it, forged headers are not detected.
- In "Privacy", replace the claim that the plugin sends nothing to external services. State that once a day the plugin downloads the probing patterns from raw.githubusercontent.com and the IP ranges from cloudflare.com, and that no site or visitor data is sent.
- In the FAQ, add an entry explaining the Cloudflare IP check, including the hosts that do not forward Cloudflare's address. Also add one explaining that updated patterns replace the built-in list, with the built-in list as an offline fallback.
- In "What happens when I uninstall the plugin?", mention that the downloaded patterns and ranges are deleted too.

**Context:** none