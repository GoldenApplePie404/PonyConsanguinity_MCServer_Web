# 万驹同源服务器官网

<p align="center">
  <a href="#项目简介">项目简介</a> •
  <a href="#功能特性">功能特性</a> •
  <a href="#技术栈">技术栈</a> •
  <a href="#项目结构">项目结构</a> •
  <a href="#快速开始">快速开始</a> •
  <a href="#使用指南">使用指南</a> •
  <a href="#开发流程">开发流程</a> •
  <a href="#开发指导">开发指导</a> •
  <a href="#贡献指南">贡献指南</a>
</p>

## 项目简介

**万驹同源（PonyConsanguinity）官网**是一个专属于万驹同源 Minecraft 服务器官网，致力于为服务器玩家提供优质的社区环境，拥有以下功能：

- **服务器介绍**：详细的服务器信息和玩法介绍
- **论坛系统**：支持 Markdown 格式的帖子发布和回复；也包含服务器规则、公告等内容
- **用户系统**：注册、登录和个人中心，同时分为管理员和普通用户
- **服务器状态**：实时服务器状态监控，包含性能数据图表
- **性能监控**：记录和展示服务器性能数据（玩家人数、CPU、内存使用率）
- **音乐播放器**：内置音乐播放器，支持进度控制（闲着没事加上去的x）


## 技术栈

- **前端**：HTML、CSS、JavaScript
- **后端**：PHP

## 项目结构

```
PC_Web/
├── api/               # PHP API 接口
│   ├── forum.php      # 论坛相关 API
│   ├── login.php      # 登录 API
│   ├── register.php   # 注册 API
│   ├── performance.php # 性能数据记录 API
│   ├── get_performance.php # 性能数据获取 API
│   └── ...            # 其他 API 接口
├── assets/            # 静态资源
│   ├── img/           # 图片资源
│   └── music/         # 音乐资源
├── components/        # 可复用组件
│   ├── navbar.html    # 导航栏
│   ├── footer.html    # 页脚
│   └── sidebar-player.html # 音乐播放器
├── css/               # 样式文件
│   ├── style.css      # 主样式
│   └── ...            # 其他样式文件
├── data/              # 数据文件
│   ├── posts.php      # 帖子数据
│   ├── users.php      # 用户数据
│   └── ...            # 其他数据文件
├── js/                # JavaScript 文件
│   ├── api.js         # API 客户端
│   ├── main.js        # 主脚本
│   └── ...            # 其他脚本文件
├── pages/             # 页面文件
│   ├── forum.html     # 论坛页面
│   ├── status.html    # 状态页面
│   └── ...            # 其他页面
├── test/              # 测试文件
│   ├── test_token.php # 令牌验证测试脚本
│   └── ...            # 其他测试脚本
├── index.html         # 首页
├── log.md             # 日志
└── README.md          # 说明

```

## 快速开始

### 环境要求
- **本地开发**：任意静态文件服务器
- **生产部署**：支持 PHP 的 Web 服务器（如 Apache、Nginx）
- **浏览器**：现代浏览器（Chrome、Firefox、Edge 等）

### 本地开发

1. **克隆项目**
   ```bash
   git clone https://github.com/GoldenApplePie404/PonyConsanguinity_MCServer_Web.git
   cd PC_Web
   ```

2. **启动本地服务器**
   - 使用 PHP 内置服务器
     ```bash
     php -S localhost:8000
     ```
   - 或使用其他静态文件服务器（如 VS Code Live Server 扩展）

3. **访问网站**
   打开浏览器访问 `http://localhost:8000`

### 生产部署

1. **准备服务器**
   - 确保服务器安装了 PHP 8.0+
   - 配置 Web 服务器

2. **上传文件**
   将项目文件上传到服务器的 Web 根目录

3. **配置**
    - 项目已使用统一配置文件 `js/config.js`，会自动根据环境检测并设置正确的 API 地址
    - 本地开发环境（localhost/127.0.0.1）会自动使用 `http://localhost:8000`
    - 生产环境会自动使用相对路径 `/api`，无需手动修改配置
    - 如需自定义配置，可编辑 `js/config.js` 文件中的相关配置项

4. **访问网站**
   打开浏览器访问服务器域名

## 安全配置

### 概述

本项目包含敏感数据（用户信息、帖子内容等），必须采取适当的安全措施来保护这些数据。

### 核心安全机制

本项目实施了全面的数据系统安全性增强措施，通过多层防护机制保护敏感数据，防止未授权访问和数据泄露。

#### 1. PHP文件包装机制

**原理**：将部分数据从JSON文件转换为PHP文件，利用PHP的访问控制特性防止直接HTTP访问。

**实现方式**：
- 原始JSON文件（如`users.json`）转换为`users.php`
- PHP文件头部添加访问控制检查
- 数据以PHP数组形式存储，通过`include`语句读取

**示例代码**（users.php）：
```php
<?php
if (!defined('ACCESS_ALLOWED')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

return [
    'GoldenApplePie' => [
        'id' => 'c361cebb-3a36-4799-b094-46bc1d81f0f5',
        'username' => 'GoldenApplePie',
        'password' => 'gap12345',
        'email' => 'czhdqqyx6044@qq.com',
        'created_at' => '2026-01-26 09:35:23',
        'role' => 'admin'
    ],
];
?>
```

