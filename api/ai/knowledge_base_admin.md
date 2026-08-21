# 万驹同源 管理运维知识库

## 服务器基本信息
- 服务器名称：万驹同源（PonyConsanguinity）
- 官网：https://mcpc.goldenapplepie.xyz/
- 服务器地址：mc.eqmemory.cn（推荐 mcbgp.eqmemory.cn）
- 支持版本：1.8.x ~ 1.21.1，最佳 1.18.x ~ 1.20.1
- 类型：Java 插件服，玩法含生存、创造、小游戏

## 面板与节点（MCSManager）
- 面板通过 MCSManager 管理，MCP 工具封装了其 API。
- `get_dashboard`：查看面板概览、系统 CPU/内存、节点状态。
- `get_nodes_status`：查看每个节点的 CPU/内存/实例数。
- `list_instances`：列出所有游戏实例（含运行状态、玩家数）。
- `get_instance_detail`：单实例配置、进程资源占用（CPU/内存）、启动次数。
- `get_instance_log`：实例最近控制台日志，用于排查启动失败/卡顿。

## 实例操作
- `start_instance` / `stop_instance` / `restart_instance`：需提供 uuid 与 daemonId（节点 UUID）。
  - 务必先通过 list_instances 取得目标实例的 uuid 与 daemonId，再操作，不要凭记忆填写。
  - 停止/重启生产服前确认无重要进行中活动，必要时先公告。
- `send_command`：向实例发送控制台命令。
  - 常用：`/say <消息>` 全服广播、`/op <玩家>` 给予权限、`/deop <玩家>`、`/whitelist add <玩家>`、`/ban <玩家>`、`/kick <玩家>`。
  - 涉及 /op、/ban、/whitelist 属于敏感权限，执行前必须提示风险并确认。

## 公告与通知
- `list_announcements` / `get_announcement`：查阅现有公告。
- `write_announcement`：创建或更新公告。type 取值：update(版本更新) / event(活动) / notice(通知)。提供 id 为更新，不提供 id 为新建。支持 Markdown 正文。
- `send_notification`：向全站登录用户推送系统通知。type：system / event / update。

## 经济系统（供查询答复）
- 黄金券：全服通用稀有货币。梦幻币：生存服专用。任务币：每日任务获得。梦幻结晶：用于抽卡，需用黄金券购买。
- **黄金券在数据库中的载体是 PlayerPoints 插件的 points（1 元 = 100 黄金券 = 100 PlayerPoints 点数）。**

## 安全注意事项
- 管理操作不可逆或影响全服，执行前在对话中明确将要做什么。
- 优先用只读工具核实状态，再执行写操作。
- 不要在公开场合泄露实例 uuid / daemonId 之外的凭据。

---

# 数据库篇（mcsqlserver 生产库）

AI 助手可通过 `list_tables` / `describe_table` / `run_readonly_query` 直接读取本库。
**金额换算基准：1 元 = 100 黄金券 = 100 PlayerPoints 点数；反推金额 = points_added / 100。**

## 数据库总览（16 表速查 · 含状态）
本库共 16 张表，按状态分类如下。**查询 🟡/🔴 表时必须向用户声明其状态，不得当作当前权威数据**：
- 🟢 **afdian_orders**：爱发电订单主表，当前充值系统的权威订单来源（订单↔玩家以游戏名绑定）
- 🟢 **processed_orders**：订单处理幂等表，防止同一订单重复到账
- 🟢 **playerpoints_points**：玩家当前黄金券点数余额（核心经济表，最重要）
- 🟢 **playerpoints_username_cache**：玩家游戏名 ↔ UUID 缓存，充值到账与查询的关键桥梁
- 🟢 **playerpoints_migrations**：PlayerPoints 插件迁移版本记录（仅一列，无业务查询价值）
- 🟡 **SelfHomeMain_Servers**：家园园区配置（仅测试数据，后续未正式接入数据库）
- 🟡 **SelfHomeMain_Users**：家园玩家数据（仅测试数据，未正式接入）
- 🟡 **VentureChat**：生存服聊天记录归档（后续暂关停记录，含玩家聊天隐私）
- 🟡 **users**：官网用户系统（测试阶段产物，非生产权威用户源，且含密码哈希属敏感表）
- 🟡 **forum_categories**：官网论坛板块（测试后暂未正式开启）
- 🟡 **forum_posts**：官网论坛帖子（测试后暂未正式开启）
- 🟡 **forum_comments**：官网论坛评论（测试后暂未正式开启）
- 🟡 **forum_tags**：官网论坛标签（测试后暂未正式开启）
- 🟡 **forum_post_tags**：官网论坛帖子-标签多对多（测试后暂未正式开启）
- 🔴 **SWEETAFDIAN_ORDERS**：甜爱发电订单（已弃用，历史残留，充值以 afdian_orders 为准）
- 🔴 **SWEETAFDIAN_SCHEDULE**：甜爱发电定时任务（已弃用，历史残留）

