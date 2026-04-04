# WordPress 認證相關 Hooks 參考

此文件列出 audit trail 外掛會用到的所有 WordPress hooks。
建構時對照此文件確保 hook 的使用方式正確。

---

## 核心認證 Hooks

### wp_login (action)

觸發時機：使用者成功登入後
參數：`$user_login` (string), `$user` (WP_User)
用途：記錄成功登入事件

```php
add_action( 'wp_login', 'wuat_log_success', 10, 2 );
function wuat_log_success( $user_login, $user ) {
    // $user->ID, $user->user_login, $user->roles
}
```

注意事項：
- 此 hook 在 `wp_set_auth_cookie()` 之後觸發
- WooCommerce 的註冊登入可能不觸發此 hook（需額外處理）
- `$user` 是完整的 WP_User 物件，可取得角色等資訊

---

### wp_login_failed (action)

觸發時機：登入失敗後
參數：`$username` (string), `$error` (WP_Error)
用途：記錄失敗嘗試 + 暴力破解偵測

```php
add_action( 'wp_login_failed', 'wuat_log_failure', 10, 2 );
function wuat_log_failure( $username, $error ) {
    // $username 是使用者嘗試的名稱（可能不存在於系統中）
    // $error->get_error_code() 可能是 'invalid_username', 'incorrect_password' 等
    // ⚠️ 此 hook 不傳入密碼，這是 WordPress 的安全設計
}
```

注意事項：
- `$username` 可能是 email 也可能是 username
- 不要用 `$error->get_error_code()` 來區分「帳號不存在」和「密碼錯誤」回應給前端
- 第二個參數 `$error` 是 WP 5.4+ 才加入的

---

### wp_logout (action)

觸發時機：使用者登出時
參數：`$user_id` (int)
用途：記錄登出事件

```php
add_action( 'wp_logout', 'wuat_log_logout', 10, 1 );
function wuat_log_logout( $user_id ) {
    $user = get_userdata( $user_id );
    // 在 cookie 清除前取得 user 資訊
}
```

注意事項：
- `$user_id` 參數是 WP 5.5+ 才加入的
- 向下相容寫法：`$user_id = $user_id ?: get_current_user_id();`

---

### authenticate (filter)

觸發時機：驗證使用者帳密時（在 wp_login / wp_login_failed 之前）
參數：`$user` (null|WP_User|WP_Error), `$username` (string), `$password` (string)
用途：可用於額外驗證或 IP 封鎖

```php
add_filter( 'authenticate', 'wuat_check_ip_lockout', 30, 3 );
function wuat_check_ip_lockout( $user, $username, $password ) {
    // ⚠️ 此 filter 可取得明文密碼，但 audit trail 絕不記錄密碼
    // 用途：如果 IP 已被鎖定，直接回傳 WP_Error 阻止登入
    if ( wuat_is_ip_locked( $_SERVER['REMOTE_ADDR'] ) ) {
        return new WP_Error(
            'wuat_ip_locked',
            __( 'Too many failed attempts. Please try again later.', 'wp-unauthorized-access-tracker' )
        );
    }
    return $user;
}
```

注意事項：
- priority 設為 30（在 WordPress 預設的 20 之後）
- 如果 IP 封鎖功能啟用，需在此 filter 攔截
- **絕對不要記錄 `$password` 參數**

---

## Admin 相關 Hooks

### admin_menu (action)
用途：註冊 Admin 頁面

### admin_enqueue_scripts (action)
用途：載入 Admin CSS/JS，參數 `$hook` 用於判斷當前頁面

### admin_init (action)
用途：註冊 Settings API 的 settings/sections/fields

---

## 排程相關

### wuat_cleanup_old_logs (custom action)
自訂 WP-Cron hook，用於定期清理過期紀錄。

```php
add_action( 'wuat_cleanup_old_logs', 'wuat_do_cleanup' );
function wuat_do_cleanup() {
    $settings = get_option( 'wuat_settings', array() );
    $days     = absint( $settings['retention_days'] ?? 90 );
    $cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

    global $wpdb;
    $table = $wpdb->prefix . 'wuat_access_log';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table} WHERE event_time < %s",
            $cutoff
        )
    );
}
```

---

## 自訂 Hooks（供其他外掛擴充）

外掛應提供以下自訂 hooks，讓其他外掛可以擴充功能：

```php
// Action: 偵測到暴力破解時觸發
do_action( 'wuat_brute_force_detected', $ip, $fail_count, $username );

// Action: 記錄事件前觸發（可用於跳過特定事件）
$should_log = apply_filters( 'wuat_should_log_event', true, $event_type, $username );

// Action: 告警發送前觸發（可用於加入額外通知管道）
do_action( 'wuat_before_alert', $event_type, $alert_data );

// Filter: 自訂告警 email 內容
$email_body = apply_filters( 'wuat_alert_email_body', $body, $event_type, $alert_data );
```