**安全效果**：
- 直接访问`/data/users.php`会返回403 Forbidden
- 只有定义了`ACCESS_ALLOWED`常量的PHP脚本才能读取数据
- 防止了通过浏览器直接查看敏感数据的风险

#### 2. 访问控制常量

**常量定义**：在`api/config.php`中定义全局访问控制常量。

```php
// 定义访问常量
define('ACCESS_ALLOWED', true);
```

**使用方式**：
- 所有需要访问敏感数据的API文件必须先引入`config.php`
- 引入后自动定义`ACCESS_ALLOWED`常量
- 数据文件检查此常量是否存在

**安全流程**：
```
API请求 → 引入config.php → 定义ACCESS_ALLOWED → 访问数据文件 → 验证常量 → 允许访问
```

#### 3. 安全数据访问函数

**函数定义**：在`api/secure_data.php`中提供统一的数据访问接口。

**核心函数**：

##### verifyDataAccess()
```php
function verifyDataAccess($requireToken = true) {
    if (!$requireToken) {
        return;
    }
    $token = $_GET['token'] ?? $_SERVER['HTTP_X_DATA_TOKEN'] ?? '';
    if ($token !== DATA_ACCESS_TOKEN) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
}
```

**功能**：
- 验证数据访问令牌
- 可配置是否强制要求令牌验证

##### 令牌安全建议

⚠️ **重要**：`DATA_ACCESS_TOKEN` 是系统的重要安全组件，请务必：

1. **修改默认令牌**：将 `api/secure_data.php` 中的默认值 `your-secret-token-here` 替换为强随机值
2. **使用强令牌**：使用尽量长的随机字符串，包含大小写字母、数字和特殊字符
3. **定期更换**：定期更新令牌值，保障系统安全性
4. **妥善保管**：不要将令牌提交到代码仓库或分享给未授权人员
5. **访问控制**：只在必要的操作中使用 `requireToken = true`，避免过度使用

**生成强令牌的方法**：
```bash
# 使用PHP生成
php -r "echo bin2hex(random_bytes(32));"

```

**示例强令牌**：
```
8f42a73e6b9f4c8d9e2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e
```

##### 令牌验证测试

为了验证数据访问令牌的功能，项目提供了测试脚本，位于 `test/` 目录：

**测试脚本**：
- `test/test_token.php` - 命令行测试脚本
- `test/test_token_api.php` - API端点测试脚本

**使用方法**：

1. **启动本地服务器**：
   ```bash
   php -S localhost:8000
   ```

2. **测试不提供令牌**：
   ```powershell
   Invoke-WebRequest -Uri "http://localhost:8000/test/test_token_api.php" -UseBasicParsing
   # 预期结果：403 Forbidden
   ```

3. **测试提供正确的令牌**：
   ```powershell
   Invoke-WebRequest -Uri "http://localhost:8000/test/test_token_api.php?token=YOUR_TOKEN_HERE" -UseBasicParsing
   # 预期结果：200 OK，返回成功信息
   ```

4. **测试提供错误的令牌**：
   ```powershell
   Invoke-WebRequest -Uri "http://localhost:8000/test/test_token_api.php?token=wrong-token" -UseBasicParsing
   # 预期结果：403 Forbidden
   ```

5. **测试不需要令牌的接口**：
   ```powershell
   Invoke-WebRequest -Uri "http://localhost:8000/test/test_token_api.php?action=public" -UseBasicParsing
   # 预期结果：200 OK，返回成功信息
   ```

**注意**：将 `YOUR_TOKEN_HERE` 替换为你在 `api/secure_data.php` 中设置的实际令牌值。

##### secureReadData()
```php
function secureReadData($file, $requireToken = false) {
    verifyDataAccess($requireToken);
    if (file_exists($file)) {
        return include $file;
    }
    return [];
}
```

**功能**：
- 安全读取数据文件
- 自动进行令牌验证（可选）
- 使用`include`语句读取PHP文件
- 文件不存在时返回空数组

##### secureWriteData()
```php
function secureWriteData($file, $data, $requireToken = false) {
    verifyDataAccess($requireToken);
    $dir = dirname($file);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    // 对于 PHP 文件，使用 include 格式写入
    $content = "<?php\n";
    $content .= "if (!defined('ACCESS_ALLOWED')) {\n";
    $content .= "    header('HTTP/1.1 403 Forbidden');\n";
    $content .= "    exit;\n";
    $content .= "}\n\n";
    $content .= "return " . var_export($data, true) . ";\n";
    $content .= "?>";
    file_put_contents($file, $content);
}
```

**功能**：
- 安全写入数据文件
- 自动创建目录（如果不存在）
- 自动添加访问控制头部
- 使用`var_export()`确保数据格式正确

**使用示例**：
```php
// 读取用户数据
$users = secureReadData(USERS_FILE);

// 写入会话数据
$sessions[$token] = $userData;
secureWriteData(SESSIONS_FILE, $sessions);
```

#### 4. 目录保护

**保护机制**：在`data/`目录下创建`index.php`文件。

**实现代码**：
```php
<?php
header('HTTP/1.1 403 Forbidden');
header('Status: 403 Forbidden');
echo 'Access Denied';
exit;
```

**安全效果**：
- 防止目录列表泄露
- 访问`/data/`返回403错误
- 保护目录结构信息

#### 5. 会话管理

**FIFO会话清理**：系统实现了先进先出（FIFO）的会话清理机制。

**配置**：
```php
// 会话配置
define('MAX_SESSIONS', 10);
```

