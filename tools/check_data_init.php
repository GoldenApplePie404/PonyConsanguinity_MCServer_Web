<?php
/**
 * CLI：检测并初始化 data/ 数据目录（手动触发）
 * ============================================================
 * 用法：php check_data_init.php
 *
 * 功能：
 *  1) 调用 includes/data-init.php 的 ensure_data_initialized()
 *     - data/ 不存在 → 从 data-init/ 全量复制（全新环境）
 *     - data/ 存在   → 幂等补建缺失的子目录与文件（不覆盖已有数据）
 *  2) 对比 data/ 与 data-init/ 模板，报告缺失/已补齐清单
 *
 * 输出为纯文本，供命令行（run.bat 菜单项）直接展示。
 */

define('ACCESS_ALLOWED', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 项目根目录 = 本脚本所在目录的上级（脚本位于 tools/ 下）
$root = dirname(__DIR__);
$dataDir = $root . '/data';
$templateDir = $root . '/data-init';

echo '==========================================' . PHP_EOL;
echo '  Data Directory Check & Init' . PHP_EOL;
echo '==========================================' . PHP_EOL;

// ── 前置检查：data-init 模板是否存在 ──
if (!is_dir($templateDir)) {
    echo '[ERROR] Template directory missing: data-init/' . PHP_EOL;
    echo '        Cannot initialize. Is the repository complete?' . PHP_EOL;
    exit(1);
}

// ── 1. 执行初始化引擎 ──
require_once $root . '/includes/data-init.php';
$wasMissing = !is_dir($dataDir);
ensure_data_initialized();

if ($wasMissing) {
    echo '[OK] data/ was missing -> fully recreated from data-init/' . PHP_EOL;
} else {
    echo '[OK] data/ exists -> missing files/dirs filled in (existing data untouched)' . PHP_EOL;
}

// ── 2. 对比模板，报告仍然缺失的文件 ──
echo PHP_EOL . '--- Missing vs template (should be empty) ---' . PHP_EOL;

$missing = array();
$tmplFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templateDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($tmplFiles as $file) {
    if ($file->isDir()) {
        continue;
    }
    $rel = substr($file->getPathname(), strlen($templateDir) + 1);
    // .gitkeep 仅为 git 占位符，运行时目录已由引擎创建，不视为缺失
    if (basename($rel) === '.gitkeep') {
        continue;
    }
    if (!file_exists($dataDir . '/' . $rel)) {
        $missing[] = $rel;
    }
}

if (empty($missing)) {
    echo '[OK] All template files present in data/' . PHP_EOL;
} else {
    foreach ($missing as $m) {
        echo '[WARN] Missing: ' . $m . PHP_EOL;
    }
}

// ── 3. 关键文件抽查 ──
echo PHP_EOL . '--- Key files ---' . PHP_EOL;
$keys = array('users.php', 'sessions.php', 'captchas.php', 'posts.php');
foreach ($keys as $k) {
    $ok = file_exists($dataDir . '/' . $k);
    echo ($ok ? '[OK] ' : '[WARN] ') . $k . PHP_EOL;
}

echo PHP_EOL . 'Done. Data directory is ready.' . PHP_EOL;
exit(0);
