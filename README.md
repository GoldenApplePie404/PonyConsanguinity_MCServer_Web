# 万驹同源服务器官网

<p align="center">
  <img src="assets/img/pc_logo2.webp" alt="Pony Consanguinity Logo" class="no-hover" style="max-width: 500px; width: 90%; border-radius: 10px; pointer-events: none;">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP Version">
  <img src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white" alt="HTML5">
  <img src="https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white" alt="CSS3">
  <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript">
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License">
  <img src="https://img.shields.io/badge/Version-5.8.1-blue?style=for-the-badge" alt="Version">
  <img src="https://img.shields.io/badge/Status-Active-success?style=for-the-badge" alt="Status">
</p>

## 目录

<p align="center">
  <a href="#项目简介">项目简介</a> •
  <a href="#功能特性">功能特性</a> •
  <a href="#页面列表">页面列表</a> •
  <a href="#快速开始">快速开始</a> •
  <a href="#技术栈第三方库">技术栈</a> •
  <a href="#常用功能速览">常用功能速览</a> •
  <a href="#文档导航">文档导航</a>
</p>

## 项目简介

**万驹同源（PonyConsanguinity）官网**是一个专属于万驹同源 Minecraft 服务器官网，致力于为广大玩家提供优质的社区环境。

## 功能特性

- **服务器介绍**：详细的服务器信息和玩法介绍
- **实时状态监控**：查看服务器在线人数、CPU、内存使用情况；「服务器状态」卡展示各子服运行状态（名称/状态/版本/核心/人数，配置驱动）
- **性能数据图表**：展示服务器性能变化趋势
- **卫星云图**：基于万驹同源的特色，专门为生存服打造的卫星云图页面
- **玩家经济排行**：基于万驹同源的特色，展示出服务器的玩家经济排行榜
- **论坛系统**：支持 Markdown 格式的帖子发布和回复，具备分类功能，支持 LaTeX 公式与 Mermaid 图表，代码块语法高亮（支持行号显示和折叠展开），且带有可视化编辑器
- **论坛增强功能**：帖子分页、回复分页、帖子编辑、回复删除、文章目录预览、发布预览
- **图片系统**：文章图片上传、图库管理、图片压缩处理
- **用户系统**：注册、登录、个人中心、邮箱验证，用户分为管理员和普通用户，搭配等级与积分系统，支持 EYPA 第三方注册登录
- **EYPA OAuth 登录**：支持通过 EYPA 第三方 OAuth 登录，自动注册/登录/绑定/解绑，完整的授权码流程
- **问卷系统**：管理员可创建问卷（单选/多选/填空），用户参与投票，支持结果统计与 CSV 导出，每人限答一次
- **管理员后台**：统一管理后台，涵盖用户管理、公告管理、图库管理、通知发送、问卷管理、数据库查询、MCP 控制台、AI 配置等功能
- **后台邮件发送**：管理员可向指定用户群发或手动输入邮箱发送邮件，支持 Markdown 与 HTML 双模式编辑与实时预览
- **签到系统**：每日签到获取积分，验证邮箱后方可签到
- **积分商城**：积分兑换系统（初步开发），可以购买一些道具，如可以加快站内用户升级、甚至可以购买黄金券
- **通知系统**：系统通知推送、用户通知管理
- **公告系统**：管理员可发布和管理公告
- **皮肤站**：自定义玩家皮肤，支持玩家上传和管理自己的皮肤
- **充值系统**：爱发电支付集成，支持黄金券自动充值功能，不依赖任何其它的支付插件
- **音乐播放器**：内置音乐播放器，可以没事听听曲子（x）
- **AI 客服与管理 AI**：面向玩家的只读客服 AI（`pages/ai/kefu.html`）与面向管理员的全量 AI 助手（`tools/admin-hub.html#/ai`），双人格隔离，详见「常用功能速览」

## 页面列表

### 概述

项目包含 30+ 个功能页面，涵盖服务器展示、社区互动、用户管理、后台管理等多个维度。以下是所有页面的完整列表和功能说明。

### 页面分类

#### 核心页面

- **index.html** - 网站首页，展示服务器主要信息和导航

#### 游戏功能页面