**工作原理**：
- 每次用户登录时，系统会检查当前会话数量
- 如果会话数量达到或超过`MAX_SESSIONS`，系统会删除最旧的会话
- 然后添加新的会话，始终保持会话数量不超过限制


#### 6. API模式配置

**配置文件**：`js/api.js`

**关键设置**：
```javascript
// 强制使用真实API模式
USE_MOCK_MODE = false;
console.log('使用真实 API 模式，因为系统中已安装 PHP');
```

**功能说明**：
- `USE_MOCK_MODE = false`：使用真实PHP API
- `USE_MOCK_MODE = true`：使用模拟数据（开发测试用）
- 系统会自动检测API可用性

**API请求流程**：
```
前端请求 → api.js → 检查USE_MOCK_MODE → 
真实API: 发送到PHP后端 → secureReadData() → 读取数据 → 返回结果
Mock模式: 返回模拟数据 → 直接返回结果
```

### 数据文件结构

#### 转换后的PHP文件

| 原始文件 | 转换后文件 | 用途 |
|---------|-----------|------|
| `users.json` | `users.php` | 用户数据（用户名、密码、邮箱等） |
| `sessions.json` | `sessions.php` | 会话数据（登录令牌、用户信息） |
| `posts.json` | `posts.php` | 帖子数据（帖子列表、内容） |

#### 保留的JSON文件

| 文件 | 用途 | 安全措施 |
|------|------|---------|
| `notifications.json` | 系统通知 | 通过API访问，目录保护 |
| `user_notifications/{username}.json` | 用户已读通知 | 通过API访问，目录保护 |
| `replies/{post_id}.json` | 帖子回复 | 通过API访问，目录保护 |
| `content/{timestamp}.md` | 帖子内容 | 通过API访问，目录保护 |

### 安全架构图

```
┌─────────────────────────────────────────────────────────┐
│                     前端应用层                            │
│  (HTML/CSS/JavaScript)                                  │
└────────────────────┬────────────────────────────────────┘
                     │
                     │ HTTP请求
                     ▼
┌─────────────────────────────────────────────────────────┐
│                   API网关层                              │
│  (api/*.php)                                            │
│  - 引入config.php (定义ACCESS_ALLOWED)                   │
│  - 引入secure_data.php (提供安全访问函数)                  │
└────────────────────┬────────────────────────────────────┘
                     │
                     │ 验证令牌 + 定义常量
                     ▼
┌─────────────────────────────────────────────────────────┐
│                  安全访问层                               │
│  (secure_data.php)                                      │
│  - verifyDataAccess() (令牌验证)                         │
│  - secureReadData() (安全读取)                           │
│  - secureWriteData() (安全写入)                          │
└────────────────────┬────────────────────────────────────┘
                     │
                     │ include访问
                     ▼
┌─────────────────────────────────────────────────────────┐
│                   数据存储层                              │
│  (data/*.php)                                           │
│  - 检查ACCESS_ALLOWED常量                                │
│  - 返回PHP数组数据                                        │
└─────────────────────────────────────────────────────────┘
```

### 安全测试

#### 测试用例

**测试1：直接访问数据文件**
```bash
# 预期结果：403 Forbidden
curl http://localhost:8000/data/users.php
```

**测试2：通过API访问数据**
```bash
# 预期结果：成功返回数据
curl -H "Authorization: Bearer {token}" \
     http://localhost:8000/api/notification.php?action=list
```

**测试3：目录列表保护**
```bash
# 预期结果：403 Forbidden
curl http://localhost:8000/data/
```

**测试4：通知功能测试**
```bash
# 预期结果：成功返回通知列表
curl -H "Authorization: Bearer {token}" \
     http://localhost:8000/api/notification.php?action=list
```

### 安全优势

1. **多层防护**：PHP文件包装 + 访问控制常量 + 令牌验证
2. **最小权限原则**：只有定义了常量的API才能访问数据
3. **防止直接访问**：所有敏感文件都无法通过HTTP直接访问
4. **统一接口**：通过`secure_data.php`提供统一的数据访问接口
5. **灵活配置**：可配置是否强制要求令牌验证
6. **易于维护**：代码结构清晰，便于后续扩展


## 使用指南

### 论坛系统

#### 发布帖子
1. 点击导航栏中的「论坛」进入论坛页面
2. 点击「发布新帖」按钮
3. 填写标题和内容（支持 Markdown 格式）
4. 点击「发布」按钮

#### 回复帖子
1. 进入帖子详情页面
2. 在回复框中输入内容（支持 Markdown 格式）
3. 点击「回复」按钮

### 音乐播放器

- **播放/暂停**：点击播放按钮
- **调整音量**：拖动音量滑块
- **进度控制**：点击进度条调整播放位置
- **切换歌曲**：点击歌曲列表中的歌曲（这些歌曲全身本人自行编的曲，不喜勿喷！）

### 用户系统

#### 注册
1. 点击导航栏中的「登录/注册」
2. 选择「注册」选项卡
3. 填写用户名、邮箱和密码
4. 点击「注册」按钮
5. 注册时不能使用已存在的用户名

#### 登录
1. 点击导航栏中的「登录/注册」
2. 选择「登录」选项卡
3. 填写用户名和密码
4. 点击「登录」按钮

#### 注销
1. 进入用户个人中心页面
2. 选择「注销」选项卡
3. 输入密码确认注销

### 公告系统

