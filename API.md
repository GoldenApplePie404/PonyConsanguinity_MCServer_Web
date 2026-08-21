# API 文档

> 本文档基于 **源码逐文件核对**（2026-08-17，项目 v5.8.1）重写，覆盖 `api/` 目录全部 87 个 PHP 文件中的对外端点。此前版本大量内容已过时（`errors` 字段、golden-ticket 余额/兑换、map_proxy 坐标参数、mcstatus/performance 响应结构等均与源码不符），本次已全部修正。
> 项目概览：[README.md](README.md) ｜ 配置说明：[CONFIGURATION.md](CONFIGURATION.md) ｜ 开发与安全：[DEVELOPMENT.md](DEVELOPMENT.md)

## 1. 概述

本 API 文档描述万驹同源服务器官网的后端接口，涵盖认证、用户、论坛、图片、系统状态、聊天、问卷、商城、充值、AI 等模块。

## 2. 通用约定

### 2.1 认证方式

- **Token 认证**：请求头 `Authorization: Bearer {token}`，token 通过登录接口获取
- **滑块验证**：登录/注册等敏感接口需先通过 `/api/captcha.php` 获取一次性 `captcha_token`（见 3.1）
- **管理员接口**：额外校验 `AuthHelper::requireAdmin()`（管理员会话）

### 2.2 响应格式

所有 API 响应均为 JSON。统一辅助函数 `json_response()` 的结构为 **`{success, message, data?}`，不含 `errors` 字段**（v1.x 文档中的 errors 数组不存在）：

```json
{
  "success": true,
  "message": "响应消息",
  "data": { }
}
```

部分历史文件（`forum.php`、`announcement.php`、`recruit.php` 等）直接 `echo json_encode(...)`，字段一致，部分用 `error` 代替 `message`（见各节说明）。

### 2.3 请求体

- `Content-Type: application/json` + JSON body（`get_post_data()` 解析）
- 或传统表单字段（`$_GET` / `$_POST`）
- 各接口以实际代码为准，下文已标注

### 2.4 HTTP 错误码

| 错误码 | 描述 |
|-------|------|
| 400 | 请求参数错误 / 滑块验证失败 |
| 401 | 未授权，需要登录 |
| 403 | 禁止访问（无权限 / 非作者） |
| 404 | 资源不存在 |
| 405 | 请求方法不支持 |
| 429 | 登录尝试过多，账户锁定 |
| 500 | 服务器内部错误 |

## 3. 认证与账户 API

### 3.1 滑块验证码 `captcha.php`（v5.8+ 新增）

登录/注册前置流程：先 `create` 获取挑战，前端渲染滑块，用户完成后 `verify` 换取一次性 `captcha_token`，随后登录/注册携带该 token。

**端点**：`POST /api/captcha.php`（JSON body）

| action | 参数 | 说明 |
|--------|------|------|
| `create` | — | 生成滑块挑战，返回 `challenge_id` 与 `target_pct` |
| `verify` | `challenge_id`, `x_pct`, `trail` | 校验滑块结果，通过后返回一次性 `captcha_token` |

**响应（create）**：
```json
{ "success": true, "message": "挑战已生成", "data": { "challenge_id": "b0a0aef2...", "target_pct": 56.3 } }
```

**响应（verify）**：
```json
{ "success": true, "message": "验证通过", "data": { "captcha_token": "..." } }
```

> ⚠️ 实测（2026-08-17）：`captcha_token` 为**一次性**令牌，`CaptchaHelper::consumeToken()` 消费后即失效。

### 3.2 注册 `register.php`

**端点**：`POST /api/register.php`（JSON body）

| 参数 | 类型 | 必须 | 说明 |
|------|------|------|------|
| username | string | 是 | 用户名 |
| password | string | 是 | ≥8 位，须含大写+小写+数字 |
| email | string | 否 | 邮箱（启用邮箱验证时用于验证） |
| captcha_token | string | **是** | 滑块验证令牌（一次性） |

**响应**：`{success, message, data:{user:{...}, email_verification:{enabled, email_sent, message}}}`

**错误**：400 用户名已存在 / 密码不合规 / 滑块未通过。

### 3.3 登录 `login.php`

