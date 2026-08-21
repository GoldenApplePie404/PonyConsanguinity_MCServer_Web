# data/ 目录说明

> **安全策略**：本目录已被 `.htaccess` 禁止所有外部 HTTP 访问。  
> PHP 后端通过 `secureReadData()` / `secureWriteData()` 以文件系统路径读写，不受影响。  
> **Git 策略**：运行时数据文件（`sessions.php`、`ai_conversations.php`、`users_.php`、`captchas.php` 等）**禁止入库**，已在 `.gitignore` 中排除；仓库内仅保留 `data-init/` 下的空模板，clone 后由 `includes/data-init.php` 自动补齐。

---

## 用途

`data/` 是万驹同源官网的**本地持久化存储目录**，相当于项目的"数据库"。  
所有用户数据、内容数据、会话、缓存均以文件形式存放在此。

---

## 目录结构

```
data/
├── .htaccess                  # 访问控制，禁止外部 HTTP 访问
├── index.php                  # 兜底：返回 403（第二层防护）
├── README.md                  # 本文件
│
├── users.php                  # [PHP] 用户账户数据（密码哈希、积分、权限等）★运行时
├── users_.php                 # [PHP] 用户数据结构展示样本（密码为破坏处理的假哈希，不可登录）★运行时
├── sessions.php               # [PHP] 活跃会话/token 管理 ★运行时
├── captchas.php               # [PHP] 滑块人机验证挑战存储（challenges 数组）★运行时
├── ai_conversations.php       # [PHP] AI 助手对话历史（conversations + messages）★运行时
├── posts.php                  # [PHP] 论坛帖子索引（帖子正文在 content/ 下）
├── notifications.php          # [PHP] 全局通知数据
│
├── announcements.json         # [JSON] 公告列表（标题、摘要、时间等）
├── bookmarks.json             # [JSON] 用户收藏夹
├── images.json                # [JSON] 资源站图片索引
├── likes.json                 # [JSON] 帖子点赞记录
├── messages.json              # [JSON] 站内信/私信数据
├── notifications.json         # [JSON] 前端通知数据
├── questionnaires.json        # [JSON] 问卷结果
├── performance_data.json      # [JSON] 性能监控/运行指标
├── mcstatus_cache.json        # [JSON] MC 服务器状态缓存
├── mc_instances_cache.json    # [JSON] MCSM 面板实例状态缓存（多实例运行状态/玩家数）
├── nfc_debug.log              # [LOG] NFC 智能卡调试日志（追加式，含设备/用户操作记录）
│
├── content/                   # 论坛帖子正文（Markdown 格式）
│   ├── 1769484319.md          #   文件名 = 帖子 ID（Unix 时间戳）
│   ├── 1769484392.md
│   ├── 1772626959.md
│   ├── 1772957435.md
│   └── announcements/         #   公告详情（Markdown 格式）
│       ├── v3.0.md ~ v3.5.md
│       ├── firework.md
│       ├── hide-and-seek.md
│       ├── mod2.md
│       └── ...
│
├── posts/                     # 预留目录（当前为空，规划中）
│
├── replies/                   # 论坛帖子回复（每个帖子一个 JSON 文件）
│   ├── 1769484319.json
│   ├── 1769484392.json
│   ├── 1772626959.json
│   └── ...
│
├── shop_items/                # 积分商城商品定义
│   ├── exp_pack.json          #   经验包
│   ├── golden_ticket.json     #   黄金券
│   └── *.php.bak              #   旧 PHP 商品文件备份（已废弃）
│
├── user_notifications/        # 用户个人通知（每用户一个 JSON 文件）
│   ├── 金苹果派.json
│   ├── gap.json
│   └── ...
│
├── recruitments/              # 招募申请（每个申请一个 JSON 文件）
│   └── recruit_xxx.json       #   包含申请人信息、状态、管理员备注
└── backups/                   # 数据备份目录（当前为空，待自动化备份功能上线）
```

> **★运行时**：标记文件为程序运行期自动生成/改写的敏感数据，**禁止提交到 git**，已在 `.gitignore` 排除。clone 仓库后由 `data-init/` 模板自动补齐空文件。

