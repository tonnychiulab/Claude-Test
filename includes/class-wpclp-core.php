<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WPCLP_Core — Content locking engine
 *
 * Hooks into 'the_content' filter and replaces content with a lock screen
 * when a post is locked and the visitor has not yet unlocked it.
 *
 * Calls WPCLP_Gate_Password or WPCLP_Gate_Email to delegate gate logic.
 */
class WPCLP_Core {

    /** @var WPCLP_Gate_Password */
    private $gate_password;

    /** @var WPCLP_Gate_Email */
    private $gate_email;

    public function __construct() {
        $this->gate_password = new WPCLP_Gate_Password();
        $this->gate_email    = new WPCLP_Gate_Email();
    }

    /**
     * Register all hooks.
     */
    public function init(): void {
        add_filter( 'the_content', array( $this, 'filter_content' ), 99 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // Password gate AJAX — both logged-in and nopriv (gates are public).
        add_action( 'wp_ajax_wpclp_unlock_password',        array( $this->gate_password, 'handle_ajax_unlock' ) );
        add_action( 'wp_ajax_nopriv_wpclp_unlock_password', array( $this->gate_password, 'handle_ajax_unlock' ) );

        // Email gate AJAX — both logged-in and nopriv.
        add_action( 'wp_ajax_wpclp_unlock_email',        array( $this->gate_email, 'handle_ajax_unlock' ) );
        add_action( 'wp_ajax_nopriv_wpclp_unlock_email', array( $this->gate_email, 'handle_ajax_unlock' ) );
    }

    /**
     * Main content filter. Returns locked content or original content.
     *
     * @param string $content
     * @return string
     */
    public function filter_content( string $content ): string {
        // Skip contexts where locking does not apply.
        if (
            ! is_singular() ||
            is_admin() ||
            ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
            is_feed() ||
            is_preview()
        ) {
            return $content;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return $content;
        }

        $config = $this->get_lock_config( $post_id );

        if ( ! $config['enabled'] ) {
            return $content;
        }

        if ( $this->is_unlocked( $post_id, $config ) ) {
            return $content;
        }

        // Build the output: optional partial reveal + lock screen.
        $output = '';

        $word_count = absint( $config['partial_reveal'] );
        if ( $word_count > 0 ) {
            $output .= '<div class="wpclp-partial-content">';
            $output .= wpautop( $this->get_partial_content( $content, $word_count ) );
            $output .= '</div>';
        }

        $output .= $this->render_lock_screen( $post_id, $config );

        return $output;
    }

    /**
     * Enqueue frontend CSS/JS only on singular locked posts.
     */
    public function enqueue_frontend_assets(): void {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }

        $config = $this->get_lock_config( $post_id );

        if ( ! $config['enabled'] || $this->is_unlocked( $post_id, $config ) ) {
            return;
        }

        wp_enqueue_style(
            'wpclp-frontend',
            WPCLP_PLUGIN_URL . 'assets/frontend.css',
            array(),
            WPCLP_VERSION
        );

        wp_enqueue_script(
            'wpclp-frontend',
            WPCLP_PLUGIN_URL . 'assets/frontend.js',
            array( 'jquery' ),
            WPCLP_VERSION,
            true
        );

        $gate_type = $config['gate_type'];
        $nonce     = wp_create_nonce( 'wpclp_unlock_' . $gate_type . '_' . $post_id );

        // Pass data via wp_localize_script — no inline data in JS files.
        wp_localize_script(
            'wpclp-frontend',
            'wpclpData',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'posts'   => array(
                    $post_id => array(
                        'postId'   => $post_id,
                        'gateType' => $gate_type,
                        'nonce'    => $nonce,
                    ),
                ),
            )
        );
    }

    /**
     * Return the lock configuration for a post.
     *
     * @param int $post_id
     * @return array{
     *   enabled: bool,
     *   gate_type: string,
     *   message: string,
     *   partial_reveal: int,
     *   password: string
     * }
     */
    public function get_lock_config( int $post_id ): array {
        $enabled        = (bool) get_post_meta( $post_id, WPCLP_META_ENABLED, true );
        $gate_type      = get_post_meta( $post_id, WPCLP_META_GATE_TYPE, true );
        $message        = get_post_meta( $post_id, WPCLP_META_MESSAGE, true );
        $partial_reveal = get_post_meta( $post_id, WPCLP_META_PARTIAL_REVEAL, true );
        $password       = get_post_meta( $post_id, WPCLP_META_PASSWORD, true );

        // Ensure gate_type is a recognised value; default to 'password'.
        $allowed_gate_types = self::get_gate_types();
        if ( ! in_array( $gate_type, $allowed_gate_types, true ) ) {
            $gate_type = 'password';
        }

        return array(
            'enabled'        => $enabled,
            'gate_type'      => (string) $gate_type,
            'message'        => (string) $message,
            'partial_reveal' => absint( $partial_reveal ),
            'password'       => (string) $password,
        );
    }

    /**
     * Check whether the current visitor has already unlocked $post_id.
     * Delegates to the correct gate based on config['gate_type'].
     *
     * @param int   $post_id
     * @param array $config From get_lock_config()
     * @return bool
     */
    private function is_unlocked( int $post_id, array $config ): bool {
        if ( 'email' === $config['gate_type'] ) {
            return $this->gate_email->is_unlocked( $post_id );
        }

        return $this->gate_password->is_unlocked( $post_id );
    }

    /**
     * Render the lock screen HTML for a given post.
     * Loads template from templates/lock-screen-{gate_type}.php.
     *
     * @param int   $post_id
     * @param array $config
     * @return string Escaped HTML
     */
    private function render_lock_screen( int $post_id, array $config ): string {
        $gate_type     = $config['gate_type'];
        $template_file = WPCLP_PLUGIN_DIR . 'templates/lock-screen-' . $gate_type . '.php';

        if ( ! file_exists( $template_file ) ) {
            return '';
        }

        $nonce = wp_create_nonce( 'wpclp_unlock_' . $gate_type . '_' . $post_id );

        ob_start();
        // Extract variables into local template scope.
        // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
        require $template_file;
        return ob_get_clean();
    }

    /**
     * Extract the first $word_count words from $content (strips tags first).
     *
     * @param string $content Raw post content
     * @param int    $word_count
     * @return string Plain-text excerpt
     */
    private function get_partial_content( string $content, int $word_count ): string {
        $plain = strip_tags( $content );
        return wp_trim_words( $plain, $word_count, '' );
    }

    /**
     * Return allowed gate type slugs.
     *
     * @return string[]
     */
    public static function get_gate_types(): array {
        return array( 'password', 'email' );
    }
}
