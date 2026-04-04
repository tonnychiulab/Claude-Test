---
name: wp-audit-trail-review
description: >
  審查 WordPress 未授權存取偵測外掛的程式碼品質與安全性。
  當使用者完成外掛程式碼後，使用此 skill 進行安全性與合規性審查。
  觸發情境：「review 我的外掛」、「檢查這個 plugin 的安全性」、
  「審查 audit trail 外掛」、「這個外掛有沒有漏洞」、
  「幫我 code review WordPress plugin」。
  也適用於任何 WordPress 外掛的安全性審查需求。
  不適用於從零開始建立外掛（那是 wp-audit-trail-builder 的工作）。
---

> ⚠️ **此 Skill 由 Meta Skill Factory 自動產出**
> - 來源文件：WordPress Plugin Handbook Security 章節、WordPress Coding Standards、OWASP Top 10
> - 來源版本：WordPress 6.x / 2026-04
> - 產出日期：2026-04-03
> - 審核狀態：🔴 待審核
> - 審核人：[待填]
> - 核可日期：[待填]
>
> **未經人類審核核可前，此 skill 不得用於正式作業。**

# WordPress 未授權存取偵測外掛 — 程式碼審查 Skill

審查 WordPress audit trail 外掛的安全性、效能、與合規性。
確保外掛本身不會成為新的攻擊面。

---

## 適用範圍

- 適用於：WordPress 外掛 PHP 程式碼的安全性審查，特別是涉及登入/認證/稽核紀錄的外掛
- 不適用於：WordPress 佈景主題審查、一般 PHP 專案審查、外掛功能規劃

---

## 審查流程

### 第一步：檔案結構檢查

確認外掛具備正確的 WordPress 外掛結構：

| 檢查項目 | 合格標準 | 依據 |
|---------|---------|------|
| 主檔案 header | 包含 Plugin Name, Version, Description, Author, License | Plugin Handbook: Header Requirements |
| 檔案組織 | PHP 邏輯與 assets（CSS/JS）分離 | WP Coding Standards |
| Text Domain | 有定義且與外掛 slug 一致 | Plugin Handbook: Internationalization |
| Uninstall 處理 | 有 uninstall.php 或 register_uninstall_hook | Plugin Handbook: Uninstall |

### 第二步：安全性審查（最重要）

因為 audit trail 外掛本身處理敏感的認證資料，安全性審查是最優先的。

#### 2.1 SQL Injection 防護

| # | 檢查項目 | 合格標準 | 依據 |
|---|---------|---------|------|
| 1 | 所有 `$wpdb` 查詢都使用 `$wpdb->prepare()` | 沒有任何直接拼接 SQL 字串的查詢 | Plugin Handbook: Protecting Against SQL Injection |
| 2 | 使用 `$wpdb->insert()` / `$wpdb->update()` 進行資料寫入 | 避免手動拼接 INSERT/UPDATE 語句 | wpdb Class Reference |
| 3 | 資料表名稱使用 `$wpdb->prefix` | 不可硬編碼表名 | Plugin Handbook: Custom Tables |
| 4 | LIKE 查詢使用 `$wpdb->esc_like()` 後再套 `$wpdb->prepare()` | 萬用字元 % 正確處理 | wpdb::esc_like() |

> 📎 這是最關鍵的檢查。audit trail 外掛會頻繁寫入資料庫，任何一個未 prepare 的查詢都是嚴重漏洞。

#### 2.2 XSS 防護

| # | 檢查項目 | 合格標準 | 依據 |
|---|---------|---------|------|
| 5 | 所有使用者輸入在儲存前經過 sanitize | 使用 `sanitize_text_field()`, `sanitize_email()` 等 | Plugin Handbook: Sanitizing Input |
| 6 | 所有輸出到 HTML 時經過 escape | 使用 `esc_html()`, `esc_attr()`, `esc_url()` | Plugin Handbook: Escaping Output |
| 7 | Admin 頁面的表單資料正確 escape | 表格、設定頁面的動態值都經過 `esc_html()` | OWASP XSS Prevention |
| 8 | 不使用 `echo $_GET[...]` 或 `echo $_POST[...]` | 所有 superglobal 輸出必須 escape | Plugin Handbook: Common Issues |

> 📎 audit trail 的 admin 頁面會顯示使用者名稱、IP 等來自外部的資料，每個 echo 都是潛在的 XSS 點。

#### 2.3 CSRF 防護

| # | 檢查項目 | 合格標準 | 依據 |
|---|---------|---------|------|
| 9 | 所有表單使用 `wp_nonce_field()` | 每個 POST 表單都有 nonce | Plugin Handbook: Nonces |
| 10 | 表單處理前驗證 nonce | 使用 `wp_verify_nonce()` 搭配 `wp_unslash()` + `sanitize_text_field()` | Plugin Handbook: Nonces |
| 11 | AJAX handler 驗證 nonce | 使用 `check_ajax_referer()` | Plugin Handbook: AJAX Nonces |
| 12 | 設定頁面使用 Settings API | 或至少手動驗證 nonce | Settings API Reference |

#### 2.4 權限控制