- **pages/map.html** - 卫星地图页面，实时查看万驹同源生存服的地图和玩家位置，需要使用 [Dynmap](https://modrinth.com/plugin/dynmap) 插件
- **pages/skin.html** - 皮肤站页面，自定义玩家皮肤和披风。当前作为跳转引导页，可通过 [Blessing Skin Server](https://github.com/bs-community/blessing-skin-server) 搭建独立皮肤站
- **pages/survival.html** - 生存服介绍页面，介绍生存玩法和特色功能
- **pages/mod.html** - 模组服介绍页面（炉边茶社），含实时状态、全景预览与整合包下载入口
- **pages/playerpoints.html** - PlayerPoints 插件数据库连接测试页（已弃用）

#### 社区功能页面

- **pages/forum.html** - 论坛首页，浏览和发布帖子
- **pages/post-detail.html** - 帖子详情页，查看帖子内容和回复，基于 Markdown 文件解析生成，支持 LaTeX 公式与 Mermaid 图表，支持图片上传
- **pages/announcement.html** - 公告列表页，查看服务器公告
- **pages/announcement-detail.html** - 公告详情页，查看公告详细内容，基于 Markdown 文件解析生成
- **pages/shop.html** - 积分商城页面，用户浏览商品、加入购物车、兑换积分商品
- **pages/questionnaire.html** - 问卷中心页面，参与问卷投票，查看结果统计
- **pages/gallery-history.html** - 服务器回忆廊页面，以「时间档案馆」形式展示服务器大事记，按年份分馆陈列（完善中）
- **pages/resources.html** - 服务器资源站页面，下载资源包、地图文件、工具等（开发中）

#### 用户功能页面

- **pages/login.html** - 登录页面，用户登录（支持 EYPA 第三方登录）
- **pages/register_success.html** - 注册成功页面，提示用户注册完成并引导前往邮箱验证
- **pages/profile.html** - 个人中心，管理个人信息、查看通知、每日签到、积分商城/问卷/回忆廊/资源站入口卡片、EYPA 绑定/解绑
- **pages/status.html** - 服务器状态页，查看服务器实时状态（注：黄金券排行榜采用 PlayerPoints 插件的数据库，需在插件中配置）
- **pages/complete_profile.html** - 完善个人信息页，使用 EYPA 第三方注册后跳转至此页面，补全用户名等资料
- **pages/eypa_login_success.html** - EYPA 登录成功页，登录成功后跳转至此页
- **pages/payment.html** - 充值中心页，黄金券充值功能，基于爱发电支付集成
- **pages/messages.html** - 聊天中心页，支持群组聊天、私聊等即时通讯功能
- **pages/verify.html** - 邮箱验证结果页，显示邮箱验证成功/失败状态

#### 管理功能页面

- **pages/logs.html** - 开发日志页，记录万驹同源服务器的开发历程与版本更新
- **pages/rules.html** - 服务器规则页，查看游戏规则与社区守则
- **tools/admin-hub.html** - 统一管理后台，涵盖仪表盘、服务器管理、AI 助手（管理 AI）、数据库管理、订单更新、公告管理、通知发送、数据管理、图库管理、用户管理（含角色升降级）、投票管理、聊天监控、问卷管理、商城管理、AI 配置、MCP 控制台、QQ 机器人等功能面板

以下为已被 admin-hub 取代的独立管理工具（保留但不再维护）：

- ~~**tools/announcement-manager.html**~~ → 由 admin-hub 公告管理面板替代
- ~~**tools/user-management.html**~~ → 由 admin-hub 用户管理面板替代
- ~~**tools/gallery-management.html**~~ → 由 admin-hub 图库管理面板替代
- ~~**tools/auto_update.html**~~ → 由 admin-hub 订单更新面板替代
- ~~**tools/data-management.html**~~ → 由 admin-hub 数据管理面板替代
- ~~**tools/mcp-console.html**~~ → 由 admin-hub MCP 控制台面板替代
- ~~**pages/admin/data-management.html**~~ → 由 admin-hub 数据管理面板替代

#### 法律页面

- **pages/privacy-policy.html** - 隐私政策页，了解隐私保护政策
- **pages/user-agreement.html** - 用户协议页，了解使用条款
- **pages/disclaimer.html** - 免责声明页，了解责任限制

#### 其他页面

- **pages/404.html** - 404 错误页面，页面未找到提示
- **pages/recruit.html** - 团队招募页面，展示服务器团队招募信息与加入方式
- **pages/ai/kefu.html** - 万驹同源 AI 客服页面，面向普通玩家的客服，支持服务器状态查询、公告查看等只读功能
- **pages/db_test.html** - 数据库连接测试页面，仅供开发调试使用
- **pages/upload.html** - 帖子图片上传测试页面，仅供测试使用
- **pages/template-example.html** - 页面模板示例，供开发者参考页面结构

## 快速开始

### 环境要求

- 支持 PHP 的 Web 服务器（如 Apache、Nginx），建议使用PHP 8.0+，且你需要在php.ini中开启mysqli、openssl、pdo_mysql扩展，可以参考`config/php.ini`

### 本地开发

1.  **克隆项目**
    ```bash
    git clone https://github.com/GoldenApplePie404/PonyConsanguinity_MCServer_Web.git
    cd PC_Web
    ```

2.  **启动本地服务器**
    -   使用 PHP 内置服务器
        ```bash
        php -S localhost:8000
        ```
    -   或使用其他静态文件服务器（建议不要使用vs code的live server，因为它会导致一些问题，推荐使用php或者python的http.server）

3.  **访问网站**

    打开浏览器访问 `http://localhost:8000`

### 使用启动脚本（run.bat）

如果不想手动敲命令，项目根目录提供了 `run.bat` 一键启动菜单（Windows）。双击运行后按提示输入数字选择模式：

| 选项 | 模式 | 说明 |
|------|------|------|
| `[1]` | Local Development | 本地开发：启动 `localhost:8000` 并自动打开浏览器，仅本机可访问 |
| `[2]` | Public Access (ngrok) | 公网穿透：通过 ngrok 把本地 `8000` 端口暴露到公网，需本机已安装 ngrok 且 `run.bat` 中已配置 authtoken |
| `[3]` | LAN / Hotspot Mode | 局域网/热点：监听 `0.0.0.0:8080`，同一 WiFi 或连接本机热点的手机/其他设备可访问 |
| `[4]` | Check & Init Data Directory | 数据目录初始化：校验 `data/` 完整性，并从 `data-init/` 模板自动补全缺失文件（已有数据不会被覆盖）；首次 clone 后或怀疑 `data/` 缺失时运行 |
| `[0]` | Exit | 退出 |

链接：[Ngrok](https://ngrok.com/)

> **提示**：选项 `[4]` 与后端自动初始化（`includes/data-init.php` 在首次访问时自动补齐 `data/`）互为补充，手动运行可用于主动修复或排查数据目录问题。

### 生产部署

- **前端配置**：
    -   项目已使用统一配置文件 `js/config.js`，会自动根据环境检测并设置正确的 API 地址
    -   本地开发环境（localhost/127.0.0.1）会自动使用 `http://localhost:8000`
    -   生产环境会自动使用相对路径 `/api`，无需手动修改配置
    -   如需自定义配置，可编辑 `js/config.js` 文件中的相关配置项

-  **后端配置**：
    -   所有后端配置已迁移到 `config/config.php`   ——v5.6
    -   编辑 `config/config.php` 配置数据库、API 密钥等信息
    -   详细配置说明请参考 [CONFIGURATION.md](CONFIGURATION.md)

- **部署环境测试**

    如果你想在部署环境中进行测试，可以尝试使用[ngrok](https://ngrok.com/)等工具将本地服务器暴露到公网，然后访问公网地址进行测试。

## 技术栈（第三方库）

注：为了能够流畅加载，以下第三方库已经做了本地化处理。

-   **Chart.js**：数据可视化图表库
-   **Font Awesome**：图标字体库
-   **Marked**：Markdown解析库
-   **Highlight.js**：代码块语法高亮库，支持行号显示和折叠展开功能
-   **PowerShell.js**：PowerShell样式代码块高亮
-   **KaTeX**：数学公式渲染库
-   **Mermaid**：流程图和图表渲染库
-   **PHPMailer**：邮件发送库
-   **Vditor**：Markdown 编辑器，可以进行可视化编辑和预览，支持代码块、表格等
-   **Pannellum**：360° 全景图查看器

## 常用功能速览

### 管理员后台

管理员后台提供了一个统一的管理界面，可以通过访问 `tools/admin-hub.html` 页面进入。涵盖：

- 仪表盘：管理员后台所有功能以及网站数据概览
- 服务器管理：将控制服务器面板的操作集成到后台，还能查看服务器的状态
- 数据库管理：数据库的管理面板，支持增删改查
- 订单更新：爱发电订单更新面板，用于手动和开启自动更新
- 公告管理：网站公告的管理面板，支持公告的增加、编辑、发送、删除等
- 发送通知：系统通知推送面板，管理员可向全体或指定用户发送通知消息
- 邮件发送：管理员邮件广播面板，可向选定用户或手动输入邮箱发送 HTML/Markdown 邮件，支持测试发送与逐封结果反馈
- 数据管理：分为帖子、用户、回复、备份四个小版块
- 图库管理：网站图片上传、删除、预览等管理功能
- 用户管理：查看所有用户信息（用户名、ID、邮箱、角色、积分、验证状态等），支持角色升降级
- 投票管理：社区投票的新建、编辑、删除、结果统计
- 聊天监控：服务器内玩家聊天记录的实时查看与管理
- 问卷管理：调查问卷的创建、编辑、开关、结果查看、CSV 导出
- AI 配置：AI 客服与管理 AI 的 API Key、模型选择、温度、Max Token 等参数配置
- AI 助手：管理员后台内置的 AI 管理助手，支持自然语言操作服务器，可通过 TOOL_CALL 循环执行 MCP 工具
- MCP 控制台：直接调用 MCP 工具，管理服务器实例
- QQ 机器人：QQ 机器人配置管理面板（基于 [Koishi](https://koishi.chat/zh-CN/) ）

### AI 助手与客服

万驹同源 AI 体系包含**两个独立人格**，共享底层模型引擎但权限完全隔离：

- **客服 AI**（面向普通玩家，只读）：`pages/ai/kefu.html`（个人中心左下角客服按钮也可进入）
- **管理 AI 助手**（面向管理员，全量）：`tools/admin-hub.html#/ai`（管理员后台侧栏「AI 助手」标签页）

客服通道由服务端强制 `customer` persona 白名单（仅 5 个只读 MCP 工具），**即使管理员登录客服页也无法执行写操作**。系统原理与配置详见 [DEVELOPMENT.md](DEVELOPMENT.md) 与 [CONFIGURATION.md](CONFIGURATION.md)。

### 卫星地图 / 充值 / 邮箱验证 / 状态页

| 功能 | 访问入口 | 配置位置 |
|---|---|---|
| 卫星地图 | `pages/map.html`（基于 Dynmap） | 地图地址配置见 [CONFIGURATION.md](CONFIGURATION.md) |
| 充值系统（爱发电） | `pages/payment.html` | 爱发电/Webhook 配置见 [CONFIGURATION.md](CONFIGURATION.md) |
| 邮箱验证 | 注册后自动引导 | SMTP 配置见 [CONFIGURATION.md](CONFIGURATION.md) |
| 状态页子服一览 | `pages/status.html` | 子服卡片配置见 [CONFIGURATION.md](CONFIGURATION.md) |
| 后台邮件发送 | `tools/admin-hub.html#/mail` | 模板示例与 MCP 工具见 [CONFIGURATION.md](CONFIGURATION.md) |


## 文档导航

本项目文档已按读者身份拆分，请按需查阅：

| 文档 | 面向 | 内容 |
|---|---|---|
| [README.md](README.md) | 玩家 / 新访客（本页） | 项目概述、功能、页面列表、快速开始、常用功能速览 |
| [CONFIGURATION.md](CONFIGURATION.md) | 服主 / 运维 | 所有配置说明汇总：`config/config.php`、`js/config.js`、弹幕/性能/通知/数据库查询/卫星地图/充值/邮箱验证/状态页/AI 等 |
| [DEVELOPMENT.md](DEVELOPMENT.md) | 开发者 | 开发指导（UI 组件、画廊、页面模板）、安全配置、AI 客服原理、AI Agent 远程框架 |
| [API.md](API.md) | 开发者 | 后端 API 接口说明 |


<p align="center">
  <b>🌟 如果觉得这个项目对你有帮助，欢迎给一个 Star 支持一下！ 🌟</b>
</p>