**端点**：`POST /api/login.php`（JSON body）

| 参数 | 类型 | 必须 | 说明 |
|------|------|------|------|
| username | string | 是 | 用户名 |
| password | string | 是 | 密码 |
| captcha_token | string | **是** | 滑块验证令牌（一次性） |

**响应**：
```json
{
  "success": true,
  "message": "登录成功",
  "data": {
    "token": "auth_token",
    "user": { "id": "...", "username": "...", "email": "...", "role": "user", "email_verified": true },
    "email_verification": { "enabled": true, "verified": true }
  }
}
```

**错误**：400 滑块未通过 / 参数缺失；401 用户名或密码错误；429 账户锁定（5 次失败锁 15 分钟）。

> 登录成功写入 `data/sessions.php`，采用 FIFO 清理（`MAX_SESSIONS`，默认 10）。

### 3.4 注销 `logout.php`

**端点**：`POST /api/logout.php`（JSON body）

| 参数 | 类型 | 必须 | 说明 |
|------|------|------|------|
| token | string | 是 | 当前会话 token |

**响应**：`{success: true, message: "注销成功"}`；400 未提供令牌。

### 3.5 邮箱验证 `verify_email.php`

**端点**：`GET /api/verify_email.php?token={token}`

**响应**：`{success: true, message: "邮箱验证成功"}`（失败返回 `{success:false}`）。

### 3.6 重新发送验证邮件 `resend_verify_email.php`

**端点**：`POST /api/resend_verify_email.php`（需登录，Bearer Token）

**响应**：`{success: true, message: "验证邮件已重新发送"}`。

### 3.7 检查验证状态 `check_verify_status.php`

**端点**：`GET /api/check_verify_status.php`（需登录）

**响应**：`{success: true, data: {email_verified: true}}`。

### 3.8 修改密码 `change_password.php`（v5.8+ 新增）

**端点**：`POST /api/change_password.php`（需登录）

**参数**：`old_password`（原密码）、`new_password`（新密码，同注册强度要求）。

**响应**：`{success: true, message: "密码修改成功"}`。

### 3.9 忘记密码 `forgot_password.php`（v5.8+ 新增）

**端点**：`POST /api/forgot_password.php`

**参数**：`email` 或 `username`（以源码为准）。

**响应**：`{success: true, message: "重置邮件已发送"}`（若未启用邮件则返回相应提示）。

### 3.10 重置密码 `reset_password.php`（v5.8+ 新增）

**端点**：`POST /api/reset_password.php`

**参数**：`token`（重置令牌）、`new_password`。

**响应**：`{success: true, message: "密码重置成功"}`。

### 3.11 EYPA 第三方登录（v5.8+ 扩展）

| 端点 | 方法 | 说明 |
|------|------|------|
| `api/eypa/login.php` | GET | 参数 `code`（EYPA 授权码），返回 token + user（自动注册） |
| `api/eypa/callback.php` | GET | OAuth 回调，处理授权码换 token |
| `api/eypa/bind.php` | POST | 绑定 EYPA 账号到当前登录用户（需登录） |
| `api/eypa/unbind.php` | POST | 解绑 EYPA 账号（需登录） |
| `api/eypa/status.php` | GET | 查询当前用户 EYPA 绑定状态（需登录） |

> 完整授权码流程：跳转 `EYPA_AUTH_URL` → 回调 `EYPA_REDIRECT_URI`（`api/eypa/callback.php`）→ 前端跳转登录/绑定页。

## 4. 用户资料与签到 API

### 4.1 获取用户资料 `get_profile.php`

**端点**：`GET /api/get_profile.php`（需登录）

**响应**：`data` 含 `username, email, role, points, experience, email_verified, created_at, last_login, inventory[], buffs[]`（物品/增益列表，详见商城章节）。

### 4.2 完善个人资料 `complete_profile.php`

**端点**：`POST /api/complete_profile.php`（需登录）

**参数**：`nickname`（否）、`bio`（否）。

**响应**：`{success: true, message: "个人资料更新成功"}`。

### 4.3 签到状态 `get_checkin_status.php`

**端点**：`GET /api/get_checkin_status.php`（需登录）

**响应**：`data: {checked_in, last_checkin, consecutive_days}`。

