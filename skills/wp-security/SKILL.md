---
name: wp-security
description: "Enforces WordPress security rules for plugin and theme development. Activate when writing new plugins or themes, auditing WordPress code for vulnerabilities, fixing SQL injection, XSS, CSRF, or access control bugs, securing AJAX handlers or REST endpoints, reviewing file upload handlers, or when the user says 'secure WordPress code', 'audit WordPress plugin', 'fix WordPress vulnerability', 'write secure WordPress', 'review WordPress security', 'add nonce', or 'check capabilities'."
version: 1.2.0
allowed-tools:
  - Read
  - Edit
  - Write
  - Grep
  - Glob
---

# WordPress Plugin Security

## When to Use

- Writing a new WordPress plugin or theme from scratch
- Adding a new feature (form, AJAX handler, REST endpoint, file upload) to an existing plugin
- Auditing a plugin codebase for security vulnerabilities before release or deployment
- Fixing a reported vulnerability (SQL injection, XSS, CSRF, privilege escalation)
- Reviewing a pull request that touches data input, output, or user permissions

## When NOT to Use

- Reviewing non-WordPress PHP code — use a general PHP security review instead
- Answering conceptual WordPress questions with no code to write or modify — respond directly without enforcing these rules
- Reviewing front-end JS/CSS assets with no PHP interaction — treat as a standard front-end review, no security enforcement needed

---

## Workflow

Determine mode first. If the user's intent is not clear, ask:
> "Are you asking me to write new code or review existing code?"

### Mode A — Writing New Code

1. Before writing any handler, identify: does it accept user input? Does it change state? Does it output data?
2. Apply all rules from the Security Rules section below as you write.
3. After writing, self-check against `{baseDir}/references/review-checklist.md`.
4. Report any rule you could not satisfy and explain why.

**Exit:** When all rules are satisfied, or each unsatisfied rule is documented with a justification.

### Mode B — Reviewing Existing Code

1. Use `Glob` to discover all PHP files in scope.
2. Use `Grep` to scan for high-risk patterns: raw `$_GET`/`$_POST` usage, `echo` without escaping, queries without `prepare()`, handlers without `current_user_can()`.
3. Use `Read` to read flagged files and confirm each match in context.
4. For each finding record: file path, line number, violated rule, unsafe pattern, corrected code.
5. Present findings as a structured report grouped by rule category.
6. **Ask the user which findings to fix before making any edits.**
7. Apply fixes one file at a time. Confirm each file after editing.
8. Re-run the Grep patterns from step 2 on each edited file. Confirm no violations remain. If new violations appear, return to step 4.

**Exit:** When the user has accepted or dismissed every finding and all accepted fixes have been verified clean in step 8.

---

## Security Rules

Enforce all rules below. Never write or accept code that violates them without explicitly flagging it.

### 1. SQL Injection Prevention

**NEVER** use string concatenation or interpolation to build SQL queries.

- Use `$wpdb->prepare()` with `%d`, `%f`, `%s` placeholders
- Use `$wpdb->insert()`, `$wpdb->update()`, `$wpdb->delete()` for CRUD — they escape automatically
- For LIKE queries, apply `$wpdb->esc_like()` to the search term before passing to `prepare()`
- Cast integer inputs with `absint()` or `(int)` before use

**Flag immediately:** `"WHERE id = $user_id"`, `"WHERE id = " . $_GET['id']`, any query interpolating `$_GET`/`$_POST` directly.

See `{baseDir}/references/code-examples.md#sql` for correct patterns.

### 2. XSS Prevention

**ALWAYS** escape data at output time using the correct context-specific function.

| Context | Function |
|---|---|
| HTML content | `esc_html()` |
| HTML attribute | `esc_attr()` |
| URL (href/src) | `esc_url()` |
| JavaScript string | `esc_js()` |
| Textarea content | `esc_textarea()` |
| Allow safe HTML | `wp_kses_post()` or `wp_kses($html, $allowed)` |
| JSON in `<script>` | `wp_json_encode()` |

For JS data, prefer `wp_localize_script()` over inline `<script>` blocks.
For i18n strings, use `esc_html__()`, `esc_attr__()`, `esc_html_e()` — not `echo __()`.

**Flag immediately:** `echo $_GET['x']`, `echo $user_input`, `echo get_post_meta(...)` without escaping.

See `{baseDir}/references/code-examples.md#xss` for correct patterns.

### 3. CSRF Prevention (Nonces)

Every state-changing operation MUST include nonce generation and verification.

- **Forms:** `wp_nonce_field($action, $name)` to generate; `wp_verify_nonce($_POST[$name], $action)` to verify
- **GET links:** `wp_nonce_url($url, $action, $name)` to generate; `wp_verify_nonce($_GET[$name], $action)` to verify
- **AJAX:** Pass nonce via `wp_localize_script()`; verify with `check_ajax_referer($action, $name)` or `wp_verify_nonce()`
- **Nonce action names:** Always include a unique ID to scope the nonce, e.g. `'delete_post_' . $post_id`

**Flag immediately:** Any form handler, GET action, or AJAX handler missing nonce verification.

See `{baseDir}/references/code-examples.md#csrf` for correct patterns.

### 4. Access Control

Every handler (admin action, AJAX, REST endpoint, form processor) MUST call `current_user_can()` before executing.

- Use the most specific capability: `delete_post` over `delete_posts`; `edit_post` over `edit_posts`
- REST endpoints must define a real `permission_callback` — **never** `'permission_callback' => '__return_true'` for authenticated actions
- Only register `wp_ajax_nopriv_` for actions that are genuinely public
- Front-end checks are not sufficient — always check on the server

See `{baseDir}/references/code-examples.md#access-control` for common capability reference and patterns.

### 5. Input Sanitization

Sanitize all external input before storing or using it. Use the most specific function available:

- `sanitize_text_field()` — single-line text
- `sanitize_textarea_field()` — multi-line text
- `sanitize_email()` + `is_email()` validation — email
- `esc_url_raw()` + `filter_var($url, FILTER_VALIDATE_URL)` — URL for storage
- `absint()` — positive integer
- `sanitize_key()` — slug/key format
- `wp_kses_post()` — user content that may contain safe HTML

Validate after sanitizing. Reject inputs that fail validation with a clear error.

### 6. File Upload Security

Include ALL checks in order: permission → nonce → error → size → MIME whitelist → extension whitelist → image verification → `wp_handle_upload()`.

For images, also verify with `getimagesize()` and re-render through GD/Imagick to strip embedded payloads.

See `{baseDir}/references/code-examples.md#file-upload` for the complete handler template.

### 7. Sensitive Data Protection

- **API keys:** Never output to frontend HTML or JS. Proxy all external API calls through server-side AJAX handlers.
- **Error messages:** Log details with `error_log()`. Show only generic messages to users. Never expose `$wpdb->last_error`, file paths, or stack traces.
- **Direct access:** Add `if (!defined('ABSPATH')) { exit; }` at the top of every PHP file.
- **Credentials:** Never store in plugin files. Use `wp-config.php` constants or environment variables.

---

## Enforcement

When writing new code: apply all rules above without being asked. Self-check against the checklist before presenting code.

When reviewing existing code: follow Mode B workflow. Report file path, line number, rule violated, unsafe pattern, and corrected code for each finding. Do not report theoretical issues — only flag concrete violations visible in the code.

Reference files:
- `{baseDir}/references/code-examples.md` — complete correct code patterns for each rule
- `{baseDir}/references/review-checklist.md` — structured checklist for pre-commit review
