<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Template: Meta Box view
 * Available variables (set by WPCLP_Meta_Box::render):
 *   $post           WP_Post
 *   $enabled        bool
 *   $gate_type      string  'password'|'email'
 *   $message        string  sanitized
 *   $partial_reveal int
 *
 * WORKER: Render the meta box form. Rules:
 * - wp_nonce_field('wpclp_meta_box', 'wpclp_meta_nonce') — already called in render().
 * - All values output via esc_attr() / esc_html() / checked() / selected().
 * - Password field: placeholder only — never output the stored hash.
 * - Gate type select triggers JS show/hide of password field.
 */

$gate_types = WPCLP_Core::get_gate_types();
?>
<div class="wpclp-meta-box">

    <div class="wpclp-field wpclp-field--checkbox">
        <label>
            <input
                type="checkbox"
                name="wpclp_enabled"
                id="wpclp-enabled"
                value="1"
                <?php checked( $enabled, true ); ?>
            />
            <?php esc_html_e( 'Enable content lock on this post', 'wp-content-lock-pro' ); ?>
        </label>
    </div>

    <div class="wpclp-field">
        <label for="wpclp-gate-type">
            <?php esc_html_e( 'Gate Type', 'wp-content-lock-pro' ); ?>
        </label>
        <select name="wpclp_gate_type" id="wpclp-gate-type">
            <?php foreach ( $gate_types as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $gate_type, $key ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="wpclp-field" id="wpclp-password-row" style="<?php echo ( 'password' === $gate_type ) ? '' : 'display:none;'; ?>">
        <label for="wpclp-password">
            <?php esc_html_e( 'Password', 'wp-content-lock-pro' ); ?>
        </label>
        <input
            type="password"
            name="wpclp_password"
            id="wpclp-password"
            value=""
            placeholder="<?php esc_attr_e( 'Enter new password to change', 'wp-content-lock-pro' ); ?>"
            autocomplete="new-password"
        />
        <p class="description">
            <?php esc_html_e( 'Leave blank to keep the existing password.', 'wp-content-lock-pro' ); ?>
        </p>
    </div>

    <div class="wpclp-field">
        <label for="wpclp-message">
            <?php esc_html_e( 'Gate Message', 'wp-content-lock-pro' ); ?>
        </label>
        <textarea
            name="wpclp_message"
            id="wpclp-message"
            rows="3"
        ><?php echo esc_textarea( $message ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Message shown to visitors before they unlock the content.', 'wp-content-lock-pro' ); ?>
        </p>
    </div>

    <div class="wpclp-field">
        <label for="wpclp-partial-reveal">
            <?php esc_html_e( 'Partial Reveal (words)', 'wp-content-lock-pro' ); ?>
        </label>
        <input
            type="number"
            name="wpclp_partial_reveal"
            id="wpclp-partial-reveal"
            value="<?php echo esc_attr( $partial_reveal ); ?>"
            min="0"
            step="1"
        />
        <p class="description">
            <?php esc_html_e( 'Number of words to show before the gate. Set to 0 to hide all content.', 'wp-content-lock-pro' ); ?>
        </p>
    </div>

</div>