#### 公告管理工具
管理员可以通过公告管理工具页面（`tools/announcement-manager.html`）进行公告的添加、编辑和删除操作。

##### 访问方式
1. 以管理员身份登录系统
2. 在管理面板中找到「公告管理」选项
3. 点击进入公告管理工具页面

##### 功能说明

1. **公告列表**：查看所有已发布的公告，显示标题、类型、作者、日期等信息
2. **添加公告**：创建新公告，填写公告ID、标题、类型、作者、发布日期、摘要和内容
3. **编辑公告**：修改现有公告的内容，包括标题、类型、作者、发布日期、摘要和内容
4. **删除公告**：删除不需要的公告
5. **预览功能**：实时预览 Markdown 格式的公告内容

##### 添加公告
1. 在「添加公告」标签页中填写所有必填字段
2. 使用 Markdown 格式编写公告内容
3. 点击「保存公告」按钮
4. 系统会显示成功提示，并自动刷新公告列表

##### 编辑公告
1. 在「编辑公告」标签页中选择要编辑的公告
2. 修改需要更新的字段
3. 点击「保存修改」按钮
4. 系统会显示成功提示，并自动刷新公告列表

##### 删除公告
1. 在「公告列表」标签页中找到要删除的公告
2. 点击「删除」按钮
3. 确认删除操作
4. 系统会显示成功提示，并自动刷新公告列表

##### 数据存储
- 公告元数据存储在 `data/announcements.json` 文件中
- 公告内容存储在 `data/content/announcements/` 目录下的 Markdown 文件中
- 系统会自动创建和管理这些文件

##### 注意事项
- 公告ID必须唯一，建议使用小写字母和连字符
- 公告内容支持完整的 Markdown 语法
- 保存公告后会自动创建对应的 Markdown 文件
- 请确保有足够的文件读写权限


## 开发指导

### 画廊图片添加方法

#### 概述

本项目首页包含一个图片画廊，用于展示服务器风采。添加新图片需要修改两个文件：`index.html` 和 `css/style.css`。

#### 步骤1：在index.html中添加图片

在 `gallery-track` 中，你需要添加两次图片（第一组和第二组，用于无缝循环）：

**第一组位置**：在第254行之前添加
```html
<div class="gallery-item" data-title="图片标题" data-description="图片描述">
    <img src="assets/img/图片文件名.png" alt="图片标题">
    <div class="gallery-item-overlay">
        <div class="gallery-item-description">图片描述</div>
    </div>
</div>
```

**第二组位置**：在第339行之前添加（复制第一组的代码）

#### 步骤2：在css/style.css中更新动画距离

每添加一张图片，需要更新 `@keyframes scroll` 中的移动距离：
- 每张图片宽度：300px
- 左右边距：15px × 2 = 30px
- 单张图片总宽度：330px
- 动画移动距离 = 图片总数 × 330px

例如：
- 14张图片：4620px
- 15张图片：4950px
- 16张图片：5280px

在 `css/style.css` 的 `@keyframes scroll` 中修改：
```css
100% {
    transform: translateX(-4620px);
}
```

### 页面模板使用

#### 概述

本项目提供了统一的页面模板，用于创建符合网站风格的新页面，避免重复代码。所有页面都应遵循此模板结构。

#### 文件位置

模板文件位于：`templates/page-template.html`

#### 使用方法

1. 复制 `templates/page-template.html` 到 `pages/` 目录
2. 重命名为合适的文件名，如 `new-page.html`
3. 根据需要修改内容

#### 模板结构说明

##### 1. CSS样式引入

```html
<!-- ========== CSS样式文件引入 ========== -->
<link rel="stylesheet" href="../css/style.css?v=3.22">
<link rel="stylesheet" href="../css/sidebar-player.css?v=2.0">
<link rel="stylesheet" href="../css/back-to-top.css?v=1.0">
```

##### 2. 组件配置

```html
<!-- ========== body标签中的data-components属性 ========== -->
<!-- 可用组件：sidebarPlayer, backToTop, navbar, footer -->
<body data-components="sidebarPlayer,backToTop,navbar,footer">
```

根据页面需要，可以移除不需要的组件。

##### 3. Banner区域（可选）

如果页面需要Banner，使用以下代码，并添加 `common-banner` 类以确保统一的高度和布局：

```html
<!-- ========== Banner区域（可选） ========== -->
<section class="subpage-banner common-banner">
    <div class="subpage-banner-bg"></div>
    <div class="subpage-banner-particles"></div>
    <div class="container">
        <div class="subpage-banner-content">
            <h1 class="subpage-banner-title">页面标题</h1>
            <p class="subpage-banner-subtitle">副标题描述</p>
        </div>
    </div>
</section>
```

##### 4. 公共Banner类说明

本项目使用 `common-banner` 类来统一所有子页面的Banner样式：

- **适用范围**：所有子页面（论坛、状态页、皮肤站等），不包括首页
- **统一高度**：默认最小高度为350px
- **布局方式**：使用Flex布局居中内容
- **响应式设计**：使用min-height确保灵活性

**使用示例**：

```html
<!-- 论坛页面Banner -->
<section class="forum-banner common-banner">
    <!-- 内容 -->
</section>

<!-- 状态页面Banner -->
<section class="status-banner common-banner">
    <!-- 内容 -->
</section>

<!-- 皮肤站页面Banner -->
<section class="skin-header common-banner">
    <!-- 内容 -->
</section>
```

**注意**：首页使用独立的 `hero-banner` 类，高度为650px，不受此公共类影响。