### 4.4 执行签到 `checkin.php`

**端点**：`POST /api/checkin.php`（需登录）

**响应**：`data: {points, experience, reward, reward_experience, checkin_date}`。

### 4.5 删除账户 `delete_account.php`

**端点**：`POST /api/delete_account.php`（需登录）

**响应**：`{success: true, message: "账户删除成功"}`。

### 4.6 用户信息 `user_info.php`（v5.8+ 新增）

**端点**：`GET /api/user_info.php?username={username}`

**响应**：`data: {username, role, points, ...}`（公开字段，无需登录）。

### 4.7 我的内容 `profile/my_content.php`（v5.8+ 新增）

**端点**：`GET /api/profile/my_content.php`（需登录）

**响应**：`data` 含当前用户的帖子/回复/收藏等内容聚合。

## 5. 论坛 API

### 5.1 旧版论坛 `forum.php`

基于 `data/posts.php` + `data/content/*.md` + `data/replies/*.json` 的原始论坛实现。

**GET（列表 / 回复）**：

| action | 参数 | 说明 |
|--------|------|------|
| —（默认） | — | 帖子列表，`data.posts[]` |
| `get_replies` | `postId` | 帖子回复列表 |

**POST（JSON body）**：

| action | 参数 | 权限 | 说明 |
|--------|------|------|------|
| `reply` | `postId, content, author` | 无显式校验（前端传作者） | 回复帖子 |
| `delete_reply` | `postId, replyId` | 需登录；仅作者本人或 admin | 删除回复 |
| `edit_post` | `postId, title, content` | 需登录；仅作者本人 | 编辑帖子 |
| `vote` | `postId, optionIndex` | 需登录，每人限投一次 | 帖子投票 |
| `update_poll` | `postId, options[]` | 需登录（帖子作者/admin） | 更新投票选项 |

**DELETE**：删除帖子（`postId`），需登录，作者本人或 admin。

### 5.2 新版帖子系统 `posts.php` / `post.php`（v5.8+ 新增）

⚠️ **已知问题（2026-08-17 实测）**：`posts.php` / `post.php` 引用了未定义的常量 **`POSTS_DIR`**（`config/config.php` 仅定义 `POSTS_FILE`），直接访问会 **Fatal error**。修复前这两个端点不可用，详见「18. 源码核对发现的问题」。

| 端点 | 方法 | 说明 |
|------|------|------|
| `api/posts.php` | GET | 帖子列表（`data.posts[]`） |
| `api/posts.php` | POST | 发布帖子（`title, content`，需登录，201） |
| `api/post.php?id={id}` | GET | 帖子详情（404 不存在） |
| `api/post.php` | PUT | 编辑帖子（`id, title, content`，需登录+作者，403） |
| `api/post.php` | DELETE | 删除帖子（`id`，需登录+作者，403） |

### 5.3 点赞 / 收藏（v5.8+ 新增）

| 端点 | 方法 | action | 说明 |
|------|------|--------|------|
| `api/forum/like.php` | POST | `like` / `unlike`（`postId`） | 帖子点赞（需登录） |
| `api/forum/like.php` | GET | `status` / `count`（`postId`） | 点赞状态 / 计数 |
| `api/forum/bookmark.php` | POST | `add` / `remove`（`postId`） | 收藏帖子（需登录） |
| `api/forum/bookmark.php` | GET | `list` / `status` | 收藏列表 / 状态 |

## 6. 图片管理 API

### 6.1 上传 `image_api/upload.php`

**端点**：`POST /api/image_api/upload.php`（需登录，multipart）

**参数**：`image`（文件）。

**响应**：`data: {id, filename, original_name, url, size, type, width, height, mime}`。

### 6.2 列表 `image_api/list.php`

**端点**：`GET /api/image_api/list.php`（需登录）

**响应**：`data: {images[], total}`。

### 6.3 删除 `image_api/delete.php`

**端点**：`POST /api/image_api/delete.php`（需登录）

**参数**：`imageId`。

**响应**：`{success: true, message: "删除成功"}`。

### 6.4 图库管理 `image_api/gallery_manager.php`

**端点**：`GET/POST /api/image_api/gallery_manager.php`（需登录）

