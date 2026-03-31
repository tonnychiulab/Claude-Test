<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Template: Password gate lock screen
 * Available variables (set by WPCLP_Core::render_lock_screen):
 *   $post_id (int)
 *   $config  (array) — from WPCLP_Core::get_lock_config()
 *   $nonce   (string) — wp_create_nonce('wpclp_unlock_password_' . $post_id)
 */
?>
<div class="wpclp-lock-screen wpclp-lock-password" data-post-id="<?php echo esc_attr( $post_id ); ?>">

    <span class="wpclp-lock-icon" aria-hidden="true">&#128274;</span>

    <?php if ( ! empty( $config['message'] ) ) : ?>
        <p class="wpclp-lock-message"><?php echo esc_html( $config['message'] ); ?></p>
    <?php else : ?>
        <p class="wpclp-lock-message"><?php esc_html_e( 'This content is password protected.', 'wp-content-lock-pro' ); ?></p>
    <?php endif; ?>

    <form method="post" autocomplete="on" novalidate>
        <input
            type="hidden"
            name="nonce"
            value="<?php echo esc_attr( $nonce ); ?>"
        />

        <input
            type="password"
            name="wpclp_password"
            id="wpclp-password-<?php echo esc_attr( $post_id ); ?>"
            placeholder="<?php esc_attr_e( 'Enter password&hellip;', 'wp-content-lock-pro' ); ?>"
            autocomplete="current-password"
            required
            aria-label="<?php esc_attr_e( 'Password', 'wp-content-lock-pro' ); ?>"
            aria-describedby="wpclp-pw-error-<?php echo esc_attr( $post_id ); ?>"
        />

        <button type="submit">
            <?php esc_html_e( 'Unlock', 'wp-content-lock-pro' ); ?>
        </button>

        <div
            class="wpclp-error"
            id="wpclp-pw-error-<?php echo esc_attr( $post_id ); ?>"
            role="alert"
            aria-live="polite"
        ></div>
    </form>

</div>
