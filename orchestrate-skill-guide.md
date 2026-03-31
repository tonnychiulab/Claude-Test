# Claude Code Orchestrate Skill — 完整教學

> 給新同事的參考文件
> 作者：透過 Claude Code 自動生成
> 日期：2026-03-31

---

## 目錄

1. [背景：為什麼要做這個？](#背景)
2. [我們做了什麼？](#我們做了什麼)
3. [前置安裝](#前置安裝)
4. [Skill 結構說明](#skill-結構說明)
5. [使用方式](#使用方式)
6. [實際 Demo 重現](#實際-demo-重現)
7. [運作原理](#運作原理)
8. [進階使用技巧](#進階使用技巧)
9. [常見問題](#常見問題)

---

## 背景

### 靈感來源：agent-orchestrator

[ComposioHQ/agent-orchestrator](https://github.com/ComposioHQ/agent-orchestrator) 是一個讓開發者同時跑 10–30 個 AI coding agent 的艦隊管理系統，核心概念：

- 一個 **Orchestrator（協調者）** 負責規劃和分配任務
- 多個 **Worker agents** 各自在獨立 git branch 處理任務
- 每個 worker 完成後自動開 PR

**問題：** 它需要 tmux，無法在 Windows 原生執行。

### 我們的解法

Claude Code 本身已內建所有需要的能力：

| agent-orchestrator 的做法 | Claude Code 原生方案 |
|---|---|
| tmux 管理 agent session | `Agent` tool 直接 spawn subagent |
| git worktree 隔離 | `isolation: "worktree"` 參數 |
| 自訂 orchestrator prompt | Skill 定義檔 |
| Web 看板監控 | `TaskCreate / TaskUpdate` |
| Claude API 任務分解 | 直接在 skill 裡呼叫 |
| **需要 WSL2 / Linux** | **✅ Windows 原生支援** |

所以我們直接做成一個 **Claude Code Skill**，不需要任何額外工具。

---

## 我們做了什麼

### 建立的檔案

```
C:\Users\{你的帳號}\.claude\skills\orchestrate\
├── SKILL.md                        ← 主要 skill 定義（7 個 phases 的工作流程）
└── references\
    ├── decompose-prompt.md         ← 任務分解的啟發式規則
    └── worker-instructions.md      ← Worker agent 的標準工作指令
```

### Skill 的核心流程

```
你輸入指令
    ↓
[Phase 1] 偵測模式（GitHub Issues / 任務清單 / 大目標）
    ↓
[Phase 2] 任務分解 → 顯示計畫表格 → 等你確認
    ↓
[Phase 3] TaskCreate 建立追蹤任務
    ↓
[Phase 4] 同時 spawn N 個 Agent（每個有獨立 worktree）← 平行執行
    ↓
[Phase 5] 收集所有 agent 結果
    ↓
[Phase 6] git push + gh pr create（自動開 PR）
    ↓
[Phase 7] 最終報告
```

---

## 前置安裝

### 1. 安裝 Claude Code

從 [claude.ai/code](https://claude.ai/code) 下載桌面版，或：

```bash
npm install -g @anthropic-ai/claude-code
```

### 2. 安裝 GitHub CLI

```bash
winget install --id GitHub.cli
```

重新開啟終端機後登入：

```bash
gh auth login
```

選擇：
- `GitHub.com`
- `HTTPS`
- `Login with a web browser`

驗證是否成功：

```bash
gh auth status
```

### 3. 確認 Skill 已安裝

Skill 檔案位於：

```
C:\Users\{你的帳號}\.claude\skills\orchestrate\
```

如果目錄不存在，複製本教學附帶的 `skills\orchestrate\` 資料夾到上面的路徑。

---

## Skill 結構說明

### SKILL.md

Skill 的核心定義檔。包含：

- **觸發條件**：哪些關鍵字會自動啟動這個 skill
- **7 個 Phases**：完整的工作流程
- **Rules**：orchestrator 的行為規則

關鍵規則：
```
- 永遠不直接寫程式碼，全部委派給 worker agent
- 一定要用 isolation: "worktree" 讓每個 agent 有獨立分支
- 最多同時 8 個 parallel agents
- 確認計畫後才執行
```

### references/decompose-prompt.md

任務分解的判斷規則，例如：

- `bug` label → Atomic（1 個 agent）
- 觸及 3+ 模組的 feature → Composite（拆成 2–4 個 agent）
- 如何偵測任務之間的依賴關係

### references/worker-instructions.md

每個 worker agent 的標準工作流程和結果格式，確保回傳統一的結構化結果：

```
---
## Orchestrator Result
Status: completed | partial | blocked
Files changed:
  - src/xxx.js (說明)
Summary: 做了什麼
Tests: passed / failed / skipped
Blockers: none 或說明
---
```

---

## 使用方式

### 基本指令

在 Claude Code 裡輸入：

```
/orchestrate <你的目標>
```

### 三種模式

#### Mode A — GitHub Issues（最常用）

```
/orchestrate fix all open issues labeled "bug"
/orchestrate process all open GitHub issues in this repo
/orchestrate fix issues #1 #2 #5
```

會自動：
1. 用 `gh issue list` 拉取 issues
2. 分析每個 issue 的內容
3. 規劃哪些可以平行、哪些有依賴

#### Mode B — 明確任務清單

```
/orchestrate do these 3 tasks:
Task 1: Add input validation to src/user.js
Task 2: Write unit tests for src/payment.js
Task 3: Fix the null pointer bug in src/auth.js
```

#### Mode C — 大目標分解

```
/orchestrate refactor the authentication module into smaller components
/orchestrate add comprehensive error handling to all API endpoints
/orchestrate migrate all callbacks to async/await
```

---

## 實際 Demo 重現

這是我們在測試中跑過的完整流程，你可以照著重現。

### 步驟 1：建立測試 Repo

```bash
# 在 GitHub 建立一個新的空 repo，然後 clone
gh repo create my-test-repo --public
gh repo clone my-test-repo D:/ClaudeCode/my-test-repo
cd D:/ClaudeCode/my-test-repo
```

### 步驟 2：加入有 bug 的程式碼

建立 `src/calculator.js`：

```javascript
function divide(a, b) {
  return a / b; // BUG: no division by zero check
}

function average(numbers) {
  return numbers.reduce((sum, n) => sum + n, 0) / numbers.length; // BUG: empty array
}

module.exports = { divide, average };
```

Commit 並 push：

```bash
git add . && git commit -m "Add calculator with known bugs"
git push origin main
```

### 步驟 3：建立 GitHub Issues

```bash
gh issue create \
  --title "Fix division by zero in calculator.js" \
  --label "bug" \
  --body "divide(10, 0) should throw Error instead of returning Infinity"
```

### 步驟 4：執行 Orchestrate

在 Claude Code 輸入：

```
/orchestrate fix all open bugs in this repo
```

### 步驟 5：確認計畫

Claude 會顯示計畫表格：

```
| # | Issue | Agent   | Branch          | Scope |
|---|-------|---------|-----------------|-------|
| 1 | #1    | worker-1| fix/issue-1     | Small |
```

輸入「確認」或「yes」後開始執行。

### 步驟 6：等待結果

Claude 同時 spawn 所有 worker agents，每個 agent：
- 讀取相關程式碼
- 實作修復
- commit + push 到獨立 branch

### 步驟 7：自動開 PR

每個 worker 完成後，orchestrator 自動呼叫 `gh pr create`，PR body 包含：
- 修改摘要
- 變更的檔案
- `Closes #issue_number`

### 步驟 8：Code Review + Merge

```
請對所有 PR 做 code review 並判斷是否 merge
```

Claude 會：
1. 讀取每個 PR 的 diff
2. 分析正確性和潛在問題
3. 給出 ✅ Merge / ⚠️ 需修改 / ❌ 不建議 Merge 的判斷
4. 你確認後執行 `gh pr merge`

---

## 運作原理

### Worktree Isolation

每個 worker agent 使用 `isolation: "worktree"` 參數，Claude Code 會自動：

1. 建立一個新的 git worktree（獨立目錄，共享 `.git`）
2. 在新的 branch 上工作
3. Agent 完成後，worktree 可以保留或清除

這讓 10 個 agent 同時在同一個 repo 的 10 個不同 branch 工作，互不干擾。

### Task 追蹤

```
TaskCreate → 建立任務（pending）
TaskUpdate → in_progress（agent 啟動時）
TaskUpdate → completed / failed（agent 回報結果後）
```

你可以在 Claude Code 用 `/tasks` 查看即時狀態。

### Parallel Agent Execution

```javascript
// 在同一個 message 裡呼叫多個 Agent tool = 平行執行
Agent(worker-1) ─┐
Agent(worker-2) ─┼─ 同時執行
Agent(worker-3) ─┘
```

---

## 進階使用技巧

### 指定最大 agent 數

```
/orchestrate fix all bugs, use max 3 agents at a time
```

### 跳過確認直接執行

```
/orchestrate fix issue #1 #2 #3, skip confirmation
```

### 只處理特定 label

```
/orchestrate process all issues labeled "good first issue"
```

### 搭配 /schedule 定時執行

```
/schedule every day at 9am: orchestrate fix all new bug issues opened yesterday
```

### 限制處理範圍

```
/orchestrate fix bugs only in src/api/ directory
```

---

## 常見問題

### Q: gh 指令找不到（command not found）

A: 重新開啟終端機，或使用完整路徑：
```
"C:/Program Files/GitHub CLI/gh.exe" auth status
```

### Q: Worker agent 回報 blocked

A: Orchestrator 會顯示阻塞原因，你可以：
- 直接告訴 Claude 額外資訊，讓它 re-spawn 該 agent
- 手動處理後告知 Claude 繼續

### Q: 可以不用 GitHub Issues 直接跑嗎？

A: 可以，用 Mode B 直接列任務：
```
/orchestrate do these tasks:
1. Add error handling to src/api.js
2. Write tests for src/user.js
```

### Q: 最多可以幾個 agent 同時跑？

A: Skill 預設上限 8 個。超過 8 個任務時自動分批（Round 1 跑 8 個，完成後跑 Round 2）。實際上限取決於你的 Claude Code 方案的 token 預算。

### Q: 如何確認 PR 真的 merge 了？

```bash
gh pr list --repo owner/repo --state merged
```

---

## 檔案清單

```
# Skill 定義（複製到每個使用者的 ~/.claude/skills/orchestrate/）
~/.claude/skills/orchestrate/SKILL.md
~/.claude/skills/orchestrate/references/decompose-prompt.md
~/.claude/skills/orchestrate/references/worker-instructions.md
```

---

## 延伸閱讀

- [Claude Code 官方文件](https://docs.anthropic.com/claude-code)
- [agent-orchestrator 原始專案](https://github.com/ComposioHQ/agent-orchestrator)（靈感來源，需 Linux/WSL2）
- [GitHub CLI 文件](https://cli.github.com/manual/)
