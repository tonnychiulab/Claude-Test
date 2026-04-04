# WordPress Security Review Checklist

Use this checklist before presenting code or after completing a review pass.

## SQL

- [ ] All queries use `$wpdb->prepare()` or `insert()`/`update()`/`delete()`
- [ ] No direct interpolation of `$_GET`, `$_POST`, or variables in query strings
- [ ] LIKE queries apply `$wpdb->esc_like()` to the search term
- [ ] Integer inputs cast with `absint()` or `(int)` before use in queries

## XSS

- [ ] All HTML content output uses `esc_html()`
- [ ] All HTML attribute output uses `esc_attr()`
- [ ] All URL output uses `esc_url()`
- [ ] All JS string output uses `esc_js()` or `wp_localize_script()`
- [ ] JSON output uses `wp_json_encode()`
- [ ] When HTML must be allowed, `wp_kses_post()` or `wp_kses()` with explicit allowlist is used
- [ ] No raw `echo` of database values, `get_option()`, `get_post_meta()`, or superglobals

## CSRF

- [ ] All forms include `wp_nonce_field()`
- [ ] All form handlers call `wp_verify_nonce()` before processing
- [ ] All GET-based state-changing actions use `wp_nonce_url()`
- [ ] AJAX handlers receive nonce via `wp_localize_script()` and verify with `check_ajax_referer()`
- [ ] Nonce action names are scoped with a unique identifier (e.g. `'delete_post_' . $post_id`)

## Access Control

- [ ] Every handler calls `current_user_can()` with the most specific capability
- [ ] Object-level operations use `current_user_can('edit_post', $post_id)`, not just `current_user_can('edit_posts')`
- [ ] REST endpoints define a real `permission_callback` (not `'__return_true'` for authenticated actions)
- [ ] `wp_ajax_nopriv_` only used for genuinely public actions
- [ ] No reliance on front-end permission checks alone

## Input Sanitization

- [ ] All `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE` values sanitized before storage or use
- [ ] Email inputs validated with `is_email()` after `sanitize_email()`
- [ ] URL inputs validated with `filter_var($url, FILTER_VALIDATE_URL)` after `esc_url_raw()`
- [ ] Integer inputs validated for expected range after `absint()`

## File Uploads

- [ ] Permission checked with `current_user_can('upload_files')` or appropriate capability
- [ ] Nonce verified before processing
- [ ] File size checked against a maximum
- [ ] MIME type whitelisted
- [ ] Extension whitelisted
- [ ] Images verified with `getimagesize()` and re-rendered through GD/Imagick
- [ ] `wp_handle_upload()` used for final storage

## General

- [ ] Every PHP file starts with `if (!defined('ABSPATH')) { exit; }`
- [ ] No API keys, credentials, or secrets in plugin source files
- [ ] Error messages shown to users are generic (details logged with `error_log()`)
- [ ] `WP_DEBUG` and `WP_DEBUG_LOG` set correctly for environment