## 表状态图例（LLM 查询前必读）
| 状态 | 含义 | 查询时的处理 |
|------|------|--------------|
| 🟢 活跃 | 生产在用，数据权威 | 可直接作为答复依据 |
| 🟡 测试 / 暂未接入 | 仅测试数据、或功能未正式上线 / 暂关停记录 | 可查，但答复中必须向用户声明"该表为测试数据 / 暂未接入 / 已停记"，不得当作当前权威数据 |
| 🔴 弃用 | 历史插件，已不再使用 | 仅作历史参考，优先用 🟢 活跃表；明确告知用户此表已弃用 |

## 🟢 afdian_orders（爱发电订单主表 · 生产权威）
- 当前充值系统的权威订单来源（对标爱发电官方方案）。
- `id`(int, PRI, auto_increment)
- `out_trade_no`(varchar255, UNI)：爱发电订单号，唯一
- `remark`(varchar255, 可空)：玩家的 **Minecraft 游戏名**——订单↔玩家的绑定键（不是 UUID！）
- `create_time`(int, 可空)：爱发电推送的订单时间（Unix 时间戳，旧字段）
- `plan_title`(varchar255)：方案标题（如"黄金券"）
- `plan_id`(varchar255)：方案 ID，黄金券对应值见 config（`golden_ticket`）
- `sku_count`(int, 默认 1)：数量
- `points_added`(int, 可空)：本次充值增加的黄金券点数（1元=100点）；失败单为 0 或 NULL
- `player_uuid`(varchar255)：玩家 UUID（充值成功写入）
- `player_username`(varchar255)：玩家名（冗余）
- `status`(varchar)：`pending`(待处理) / `processing`(处理中) / `completed`(已到账) / `failed`(失败，见 error_message)
- `error_message`(text)：失败原因（玩家不存在 / 未进服等）
- `processed_at`(timestamp)：到账时间
- `created_at`(timestamp)：入库时间（**优先用此字段**判断时间）

## 🟢 processed_orders（订单处理幂等表）
- 与 afdian_orders 并行，记录已处理订单，防止重复到账。
- `id`(int, PRI, auto_increment)
- `order_id`(varchar255, UNI)：订单号，对应 afdian_orders.out_trade_no
- `processed_at`(timestamp, 默认 CURRENT_TIMESTAMP)

## 🟢 playerpoints_points（核心经济表 · 最重要）
- 玩家当前黄金券点数余额。
- `id`(int, PRI, auto_increment)
- `uuid`(varchar36, UNI, 非空)：玩家 UUID
- `points`(int, 非空)：当前黄金券点数余额

## 🟢 playerpoints_username_cache（玩家名↔UUID 缓存）
- `uuid`(varchar36, PRI, 非空)：玩家 UUID
- `username`(varchar30, MUL, 非空)：玩家游戏名
- 作用：游戏名 → UUID 的映射，充值到账与查询的关键桥梁

## 🟢 playerpoints_migrations（插件迁移版本记录）
- `migration_version`(int, 非空)：仅一列，记录 PlayerPoints 插件迁移版本，无业务查询价值

## 🟡 SelfHomeMain_Servers（家园园区配置 · 测试）
- 来源：SelfHomeMain 插件（服务器内的家园/领地插件）。
- 状态：**仅保留测试数据，后续未正式接入数据库**，不要当作真实园区配置。
- `Server`(varchar100)：园区/服务器标识
- `Amount`(double)：数值字段（具体业务含义以插件为准）

## 🟡 SelfHomeMain_Users（家园玩家数据 · 测试）
- 来源：SelfHomeMain 插件。
- 状态：同 SelfHomeMain_Servers，**未正式接入，仅测试数据**。
- 字段均为 varchar(255)，语义以插件为准，如实呈现原始值，不臆测：
  - `Name` 玩家名；`Members`/`Denys`/`OP`/`Public` 成员/拒绝名单/管理员/公开
  - `Level` 家园等级；`pvp`/`pickup`/`dropitem` 权限开关
  - `Server` 归属服务器；`locktime`/`lockweather`/`time`/`visittime`/`limitblock` 锁定与访问
  - `X`/`Y`/`Z` 家园坐标；`flowers`/`popularity`/`gifts`/`advertisement`/`icon` 装饰与社交

## 🟡 VentureChat（生存服聊天记录 · 暂关停记录）
- 来源：VentureChat 插件归档的聊天记录。
- 状态：**后续暂时关停记录**，历史数据可能不全，查询仅供合规排查。
- `ID`(bigint unsigned, PRI, auto_increment)
- `ChatTime`(text)：聊天时间
- `UUID`(text)、`Name`(text)：发言玩家
- `Server`(text)、`Channel`(text)：所在服务器 / 频道
- `Text`(text)：聊天内容
- `Type`(text)
- ⚠️ 隐私：含玩家聊天内容（`Text`），仅用于合规排查，勿外传或公开摘要

