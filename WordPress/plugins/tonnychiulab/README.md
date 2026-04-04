![Built with Claude Code](https://img.shields.io/badge/Built%20with-Claude%20Code-blueviolet?style=for-the-badge)
![WordPress 6.x](https://img.shields.io/badge/WordPress-6.x-21759b?style=for-the-badge&logo=wordpress&logoColor=white)
![License GPL v2](https://img.shields.io/badge/License-GPL%20v2-green?style=for-the-badge)
![Security 35/35](https://img.shields.io/badge/Security-35%2F35%20Passed-brightgreen?style=for-the-badge)

# 🔌 WordPress Plugins by Claude Code

> **AI 協作開發的 WordPress 免費外掛集合**
>
> 每一個外掛都由 [Claude Code](https://docs.anthropic.com/en/docs/claude-code) 從零建構，
> 使用自訂的 [Agent Skills](https://agentskills.io/) 確保程式碼品質，
> 並經過 35 項安全性 checklist 審查後才發布。

---

## 為什麼做這個專案？

我在台灣的醫院擔任跨 HR / IT / 資安的角色。日常工作中經常需要快速建構內部工具，
但醫院環境對安全性和合規性的要求很高——不是能跑就好，必須通過稽核。

這個專案記錄了我用 Claude Code + 自訂 Skills 開發 WordPress 外掛的完整流程。
每個外掛不只是「產出程式碼」，而是經過：

```
📝 Skill 驅動開發    →    🔍 35 項安全審查    →    ✅ 人類核可    →    📦 發布
   (Builder Skill)         (Review Skill)        (Human-in-the-Loop)     (GitHub)
```

所有外掛都是**免費開源**的，歡迎使用、回報問題、提交 PR。

---

## 📦 外掛清單

| 外掛 | 說明 | 版本 | 狀態 |
|------|------|------|------|
| [wp-unauthorized-access-tracker](./wp-unauthorized-access-tracker) | 偵測未授權存取 WP 後台的稽核軌跡，含暴力破解偵測與 Email 告警 | 1.0.0 | ✅ Released |

> 後續會持續新增更多外掛到此目錄。

---

## 🚀 快速安裝

### 方法一：直接下載

1. 點進任一外掛目錄
2. 下載整個資料夾（或 clone 本 repo）
3. 將外掛資料夾放到你的 WordPress `/wp-content/plugins/` 目錄
4. 在 WordPress 後台 → 外掛 → 啟用

### 方法二：Git Clone

```bash
cd /path/to/your/wordpress/wp-content/plugins/
git clone https://github.com/tonnychiulab/Claude-Test.git temp-clone
cp -r temp-clone/WordPress/plugins/wp-unauthorized-access-tracker .
rm -rf temp-clone
```

---

## 🛡️ 安全性保證

每個外掛在發布前都通過 **35 項安全性 checklist** 審查，涵蓋：

| 類別 | 檢查項目數 | 涵蓋範圍 |
|------|-----------|---------|
| SQL Injection 防護 | 4 項 | `$wpdb->prepare()`、`$wpdb->insert()`、`$wpdb->prefix`、`esc_like()` |
| XSS 防護 | 4 項 | 輸入 sanitize、輸出 escape、Admin 表單、superglobal 處理 |
| CSRF 防護 | 4 項 | nonce field、nonce 驗證、AJAX nonce、Settings API |
| 權限控制 | 4 項 | `current_user_can()`、AJAX 權限、REST API permission、最小權限 |
| 敏感資料 | 4 項 | 不記錄密碼、IP 匿名化、防帳號列舉、定期清除 |
| 效能 | 4 項 | INDEX、分頁、輕量 callback、WP-Cron |
| WordPress 整合 | 6 項 | Hook 系統、enqueue、i18n、activation/deactivation、dbDelta、前綴命名 |
| 業務邏輯 | 5 項 | 依外掛功能而定 |

---

## 🏗️ 開發方法：Skill 驅動開發

本專案使用三個自訂的 Claude Code Skills 來確保開發品質：

### 1. Meta Skill Factory — 產出新 Skill

讀取法規、標準、SOP 等文件，自動產出新的開發 Skill，強制 human-in-the-loop 審核。

```
輸入：法規 / 標準 / SOP 文件
輸出：SKILL.md 草稿 → 人類審核 → 核可後部署
```

### 2. Builder Skill — 建構外掛

根據 Skill 中的規格、安全模式、Hook 參考，從零產出完整的外掛程式碼。

```
輸入：「幫我寫一個 WordPress 外掛，偵測未授權存取」
輸出：完整的外掛目錄（PHP + CSS + JS + readme.txt）
```

### 3. Review Skill — 安全審查

對照 35 項 checklist 逐項審查程式碼，產出分級報告。

```
輸入：外掛 PHP 程式碼
輸出：🔴 嚴重 / 🟡 中度 / 🟢 輕微 的審查報告
```

### 三個 Skill 的協作流程

```
                    ┌─────────────────┐
                    │  Meta Skill     │
                    │  Factory        │
                    │  (從法規產出     │
                    │   新的 Skill)   │
                    └────────┬────────┘
                             │ 產出 Builder / Review Skill
                             ▼
┌─────────────────┐    ┌─────────────────┐
│  Builder Skill  │───▶│  Review Skill   │
│  (建構外掛)      │    │  (35 項審查)    │
└─────────────────┘    └────────┬────────┘
         ▲                      │
         │    修復建議            │
         └──────────────────────┘
                   迴圈直到 35/35 通過
                             │
                             ▼
                    ┌─────────────────┐
                    │   ✅ 發布到     │
                    │   GitHub        │
                    └─────────────────┘
```

> 這個方法受 Google ADK SkillToolset 的 **Progressive Disclosure** 模式啟發。
> Skill 的三層結構（Metadata → Instructions → References）讓 AI agent
> 只在需要時載入對應的知識，而不是把所有規範塞進一個巨大的 prompt。

---

## 📁 專案結構

```
WordPress/plugins/
├── README.md                              ← 你正在讀的這份文件
├── wp-unauthorized-access-tracker/        ← 第一個外掛
│   ├── wp-unauthorized-access-tracker.php
│   ├── includes/
│   │   ├── class-wuat-logger.php
│   │   ├── class-wuat-detector.php
│   │   ├── class-wuat-alerter.php
│   │   ├── class-wuat-admin.php
│   │   └── class-wuat-settings.php
│   ├── assets/css/
│   ├── assets/js/
│   ├── uninstall.php
│   └── readme.txt
└── [未來的外掛]/
    └── ...
```

---

## 🤝 貢獻

歡迎以下形式的貢獻：

- **回報問題**：在 [Issues](https://github.com/tonnychiulab/Claude-Test/issues) 提交 bug 或功能建議
- **提交 PR**：修復問題或新增功能
- **分享你的 Skill**：如果你也在用 Claude Code Skills 開發 WordPress 外掛，歡迎交流

### 貢獻外掛的品質要求

如果你想提交新的外掛到這個目錄，請確保：

1. 通過本專案的 35 項安全性 checklist（或等效的審查）
2. 包含完整的 `readme.txt`（WordPress.org 格式）
3. 包含 `uninstall.php` 處理反安裝清理
4. 所有字串 i18n ready

---

## 📜 授權

所有外掛均以 [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html) 授權釋出。

---

## 🔗 相關資源

- [Claude Code](https://docs.anthropic.com/en/docs/claude-code) — Anthropic 的命令列 AI 開發工具
- [Agent Skills Specification](https://agentskills.io/) — AI agent 技能的開放標準
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/) — WordPress 官方外掛開發指南
- [Google ADK SkillToolset](https://google.github.io/adk-docs/skills/) — Progressive Disclosure 的實作參考

---

---

*Made with ❤️ in Taiwan by [Tonny](https://github.com/tonnychiulab) — powered by Claude Code + Agent Skills*
