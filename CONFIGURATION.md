# 配置指南 · PonyConsanguinity

> 本文档汇总**万驹同源服务器官网**的全部配置说明，面向服主与运维人员。
> 项目概览与快速上手请看 [README.md](README.md)；开发与安全细节请看 [DEVELOPMENT.md](DEVELOPMENT.md)。

## 目录

1. [统一后端配置 config/config.php](#一统一后端配置-configconfigphp)
2. [前端统一配置 js/config.js](#二前端统一配置-jsconfigjs)
3. [卫星地图系统配置](#三卫星地图系统配置)
4. [充值系统（爱发电）配置](#四充值系统爱发电配置)
5. [邮箱验证系统配置](#五邮箱验证系统配置)
6. [后台邮件发送功能](#六后台邮件发送功能)
7. [状态页子服一览配置](#七状态页子服一览配置)
8. [数据库查询配置](#八数据库查询配置)
9. [弹幕系统配置](#九弹幕系统配置)
10. [性能监控系统配置](#十性能监控系统配置)
11. [通知系统配置](#十一通知系统配置)
12. [AI 大模型配置（AI 客服系统）](#十二ai-大模型配置ai-客服系统)


---

## 一、统一后端配置 config/config.php

### 功能说明

**v5.6+ 版本更新**：项目已采用统一的配置文件架构，所有配置集中管理在 `config/config.php` 文件中。这是**推荐的配置方式**，提供了更好的安全性、可维护性和向后兼容性。

**特别提醒：此文件涉及过多的敏感信息，绝对禁止提交到仓库！**

`config/config.php` 是项目的统一配置文件，包含了所有重要的系统配置：

-   数据库配置
-   MCSManager API 配置
-   服务器状态 API 配置
-   HTTPS 和 CORS 配置
-   会话和数据路径配置
-   爱发电 API 配置
-   EYPA OAuth 配置
-   AI 大模型配置
-   MCP 远程 Service Key 配置
-   ……

### 配置项

```php
// ==================== 数据库配置 ====================
define('DB_HOST', 'xxx.xxx.xxx.xxx');
define('DB_PORT', 3306);
define('DB_NAME', 'database_name');
define('DB_USER', 'username');
define('DB_PASS', 'password');

// ==================== MCSManager API 配置 ====================
define('MCSM_API_URL', 'https://mcpanel.example.com/mcs/api');
define('MCSM_API_KEY', 'your-api-key');

// ==================== 服务器状态 API 配置 ====================
define('MCSTATUS_API_URL', 'http://mcstatus.example.com/api');
define('MC_SERVER_IP', 'mc.example.com');
define('MC_SERVER_PORT', 25565);

// ==================== 服务器状态缓存配置 ====================
define('MCSTATUS_CACHE_FILE', dirname(__DIR__) . '/data/mcstatus_cache.json');
define('MCSTATUS_CACHE_TIME', 60);      // 缓存有效期（秒）
define('MCSTATUS_MAX_RETRIES', 2);      // 状态获取最大重试次数

// ==================== HTTPS 配置 ====================
define('IS_HTTPS', true);

// ==================== CORS 配置 ====================
// 环境感知白名单：生产仅放行正式域名，本地开发自动追加本机入口；
// 由 $corsAllowed 数组与 set_cors_headers() 共同实现，非通配符 *
define('CORS_ALLOW_ORIGIN', 'https://mcpc.goldenapplepie.xyz');
define('CORS_ALLOW_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_ALLOW_HEADERS', 'Content-Type, Authorization, X-Requested-With');

// ==================== 会话配置 ====================
define('MAX_SESSIONS', 10);

// ==================== 数据文件路径配置 ====================
define('USERS_FILE', dirname(__DIR__) . '/data/users.php');
define('SESSIONS_FILE', dirname(__DIR__) . '/data/sessions.php');
define('POSTS_FILE', dirname(__DIR__) . '/data/posts.php');
define('NOTIFICATIONS_FILE', dirname(__DIR__) . '/data/notifications.php');
define('CONTENT_DIR', dirname(__DIR__) . '/data/content');
define('REPLIES_DIR', dirname(__DIR__) . '/data/replies');
define('MESSAGES_FILE', dirname(__DIR__) . '/data/messages.json');

// ==================== 点赞/收藏数据文件配置 ====================
define('LIKES_FILE', dirname(__DIR__) . '/data/likes.json');
define('BOOKMARKS_FILE', dirname(__DIR__) . '/data/bookmarks.json');

// ==================== 问卷系统配置 ====================
define('QUESTIONNAIRES_FILE', dirname(__DIR__) . '/data/questionnaires.json');

// ==================== 招募系统配置 ====================
define('RECRUITMENT_DIR', dirname(__DIR__) . '/data/recruitments');

// ==================== 爱发电 API 配置 ====================
define('AFDIAN_USER_ID', 'your-user-id');
define('AFDIAN_API_TOKEN', 'your-api-token');
define('AFDIAN_ORDER_UPDATE_MODE', 'all');
define('AFDIAN_API_SECRET', 'your-shared-secret'); // 防滥用共享密钥（前端携带 X-Afdian-Key）
// 定时任务、商品方案、Webhook 验签、日志等扩展配置见 config.php 内对应注释

// ==================== EYPA OAuth 配置 ====================
define('EYPA_OAUTH_ENABLED', true);
define('EYPA_API_ENDPOINT', 'https://eqmemory.cn/eu-json/eu-connect/v1/user-profile');
define('EYPA_AUTH_URL', 'https://eqmemory.cn/eu-authorize');
define('EYPA_REDIRECT_URI', 'https://你的域名/api/eypa/callback.php'); // 自动生成，一般无需修改
define('EYPA_OAUTH_DEBUG', false); // 调试模式，生产环境务必关闭

// ==================== AI 大模型配置 ====================
// 详细说明见「AI 大模型配置」章节
define('AI_CUSTOMER_SERVICE_ENABLED', true); // 是否启用 AI 客服
define('AI_KNOWLEDGE_BASE_ENABLED', true);   // 是否启用知识库检索（RAG）
define('AI_SHOW_CONFIG_PANEL', false);       // 是否在网页显示配置面板（生产建议 false）

// ==================== MCP 远程 Service Key 配置 ====================
// 远程 MCP 客户端鉴权表：SHA256 哈希 => 角色（admin / user）
// 明文 Key 只在创建时展示一次，config 仅存哈希；详见「DEVELOPMENT.md · AI Agent 远程框架」
// define('MCP_SERVICE_KEYS', [
//     hash('sha256', 'your-plaintext-key') => 'admin',
// ]);
```

### 配置说明

| 配置项 | 说明 | 示例值 |
| ------------------ | ---------------- | ------------------------------------- |
| DB_HOST | 数据库主机地址 | xxx.xxx.xxx.xxx |
| DB_PORT | 数据库端口 | 3306 |
| DB_NAME | 数据库名称 | database_name |
| DB_USER | 数据库用户名 | username |
| DB_PASS | 数据库密码 | password |
| MCSM_API_URL | MCSManager API地址 | https://mcpanel.example.com/mcs/api |
| MCSM_API_KEY | MCSManager API密钥 | your-api-key |
| MCSTATUS_API_URL | 服务器状态API地址 | http://mcstatus.example.com/api |
| MC_SERVER_IP | Minecraft服务器IP | mc.example.com |
| MC_SERVER_PORT | Minecraft服务器端口 | 25565 |
| MCSTATUS_CACHE_FILE | 服务器状态缓存文件 | data/mcstatus_cache.json |
| MCSTATUS_CACHE_TIME | 状态缓存有效期（秒） | 60 |
| MCSTATUS_MAX_RETRIES | 状态获取最大重试次数 | 2 |
| IS_HTTPS | 是否使用HTTPS | true |
| CORS_ALLOW_ORIGIN | 允许跨域来源白名单（逗号分隔，非通配符） | https://mcpc.goldenapplepie.xyz |
| MAX_SESSIONS | 最大会话数量 | 10 |
| MESSAGES_FILE | 聊天消息数据文件 | data/messages.json |
| LIKES_FILE | 点赞数据文件 | data/likes.json |
| BOOKMARKS_FILE | 收藏数据文件 | data/bookmarks.json |
| QUESTIONNAIRES_FILE | 问卷数据文件 | data/questionnaires.json |
| RECRUITMENT_DIR | 招募数据目录 | data/recruitments |
| AFDIAN_USER_ID | 爱发电用户ID | your-user-id |
| AFDIAN_API_TOKEN | 爱发电API令牌 | your-api-token |
| AFDIAN_API_SECRET | 爱发电API防滥用共享密钥（前端携带 X-Afdian-Key） | your-shared-secret |
| EYPA_OAUTH_ENABLED | 是否启用 EYPA OAuth 登录 | true |
| EYPA_API_ENDPOINT | EYPA 用户信息端点（eu-json 前缀） | https://eqmemory.cn/eu-json/eu-connect/v1/user-profile |
| EYPA_AUTH_URL | EYPA 授权页面地址 | https://eqmemory.cn/eu-authorize |
| EYPA_OAUTH_DEBUG | EYPA OAuth 调试模式（生产关闭） | false |
| AI_CUSTOMER_SERVICE_ENABLED | 是否启用 AI 客服 | true |
| AI_KNOWLEDGE_BASE_ENABLED | 是否启用知识库检索（RAG） | true |
| AI_SHOW_CONFIG_PANEL | 是否在网页显示 AI 配置面板 | false |
| MCP_SERVICE_KEYS | MCP 远程鉴权表（SHA256 哈希 => 角色） | 见「DEVELOPMENT.md · AI Agent 远程框架」 |

### 辅助函数

统一配置文件提供了以下辅助函数，确保向后兼容：
```php
// 获取数据库配置数组
$config = get_db_config();
// 返回: ['hostname' => ..., 'port' => ..., 'database' => ..., 'username' => ..., 'password' => ...]

// 获取爱发电配置数组
$afdianConfig = get_afdian_config();
// 返回完整的爱发电配置数组

// 设置 CORS 响应头
set_cors_headers();

// 设置安全响应头
set_security_headers();
```

### 自动初始化（data-init 模板 + 幂等引擎）

`data/` 目录存放用户、会话、帖子等**敏感数据，已被 `.gitignore` 整体屏蔽不纳入版本库**。为保证项目开箱即用，采用「模板 + 幂等初始化」机制：

-   **模板目录 `data-init/`**（纳入 git）：包含完整的目录结构、空数据模板（PHP/JSON）、种子公告与 `README.md` 等 30+ 个文件
-   **初始化引擎 `includes/data-init.php`**：`ensure_data_initialized()` 幂等复制——**已有数据绝不覆盖**，仅补建缺失的文件与目录
-   **触发时机**：`config/config.php` 的 `init_config()` 自动委托调用，无需手动操作

**工作方式**：
1.  全新部署（`data/` 不存在）：整树复制 `data-init/` → `data/`
2.  存量部署（`data/` 已有数据）：仅补建缺失项，绝不覆盖现有数据

> 如需重置数据：删除 `data/` 目录后重启即可自动重新生成（注意会清空现有数据）。

### 旧版配置文件 (api/config.php) ⚠️ 已弃用

**v5.6+ 版本更新**：`api/config.php` 已不再作为主要的配置文件。它现在只是作为 API 的入口文件，负责：

1.  引入统一的 `config/config.php` 配置文件
2.  引入辅助函数 `helper.php`
3.  引入数据安全处理 `secure_data.php`
4.  验证配置完整性

为了保持向后兼容，`api/config.php` 仍然可以正常引入，它会自动加载 `config/config.php` 中的所有配置。

**旧代码无需修改**：
```php
// 旧代码仍然可以正常工作
require_once 'config.php'; // 这会加载 api/config.php，它会自动引入 config/config.php
```

---

## 二、前端统一配置 js/config.js

### 功能说明

`js/config.js` 是项目的统一配置文件，用于管理全局配置，自动区分本地开发环境和生产环境，无需手动修改配置。

### 配置文件位置

```PC_Web/
└── js/
    └── config.js # 统一配置文件
```

### 配置项说明

#### 1. 环境检测

```javascript
const ENV = {
    // 检测是否为本地开发环境
    isLocalhost: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1',

    // 检测是否为生产环境
    isProduction: window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1'
};
```

#### 2. API 配置

```javascript
const API_CONFIG = {
    // API 基础地址
    // 本地开发环境使用 localhost:8000，生产环境使用指定域名
    baseUrl: ENV.isLocalhost ? 'http://localhost:8000/api' : 'https://您的域名/api',

    // API 完整地址（用于某些需要完整 URL 的场景）
    fullUrl: ENV.isLocalhost ? 'http://localhost:8000' : 'https://您的域名'
};
```

#### 3. 页面路径配置

```javascript
const PATH_CONFIG = {
    // 数据库查询页面
    dbTest: ENV.isLocalhost ? 'http://localhost:8000/pages/db_test.html' : 'https://您的域名/pages/db_test.html',

    // 公告管理页面
    announcementManager: ENV.isLocalhost ? 'http://localhost:8000/tools/announcement-manager.html' : 'https://您的域名/tools/announcement-manager.html'
};
```

#### 4. 应用配置

```javascript
const APP_CONFIG = {
    // 应用名称
    appName: '万驹同源',

    // API 可用性检测超时时间（毫秒）
    apiTimeout: 3000,

    // 是否启用调试模式
    debugMode: ENV.isLocalhost
};
```

### 如何修改域名配置

1.  **打开配置文件**：使用文本编辑器打开 `js/config.js` 文件
2.  **修改 API 配置**：
    ```javascript
     // API 配置
     const API_CONFIG = {
         // API 基础地址
         baseUrl: ENV.isLocalhost ? 'http://localhost:8000/api' : 'https://您的新域名/api',

         // API 完整地址
         fullUrl: ENV.isLocalhost ? 'http://localhost:8000' : 'https://您的新域名'
     };
    ```
3.  **修改页面路径配置**：
    ```javascript
     // 页面路径配置
     const PATH_CONFIG = {
         // 数据库查询页面
         dbTest: ENV.isLocalhost ? 'http://localhost:8000/pages/db_test.html' : 'https://您的新域名/pages/db_test.html',

         // 公告管理页面
         announcementManager: ENV.isLocalhost ? 'http://localhost:8000/tools/announcement-manager.html' : 'https://您的新域名/tools/announcement-manager.html'
     };
    ```
4.  **保存文件**：保存修改后的配置文件

### 环境自动适配

-   **本地开发环境**：当访问地址为 `localhost` 或 `127.0.0.1` 时，自动使用 `http://localhost:8000`
-   **生产环境**：当访问地址为其他域名时，自动使用配置的域名

### 如何在其他文件中使用配置

#### 在 HTML 文件中使用

1.  首先引入配置文件：
    ```html
     <script src="../js/config.js"></script>
    ```
2.  然后在 JavaScript 代码中使用：
    ```javascript
     // 使用 API 基础地址
     const API_BASE_URL = API_CONFIG.baseUrl;

     // 使用页面路径
     window.open(PATH_CONFIG.dbTest, '_blank');

     // 使用环境配置
     if (ENV.isLocalhost) {
         // 本地环境特定逻辑
     }
    ```

#### 在 JavaScript 文件中使用

1.  确保配置文件在其他 JS 文件之前加载
2.  直接使用全局配置对象：
    ```javascript
     // 使用 API 配置
     const response = await fetch(API_CONFIG.baseUrl + '/mcstatus.php');

     // 使用应用配置
     if (APP_CONFIG.debugMode) {
         console.log('调试信息');
     }
    ```

### 配置验证

配置文件会在页面加载时在控制台输出当前环境信息，您可以通过浏览器开发者工具查看：
```=== 环境配置 ===
当前环境: 本地开发环境
API 基础地址: http://localhost:8000/api
完整 URL: http://localhost:8000
================
```

---

## 三、卫星地图系统配置

### 功能说明

卫星地图系统提供了实时的服务器地图查看功能，使用[Dynmap](https://github.com/webbukkit/dynmap)插件实现，支持玩家位置追踪、地图缩放等高级功能。

### 访问方式

访问 `pages/map.html` 页面即可查看卫星地图。

### 配置说明

地图地址配置在 `pages/map.html` 中：
```html
<iframe src="https://dynmap.eqmemory.cn/"></iframe>
```
如需修改地图地址，请修改iframe的src属性。

---

## 四、充值系统（爱发电）配置

> ⚠️ **声明：此充值系统目前还处于测试阶段，暂未进行安全性测试，请勿随意使用，若遇上安全漏洞造成的财产损失，作者与本站不承担任何责任！！！**

### 功能说明

充值系统集成了[爱发电](https://afdian.com/)支付平台，支持玩家通过爱发电进行黄金券充值，系统会自动处理订单并更新玩家的黄金券数量。

### 核心特性

-   **爱发电支付集成**：支持通过爱发电平台进行支付
-   **自动订单处理**：支付成功后自动更新玩家黄金券
-   **多重更新模式**：支持webhook实时通知和API定时检查
-   **安全可靠**：采用事务处理和幂等性设计，确保订单处理的可靠性
-   **详细日志**：完整的操作日志，便于问题排查

### 访问方式

访问 `pages/payment.html` 页面即可进入充值中心。

### 配置说明

#### 爱发电配置

爱发电配置现在统一在 `config/config.php` 中管理。

配置文件：`config/config.php`
```php
// ==================== 爱发电 API 配置 ====================
define('AFDIAN_USER_ID', 'your-user-id'); // 从爱发电开发者后台获取
define('AFDIAN_API_TOKEN', 'your-api-token'); // 从爱发电开发者后台获取

// ==================== 爱发电订单更新模式配置 ====================
// 可选值: 'api' (纯API模式), 'webhook' (仅webhook模式), 'all' (webhook为主，API为辅)
define('AFDIAN_ORDER_UPDATE_MODE', 'all');

// ==================== 爱发电自动定时任务配置 ====================
define('AFDIAN_CRON_ENABLED', true); // 是否启用自动定时任务
define('AFDIAN_CRON_INTERVAL', 120); // 执行间隔（秒），120秒=2分钟
define('AFDIAN_CRON_MAX_TIME', 300); // 单次执行最长时间（秒）
define('AFDIAN_CRON_LOG_LEVEL', 'info'); // 日志级别：debug, info, error

// ==================== 爱发电方案/商品ID配置 ====================
define('AFDIAN_PLAN_GOLDEN_TICKET', 'your-golden-ticket-plan-id'); // 黄金券充值商品ID
define('AFDIAN_PLAN_VIP_MONTH', 'your-vip-month-plan-id'); // VIP月卡商品ID
define('AFDIAN_PLAN_VIP_YEAR', 'your-vip-year-plan-id'); // VIP年卡商品ID

// ==================== 爱发电 Webhook 配置 ====================
define('AFDIAN_WEBHOOK_VERIFY_SIGN', true); // 是否验证签名

// ==================== 爱发电日志配置 ====================
define('AFDIAN_LOG_DIR', dirname(__DIR__) . '/logs');
define('AFDIAN_LOG_LEVEL', 'info');
define('AFDIAN_LOG_MODULE', 'aifadian');
```

#### Webhook 配置

在爱发电开发者后台设置webhook地址：
```
https://your-domain.com/api/aifadian/api/webhook.php
```

### 管理功能

#### 自动更新管理

访问 `tools/auto_update.html` 页面可以管理自动更新功能（v5.8已经不再进行维护，且此管理功能已经集成至了管理员后台）：

-   **开启自动更新**：自动定期检查和处理新订单
-   **手动更新**：立即检查和处理新订单
-   **查看日志**：查看自动更新的执行日志

### 日志系统

充值系统的日志存储在 `logs/` 目录：

-   `logs/webhook_*.log` - Webhook处理日志
-   `logs/aifadian_webhook_*.log` - 订单处理日志
-   `logs/aifadian_process_*.log` - 定时任务处理日志
-   `logs/aifadian_auto_*.log` - 自动更新日志

### 基本原理

爱发电提供了webhook接口，当有新订单时，会向配置的webhook地址发送POST请求，包含订单详情。
充值系统通过webhook接口实时接收订单信息，并根据配置自动更新玩家的黄金券数量。
同时爱发电还提供了API接口，充值系统也可以通过API接口定时检查新订单。这为充值系统提供了基础的订单处理功能。
再鉴于我们可以通过网页后端操作数据库，只要将相关逻辑（如数据库的连接、操作等）与爱发电的订单信息进行关联，就可以实现自动更新玩家的黄金券数量。

### 注意事项

-   **重要：在初次部署充值系统时一定一定要先在auto_update.html中手动更新一次，以初始化数据库，否则后续的订单更新将无法正常工作！！！**
-   建议在config.php中开启混合模式（all）
-   此充值系统依赖于爱发电平台以及你的MC服务器数据库（尤其是要有playerpoints插件），确保两者正常运行才能正常工作。
-   此充值系统仅依赖于playerpoints插件的数据库，不依赖于任何其他MC服务器插件。当然，你可以仿造此架构完成一些其它的功能，比如玩家的VIP系统（如理论上也可以实现使用luckperms插件的数据库）
-   如果你使用了充值系统，建议修改相关的充值系统声明，否则会与当前网站的声明与协议产生冲突

### 补充

- 爱发电平台：<https://afdian.com/>
- 爱发电开发者功能汇总：<https://afdian.com/p/010ff078177211eca44f52540025c377>

---

## 五、邮箱验证系统配置

### 功能说明

邮箱验证系统实现了用户邮箱验证功能，支持两种运行模式：

-   **本地开发模式**：直接返回验证链接，方便开发和测试
-   **生产部署模式**：使用 SMTP 发送真实验证邮件
    系统会自动检测运行环境并选择合适的模式。

### 配置文件

**后端配置**：`config/config.php`
```php
// ==================== 邮件 SMTP 配置 ====================
// 是否启用邮箱验证功能
define('EMAIL_VERIFICATION_ENABLED', true);

// SMTP 服务器配置
define('SMTP_HOST', 'smtp.qq.com'); // SMTP服务器地址
define('SMTP_PORT', 465); // SMTP端口（SSL: 465, TLS: 587）
define('SMTP_USERNAME', 'your-email@qq.com'); // 发件人邮箱
define('SMTP_PASSWORD', 'your-auth-code'); // 邮箱授权码（不是登录密码）
define('SMTP_ENCRYPTION', 'ssl'); // 加密方式：ssl、tls 或空字符串
define('SMTP_AUTH', true); // 是否启用SMTP认证

// 发件人信息
define('MAIL_FROM_EMAIL', 'your-email@qq.com');
define('MAIL_FROM_NAME', '万驹同源服务器');

// 验证邮件配置
define('VERIFY_TOKEN_EXPIRY', 86400); // 验证令牌有效期（秒）默认24小时
define('VERIFY_RESEND_INTERVAL', 600); // 重新发送间隔（秒）默认10分钟
define('VERIFY_MAX_RESEND', 3); // 每小时最大重发次数
```

**前端配置**：`js/config.js`
```javascript
emailVerification: {
    // 本地开发环境：使用手动验证链接（方便测试）
    // 生产环境：使用真实邮件发送
    useManualVerification: ENV.isLocalhost,

    // 验证邮件发送间隔（秒）
    resendInterval: 60,

    // 验证令牌有效期（秒）
    tokenExpiry: 86400
}
```

### 运行模式

#### 本地开发模式

**环境检测**：访问地址为 `localhost` 或 `127.0.0.1`

**特点**：

-   不发送真实邮件
-   直接返回验证链接
-   用户手动复制链接到浏览器验证
-   适合开发和测试

**流程**：

1.  用户点击"发送验证邮件"
2.  系统生成验证令牌并保存
3.  返回验证链接（通过弹窗显示）
4.  用户复制链接到浏览器
5.  完成验证
    如果你想在本地模式下测试邮箱发送功能，可以利用test/mail/test_email.php脚本。

#### 生产部署模式

**环境检测**：访问地址为其他域名

**特点**：

-   使用 SMTP 发送真实邮件
-   用户通过邮件中的按钮验证
-   需要配置正确的 SMTP 服务器
-   适合生产环境

**流程**：

1.  用户点击"发送验证邮件"
2.  系统生成验证令牌并保存
3.  使用 PHPMailer 发送验证邮件
4.  用户收到邮件，点击验证按钮
5.  完成验证

### 邮件模板

邮件模板文件：`includes/email_templates/verify_email.html`

**模板变量**：

-   `{site_name}` - 站点名称
-   `{username}` - 用户名
-   `{verify_url}` - 验证链接
-   `{expiry_hours}` - 过期小时数

### 用户数据结构

验证相关字段存储在 `data/users.php` 中：
```php
'username' => [
    'email' => 'user@example.com',
    'email_verified' => false, // 验证状态
    'verify_token' => '64位随机令牌', // 验证令牌
    'verify_expires' => '2026-03-02 09:12:46', // 过期时间
    'verify_sent_at' => '2026-03-01 09:12:46', // 发送时间
    'verify_resend_count' => 1, // 重发次数
]
```

### 安全特性

1.  **令牌安全**：64位随机令牌，24小时有效期
2.  **频率限制**：60秒内只能发送一次验证邮件
3.  **一次性使用**：验证成功后令牌立即失效
4.  **数据保护**：使用 `secureReadData` 和 `secureWriteData` 安全读写

### 如何配置SMTP

我们以QQ邮箱为例，配置SMTP服务器如下：

1.  登录QQ邮箱，点击“设置” -> “账户”
2.  找到“SMTP服务”，开启SMTP服务并获取授权码
3.  配置SMTP服务器信息：
    -   主机：`smtp.qq.com`
    -   端口：`465`（SSL）或`587`（TLS）
    -   用户名：你的QQ邮箱地址
    -   密码：SMTP授权码
    -   加密：`ssl`或`tls`
    -   认证：`true`

---

## 六、后台邮件发送功能

### 功能说明

管理后台新增「邮件发送」面板（侧边栏「内容管理」区，入口 `tools/admin-hub.html#/mail`），复用现有 SMTP 组件（PHPMailer + `MailHelper`）向玩家发送邮件。管理员可：

-   **选择收件人**：从用户列表多选（仅带邮箱的账号可勾选，无邮箱的置灰标红），或手动输入邮箱（逗号分隔），系统自动去重并实时计数
-   **编辑正文**：Markdown / HTML 双模式切换，支持全屏编辑，实时预览
-   **发送**：单发 / 群发（上限 200 人，逐封发送互不影响），返回每封结果与成功/失败计数
-   **自检**：「发测试邮件」按钮向当前管理员绑定邮箱发一封，快速验证 SMTP 连通性

### 内容示例

以下为可直接粘贴的示例内容。

#### ① Markdown 模式（服务器介绍 / 拉新）

```markdown
# 🏇 万驹同源 · 你的方块乌托邦

> 100% 公益 · 自 2020 年起，与你同行的每一寸世界

还在找一个**不肝不氪、纯粹快乐**的 MC 服务器？

## ✨ 为什么选择我们

- 🌈 **双版本互通**：1.12 ~ 1.21 全都能进，老玩家新玩家都能找到家
- 🛡️ **纯净公益**：零氪金压迫，道具全靠玩出来
- 🎨 **丰富玩法**：生存 / 建造 / 模组服 / 小游戏，总有一款适合你
- 👥 **活跃社区**：QQ 群 + 论坛 + 皮肤站，随时找到搭子
- 🔧 **稳定运维**：专业后台 + 智能助手，问题秒响应

## 🚀 三步入服

1. 启动器添加服务器地址 `mc.eqmemory.cn`
2. 进服创建角色，开启你的专属旅程
3. 加入 QQ 群，认领新手大礼包 🎁

---

**万驹同源，不止是服务器，更是一群同好的家。**

期待在方块世界里，遇见你 ❤️

🔗 [官网](https://mc.eqmemory.cn) ｜ [皮肤站](https://skin.eqmemory.cn) ｜ [QQ 群](https://jq.qq.com)
```

#### ② HTML 模式（精装打广告版）

```html
<div style="max-width:600px;margin:0 auto;font-family:-apple-system,'Segoe UI',Roboto,'Microsoft YaHei',sans-serif;color:#1d2b4f;background:#f5f8ff;border-radius:18px;overflow:hidden;box-shadow:0 10px 40px rgba(46,111,219,.18);">
  <!-- 头部 -->
  <div style="background:linear-gradient(135deg,#2e6fdb 0%,#1d4fa0 100%);padding:36px 32px;color:#fff;text-align:center;">
    <div style="font-size:40px;line-height:1;">🏇</div>
    <h1 style="margin:12px 0 6px;font-size:26px;letter-spacing:1px;">万驹同源</h1>
    <p style="margin:0;opacity:.92;font-size:14px;">你的方块乌托邦 · 100% 公益服务器</p>
  </div>
  <!-- 导语 -->
  <div style="padding:26px 32px 8px;">
    <p style="font-size:15px;line-height:1.8;margin:0 0 4px;">还在找一个 <b style="color:#2e6fdb;">不肝不氪、纯粹快乐</b> 的 MC 服务器？</p>
    <p style="font-size:15px;line-height:1.8;margin:0;color:#5a6b8c;">自 2020 年起，我们一直在这里，等一个你。</p>
  </div>
  <!-- 卖点卡片 -->
  <div style="padding:8px 32px 20px;">
    <div style="display:flex;gap:12px;margin:12px 0;align-items:flex-start;">
      <div style="flex:0 0 36px;height:36px;background:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 2px 8px rgba(46,111,219,.12);">🌈</div>
      <div style="font-size:14px;line-height:1.6;"><b>双版本互通</b><br>1.12 ~ 1.21 全都能进，老玩家新玩家都有家</div>
    </div>
    <div style="display:flex;gap:12px;margin:12px 0;align-items:flex-start;">
      <div style="flex:0 0 36px;height:36px;background:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 2px 8px rgba(46,111,219,.12);">🛡️</div>
      <div style="font-size:14px;line-height:1.6;"><b>纯净公益</b><br>零氪金压迫，好东西全靠玩出来</div>
    </div>
    <div style="display:flex;gap:12px;margin:12px 0;align-items:flex-start;">
      <div style="flex:0 0 36px;height:36px;background:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 2px 8px rgba(46,111,219,.12);">🎨</div>
      <div style="font-size:14px;line-height:1.6;"><b>丰富玩法</b><br>生存 / 建造 / 模组服 / 小游戏，总有一款适合你</div>
    </div>
    <div style="display:flex;gap:12px;margin:12px 0;align-items:flex-start;">
      <div style="flex:0 0 36px;height:36px;background:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 2px 8px rgba(46,111,219,.12);">🔧</div>
      <div style="font-size:14px;line-height:1.6;"><b>稳定运维</b><br>专业后台 + 智能助手，问题秒响应</div>
    </div>
  </div>
  <!-- 步骤 -->
  <div style="background:#eef3ff;margin:0 24px;padding:18px 22px;border-radius:12px;">
    <p style="margin:0 0 10px;font-weight:700;color:#1d4fa0;font-size:14px;">🚀 三步入服</p>
    <p style="margin:4px 0;font-size:13px;line-height:1.7;">① 启动器添加 <code style="background:#fff;padding:1px 6px;border-radius:5px;color:#2e6fdb;">mc.eqmemory.cn</code><br>② 进服创建角色，开启专属旅程<br>③ 加入 QQ 群，认领新手大礼包 🎁</p>
  </div>
  <!-- 行动按钮 + 底部 -->
  <div style="padding:24px 32px 28px;text-align:center;">
    <a href="https://mc.eqmemory.cn" style="display:inline-block;background:#f2b705;color:#1d2b4f;text-decoration:none;font-weight:700;padding:13px 34px;border-radius:12px;font-size:15px;box-shadow:0 6px 18px rgba(242,183,5,.4);">立即加入我们 →</a>
    <p style="margin:18px 0 0;font-size:13px;color:#7a8aa8;">万驹同源，不止是服务器，更是一群同好的家 ❤️</p>
  </div>
  <div style="background:#e3ebfb;padding:14px 32px;font-size:12px;color:#7a8aa8;text-align:center;">
    🔗 <a href="https://mc.eqmemory.cn" style="color:#2e6fdb;text-decoration:none;">官网</a> ｜ <a href="https://skin.eqmemory.cn" style="color:#2e6fdb;text-decoration:none;">皮肤站</a> ｜ <a href="https://jq.qq.com" style="color:#2e6fdb;text-decoration:none;">QQ 群</a>
  </div>
</div>
```

#### ③ 供 LLM 调用（MCP 工具 `send_email`）

同一套发送能力已封装为 MCP 工具 `send_email`，可供站内 AI 助手、Godot APP、WorkBuddy 等任意 MCP Host 通过 LLM 调用，无需重复实现。

-   **注册位置**：`mcp/tools/send_email.php`，由 `mcp/toolbase.php` 经 `glob` 自动发现，双端点（`mcp/mcp-server.php` 站内管理渠道、`mcp/remote.php` 远程 Streamable HTTP）零配置复用
-   **权限**：`admin_only` —— 仅 admin 角色可调；远程端点需在 `Authorization` 头携带 admin Service Key（值见 `config.php` 的 `MCP_SERVICE_KEYS`）。客服 AI 白名单不含本工具，天然隔离
-   **执行确认**：无 `requiresConfirm`，由权限 + Service Key 鉴权把控，LLM 可直接触发发送
-   **复用**：后端 `MailHelper` SMTP 组件，与「邮件发送」面板同一套投递链路

**JSON-RPC 2.0 调用示例（远程端点 `POST /mcp/remote.php`）**：

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "send_email",
    "arguments": {
      "to": "player@example.com, admin@mc.eqmemory.cn",
      "subject": "万驹同源 · 月度活动通知",
      "body": "<div style=\"font-family:sans-serif;\"><h2 style=\"color:#2e6fdb;\">活动来啦</h2><p>这是通过 MCP 工具直接发出的邮件。</p></div>"
    }
  }
}
```

请求头：`Authorization: Bearer <admin Service Key>`。

**返回示例**（handler 返回的 JSON 摘要由 `result.content[].text` 包裹）：

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "{\"success\":true,\"sent\":2,\"failed\":0,\"total\":2,\"results\":[{\"to\":\"player@example.com\",\"success\":true},{\"to\":\"admin@mc.eqmemory.cn\",\"success\":true}]}"
      }
    ]
  }
}
```

**参数说明**：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `to` | string | 是 | 收件人，逗号分隔多个地址字符串，或多个地址组成的数组 |
| `subject` | string | 是 | 邮件主题 |
| `body` | string | 是 | 邮件正文，HTML 格式 |
| `altBody` | string | 否 | 纯文本备用内容；留空自动从 HTML 提取 |

注：收件人上限 200、自动去重、逐封发送互不影响，行为与后台面板一致。

---

## 七、状态页子服一览配置

状态页 `pages/status.html` 的「服务器状态」卡展示万驹同源各个子服的运行情况（名称、在线状态、版本、核心、人数），数据来自 MCSManager 面板，通过只读代理 `api/mc-instances.php` 获取（10 秒缓存，响应不暴露面板密钥，启停等写操作不在此接口）。

-   **配置驱动**：展示哪些子服、展示名、核心、版本、展示顺序全部由 `config/config.php` 的三个数组决定，**无需改动任何前端代码**
-   `MC_DISPLAY_INSTANCES`：实例原始名 → 对外展示名（数组顺序即展示顺序）
-   `MC_INSTANCE_CORES`：实例原始名 → 核心名（MCSM 面板无核心字段，需手动配置；未配置显示「—」）
-   `MC_INSTANCE_VERSIONS`：实例原始名 → 版本名（硬编码映射，不读 MCSM；未配置默认「1.12.x~1.21.1」）

当前已配置 5 个子服：炉边茶社（NeoForge · 1.21.1）、BC服（BungeeCord · 1.12.x~1.21.1）、生存服（Paper · 1.12.x~1.21.1）、创造服（Paper · 1.12.x~1.21.1）、登录服（Paper · 1.12.x~1.21.1）。

### 如何添加新的子服卡片

只需在 `config/config.php` 的三个数组中各加一行（键 = MCSManager 面板中的**实例原始名**，大小写与 `[]` 等符号需与面板完全一致）：

```php
define('MC_DISPLAY_INSTANCES', [
    // ……原有配置……
    '[PC]小游戏服' => '小游戏服',   // ① 必加：面板原始名 => 展示名（插在哪行就显示在哪）
]);

define('MC_INSTANCE_CORES', [
    // ……原有配置……
    '[PC]小游戏服' => 'Paper',      // ② 可选：面板原始名 => 核心名（不配则显示「—」）
]);

define('MC_INSTANCE_VERSIONS', [
    // ……原有配置……
    '[PC]小游戏服' => '1.12.x~1.21.1', // ③ 可选：面板原始名 => 版本名（不配默认 1.12.x~1.21.1）
]);
```

保存后刷新状态页即可看到新卡片（后端有 10 秒缓存，最多等 10 秒）。也可用 CLI 验证：`php api/mc-instances.php`，查看返回的 `meta.total` 与 `instances[].name`。

**注意事项**：

| 事项 | 说明 |
| --- | --- |
| 键必须一致 | 三个数组的键 = 面板实例原始名，与面板完全一致才生效 |
| 展示顺序 | 按 `MC_DISPLAY_INSTANCES` 的行顺序展示，调整顺序即移动行 |
| 核心可选 | 不配 `MC_INSTANCE_CORES` 则卡片上显示「—」 |
| 版本可选 | 不配 `MC_INSTANCE_VERSIONS` 则默认显示「1.12.x~1.21.1」 |
| 可提前配置 | 面板中不存在的实例会被自动跳过，不影响其他卡片 |

---

## 八、数据库查询配置

### 功能说明

数据库查询组件允许管理员通过网页界面直接查看Minecraft服务器的MySQL数据库，包括表结构、表数据等信息。

### 配置文件

-   **统一配置文件**：`config/config.php`（数据库配置部分）
-   **后端API**：`api/db_test.php`
-   **前端页面**：`pages/db_test.html`

### 配置项

数据库配置现在统一在 `config/config.php` 中管理：
```php
// ==================== 数据库配置 ====================
define('DB_HOST', 'xxx.xxx.xxx.xxx');
define('DB_PORT', 3306);
define('DB_NAME', 'database_name');
define('DB_USER', 'username');
define('DB_PASS', 'password');
```

**配置说明**：

| 配置项 | 说明 | 示例值 |
| -------- | ---------- | --------------- |
| DB_HOST | MySQL服务器地址 | xxx.xxx.xxx.xxx |
| DB_PORT | MySQL端口 | 3306 |
| DB_NAME | 数据库名称 | database_name |
| DB_USER | 数据库用户名 | username |
| DB_PASS | 数据库密码 | password |

### 功能特性

1.  **查看数据库信息**：显示数据库名称和所有表
2.  **查看表结构**：显示表的字段信息（字段名、类型、是否为空等）
3.  **查看表数据**：显示表中的数据，支持分页（每页10条记录）
4.  **分页功能**：支持翻页浏览大量数据

### 访问方式

-   **前端页面**：`/pages/db_test.html`（使用统一配置自动适配环境）
-   **后端API**：`/api/db_test.php`（使用统一配置自动适配环境）

---

## 九、弹幕系统配置

### 功能说明

弹幕系统位于首页Banner区域，实现了从右向左滚动的弹幕效果，支持随机颜色、随机速度和鼠标悬停暂停功能。

### 配置文件

-   **文件路径**：`js/main.js` 中的 `initDanmaku()` 函数

### 配置选项

现在所有配置都集中在一个 `config` 对象中，修改更加方便：
```javascript
// 弹幕配置
const config = {
    // 开关控制：true = 开启，false = 关闭
    enabled: true,
    // 弹幕文本列表
    messages: [
        '欢迎来到万驹同源服务器！',
        '服务器地址：mc.eqmemory.cn',
        '添加你的自定义弹幕内容...'
    ],
    // 弹幕颜色列表
    colors: ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F'],
    // 弹幕生成间隔（毫秒）
    interval: 1500,
    // 初始弹幕数量
    initialCount: 5,
    // 启动延迟（毫秒）
    startDelay: 5500
};
```

### 配置说明

1.  **开启/关闭弹幕**
    ```javascript
     enabled: true, // true = 开启，false = 关闭
    ```
2.  **修改弹幕内容**

    在 `messages` 数组中添加或修改弹幕文本。
3.  **修改弹幕颜色**

    在 `colors` 数组中添加或修改颜色值。
4.  **调整弹幕生成频率**
    ```javascript
     interval: 1500, // 改为你想要的毫秒数
    ```
5.  **调整初始弹幕数量**
    ```javascript
     initialCount: 5, // 改为你想要的初始数量
    ```
6.  **调整弹幕启动延迟**
    ```javascript
     startDelay: 5500, // 改为你想要的延迟毫秒数
    ```
7.  **调整弹幕滚动速度**

    在 `createDanmaku()` 函数中修改：
    ```javascript
     const duration = 8 + Math.random() * 8; // 最小8秒，最大16秒
    ```
8.  **调整弹幕字体大小**

    在 `createDanmaku()` 函数中修改：
    ```javascript
     const fontSize = 14 + Math.random() * 4; // 最小14px，最大18px
    ```

---

## 十、性能监控系统配置

### 功能说明

性能监控系统位于状态页面，用于记录和展示服务器的性能数据，包括：

-   **玩家人数**：实时在线玩家数量
-   **CPU使用率**：服务器CPU使用百分比
-   **内存使用率**：服务器内存使用百分比
-   **时间轴图表**：展示最近一段时间的数据变化趋势

注：此项功能需要用到[MCSManager](https://www.mcsmanager.com/)的CPU、内存API接口与[Minecraft-Server-Status](https://github.com/GamerNoTitle/Minecraft-Server-Status)的玩家人数API接口实现。不过关于minecraft-server-status，你可以直接使用我自行搭建的公共API使用：[https://mcpc.goldenapplepie.xyz/mcstatus/](https://mcpc.goldenapplepie.xyz/mcstatus/)。

### 数据存储机制

**数据文件**：`data/performance_data.json`

**数据结构**：
```json
[
  {
    "timestamp": "2026-02-02 04:21:50",
    "time_label": "04:21",
    "players": 5,
    "cpu": 50.9,
    "memory": 32.5
  }
]
```

**先进先出机制**：系统采用 FIFO（先进先出）数据管理机制：

-   当数据点数量达到配置上限时，自动删除最早的数据点
-   始终保留最新的性能数据
-   确保数据文件大小合理，不会无限增长

### 修改保存次数

**默认配置**：最多保存50个数据点

**修改方法**：

1.  **打开配置文件**：`api/performance.php`
2.  **找到以下代码**：
    ```php
     // 只保留最近的数据（最多50个点）
     if (count($existingData) > 50) {
         $existingData = array_slice($existingData, -50);
     }
    ```
3.  **修改数字**：将 `50` 改为你想要的最大数据点数量

**示例**：
```php
// 只保留最近的数据（最多30个点）
if (count($existingData) > 30) {
    $existingData = array_slice($existingData, -30);
}
```

---

## 十一、通知系统配置

### 功能说明

通知系统用于向用户发送信息，但仅限管理员操作

### 具体细节

管理员用户可在管理员面板——发送通知页面输入通知内容，系统会将通知发送给所有用户。通知数据存储在 `data/notifications.json` 文件中。暂不支持markdown语法

可以使用占位符：

-   `{username}`：替换为用户实际用户名
-   `{server_name}`：替换为服务器名称
-   `{server_ip}`：替换为服务器IP地址
-   `{current_date}`：替换为当前日期（格式：Y-m-d）
-   `{current_time}`：替换为当前时间（格式：H:i:s）

### 占位符修改

找到 `api/notification.php` 文件，找到以下内容：
```php
$content = $notification['content'];
$content = str_replace('{username}', $username, $content);
$content = str_replace('{server_name}', '万驹同源', $content);
$content = str_replace('{server_ip}', 'mc.eqmemory.cn', $content);
$content = str_replace('{current_date}', date('Y-m-d'), $content);
$content = str_replace('{current_time}', date('H:i:s'), $content);
```

其中，`{server_name}`（万驹同源） 和 `{server_ip}`（mc.eqmemory.cn） 可以根据实际情况修改。

---

## 十二、LLM配置（AI 客服系统）

AI 客服系统的架构原理（双 Persona、MCP 集成、知识库 RAG、统一后端代理）详见 [DEVELOPMENT.md](DEVELOPMENT.md) 的「AI 客服系统」章节，本节只讲配置。

在 `config/config.php` 中配置：

```php
// ==================== AI 大模型配置 ====================
define('ECHO_API_URL', 'https://eapi.eqmemory.cn/v1');    // EYPA/AI 平台端点（OpenAI 兼容）
define('ECHO_API_KEY', 'your-echo-api-key');               // EYPA/AI 平台密钥
define('DEEPSEEK_API_URL', 'https://api.deepseek.com');    // DeepSeek 官方端点
define('DEEPSEEK_API_KEY', 'your-deepseek-api-key');       // DeepSeek 备选密钥
define('DEEPSEEK_DEFAULT_MODEL', 'Echo-1.5-Pro');          // 默认模型

// EYPA/AI 平台模型白名单（统一走 ECHO_API_URL，识别函数 isEypaAiModel()）
define('EYPA_AI_MODELS', [
    'Echo-1.5-Flash', 'Echo-1.5-Pro', 'Echo-Image',
    'DeepSeek-V4-Flash', 'DeepSeek-V4-Pro',
    'GLM-5.2', 'MiniMax-M2.7', 'MiniMax-M3',
]);

// Persona 配置（定义在 $GLOBALS['AI_PERSONAS'] 中）
// customer → 只读客服，admin → 全量管理助手
// 每个 persona 指定：model, prompt_file, kb_file, allowed_tools, skills
```

| 配置项 | 说明 | 推荐值 |
|--------|------|--------|
| `ECHO_API_URL` | EYPA/AI 平台端点 | `https://eapi.eqmemory.cn/v1` |
| `ECHO_API_KEY` | EYPA/AI 平台密钥 | 从 EYPA/AI 获取 |
| `DEEPSEEK_API_URL` | DeepSeek 官方端点 | `https://api.deepseek.com` |
| `DEEPSEEK_API_KEY` | DeepSeek 官方密钥 | 从 DeepSeek 官网获取 |
| `DEEPSEEK_DEFAULT_MODEL` | 默认模型名 | `Echo-1.5-Pro` |
| `EYPA_AI_MODELS` | 走 EYPA/AI 端点的模型白名单 | Echo/DeepSeek-V4/GLM-5.2/MiniMax 系列 |
| `AI_PERSONAS` | 人格定义数组 | customer / admin 双通道 |

> 详细配置项请参考 `config/config.php` 中的 `AI_PERSONAS` 数组注释。