##### 4. 主要内容区域

```html
<!-- ========== 主要内容区域 ========== -->
<div class="section">
    <div class="container">
        <!-- 在这里添加页面主要内容 -->
        <!-- 示例：卡片布局 -->
        <div class="card fade-in">
            <div class="card-header">
                <h2>卡片标题</h2>
            </div>
            <div class="card-body">
                <p>卡片内容...</p>
            </div>
        </div>
    </div>
</div>
```

##### 5. JavaScript脚本引入

**注意：引入顺序很重要，必须按照以下顺序**

```html
<!-- 1. API工具库（如果需要） -->
<script src="../js/api.js"></script>

<!-- 2. 主脚本（包含常用函数） -->
<script src="../js/main.js?v=3.5"></script>

<!-- 3. 回到顶部脚本 -->
<script src="../js/back-to-top.js?v=1.0"></script>

<!-- 4. 导航栏脚本 -->
<script src="../js/navbar.js?v=1.0"></script>

<!-- 5. 音乐播放器脚本 -->
<script src="../js/sidebar-player.js?v=3.0"></script>

<!-- 6. 组件加载器 -->
<script src="../components/loader.js?v=3.0"></script>
```

#### 常用组件说明

##### 卡片组件

```html
<div class="card fade-in">
    <div class="card-header">
        <h2>卡片标题</h2>
    </div>
    <div class="card-body">
        <p>卡片内容...</p>
    </div>
</div>
```

##### 动画效果

- `fade-in`：淡入效果
- `fade-in-delay-1`：延迟淡入（1级延迟）
- `fade-in-delay-2`：延迟淡入（2级延迟）
- 以此类推...

##### 按钮样式

```html
<!-- 主要按钮 -->
<button class="btn btn-primary">按钮文本</button>

<!-- 次要按钮 -->
<button class="btn btn-outline">按钮文本</button>

<!-- 小按钮 -->
<button class="btn btn-sm btn-primary">小按钮</button>
```

#### 常见页面类型示例

##### 1. 简单信息页

```html
<div class="section">
    <div class="container">
        <div class="card fade-in">
            <div class="card-header">
                <h2>页面标题</h2>
            </div>
            <div class="card-body">
                <p>页面内容...</p>
            </div>
        </div>
    </div>
</div>
```

##### 2. 列表页（如公告页）

```html
<div class="section announcement-page">
    <div class="container">
        <div class="announcement-list">
            <!-- 列表项 -->
            <div class="announcement-item fade-in">
                <div class="announcement-header">
                    <h2 class="announcement-title">标题</h2>
                    <div class="announcement-meta">
                        <span class="announcement-type update">类型</span>
                        <span class="announcement-date">日期</span>
                    </div>
                </div>
                <div class="announcement-content">
                    <p>内容...</p>
                </div>
                <div class="announcement-actions">
                    <a href="#" class="announcement-link">查看详情 →</a>
                </div>
            </div>
        </div>
    </div>
</div>
```

##### 3. 表单页

```html
<div class="section">
    <div class="container">
        <div class="card fade-in">
            <div class="card-header">
                <h2>表单标题</h2>
            </div>
            <div class="card-body">
                <form>
                    <div class="form-group">
                        <label>标签</label>
                        <input type="text" placeholder="请输入内容">
                    </div>
                    <div class="form-group">
                        <label>标签</label>
                        <textarea rows="5" placeholder="请输入内容"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">提交</button>
                </form>
            </div>
        </div>
    </div>
</div>
```

#### 注意事项

1. **路径问题**：确保CSS和JS文件的路径正确（通常使用`../`返回上一级目录）
2. **版本号**：在CSS和JS文件名后添加版本号（如`?v=3.22`）以避免缓存问题
3. **响应式设计**：使用已有的CSS类确保页面在不同设备上正常显示
4. **组件顺序**：严格按照模板中的顺序引入JavaScript文件
5. **动画效果**：合理使用动画效果，避免过度使用

#### 示例页面

参考以下页面了解实际应用：
- `pages/announcement.html` - 列表页示例
- `pages/post-detail.html` - 详情页示例
- `pages/forum.html` - 交互页示例

### 音乐播放器开发指导

#### 概述

音乐播放器是网站的一个核心组件，位于页面右侧边栏，提供音乐播放、进度控制、音量调节等功能。

#### 文件结构

```
PC_Web/
├── components/
│   └── sidebar-player.html  # 音乐播放器组件
├── css/
│   └── sidebar-player.css   # 音乐播放器样式
└── js/
    └── sidebar-player.js    # 音乐播放器脚本
```

#### 核心功能

1. **音乐播放控制**：播放/暂停、上一曲/下一曲
2. **进度控制**：进度条显示和点击调整
3. **音量调节**：音量滑块控制
4. **歌曲信息显示**：歌曲标题
5. **播放列表**：多首歌曲切换

#### 组件集成

在页面中集成音乐播放器组件：

```html
<!-- 音乐播放器 -->
<div id="app-sidebar-player"></div>
<script src="js/sidebar-player.js?v=3.0"></script>
```

同时，在body标签中添加对应的组件配置：

```html
<body data-components="sidebarPlayer,backToTop,navbar,footer">
```

#### 添加新歌曲

1. **添加音乐文件**：将音乐文件上传到 `assets/music/` 目录
2. **修改播放器组件**：编辑 `components/sidebar-player.html` 文件，在播放列表中添加新歌曲信息：

