---
name: wp-audit-trail-builder
description: >
  從零建立 WordPress 未授權存取偵測外掛（Audit Trail Plugin），
  產出可直接安裝使用的完整外掛程式碼。
  觸發情境：「幫我寫 WordPress audit trail 外掛」、「建立登入稽核外掛」、
  「寫一個偵測未授權存取的 WP plugin」、「建立 WordPress 登入紀錄外掛」、
  「幫我做一個記錄登入失敗的外掛」、「WordPress brute force 偵測外掛」。
  不適用於審查已完成的外掛程式碼（那是 wp-audit-trail-review 的工作）。
---

# WordPress 未授權存取偵測外掛 — Builder Skill

從零產出一個完整的 WordPress 外掛，功能為偵測並記錄未授權存取 WP 後台的稽核軌跡。

---

## 外掛規格

### 外掛名稱
`wp-unauthorized-access-tracker`（簡稱 WUAT）

### 函數/類別前綴
所有函數使用 `wuat_` 前綴，避免命名衝突。

### 核心功能

1. **登入事件記錄**：監聽成功登入、登入失敗、登出
2. **暴力破解偵測**：同一 IP 在設定時間內多次失敗時觸發告警
3. **告警通知**：透過 email 通知管理員異常登入行為
4. **稽核紀錄管理**：Admin 頁面查看、篩選、匯出紀錄
5. **自動清理**：WP-Cron 定期清除過期紀錄

---

## 檔案結構

產出以下檔案結構：

```
wp-unauthorized-access-tracker/
├── wp-unauthorized-access-tracker.php   # 主檔案
├── includes/
│   ├── class-wuat-logger.php            # 事件記錄核心類別
│   ├── class-wuat-detector.php          # 暴力破解偵測邏輯
│   ├── class-wuat-alerter.php           # 告警通知
│   ├── class-wuat-admin.php             # Admin 頁面（繼承 WP_List_Table）
│   └── class-wuat-settings.php          # 設定頁面（使用 Settings API）
├── assets/
│   ├── css/
│   │   └── wuat-admin.css               # Admin 樣式
│   └── js/
│       └── wuat-admin.js                # Admin 互動（AJAX 操作）
├── languages/
│   └── wp-unauthorized-access-tracker.pot  # 翻譯範本
├── uninstall.php                        # 反安裝清理
└── readme.txt                           # WordPress.org 格式 readme
```

---

## 建構步驟

### 步驟 1：主檔案 Header 與初始化

主檔案必須包含標準 WordPress plugin header，並在 `plugins_loaded` 時初始化：

```
Plugin Name: WP Unauthorized Access Tracker
Plugin URI:  [醫院內部 URL]
Description: 偵測並記錄未授權存取 WordPress 後台的稽核軌跡
Version:     1.0.0
Author:      [醫院 IT 團隊]
License:     GPL v2 or later
Text Domain: wp-unauthorized-access-tracker
Domain Path: /languages
```

初始化流程：
1. 定義常數（`WUAT_VERSION`, `WUAT_PLUGIN_DIR`, `WUAT_PLUGIN_URL`）
2. `register_activation_hook` → 建立資料表 + 排程
3. `register_deactivation_hook` → 清除排程
4. `plugins_loaded` → 載入 text domain + 初始化各元件

### 步驟 2：資料表設計

使用 `dbDelta()` 建立自訂資料表 `{prefix}wuat_access_log`：

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | bigint(20) unsigned AUTO_INCREMENT | 主鍵 |
| event_type | varchar(50) | login_success / login_failed / logout / lockout |
| user_login | varchar(60) | 嘗試登入的使用者名稱 |
| user_id | bigint(20) unsigned | 對應的 WP user ID（成功時） |
| ip_address | varchar(45) | 用戶端 IP（支援 IPv6） |
| user_agent | text | 瀏覽器 User-Agent |
| event_time | datetime | 事件發生時間 |
| details | text | JSON 格式的額外資訊 |

INDEX：`idx_event_type`, `idx_user_login`, `idx_event_time`, `idx_ip_address`

### 步驟 3：事件記錄（class-wuat-logger.php）

Hook 進 WordPress 認證流程的三個關鍵點：

```
wp_login         → 記錄成功登入（含 user_id, IP, UA, 時間）
wp_login_failed  → 記錄失敗嘗試（含嘗試的 username, IP, UA, error）
wp_logout        → 記錄登出（含 user_id）
```