| # | 檢查項目 | 合格標準 | 依據 |
|---|---------|---------|------|
| 13 | Admin 頁面檢查 `current_user_can()` | 所有管理功能都有 capability 檢查 | Plugin Handbook: Roles & Capabilities |
| 14 | AJAX handler 檢查權限 | `wp_ajax_` 和 `wp_ajax_nopriv_` 正確區分 | Plugin Handbook: AJAX |
| 15 | REST API 端點有 `permission_callback` | 不使用 `__return_true` 作為 permission callback | REST API Handbook |
| 16 | 稽核紀錄只有管理員可查看 | 非管理員不可存取 audit log 資料 | 最小權限原則 |

#### 2.5 敏感資料處理

| # | 檢查項目 | 合格標準 | 依據 |
|---|---------|---------|------|
| 17 | 不記錄明文密碼 | `wp_login_failed` hook 的 callback 不得存取或記錄密碼參數 | OWASP Authentication |
| 18 | IP 位址有遮蔽選項 | 考慮 GDPR/個資法要求，提供 IP 匿名化選項 | GDPR Art. 25 |
| 19 | 使用者名稱的錯誤嘗試紀錄不洩漏有效帳號 | 不在前端顯示「此使用者名稱存在但密碼錯誤」 | OWASP Authentication |
| 20 | Log 資料定期清除 | 提供自動清除舊紀錄的機制（例如保留 90 天） | 資料最小化原則 |

### 第三步：效能審查

audit trail 外掛每次登入/失敗都會寫入資料庫，效能很重要。

| # | 檢查項目 | 合格標準 | 依據 |
|---|---------|---------|------|
| 21 | 自訂資料表有適當的 INDEX | 至少在 `user_id`, `event_time`, `event_type` 上建 INDEX | MySQL Best Practices |
| 22 | 查詢使用分頁 | Admin 頁面不一次載入所有紀錄 | WP_List_Table |
| 23 | Hook callback 盡量輕量 | `wp_login_failed` callback 不做複雜運算 | Plugin Performance |
| 24 | 清除排程使用 WP-Cron | `wp_schedule_event()` 處理定期清理 | WP-Cron Reference |

### 第四步：WordPress 整合規範

| # | 檢查項目 | 合格標準 | 依據 |
|---|---------|---------|------|
| 25 | 使用 WordPress hook 系統 | 不修改 core 檔案，純 hook-based | Plugin Handbook: Hooks |
| 26 | JS/CSS 使用 `wp_enqueue_script/style` | 不在 header/footer 直接輸出 | Plugin Handbook: Enqueue |
| 27 | 翻譯就緒 | 所有使用者可見字串使用 `__()` / `_e()` | Plugin Handbook: I18n |
| 28 | Activation/Deactivation hook | 正確建立/清理資料表和排程 | Plugin Handbook: Activation |
| 29 | 資料表使用 `dbDelta()` 建立 | 啟用時正確建立表結構 | Creating Tables with Plugins |
| 30 | 前綴命名避免衝突 | 函數/類別使用唯一前綴（如 `wuat_`） | Plugin Handbook: Best Practices |

### 第五步：Audit Trail 專屬邏輯審查

| # | 檢查項目 | 合格標準 | 依據 |
|---|---------|---------|------|
| 31 | 監聽正確的 hooks | 至少涵蓋 `wp_login`, `wp_login_failed`, `wp_logout` | WordPress Authentication Hooks |
| 32 | 記錄必要欄位 | event_type, username, IP, user_agent, timestamp | Audit Trail Best Practices |
| 33 | 偵測暴力破解 | 同一 IP 短時間多次失敗時觸發告警 | OWASP Brute Force Prevention |
| 34 | 告警機制 | 偵測異常時發送 email 或 webhook 通知 | 即時回應需求 |
| 35 | 紀錄防篡改 | audit log 不可被前端刪除或修改（或至少需要 `manage_options` 權限） | 稽核軌跡完整性 |

---

## 審查結果輸出格式

完成審查後，產出以下格式的報告：

```
📋 WordPress Audit Trail 外掛 — 安全性審查報告
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

外掛名稱：[名稱]
審查日期：[日期]
審查版本：[版本]

🔴 嚴重問題（必須修復才能上線）
  1. [問題描述] → [修復建議]

🟡 中度問題（建議修復）
  1. [問題描述] → [修復建議]

🟢 輕微問題（Nice to have）
  1. [問題描述] → [修復建議]

✅ 通過項目：[N] / 35
❌ 未通過項目：[N] / 35

整體結論：[ ] 可上線  [ ] 修復後重審  [ ] 需大幅重構
```

嚴重問題的判定標準：
- 任何 SQL injection 漏洞（#1-4）→ 🔴
- 任何未 escape 的輸出（#5-8）→ 🔴
- 缺少 nonce 驗證（#9-12）→ 🔴
- 記錄明文密碼（#17）→ 🔴
- 缺少權限檢查（#13-16）→ 🔴

---

## 參考資源

如需更詳細的 WordPress 安全性開發規範，載入以下 reference：
- `references/wp-security-checklist-detail.md`：每個檢查項目的詳細程式碼範例與反例
