<?php
/**
 * PHPUnit bootstrap for WP Content Lock Pro.
 *
 * Sets up Brain\Monkey, WordPress constant stubs, WordPress function stubs,
 * and loads plugin source files so tests run without a full WP installation.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ── Brain\Monkey setup ────────────────────────────────────────────────────────
Brain\Monkey\setUp();

// ── WordPress constants ───────────────────────────────────────────────────────
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'COOKIEPATH' ) ) {
    define( 'COOKIEPATH', '/' );
}
if ( ! defined( 'COOKIE_DOMAIN' ) ) {
    define( 'COOKIE_DOMAIN', '' );
}

// ── WordPress function stubs ──────────────────────────────────────────────────
if ( ! function_exists( 'absint' ) ) {
    function absint( $val ) {
        return (int) abs( (int) $val );
    }
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key, $single = false ) {
        return '';
    }
}

if ( ! function_exists( 'wp_hash' ) ) {
    function wp_hash( $data ) {
        return hash( 'sha256', $data . 'test-secret' );
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) {
        return date( 'Y-m-d H:i:s' );
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        return filter_var( $email, FILTER_SANITIZE_EMAIL );
    }
}

if ( ! function_exists( 'is_email' ) ) {
    function is_email( $email ) {
        return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( strip_tags( $str ) );
    }
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null ) {
        throw new \RuntimeException( 'json_success: ' . json_encode( $data ) );
    }
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null ) {
        throw new \RuntimeException( 'json_error: ' . json_encode( $data ) );
    }
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
        return true;
    }
}

if ( ! function_exists( 'is_ssl' ) ) {
    function is_ssl() {
        return false;
    }
}

if ( ! function_exists( 'update_post_meta' ) ) {
    function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
    function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {}
}

if ( ! function_exists( 'add_meta_box' ) ) {
    function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null ) {}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, $action = -1 ) {}
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {}
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '', $title = '', $args = array() ) {}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
    function wp_clear_scheduled_hook( $hook, $args = array() ) {}
}

// ── WPCLP meta key constants ──────────────────────────────────────────────────
if ( ! defined( 'WPCLP_META_ENABLED' ) ) {
    define( 'WPCLP_META_ENABLED', '_wpclp_enabled' );
}
if ( ! defined( 'WPCLP_META_GATE_TYPE' ) ) {
    define( 'WPCLP_META_GATE_TYPE', '_wpclp_gate_type' );
}
if ( ! defined( 'WPCLP_META_PASSWORD' ) ) {
    define( 'WPCLP_META_PASSWORD', '_wpclp_password' );
}
if ( ! defined( 'WPCLP_META_MESSAGE' ) ) {
    define( 'WPCLP_META_MESSAGE', '_wpclp_message' );
}
if ( ! defined( 'WPCLP_META_PARTIAL_REVEAL' ) ) {
    define( 'WPCLP_META_PARTIAL_REVEAL', '_wpclp_partial_reveal' );
}
if ( ! defined( 'WPCLP_VERSION' ) ) {
    define( 'WPCLP_VERSION', '1.0.0' );
}
if ( ! defined( 'WPCLP_PLUGIN_DIR' ) ) {
    define( 'WPCLP_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WPCLP_PLUGIN_URL' ) ) {
    define( 'WPCLP_PLUGIN_URL', 'http://localhost/wp-content/plugins/wp-content-lock-pro/' );
}

// ── Mock $wpdb global ─────────────────────────────────────────────────────────
if ( ! isset( $GLOBALS['wpdb'] ) ) {
    $GLOBALS['wpdb'] = new class {
        public string $prefix     = 'wp_';
        public string $posts      = 'wp_posts';
        public string $last_error = '';

        public function prepare( string $query, ...$args ): string {
            // Basic placeholder substitution for testing purposes.
            $i = 0;
            return preg_replace_callback(
                '/(%d|%s|%f)/',
                function ( $match ) use ( $args, &$i ) {
                    $val = $args[ $i++ ] ?? '';
                    return is_numeric( $val ) ? (string) $val : "'" . addslashes( (string) $val ) . "'";
                },
                $query
            );
        }

        public function get_charset_collate(): string {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        public function query( string $sql ) {
            return false;
        }

        public function get_var( ?string $query = null ) {
            return null;
        }

        public function get_results( ?string $query = null, string $output = 'OBJECT' ): ?array {
            return null;
        }

        public function insert( string $table, array $data, $format = null ) {
            return false;
        }

        public function delete( string $table, array $where, $where_format = null ) {
            return false;
        }
    };
}

// ── WPCLP_TABLE constant (depends on $wpdb->prefix) ──────────────────────────
if ( ! defined( 'WPCLP_TABLE' ) ) {
    define( 'WPCLP_TABLE', $GLOBALS['wpdb']->prefix . 'wpclp_emails' );
}

// ── Load plugin source files ──────────────────────────────────────────────────
require_once dirname( __DIR__ ) . '/includes/interface-wpclp-gate.php';
require_once dirname( __DIR__ ) . '/includes/class-wpclp-db.php';
require_once dirname( __DIR__ ) . '/includes/class-wpclp-gate-password.php';
require_once dirname( __DIR__ ) . '/includes/class-wpclp-gate-email.php';
require_once dirname( __DIR__ ) . '/includes/class-wpclp-core.php';