關鍵實作要點：
- `wp_login` hook 傳入 `$user_login` 和 `$user` (WP_User)，記錄 `$user->ID`
- `wp_login_failed` hook 傳入 `$username` 和 `$error` (WP_Error)，**絕不記錄密碼**
- IP 取得：優先 `$_SERVER['REMOTE_ADDR']`，考慮 proxy header 但要小心偽造
- 所有寫入使用 `$wpdb->insert()` 搭配 format array

### 步驟 4：暴力破解偵測（class-wuat-detector.php）

偵測邏輯：

1. 每次 `wp_login_failed` 時，查詢該 IP 在過去 N 分鐘內的失敗次數
2. 超過閾值時：
   - 記錄一筆 `lockout` 事件
   - 觸發 `wuat_brute_force_detected` 自訂 action（讓其他 plugin 可以 hook）
   - 呼叫 Alerter 發送通知
3. 可選：用 transient 暫存失敗計數，減少 DB 查詢

預設設定值（可在 Settings 頁面調整）：
- 時間窗口：15 分鐘
- 失敗次數閾值：5 次
- 是否啟用 IP 暫時封鎖：預設關閉（因為可能誤鎖合法使用者）

### 步驟 5：告警通知（class-wuat-alerter.php）

告警觸發條件：
- 暴力破解偵測到（同 IP 超過閾值）
- 管理員帳號登入失敗
- 非上班時間的成功登入（可選）

通知方式：
- Email（使用 `wp_mail()`）
- 可擴充 webhook（留 `wuat_send_alert` filter 供二次開發）

Email 內容必須包含：
- 事件類型
- 來源 IP
- 嘗試的使用者名稱
- 時間
- 最近 N 次相關紀錄摘要

### 步驟 6：Admin 管理頁面（class-wuat-admin.php）

使用 `WP_List_Table` 建立稽核紀錄列表頁面：

功能：
- 分頁瀏覽（每頁 20 筆）
- 依 event_type / username / IP / 日期範圍 篩選
- 排序（依時間、事件類型）
- 批次操作：標記已讀、匯出 CSV
- 每筆紀錄顯示：事件圖示、使用者名稱、IP、UA 摘要、時間、details

頁面位置：
- 主選單下方新增 "Audit Trail" 選單項目
- capability 要求：`manage_options`

### 步驟 7：設定頁面（class-wuat-settings.php）

使用 WordPress Settings API 建立設定頁面：

設定項目：
| 設定 | 類型 | 預設值 | 說明 |
|------|------|-------|------|
| 暴力破解閾值 | number | 5 | 幾次失敗觸發告警 |
| 時間窗口 | number | 15 | 分鐘 |
| 告警 Email | email | admin_email | 通知收件人 |
| 紀錄保留天數 | number | 90 | 超過自動清除 |
| IP 匿名化 | checkbox | off | GDPR 合規 |
| 監聽管理員登入 | checkbox | on | 是否額外關注 admin 帳號 |
| 啟用 Email 告警 | checkbox | on | 開關 |

所有 option 存放在單一 `wuat_settings` option（使用 `get_option` / `update_option`）。

### 步驟 8：自動清理排程

使用 WP-Cron 實作：

- Activation 時：`wp_schedule_event( time(), 'daily', 'wuat_cleanup_old_logs' )`
- Callback：刪除超過保留天數的紀錄
- Deactivation 時：`wp_clear_scheduled_hook( 'wuat_cleanup_old_logs' )`

清理查詢使用 `$wpdb->prepare()` + `$wpdb->query()` 搭配 DELETE 語句。

### 步驟 9：國際化（i18n）

所有使用者可見的字串使用 `__()` 或 `_e()`，text domain 為 `wp-unauthorized-access-tracker`。

優先支援：繁體中文 (zh_TW)、英文 (en_US)。

### 步驟 10：Uninstall

`uninstall.php` 執行：
1. 檢查 `WP_UNINSTALL_PLUGIN` 常數
2. 刪除自訂資料表
3. 刪除 `wuat_settings` option
4. 清除所有 transient

---

## 安全性要求（強制）

產出的程式碼必須通過 `wp-audit-trail-review` skill 的所有 35 項檢查。
在建構每個元件時，同步對照 `references/security-patterns.md` 確保合規。

載入 `references/security-patterns.md` 取得每個安全模式的實作範本。

---

## 測試建議

建構完成後，建議的測試步驟：

1. 在本機 WordPress 安裝並啟用外掛
2. 驗證資料表正確建立
3. 嘗試正確/錯誤的登入，確認紀錄寫入
4. 連續 5+ 次錯誤登入，確認告警觸發
5. 檢查 Admin 頁面的篩選和分頁功能
6. 停用外掛，確認排程清除
7. 刪除外掛，確認資料表和 options 清除
8. 使用 `wp-audit-trail-review` skill 進行完整安全性審查