```html
<!-- 示例：添加新歌曲 -->
<div class="song-item" data-src="../assets/music/NewSong.mp3" data-title="新歌曲" data-artist="艺术家">
    <div class="song-title">新歌曲</div>
    <div class="song-artist">艺术家</div>
</div>
```
## 系统配置指南

### 核心配置文件 (config.php)

#### 功能说明
`api/config.php` 是项目的核心配置文件，包含了所有重要的系统配置，包括API密钥、服务器地址、数据文件路径、会话配置等。

#### 配置项

```php
// MCSManager API 配置
define('MCSM_API_URL', 'https://mcpanel.eqmemory.cn/mcs/api');   
define('MCSM_API_KEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxx');  
// 服务器状态 API 配置
define('MCSTATUS_API_URL', 'http://mcstatus.goldenapplepie.xyz/api');  // 
define('MC_SERVER_IP', 'xx.xxxx.xx');
define('MC_SERVER_PORT', 25565);

// 访问控制常量
define('ACCESS_ALLOWED', true);

// 会话配置
define('MAX_SESSIONS', 10);

// 数据文件路径配置
define('USERS_FILE', dirname(__DIR__) . '/data/users.php');
define('SESSIONS_FILE', dirname(__DIR__) . '/data/sessions.php');
define('POSTS_FILE', dirname(__DIR__) . '/data/posts.php');
define('CONTENT_DIR', dirname(__DIR__) . '/data/content');
define('REPLIES_DIR', dirname(__DIR__) . '/data/replies');
```

**配置说明**：

| 配置项 | 说明 | 示例值 |
|--------|------|---------|
| MCSM_API_URL | MCSManager API地址 | https://xx.xxxxxx.xx/api |
| MCSM_API_KEY | MCSManager API密钥 | xxxxxxxxxxxxxxxxxxxxxxxxxxxx |
| MCSTATUS_API_URL | 服务器状态API地址 | http://mcstatus.goldenapplepie.xyz/api |
| MC_SERVER_IP | Minecraft服务器IP | xx.xxxx.xx |
| MC_SERVER_PORT | Minecraft服务器端口 | 25565 |
| ACCESS_ALLOWED | 访问控制常量 | true |
| MAX_SESSIONS | 最大会话数量 | 10 |
| USERS_FILE | 用户数据文件路径 | /data/users.php |
| SESSIONS_FILE | 会话数据文件路径 | /data/sessions.php |
| POSTS_FILE | 帖子数据文件路径 | /data/posts.php |
| CONTENT_DIR | 帖子内容目录 | /data/content |
| REPLIES_DIR | 回复数据目录 | /data/replies |

#### 配置说明

##### 1. MCSManager API配置
- **MCSM_API_URL**：MCSManager面板的API地址，用于服务器管理功能
- **MCSM_API_KEY**：访问MCSManager API的密钥，需要从MCSManager面板获取

##### 2. 服务器状态API配置
- **MCSTATUS_API_URL**：自定义的服务器状态API地址
- **MC_SERVER_IP**：Minecraft服务器的IP地址
- **MC_SERVER_PORT**：Minecraft服务器的端口号（默认25565）

##### 3. 访问控制常量
- **ACCESS_ALLOWED**：定义此常量以允许访问受保护的数据文件
- 所有需要访问敏感数据的API文件必须先引入config.php

##### 4. 会话配置
- **MAX_SESSIONS**：系统允许的最大会话数量
- 当会话数量超过此值时，系统会自动删除最旧的会话（FIFO机制）

##### 5. 数据文件路径
- **USERS_FILE**：用户数据文件路径
- **SESSIONS_FILE**：会话数据文件路径
- **POSTS_FILE**：帖子数据文件路径
- **CONTENT_DIR**：帖子内容目录
- **REPLIES_DIR**：回复数据目录

#### 自动初始化

config.php会自动检查并创建必要的目录和文件：
- 确保data目录存在
- 确保content目录存在
- 确保replies目录存在
- 如果用户、会话或帖子文件不存在，会自动创建

#### 安全提示

⚠️ **重要**：
1. **保护API密钥**：不要将包含API密钥的config.php提交到公共代码仓库
2. **定期更换密钥**：定期更换MCSM_API_KEY以增强安全性
3. **限制访问**：确保config.php无法通过HTTP直接访问
4. **备份配置**：定期备份config.php文件
5. **生产环境**：在生产环境中，考虑使用环境变量存储敏感信息

#### 修改配置

1. **打开配置文件**：使用文本编辑器打开`api/config.php`
2. **修改配置项**：根据需要修改相应的配置值
3. **保存文件**：保存修改后的文件
4. **重启服务器**：如果使用PHP内置服务器，需要重启以使配置生效

### 数据库查询配置

#### 功能说明
数据库查询组件允许管理员通过网页界面直接查看Minecraft服务器的MySQL数据库，包括表结构、表数据等信息。

备注：状态页的黄金券（万驹同源服务器通用稀有货币的名称）排行榜就是基于此完成的，采用的是playerpoints插件的数据库查询功能，更多配置请参考playerpoints插件的文档。

#### 配置文件
- **文件路径**：`api/db_test.php`
- **前端页面**：`pages/db_test.html`

备注：db_test仅提供了数据库查询功能，不支持数据库操作（如插入、更新、删除），如有其他功能需求，请自行修改代码。

#### 配置项

