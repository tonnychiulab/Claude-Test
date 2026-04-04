=== Unauthorized Access Tracker ===
Contributors: itteam
Tags: security, audit, login, brute-force, off-hours
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Detects and logs unauthorized WordPress admin access, brute-force attempts, and off-hours admin logins. Supports zh_TW, en_US, ja.

== Description ==

WP Unauthorized Access Tracker (WUAT) provides a comprehensive audit trail for your WordPress admin area:

* **Login event logging** — records successful logins, failed attempts, and logouts.
* **Brute-force detection** — alerts when the same IP fails to log in too many times within a configurable window.
* **Off-hours login detection** — flags administrator logins that occur outside defined business hours (configurable per day-of-week and time range).
* **Email alerts** — notifies designated addresses on suspicious activity.
* **Admin log viewer** — filterable, sortable, paginated list table with CSV export.
* **Auto-cleanup** — WP-Cron removes entries older than a configurable number of days.
* **Multilingual** — ships with English (en_US), Traditional Chinese (zh_TW), and Japanese (ja) translations.

== Installation ==

1. Upload the `wp-unauthorized-access-tracker` folder to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins > Installed Plugins**.
3. Go to **Audit Trail > Settings** to configure thresholds, business hours, and alert recipients.

== Frequently Asked Questions ==

= Does this plugin block brute-force IPs? =
By default, no. Detection triggers an alert but does not block. IP blocking is intentionally left to dedicated security plugins (e.g. Wordfence) to avoid locking out legitimate users.

= How are IP addresses handled for GDPR? =
Enable **IP Anonymization** in Settings to mask the last octet of IPv4 addresses and the last 80 bits of IPv6 addresses before storage.

= How do I generate compiled .mo files from the .po files? =
Run `msgfmt -o languages/wp-unauthorized-access-tracker-zh_TW.mo languages/wp-unauthorized-access-tracker-zh_TW.po` (and similarly for `ja`).

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First public release.
