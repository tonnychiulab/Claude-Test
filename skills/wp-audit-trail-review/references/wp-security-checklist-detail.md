# WordPress Audit Trail 外掛 — 安全性檢查詳細範例

此文件提供每個檢查項目的正確寫法和常見錯誤寫法。
審查時對照此文件確認程式碼品質。

---

## SQL Injection 防護 — 程式碼範例

### ✅ 正確：使用 $wpdb->prepare()

```php
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE user_login = %s AND event_time > %s",
        $username,
        $since_date
    )
);
```

### ❌ 錯誤：直接拼接

```php
// 嚴重漏洞！攻擊者可透過 $username 注入 SQL
$results = $wpdb->get_results(
    "SELECT * FROM {$table_name} WHERE user_login = '{$username}'"
);
```

### ✅ 正確：使用 $wpdb->insert()

```php
$wpdb->insert(
    $table_name,
    array(
        'event_type'  => sanitize_text_field( $event_type ),
        'user_login'  => sanitize_user( $username ),
        'ip_address'  => sanitize_text_field( $ip ),
        'user_agent'  => sanitize_text_field( $user_agent ),
        'event_time'  => current_time( 'mysql' ),
    ),
    array( '%s', '%s', '%s', '%s', '%s' )
);
```

---

## XSS 防護 — 程式碼範例

### ✅ 正確：Escape 所有輸出

```php
<td><?php echo esc_html( $log->user_login ); ?></td>
<td><?php echo esc_html( $log->ip_address ); ?></td>
<td><?php echo esc_attr( $log->event_type ); ?></td>
```

### ❌ 錯誤：直接 echo

```php
// XSS 漏洞！如果 user_login 被注入 <script> 標籤
<td><?php echo $log->user_login; ?></td>
```

---

## Nonce 驗證 — 程式碼範例

### ✅ 正確：表單 + 驗證

```php
// 表單中加入 nonce
wp_nonce_field( 'wuat_clear_logs', 'wuat_nonce' );

// 處理時驗證
if ( ! isset( $_POST['wuat_nonce'] ) ||
     ! wp_verify_nonce(
         sanitize_text_field( wp_unslash( $_POST['wuat_nonce'] ) ),
         'wuat_clear_logs'
     )
) {
    wp_die( __( 'Security check failed.', 'wp-unauthorized-access-tracker' ) );
}
```

### ✅ 正確：AJAX nonce

```php
// JS 端
jQuery.post( ajaxurl, {
    action: 'wuat_dismiss_alert',
    nonce: wuat_ajax.nonce,
    alert_id: id
});

// PHP 端
add_action( 'wp_ajax_wuat_dismiss_alert', 'wuat_handle_dismiss' );
function wuat_handle_dismiss() {
    check_ajax_referer( 'wuat_ajax_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
    }
    // ... 處理邏輯
}
```

---

## 權限控制 — 程式碼範例

### ✅ 正確：Admin 頁面權限檢查

```php
function wuat_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'wp-unauthorized-access-tracker' ) );
    }
    // ... 頁面內容
}

add_menu_page(
    __( 'Access Audit Trail', 'wp-unauthorized-access-tracker' ),
    __( 'Audit Trail', 'wp-unauthorized-access-tracker' ),
    'manage_options',  // capability 限制
    'wuat-audit-trail',
    'wuat_admin_page'
);
```

---

## 敏感資料處理 — 程式碼範例

### ✅ 正確：wp_login_failed hook 不記錄密碼

```php
// 只有 $username 和 $error，不碰密碼
add_action( 'wp_login_failed', 'wuat_log_failed_login', 10, 2 );
function wuat_log_failed_login( $username, $error ) {
    wuat_insert_log( 'login_failed', $username );
    // 注意：此 hook 的參數不包含密碼，這是 WordPress 的設計
    // 絕對不要試圖從 $_POST['pwd'] 取得密碼來記錄
}
```

### ✅ 正確：IP 遮蔽選項

```php
function wuat_get_client_ip( $anonymize = false ) {
    $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );

    if ( $anonymize ) {
        // IPv4: 192.168.1.100 → 192.168.1.0
        // IPv6: 類似處理
        $ip = preg_replace( '/\.\d+$/', '.0', $ip );
    }

    return $ip;
}
```

---

## 效能 — 資料表設計範例

### ✅ 正確：含 INDEX 的表結構

```php
function wuat_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'access_audit_trail';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_type varchar(50) NOT NULL,
        user_login varchar(60) NOT NULL DEFAULT '',
        user_id bigint(20) unsigned DEFAULT 0,
        ip_address varchar(45) NOT NULL DEFAULT '',
        user_agent text NOT NULL,
        event_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        details text,
        PRIMARY KEY  (id),
        KEY idx_event_type (event_type),
        KEY idx_user_login (user_login),
        KEY idx_event_time (event_time),
        KEY idx_ip_address (ip_address)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
```

---

## Activation / Deactivation / Uninstall

### ✅ 正確：完整的生命週期管理

```php
// Activation: 建表 + 排程
register_activation_hook( __FILE__, 'wuat_activate' );
function wuat_activate() {
    wuat_create_table();
    if ( ! wp_next_scheduled( 'wuat_cleanup_old_logs' ) ) {
        wp_schedule_event( time(), 'daily', 'wuat_cleanup_old_logs' );
    }
}

// Deactivation: 清排程
register_deactivation_hook( __FILE__, 'wuat_deactivate' );
function wuat_deactivate() {
    wp_clear_scheduled_hook( 'wuat_cleanup_old_logs' );
}

// Uninstall: 刪表 + 刪 options（放在 uninstall.php）
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}access_audit_trail" );
delete_option( 'wuat_settings' );
```