| action | 方法 | 参数 | 说明 |
|--------|------|------|------|
| `list` | GET | — | 图片列表 |
| `get` | GET | `imageId` | 图片详情 |
| `upload` | POST | `image`（文件） | 上传 |
| `delete` | POST | `imageId` | 删除 |

## 7. 系统与状态 API

### 7.1 通知列表 `notification.php`

**端点**：`GET /api/notification.php?action=list`（**需登录**，401）

**响应**：`data: {notifications[], unread_count}`。

### 7.2 标记通知已读 `notification_update.php`

**端点**：`POST /api/notification_update.php`（需登录）

**参数**：`notificationId`。

**响应**：`{success: true, message: "标记成功"}`。

### 7.3 发送通知 `send_notification.php`

**端点**：`POST /api/send_notification.php`（需登录 + **管理员**）

**参数**：`title, content, target`（target 留空=全体）。

**响应**：`{success: true, message: "通知发送成功"}`。内容支持占位符（`{username}`/`{server_name}`/`{server_ip}`/`{current_date}`/`{current_time}`，见 CONFIGURATION.md 通知章节）。

### 7.4 公告管理 `announcement.php`（⚠️ 需管理员）

**端点**：`GET/POST/DELETE /api/announcement.php`（文件头部 `AuthHelper::requireAdmin()`，**所有操作需管理员**）

| action | 方法 | 参数 | 说明 |
|--------|------|------|------|
| `list` | GET | — | 公告列表 |
| `get` | GET | `id` | 公告详情 |
| `create` | POST | `id, title, type, content` | 创建公告 |
| `update` | POST | `id, title, type, content` | 更新公告 |
| `delete` | POST | `id` | 删除公告 |

> 游客浏览公告走静态 Markdown 渲染（`pages/announcement-detail.html`），**不经过本 API**。

### 7.5 服务器状态 `mcstatus.php`

**端点**：`GET /api/mcstatus.php`（无需登录）

**实测响应**（version 为**对象**，非字符串；顶层无 data 包裹）：
```json
{
  "success": true,
  "online": true,
  "players": { "online": 10, "max": 100 },
  "version": { "name": "1.21.1", "protocol": 767 },
  "motd": "欢迎来到万驹同源服务器",
  "from_cache": false
}
```
查询失败时 `success:false, online:false`，字段仍完整返回。有 60 秒缓存（`MCSTATUS_CACHE_TIME`）。

### 7.6 性能数据 `performance.php`（⚠️ 结构已修正）

**端点**：`GET /api/performance.php`（无需登录）

**实测响应**（v1.x 文档的 `disk/network` 字段**不存在**）：
```json
{
  "success": true,
  "timestamp": "2026-08-17 10:39:07",
  "data": { "players": 5, "cpu": 50.9, "memory": 32.5 }
}
```
数据追加至 `data/performance_data.json`（FIFO，默认保留 50 点）。

### 7.7 性能图表 `get_performance.php`（v5.8+ 新增）

**端点**：`GET /api/get_performance.php`（无需登录）

**响应**：Chart.js 可直接消费的格式 `data: {labels[], players[], cpu[], memory[]}`。

### 7.8 健康检查 `health.php`（⚠️ 结构已修正）

**端点**：`GET /api/health.php`（无需登录）

**实测响应**：
```json
{ "success": true, "message": "Server is running", "status": "ok", "server": "PHP", "version": "8.1.34", "timestamp": "2026-08-17 10:39:07" }
```

### 7.9 系统信息 `system.php`（v5.8+ 新增）

**端点**：`GET /api/system.php`（需登录）

**响应**：`data` 含 MCSManager 状态、服务器在线时长（`format_uptime`）、系统概览等。

### 7.10 子服一览 `mc-instances.php`（v5.8+ 新增）

**端点**：`GET /api/mc-instances.php`（无需登录，只读代理）

**响应**：`data: {meta: {total, ...}, instances: [{name, status, version, core, online}]}`。10 秒缓存，展示内容由 `config.php` 的 `MC_DISPLAY_INSTANCES` 等三个数组配置驱动（见 CONFIGURATION.md）。

### 7.11 数据库查询 `db_test.php`（v5.8+ 新增）

