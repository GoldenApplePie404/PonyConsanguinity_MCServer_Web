<?php
/**
 * 数据目录自动检测与初始化
 * 
 * 在每次 Web 请求启动时检查 data/ 目录是否存在。
 * 若不存在（例如刚 git clone 的新环境），从 data-init/ 模板自动创建。
 * 若已存在则跳过（保留已有数据，不做覆盖）。
 * 
 * ⚠️ 该文件为公共初始化逻辑，需由 git 跟踪；
 *    config/config.php 中的 init_config() 负责调用它。
 */

if (!defined('ACCESS_ALLOWED')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

/**
 * 确保 data/ 目录及其所有子目录、文件已初始化
 * 
 * 安全策略：
 * - 仅在目录不存在时创建（幂等，绝不覆盖已有数据）
 * - 对已有 data/ 目录仅补建缺失的子目录（容错）
 */
function ensure_data_initialized(): void
{
    $rootDir = dirname(__DIR__);
    $dataDir = $rootDir . '/data';
    $templateDir = $rootDir . '/data-init';

    // ── 阶段1：确保 data/ 本身存在 ──
    $dataExists = is_dir($dataDir);

    if (!$dataExists) {
        // 全新环境：从 data-init/ 完整复制
        recurseCopy($templateDir, $dataDir);
        // 设置 .htaccess 可读但不可外部访问（保留模板中的权限控制）
        return;
    }

    // ── 阶段2：data/ 已存在，仅补建可能缺失的子目录 ──
    $requiredDirs = [
        'content',
        'content/announcements',
        'replies',
        'recruitments',
        'user_notifications',
        'backups',
        'shop_items',
        'posts', // 新帖子系统（api/posts.php / api/post.php）内容目录
    ];

    foreach ($requiredDirs as $sub) {
        $path = $dataDir . '/' . $sub;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    // ── 阶段3：缺失的必需文件（用模板版本补充） ──
    $requiredFiles = [
        '.htaccess',
        'index.php',
        'users.php',
        'sessions.php',
        'posts.php',
        'notifications.php',
        'ai_conversations.php',
        'captchas.php',               // 滑块验证码运行时数据
        'messages.json',
        'announcements.json',
        'bookmarks.json',
        'likes.json',
        'notifications.json',
        'questionnaires.json',
        'performance_data.json',
        'mcstatus_cache.json',
        'mc_instances_cache.json',    // 子服实例状态缓存
        'images.json',
    ];

    foreach ($requiredFiles as $file) {
        $target = $dataDir . '/' . $file;
        if (!file_exists($target)) {
            $source = $templateDir . '/' . $file;
            if (file_exists($source)) {
                copy($source, $target);
            }
        }
    }

    // ── 阶段4：种子数据目录（仅当目标为空时才复制，不覆盖管理员已改的内容） ──
    $seedDirs = [
        'content'               => false, // 帖子正文 content/*.md 等公开内容（仅补缺失，不覆盖）
        'shop_items'            => false, // 仅补充缺失文件
        'content/announcements' => false,
    ];

    foreach ($seedDirs as $sub => $overwrite) {
        $srcDir  = $templateDir . '/' . $sub;
        $destDir = $dataDir . '/' . $sub;

        if (!is_dir($srcDir)) {
            continue;
        }
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        $files = glob($srcDir . '/*');
        if ($files === false) {
            continue;
        }
        foreach ($files as $srcFile) {
            $name = basename($srcFile);
            // 跳过 .gitkeep 等标记文件
            if ($name === '.gitkeep') {
                continue;
            }
            $destFile = $destDir . '/' . $name;
            if ($overwrite || !file_exists($destFile)) {
                copy($srcFile, $destFile);
            }
        }
    }
}

/**
 * 递归复制目录（用于全新初始化）
 */
function recurseCopy(string $src, string $dst): void
{
    if (!is_dir($src)) {
        return;
    }

    @mkdir($dst, 0755, true);
    $dir = opendir($src);
    if (!$dir) {
        return;
    }

    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;

        if (is_dir($srcPath)) {
            recurseCopy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}
