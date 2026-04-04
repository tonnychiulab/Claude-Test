# WordPress Audit Trail 外掛 — 安全性程式碼模式

此文件包含建構外掛時必須使用的安全性模式。
每個模式都附有完整的實作範本，直接套用即可。

---

## 模式 1：安全的資料庫寫入

每次記錄事件時使用此模式：

```php
private function insert_log( $event_type, $user_login, $user_id = 0, $details = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'wuat_access_log';

    $wpdb->insert(
        $table,
        array(
            'event_type'  => sanitize_key( $event_type ),
            'user_login'  => sanitize_user( $user_login ),
            'user_id'     => absint( $user_id ),
            'ip_address'  => $this->get_client_ip(),
            'user_agent'  => sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500 ) ),
            'event_time'  => current_time( 'mysql' ),
            'details'     => sanitize_text_field( $details ),
        ),
        array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
    );
}
```

關鍵點：
- `sanitize_key()` 限制 event_type 只有小寫英數和底線
- `sanitize_user()` 處理使用者名稱
- `absint()` 確保 user_id 是正整數
- User-Agent 截斷到 500 字元防止超長字串攻擊
- 使用 format array `%s` / `%d` 明確指定型別

---

## 模式 2：安全的資料庫查詢

Admin 頁面查詢紀錄時使用此模式：

```php
private function get_logs( $args = array() ) {
    global $wpdb;
    $table = $wpdb->prefix . 'wuat_access_log';

    $defaults = array(
        'event_type' => '',
        'user_login' => '',
        'ip_address' => '',
        'date_from'  => '',
        'date_to'    => '',
        'per_page'   => 20,
        'offset'     => 0,
        'orderby'    => 'event_time',
        'order'      => 'DESC',
    );
    $args = wp_parse_args( $args, $defaults );

    // 白名單驗證 orderby 和 order
    $allowed_orderby = array( 'event_time', 'event_type', 'user_login', 'ip_address' );
    $orderby = in_array( $args['orderby'], $allowed_orderby, true )
        ? $args['orderby']
        : 'event_time';
    $order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

    $where = array( '1=1' );
    $values = array();

    if ( ! empty( $args['event_type'] ) ) {
        $where[]  = 'event_type = %s';
        $values[] = sanitize_key( $args['event_type'] );
    }
    if ( ! empty( $args['user_login'] ) ) {
        $where[]  = 'user_login = %s';
        $values[] = sanitize_user( $args['user_login'] );
    }
    if ( ! empty( $args['ip_address'] ) ) {
        $where[]  = 'ip_address = %s';
        $values[] = sanitize_text_field( $args['ip_address'] );
    }
    if ( ! empty( $args['date_from'] ) ) {
        $where[]  = 'event_time >= %s';
        $values[] = sanitize_text_field( $args['date_from'] );
    }
    if ( ! empty( $args['date_to'] ) ) {
        $where[]  = 'event_time <= %s';
        $values[] = sanitize_text_field( $args['date_to'] );
    }

    $where_clause = implode( ' AND ', $where );
    $per_page = absint( $args['per_page'] );
    $offset   = absint( $args['offset'] );

    $sql = "SELECT * FROM {$table} WHERE {$where_clause}
            ORDER BY {$orderby} {$order}
            LIMIT {$per_page} OFFSET {$offset}";

    if ( ! empty( $values ) ) {
        $sql = $wpdb->prepare( $sql, $values );
    }

    return $wpdb->get_results( $sql );
}
```

關鍵點：
- `orderby` 和 `order` 使用白名單驗證，不直接用使用者輸入
- 動態 WHERE 條件使用 prepare 的 placeholder
- `per_page` 和 `offset` 使用 `absint()` 強制正整數

---

## 模式 3：IP 取得與匿名化