## 🟡 users（官网用户系统 · 测试数据，敏感）
- 来源：官网用户系统对接数据库的测试（与 forum 测试同期产物）。
- 状态：**测试阶段产物，非生产权威用户源**；且含密码哈希，属敏感表。
- `id`(int, PRI, auto_increment)
- `username`(varchar50, UNI, 非空)
- `password`(varchar255, 非空, ⚠️ **密码哈希，禁止查询**)
- `email`(varchar100)
- `avatar`(varchar255, 默认 default.webp)
- `role`(enum('user','mod','admin'))
- `points`(int, 默认 0)
- `created_at`(timestamp, 默认 CURRENT_TIMESTAMP)
- ⚠️ 安全：`password` 列受 `run_readonly_query` 自动屏蔽；查此表时显式 SELECT 除 password 外的列

## 🟡 forum 系列（官网论坛 · 测试后暂未开启）
- 来源：官网论坛与数据库对接的测试。
- 状态：**测试后暂未正式开启**，当前数据为测试残留，勿当作线上论坛数据。
- `forum_categories`：板块 `id`(PRI) `name`(varchar50) `description`(varchar255) `icon`(varchar50) `group_name`(varchar50, 默认 其他) `sort_order`(int, 默认 0)
- `forum_posts`：帖子 `id`(PRI) `category_id`(int, MUL) `user_id`(int) `title`(varchar255) `content`(longtext) `visibility`(varchar20, 默认 public) `views`(int, 默认 0) `is_pinned`(tinyint1) `is_locked`(tinyint1) `created_at`(timestamp)
- `forum_comments`：评论 `id`(PRI) `post_id`(int, MUL) `user_id`(int) `content`(text) `created_at`(timestamp)
- `forum_tags`：标签 `id`(PRI) `name`(varchar50, UNI) `usage_count`(int, 默认 1) `created_at`(timestamp)
- `forum_post_tags`：帖子-标签多对多 `post_id`(int, PRI) `tag_id`(int, PRI)

## 🔴 SWEETAFDIAN_ORDERS（甜爱发电订单 · 已弃用）
- 来源：SweetAfdian 插件（对标爱发电的另一套订单方案）。
- 状态：**已弃用**，充值以 afdian_orders 为准；此表为历史残留。
- `out_trade_no`(varchar36, PRI)：订单号
- `order`(longtext)：原始订单 JSON（未解析）

## 🔴 SWEETAFDIAN_SCHEDULE（甜爱发电定时任务 · 已弃用）
- 来源：SweetAfdian 插件定时任务配置。
- 状态：**已弃用**，同 SWEETAFDIAN_ORDERS。
- `name`(varchar64)：任务名
- `data`(longtext)：任务配置 JSON

## 表间关系（仅 🟢 活跃表构成生产数据流）
- `afdian_orders.remark` ↔ `playerpoints_username_cache.username`（订单通过游戏名绑定玩家）
- `playerpoints_username_cache.uuid` ↔ `playerpoints_points.uuid`（名↔UUID↔点数）
- `afdian_orders.player_uuid` ↔ `playerpoints_points.uuid`（充值成功后直接写 UUID）
- `processed_orders.order_id` ↔ `afdian_orders.out_trade_no`（幂等）
- 历史/测试表关系（forum↔users、SelfHomeMain 内部）不构成生产权威链路，查询时仅作参考

## 业务规则（AI 总结时必须遵循）
- 1 元 = 100 黄金券 = 100 PlayerPoints 点数；反推金额 = points_added / 100
- 黄金券方案 `plan_id` 见 config（`golden_ticket`）；轮询/Webhook 仅处理该方案
- 订单 `status`：pending→(处理)→completed / failed；failed 单在玩家"进服使游戏名进入 username_cache"后自动重试
- 幂等：同一 `out_trade_no` 不会重复到账（`processed_orders` 唯一约束）
- 当前 `afdian_orders` **无独立 RMB 金额列**，金额一律由点数反推
- 玩家身份以 `remark`（游戏名）或 `uuid` 为准，不要用 `player_username` 冗余列做关联键

## AI 使用数据库工具的规范
- **标准维度优先用预定义工具**：
  - 单玩家充值总额：`player_recharge_summary`（客服/助手均可用，严格单玩家维度）
  - 玩家订单明细：`query_my_orders`（客服可用）/ `search_orders`（助手）
  - 玩家失败排查：`troubleshoot_order`（客服可用）/ `get_order_detail`（助手，按订单号）
  - 玩家点数/存在性：`player_lookup`（助手）/ `search_players`（助手）
  - 全服统计（仅助手）：`recharge_stats`（总充值）、`list_failed_orders`（失败单清单）
  - 任意维度汇总：`run_readonly_query` 写 SELECT（护栏：仅 SELECT / 禁写 / 禁 password / 限 200 行）
- **查询 🟡/🔴 表时必须向用户声明其状态**（测试 / 暂未接入 / 已弃用），不得当作当前权威数据作答
- 不确定表结构时，先用 `list_tables` + `describe_table` 识别，再查询
- 所有结论必须基于工具真实返回，禁止臆测字段含义