```php
$config = array(
    'hostname' => 'xxx.xxx.xxx.xxx',
    'port' => 3306,
    'database' => 'database',
    'username' => 'username',
    'password' => 'password'
);
```

**配置说明**：
| 配置项 | 说明 | 示例值 |
|--------|------|---------|
| hostname | MySQL服务器地址 | xxx.xxx.xxx.xxx |
| port | MySQL端口 | 3306 |
| database | 数据库名称 | database |
| username | 数据库用户名 | username |
| password | 数据库密码 | password |

#### 功能特性

1. **查看数据库信息**：显示数据库名称和所有表
2. **查看表结构**：显示表的字段信息（字段名、类型、是否为空等）
3. **查看表数据**：显示表中的数据，支持分页（每页10条记录）
4. **分页功能**：支持翻页浏览大量数据

#### 访问方式

- **前端页面**：`/pages/db_test.html`（使用统一配置自动适配环境）
- **后端API**：`/api/db_test.php`（使用统一配置自动适配环境）

#### 安全提示

⚠️ **重要**：数据库查询组件包含敏感的数据库凭证，请确保：
1. 不要将配置文件提交到公共代码仓库
2. 在生产环境中使用强密码
3. 限制访问权限，只有管理员可以访问此页面
4. 定期更换数据库密码

### 弹幕系统配置

#### 功能说明
弹幕系统位于首页Banner区域，实现了从右向左滚动的弹幕效果，支持随机颜色、随机速度和鼠标悬停暂停功能。现在通过代码配置控制，不再提供页面开关按钮。

#### 配置文件
- **文件路径**：`js/main.js` 中的 `initDanmaku()` 函数

#### 配置选项

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

1. **开启/关闭弹幕**
   ```javascript
   enabled: true, // true = 开启，false = 关闭
   ```

2. **修改弹幕内容**
   在 `messages` 数组中添加或修改弹幕文本。

3. **修改弹幕颜色**
   在 `colors` 数组中添加或修改颜色值。

4. **调整弹幕生成频率**
   ```javascript
   interval: 1500, // 改为你想要的毫秒数
   ```

5. **调整初始弹幕数量**
   ```javascript
   initialCount: 5, // 改为你想要的初始数量
   ```

6. **调整弹幕启动延迟**
   ```javascript
   startDelay: 5500, // 改为你想要的延迟毫秒数
   ```

7. **调整弹幕滚动速度**
   在 `createDanmaku()` 函数中修改：
   ```javascript
   const duration = 8 + Math.random() * 8; // 最小8秒，最大16秒
   ```

8. **调整弹幕字体大小**
   在 `createDanmaku()` 函数中修改：
   ```javascript
   const fontSize = 14 + Math.random() * 4; // 最小14px，最大18px
   ```

### 性能监控系统配置

#### 功能说明
性能监控系统位于状态页面，用于记录和展示服务器的性能数据，包括：

- **玩家人数**：实时在线玩家数量
- **CPU使用率**：服务器CPU使用百分比
- **内存使用率**：服务器内存使用百分比
- **时间轴图表**：展示最近一段时间的数据变化趋势

#### 数据存储机制

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
- 当数据点数量达到配置上限时，自动删除最早的数据点
- 始终保留最新的性能数据
- 确保数据文件大小合理，不会无限增长

#### 修改保存次数

**默认配置**：最多保存50个数据点

**修改方法**：

1. **打开配置文件**：`api/performance.php`

2. **找到以下代码**：
```php
// 只保留最近的数据（最多50个点）
if (count($existingData) > 50) {
    $existingData = array_slice($existingData, -50);
}
```

3. **修改数字**：将 `50` 改为你想要的最大数据点数量

**示例**：
```php
// 只保留最近的数据（最多30个点）
if (count($existingData) > 30) {
    $existingData = array_slice($existingData, -30);
}
```

### 通知

#### 功能说明
通知系统用于向用户发送信息，但仅限管理员操作

#### 具体细节

管理员用户可在管理员面板——发送通知页面输入通知内容，系统会将通知发送给所有用户。
通知数据存储在 `data/notifications.json` 文件中。
暂不支持markdown语法
可以使用占位符：
- `{username}`：替换为用户实际用户名
- `{server_name}`：替换为服务器名称
- `{server_ip}`：替换为服务器IP地址
- `{current_date}`：替换为当前日期（格式：Y-m-d）
- `{current_time}`：替换为当前时间（格式：H:i:s）

#### 占位符修改

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

### 统一配置文件 (config.js)

#### 功能说明
`js/config.js` 是项目的统一配置文件，用于管理全局配置，自动区分本地开发环境和生产环境，无需手动修改配置。

#### 配置文件位置
```
PC_Web/
└── js/
    └── config.js  # 统一配置文件
```

#### 配置项说明

##### 1. 环境检测
```javascript
const ENV = {
    // 检测是否为本地开发环境
    isLocalhost: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1',
    
    // 检测是否为生产环境
    isProduction: window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1'
};
```

##### 2. API 配置
```javascript
const API_CONFIG = {
    // API 基础地址
    // 本地开发环境使用 localhost:8000，生产环境使用指定域名
    baseUrl: ENV.isLocalhost ? 'http://localhost:8000/api' : 'https://您的域名/api',
    
    // API 完整地址（用于某些需要完整 URL 的场景）
    fullUrl: ENV.isLocalhost ? 'http://localhost:8000' : 'https://您的域名'
};
```