**端点**：`GET/POST /api/db_test.php`（需登录 + **管理员**）

| action | 说明 |
|--------|------|
| `get_tables` | 表列表 |
| `get_table_structure` | 表结构（`table`） |
| `get_table_data` | 表数据分页（`table, page`） |
| `insert` / `update` / `delete` | 行操作 |
| `backup` | 备份 |

## 8. 聊天中心 API（v5.8+ 新增）

**端点**：`GET/POST /api/message.php`（需登录）

**GET actions**：

| action | 参数 | 说明 |
|--------|------|------|
| `list` | — | 会话列表 `data.conversations[]` |
| `history` | `conv_id` | 会话消息 `data.messages[]`（403 非参与者） |
| `unread` | — | 未读总数 `data.total` |
| `search` | `q` | 搜索用户 `data.users[]`（最多 10） |

**POST actions**（JSON body）：

| action | 参数 | 说明 |
|--------|------|------|
| `send` | `conv_id, content` | 发送消息（≤2000 字） |
| `start_private` | `to` | 发起私聊 |
| `create_group` | `name, participants[]` | 创建群聊 |
| `mark_read` | `conv_id` | 标记已读 |
| `add_member` | `conv_id, username` | 添加群成员 |
| `leave_group` | `conv_id` | 退出群聊 |

数据存储：`data/messages.json`（会话结构含 `type` group/private、`name`、`participants`）。

## 9. 问卷 API（v5.8+ 新增）

**端点**：`GET/POST /api/questionnaire.php`（登录态读取；管理操作需 admin）

| action | 方法 | 权限 | 说明 |
|--------|------|------|------|
| `list` | GET | 登录 | 问卷列表（`data.questionnaires[]`） |
| `get` | GET | 登录 | 问卷详情（`id`） |
| `results` | GET | 登录 | 结果统计（`id`） |
| `submit` | POST | 登录，每人限一次 | 提交答案（`id, answers{}`） |
| `create` | POST | admin | 创建（`title, questions[]`，题型 `radio/checkbox/text`） |
| `update` | POST | admin | 更新（`id, title, questions[]`） |
| `delete` | POST | admin | 删除（`id`） |
| `toggle` | POST | admin | 开关（`id`，active/closed） |
| `export` | GET | admin | 结果 CSV 导出（`id`） |

## 10. 商城与积分 API（v5.8+ 新增/修正）

### 10.1 积分商城 `points/shop.php`

**端点**：`GET/POST /api/points/shop.php`

| action | 权限 | 说明 |
|--------|------|------|
| `get_products` | 公开 | 商品列表（`data.products[]`） |
| `buy_product` | 需登录 | 购买（`product_id`），校验等级/库存/积分 |

商品数据从 `data/shop_items/`（PHP/JSON 文件）加载。

### 10.2 商城管理 `points/shop_admin.php`

**端点**：`GET/POST /api/points/shop_admin.php`（需登录 + **管理员**）

| action | 说明 |
|--------|------|
| `list` / `create` / `update` / `delete` / `toggle` | 商品 CRUD 与上下架 |

### 10.3 积分用户操作 `points/user.php`

**端点**：`GET/POST /api/points/user.php`（需登录 + **管理员**）

| action | 说明 |
|--------|------|
| `get_user_info` | 查询用户积分/经验 |
| `add_points` / `reduce_points` | 增减积分 |
| `add_experience` | 增加经验 |

### 10.4 商城 `shop/api.php`（旧版商城）

**端点**：`GET/POST /api/shop/api.php`（需登录，`add_points`/`reduce_points` 需管理员）

| action | 说明 |
|--------|------|
| `get_items` / `get_item_detail` | 商品查询 |
| `exchange` / `use_item` | 兑换 / 使用物品 |
| `get_inventory` / `get_points` | 背包 / 积分 |
| `add_points` / `reduce_points` | 增减积分（admin） |

### 10.5 积分兑换 `exchange.php`（⚠️ action 已修正）

**端点**：`POST /api/exchange.php`（需登录）