---

## 数据格式说明

### PHP 数据文件（users.php / sessions.php / captchas.php / ai_conversations.php 等）

返回 PHP 关联数组，通过 `include` 读取：

```php
// 读取方式
$data = secureReadData(USERS_FILE);  // USERS_FILE = __DIR__ . '/data/users.php'
// 写入方式
secureWriteData(USERS_FILE, $data);  // 原子写入：先写临时文件，再 rename
```

**安全现状**：这些 PHP 文件即使被浏览器直接访问也不会泄露数据（顶层 `return` 不产生输出），但这是 PHP 的副作用而非设计意图。`.htaccess` 已提供第一层防护，PHP 执行行为是第二层防护。

**特殊说明**：
- `captchas.php` —— 滑块验证挑战，结构为 `['challenges' => [challenge_id => ['target_pct' => float, 'created_at' => int, 'used' => bool]]]`，一次性使用（`used` 标记）。
- `ai_conversations.php` —— AI 助手对话，结构为 `['conversations' => [cid => conv], 'messages' => [cid => [msg...]]]`，conv 含 `type`(group/private)、`name`。
- `users_.php` —— **仅作结构展示**，密码哈希是破坏处理的假值，无法用于登录；正式数据在 `users.php`。

### JSON 数据文件

标准 JSON 格式，读写方式：

```php
$data = secureReadData('data/announcements.json');   // 返回解码后的 PHP 数组
secureWriteData('data/announcements.json', $data);   // 原子写入
```

### Markdown 内容文件

论坛帖子正文和公告详情使用 Markdown 格式存储，由后端读取后渲染为 HTML。

---

## 已知问题

| 问题 | 影响 | 状态 |
|---|---|---|
| PHP/JSON 格式混用 | 排查问题困难，无法用统一工具处理 | 已记录，暂不迁移 |
| 无并发写保护 | 多用户同时写同一文件可能丢数据 | 现阶段用户少，可接受 |
| `sessions.php` 单文件存储 | 高并发时会频繁写冲突 | 待升级为 SQLite 后解决 |
| `captchas.php` 无清理机制 | 过期挑战堆积，文件缓慢膨胀 | 待加定期清理 |
| `backups/` 目录为空 | 无自动备份机制 | 待实现 |

---

## 数据库升级计划

### 第一阶段：格式统一（低风险，可随时执行）

将 PHP 数据文件统一转为 JSON 格式，消除格式混用问题。

- [ ] `users.php` → `users.json`
- [ ] `sessions.php` → `sessions.json`（或改用 PHP 原生 `session_start()` + 文件存储）
- [ ] `posts.php` → `posts.json`
- [ ] `notifications.php` → `notifications.json`（需合并/去重现有两个文件）
- [ ] `ai_conversations.php` / `captchas.php` → 并入 JSON 或 SQLite

**前提**：`.htaccess` 必须已生效（已创建 ✓），确保 JSON 文件不会被外部访问。

**迁移步骤**：
1. 备份原 PHP 文件（`.php.bak`）
2. 用 PHP 读取数组 → `json_encode` 写入 JSON
3. 更新所有 `secureReadData()` 调用路径
4. 验证读写正常后删除旧 PHP 文件

### 第二阶段：引入 SQLite（中风险，建议用户量达到 100+ 前完成）

SQLite 是零依赖的单文件数据库，PHP 自带扩展，部署方式不变。

**迁移目标**：
- 用户数据、会话、帖子、通知等高频读写数据迁入 SQLite
- Markdown 内容文件、商品 JSON 等低频配置保持文件存储

**SQLite 优势**：
- 天然并发读写（WAL 模式下读写不阻塞）
- 支持 SQL 查询（索引、排序、过滤）
- 事务保证数据一致性
- 单文件部署，仍是 `data/` 目录下的一个文件

**预计结构**：

```
data/
├── app.db                     # SQLite 主数据库（users/sessions/posts/notifications）
├── content/                   # 保持文件存储（Markdown 低频数据）
├── shop_items/                # 保持文件存储（配置型数据）
└── backups/                   # 自动备份（每日导出 app.db）
```

---

