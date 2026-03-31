<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

interface WPCLP_Gate_Interface {
    public function is_unlocked( int $post_id ): bool;
    public function handle_ajax_unlock(): void;
}
