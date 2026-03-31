<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Template: Email capture gate lock screen
 * Available variables:
 *   $post_id (int)
 *   $config  (array)
 *   $nonce   (string) — wp_create_nonce('wpclp_unlock_email_' . $post_id)
 */
?>
<div class="wpclp-lock-screen wpclp-lock-email" data-post-id="<?php echo esc_attr( $post_id ); ?>">

    <span class="wpclp-lock-icon" aria-hidden="true">&#128274;</span>

    <?php if ( ! empty( $config['message'] ) ) : ?>
        <p class="wpclp-lock-message"><?php echo esc_html( $config['message'] ); ?></p>
    <?php else : ?>
        <p class="wpclp-lock-message"><?php esc_html_e( 'Enter your email address to access this content.', 'wp-content-lock-pro' ); ?></p>
    <?php endif; ?>

    <form method="post" autocomplete="on" novalidate>
        <input
            type="hidden"
            name="nonce"
            value="<?php echo esc_attr( $nonce ); ?>"
        />

        <input
            type="email"
            name="wpclp_email"
            id="wpclp-email-<?php echo esc_attr( $post_id ); ?>"
            placeholder="<?php esc_attr_e( 'your@email.com', 'wp-content-lock-pro' ); ?>"
            autocomplete="email"
            required
            aria-label="<?php esc_attr_e( 'Email address', 'wp-content-lock-pro' ); ?>"
            aria-describedby="wpclp-em-error-<?php echo esc_attr( $post_id ); ?>"
        />

        <button type="submit">
            <?php esc_html_e( 'Access Content', 'wp-content-lock-pro' ); ?>
        </button>

        <div
            class="wpclp-error"
            id="wpclp-em-error-<?php echo esc_attr( $post_id ); ?>"
            role="alert"
            aria-live="polite"
        ></div>

        <p class="wpclp-privacy-note">
            <?php esc_html_e( 'Your email address will be stored to grant you access. We respect your privacy.', 'wp-content-lock-pro' ); ?>
        </p>
    </form>

</div>