```php
private function get_client_ip( $anonymize = null ) {
    if ( null === $anonymize ) {
        $settings  = get_option( 'wuat_settings', array() );
        $anonymize = ! empty( $settings['ip_anonymize'] );
    }

    // 只信任 REMOTE_ADDR，proxy header 容易偽造
    $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );

    // 驗證 IP 格式
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        $ip = '0.0.0.0';
    }

    if ( $anonymize ) {
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            // 192.168.1.100 → 192.168.1.0
            $ip = preg_replace( '/\.\d+$/', '.0', $ip );
        } elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            // 遮蔽最後 80 bits
            $ip = inet_ntop( substr( inet_pton( $ip ), 0, 6 ) . str_repeat( "\0", 10 ) );
        }
    }

    return $ip;
}
```

關鍵點：
- 不使用 `HTTP_X_FORWARDED_FOR` 等可偽造 header 作為主要來源
- `filter_var()` 驗證 IP 格式
- 匿名化選項支援 IPv4 和 IPv6

---

## 模式 4：暴力破解偵測

```php
public function check_brute_force( $ip ) {
    $settings  = get_option( 'wuat_settings', array() );
    $threshold = absint( $settings['brute_force_threshold'] ?? 5 );
    $window    = absint( $settings['brute_force_window'] ?? 15 );

    // 先查 transient 快取
    $cache_key    = 'wuat_fail_count_' . md5( $ip );
    $cached_count = get_transient( $cache_key );

    if ( false !== $cached_count && intval( $cached_count ) >= $threshold ) {
        return true; // 已知超過閾值
    }

    // 查 DB 確認
    global $wpdb;
    $table = $wpdb->prefix . 'wuat_access_log';
    $since = gmdate( 'Y-m-d H:i:s', time() - ( $window * MINUTE_IN_SECONDS ) );

    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE event_type = 'login_failed'
               AND ip_address = %s
               AND event_time > %s",
            $ip,
            $since
        )
    );

    $count = intval( $count );

    // 更新 transient 快取（存活時間 = window）
    set_transient( $cache_key, $count, $window * MINUTE_IN_SECONDS );

    return $count >= $threshold;
}
```

---

## 模式 5：AJAX 安全處理

```php
// 註冊 AJAX action（僅限已登入使用者）
add_action( 'wp_ajax_wuat_export_csv', array( $this, 'ajax_export_csv' ) );

public function ajax_export_csv() {
    // 1. 驗證 nonce
    check_ajax_referer( 'wuat_admin_nonce', 'nonce' );

    // 2. 驗證權限
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            array( 'message' => __( 'Permission denied.', 'wp-unauthorized-access-tracker' ) ),
            403
        );
    }

    // 3. Sanitize 輸入
    $event_type = isset( $_POST['event_type'] )
        ? sanitize_key( $_POST['event_type'] )
        : '';

    // 4. 處理邏輯
    $logs = $this->get_logs( array( 'event_type' => $event_type, 'per_page' => 10000 ) );

    // 5. 回應
    wp_send_json_success( array( 'data' => $logs ) );
}
```

---

## 模式 6：Enqueue 資源

```php
public function enqueue_admin_assets( $hook ) {
    // 只在自己的頁面載入
    if ( 'toplevel_page_wuat-audit-trail' !== $hook
      && 'audit-trail_page_wuat-settings' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'wuat-admin-css',
        WUAT_PLUGIN_URL . 'assets/css/wuat-admin.css',
        array(),
        WUAT_VERSION
    );

    wp_enqueue_script(
        'wuat-admin-js',
        WUAT_PLUGIN_URL . 'assets/js/wuat-admin.js',
        array( 'jquery' ),
        WUAT_VERSION,
        true
    );

    wp_localize_script( 'wuat-admin-js', 'wuat_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'wuat_admin_nonce' ),
    ) );
}
```

關鍵點：
- 用 `$hook` 判斷只在自己的頁面載入，不汙染其他 admin 頁面
- 使用 `WUAT_VERSION` 作為版本號自動清快取
- `wp_localize_script()` 安全傳遞 nonce 和 URL 給 JS