| action | 说明 |
|--------|------|
| `get_products` | 兑换商品列表 |
| `exchange` | 兑换（`item`），子类型：`exp` / `double_exp` / `chest` / `resign` / `points_boost` |
| `get_inventory` | 我的背包 |
| `use_item` | 使用物品（`item`） |

> v1.x 文档的 `item/quantity` 参数不完整，实际兑换通过 `action=exchange&item={type}` 完成，具体商品参数以源码 `switch` 为准。

### 10.6 黄金券排行榜 `golden-ticket.php`（⚠️ 已修正）

**端点**：`GET /api/golden-ticket.php`（无需登录）

**说明**：仅提供**黄金券排行榜**，查询 MC 服务器 `playerpoints` 数据库（`playerpoints_points` 表）。

| 参数 | 说明 |
|------|------|
| `action=get_ranking` | 排行榜（可选 `page`, `limit`，默认 1/10） |
| （无参数） | 默认返回第 1 页排行榜 |

**响应**：`data: {data: [{username, uuid, points}], total, page, limit}`。

> ⚠️ v1.x 文档的「GET 余额 / POST 兑换」**不存在**，余额/兑换由 `exchange.php` / `points/*` 承担。

## 11. 充值 API（爱发电）

### 11.1 Webhook `aifadian/api/webhook.php`

**端点**：`POST /api/aifadian/api/webhook.php`（爱发电服务器回调，无需登录）

**参数**：爱发电回调参数（订单详情，`AFDIAN_WEBHOOK_VERIFY_SIGN` 开启时验签）。

**响应**：`{"ec": 200, "em": "success"}`。

### 11.2 处理订单 `aifadian/process_orders.php`

**端点**：`GET /api/aifadian/process_orders.php`（需登录 + **管理员**）

**响应**：`{success: true, message: "...", data: {processed, success}}`。

### 11.3 定时更新 `aifadian/cron_update.php`（v5.8+ 新增）

**端点**：`GET /api/aifadian/cron_update.php`（定时任务入口，`AFDIAN_CRON_*` 配置驱动，需内部调用）

**响应**：`{success: true, data: {processed, ...}}`。

### 11.4 自动更新 `aifadian/auto_update.php`（v5.8+ 新增）

**端点**：`GET /api/aifadian/auto_update.php`（自动更新入口，逻辑同 cron）

### 11.5 订单查询 `aifadian/api/api.php`（v5.8+ 新增）

**端点**：`GET/POST /api/aifadian/api/api.php`（需登录 + **管理员**）

| action | 说明 |
|--------|------|
| `get_orders` / `get_processed_orders` | 订单列表 |
| `get_order_status` / `query_order` | 订单状态查询 |
| `get_plan` / `query_plan` | 方案查询 |
| `process_orders` / `ping` / `pending` / `completed` / `failed` | 订单处理与统计 |
| `get_statistics` | 统计数据 |
| `get_player_points` | 玩家点数 |

## 12. 招募 API（v5.8+ 新增）

**端点**：`GET/POST /api/recruit.php`

| action | 方法 | 权限 | 说明 |
|--------|------|------|------|
| `submit` | POST | 公开 | 提交申请（`name, position, contact, ...`） |
| `list` | GET | 管理员 | 申请列表 |
| `update_status` | POST | 管理员 | 更新审核状态 |
| `delete` | POST | 管理员 | 删除申请 |

> 注：本接口错误响应使用 `error` 字段而非 `message`。

## 13. AI 与 MCP API（v5.8+ 新增）

### 13.1 AI 后端代理 `ai/api.php`

统一 LLM 代理（API Key 只在服务端），前端不直连上游。支持**非流式 JSON**与**SSE 流式**（`stream: true`）。

**端点**：`GET/POST /api/ai/api.php`

**POST actions**（JSON body，`persona` 可选 `customer`/`admin`）：

| action | 说明 |
|--------|------|
| `send_message` | 发送消息（`message, conversation_id, model, stream`），流式时返回 SSE |
| `test_connection` | 测试模型连通性（不依赖 message） |
| `system_prompt` | 获取人格 system prompt（GET `?action=system_prompt&persona=`） |
| `list_ai_conversations` / `get_ai_conversation` | 会话列表 / 详情 |
| `delete_ai_conversation` / `clear_ai_conversation` | 删除 / 清空会话 |
| `rename_ai_conversation` | 重命名会话 |
| `append_ai_message` / `replace_last_ai_message` / `set_ai_messages` | 消息写入/修正 |