##### 3. 页面路径配置
```javascript
const PATH_CONFIG = {
    // 数据库查询页面
    dbTest: ENV.isLocalhost ? 'http://localhost:8000/pages/db_test.html' : 'https://您的域名/pages/db_test.html',
    
    // 公告管理页面
    announcementManager: ENV.isLocalhost ? 'http://localhost:8000/tools/announcement-manager.html' : 'https://您的域名/tools/announcement-manager.html'
};
```

##### 4. 应用配置
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

#### 如何修改域名配置

1. **打开配置文件**：使用文本编辑器打开 `js/config.js` 文件

2. **修改 API 配置**：
   ```javascript
   // API 配置
   const API_CONFIG = {
       // API 基础地址
       baseUrl: ENV.isLocalhost ? 'http://localhost:8000/api' : 'https://您的新域名/api',
       
       // API 完整地址
       fullUrl: ENV.isLocalhost ? 'http://localhost:8000' : 'https://您的新域名'
   };
   ```

3. **修改页面路径配置**：
   ```javascript
   // 页面路径配置
   const PATH_CONFIG = {
       // 数据库查询页面
       dbTest: ENV.isLocalhost ? 'http://localhost:8000/pages/db_test.html' : 'https://您的新域名/pages/db_test.html',
       
       // 公告管理页面
       announcementManager: ENV.isLocalhost ? 'http://localhost:8000/tools/announcement-manager.html' : 'https://您的新域名/tools/announcement-manager.html'
   };
   ```

4. **保存文件**：保存修改后的配置文件

#### 环境自动适配

- **本地开发环境**：当访问地址为 `localhost` 或 `127.0.0.1` 时，自动使用 `http://localhost:8000`
- **生产环境**：当访问地址为其他域名时，自动使用配置的域名

#### 如何在其他文件中使用配置

##### 在 HTML 文件中使用

1. 首先引入配置文件：
   ```html
   <script src="../js/config.js"></script>
   ```

2. 然后在 JavaScript 代码中使用：
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

##### 在 JavaScript 文件中使用

1. 确保配置文件在其他 JS 文件之前加载
2. 直接使用全局配置对象：
   ```javascript
   // 使用 API 配置
   const response = await fetch(API_CONFIG.baseUrl + '/mcstatus.php');
   
   // 使用应用配置
   if (APP_CONFIG.debugMode) {
       console.log('调试信息');
   }
   ```

#### 配置示例

**本地开发环境配置**（自动生效）：
- API 地址：`http://localhost:8000/api`
- 完整 URL：`http://localhost:8000`

**生产环境配置**（需要手动设置）：
- API 地址：`https://mcpc.goldenapplepie.xyz/api`
- 完整 URL：`https://mcpc.goldenapplepie.xyz`

#### 配置验证

配置文件会在页面加载时在控制台输出当前环境信息，您可以通过浏览器开发者工具查看：

```
=== 环境配置 ===
当前环境: 本地开发环境
API 基础地址: http://localhost:8000/api
完整 URL: http://localhost:8000
================
```

### 卫星地图配置

#### 功能说明
卫星地图页面（`pages/map.html`）集成了 Minecraft 服务器的实时地图功能，使用 **Dynmap 插件**实现。页面具有以下特性：

- **科技感加载动画**：卫星、流星、星星等太空元素
- **智能加载检测**：自动检测地图加载状态
- **错误处理机制**：连接失败时显示科技感错误界面
- **响应式设计**：适配不同屏幕尺寸

#### 配置文件
- **文件路径**：`pages/map.html`

#### 配置项

##### 1. Dynmap 服务器地址

**位置**：`pages/map.html` 第 825 行

```html
<iframe 
    src="http://115.231.176.218:11823/" 
    allowfullscreen 
    onload="onIframeLoad()"
    onerror="onIframeError()"
></iframe>
```

**修改方法**：
将 `src` 属性的值修改为你的 Dynmap 服务器地址：
```html
src="http://你的服务器IP:端口/"
```

**常见 Dynmap 默认端口**：
- 标准端口：`8123`
- 自定义端口：根据服务器配置而定


**位置**：`pages/map.html` 第 828-872 行

**错误信息**：
```html
<div class="error-title">连接失败</div>
<div class="error-message">无法连接到卫星数据服务器</div>
<div class="error-details">
    <div class="error-detail-item">
        <span class="error-detail-label">服务器地址</span>
        <span class="error-detail-value">115.231.176.218:11823</span>
    </div>
    <div class="error-detail-item">
        <span class="error-detail-label">错误代码</span>
        <span class="error-detail-value">CONNECTION_TIMEOUT</span>
    </div>
</div>
```

**修改方法**：
- 修改 `error-title` 改变错误标题
- 修改 `error-message` 改变错误描述
- 修改 `error-detail-value` 改变服务器地址和错误代码

#### Dynmap 插件配置

##### 服务器端配置

1. **安装 Dynmap 插件**
   - 下载适用于你 Minecraft 服务器的 Dynmap 版本
   - 将插件放入服务器的 `plugins` 目录
   - 重启服务器

2. **配置 Dynmap**
   - 编辑 `plugins/dynmap/configuration.txt`
   - 设置 Web 端口（默认 8123）
   - 配置地图渲染参数

3. **开放端口**
   - 在服务器防火墙中开放 Dynmap 端口
   - 确保外网可以访问

更多配置请参考 [Dynmap 官方文档](https://github.com/webbukkit/dynmap/wiki)