**模型路由**：EYPA/AI 平台模型（`Echo-*`、`DeepSeek-V4-*`、`GLM-*`、`MiniMax-*`）走 `ECHO_API_URL`；DeepSeek 官方（`deepseek-chat`/`deepseek-reasoner`）走 `DEEPSEEK_API_URL`；`isEypaAiModel()` 判定。

### 13.2 客服 MCP 通道 `ai/mcp-customer.php`

**端点**：`POST /api/ai/mcp-customer.php`（客服页专用，服务端强制 `customer` persona）

**说明**：仅放行 5 个只读工具白名单，不可绕过。协议与工具清单见 [DEVELOPMENT.md](DEVELOPMENT.md)。

### 13.3 MCP 体系

站内管理通道 `mcp/mcp-server.php`、远程通道 `mcp/remote.php`（Streamable HTTP + Service Key）为 **MCP Server**，非本文件范围，详见 [DEVELOPMENT.md](DEVELOPMENT.md)「AI Agent 远程框架」。

## 14. 管理后台 API（v5.8+ 新增）

以下端点仅供 `tools/admin-hub.html` 使用（需登录 + **管理员**）：

| 端点 | 说明 |
|------|------|
| `api/admin/backup_api.php` | 数据备份（action：`all` / `content` / `users`） |
| `api/admin/data_api.php` | 数据管理（action：`overview` / `view` / `download` / `upload` / `delete` / `categories` / `files` / `post` / `reply` / `user`） |
| `api/admin/mail_api.php` | 邮件发送（action：`send` / `test`） |

## 15. 其他 API

### 15.1 地图代理 `map_proxy.php`（⚠️ 参数已修正）

**端点**：`GET /api/map_proxy.php?url={url}`（无需登录）

**说明**：代理转发任意 HTTP 图片/资源 URL（含 UA 伪装与 5 分钟缓存），**不是** v1.x 文档所写的 x/z 坐标接口。

> ⚠️ 安全风险：该端点无鉴权且可代理任意 URL，存在被当作开放代理/SSRF 的隐患，建议加白名单或鉴权（见「18. 源码核对发现的问题」）。

### 15.2 玩家点数 `playerpoints.php`（⚠️ action 已修正）

**端点**：`GET /api/playerpoints.php`

| action | 说明 |
|--------|------|
| `get_ranking`（默认） | 排行榜（分页 `page/limit`） |
| `get_table_structure` / `get_table_data` | 数据库表查看 |
| `get_combined_data` | 合并数据 |

### 15.3 NFC 设备登录 `nfc/device_login.php`（v5.8+ 新增）

**端点**：`POST /api/nfc/device_login.php`

**说明**：NFC 刷卡设备登录入口（`api/nfc/migrate_add_device_fields.php` 为字段迁移脚本，非对外端点）。

### 15.4 数据初始化 `init_data.php`

**端点**：`GET /api/init_data.php`（部署/维护用）

**说明**：触发 `data-init/` 模板幂等初始化（与 `includes/data-init.php` 同引擎）。

### 15.5 测试端点

- `api/test.php`：连通性测试
- `api/init_data.php`：数据初始化（见上）

## 16. 权限总览

| API 类别 | 权限要求 |
|---------|---------|
| 认证（注册/登录/EYPA 登录/找回密码） | 无需登录（但需滑块验证） |
| 用户资料 / 签到 / 聊天 / 问卷（读） / 商城（购买） | 需要登录 |
| 论坛（发帖/回复/编辑/删除/投票/点赞/收藏） | 需要登录（编辑/删除仅作者或 admin） |
| 通知 / 系统信息 / 图片 | 需要登录 |
| 公告管理 / 发送通知 / 用户管理 / 问卷管理 / 商城管理 / 数据管理 / 备份 / 邮件 / AI 会话（admin） | 需要管理员 |
| 服务器状态 / 性能 / 健康检查 / 子服一览 / 排行榜 / 地图代理 | 无需登录 |


> 注：由AI整理生成，暂未进行人工审核，仅供参考！！！



