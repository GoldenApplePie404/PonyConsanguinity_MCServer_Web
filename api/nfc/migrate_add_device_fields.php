<?php
// ℹ️ 一次性迁移工具（2026-08-17 标注）：NFC 设备字段下发脚本，使命已完成，当前无调用方；如需为老用户补发设备凭证可手动运行，日常请勿调用。
/**
 * 迁移 / 下发脚本：为指定用户（或全体）加 NFC 设备字段。加法写入，不破坏现有字段。
 *
 * 受数据访问令牌保护（与 secure_data 一致）：?token=DATA_ACCESS_TOKEN
 *
 * 用法：
 *   ?token=DATA_ACCESS_TOKEN&user=GoldenApplePie   为单个用户生成/重发设备凭证
 *   ?token=DATA_ACCESS_TOKEN&all=1                 为全体用户生成（已绑定则重发）
 *
 * 加的字段（仅新增，不改动 password/email/role 等现有字段）：
 *   device_id            设备标识，如 DEV001
 *   device_token_hash    bcrypt(明文令牌)，存盘用
 *   device_token_created_at  下发时间戳（用于 5 分钟短时效判定）
 *   device_token_used    一次性模式标记
 *
 * 注意：明文令牌仅在此页面展示一次，请立即写入 NFC 标签的 NDEF 记录（URL 形式：
 *   https://你的域名/api/nfc/device_login.php?dev=DEV001&tok=明文令牌 ）。
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../secure_data.php';
require_once __DIR__ . '/../../includes/nfc_auth.php';

// 数据访问令牌校验（与 secure_data 机制一致）
verifyDataAccess(true);

header('Content-Type: text/html; charset=utf-8');

$users = secureReadData(USERS_FILE);

// 计算下一个 device_id 序号（避免与已有冲突）
$maxIdx = 0;
foreach ($users as $u) {
    if (!empty($u['device_id']) && preg_match('/^DEV(\d+)$/i', (string)$u['device_id'], $m)) {
        $maxIdx = max($maxIdx, (int)$m[1]);
    }
}
$nextIdx = $maxIdx + 1;

$targets = [];
if (isset($_GET['all']) && $_GET['all'] === '1') {
    $targets = array_keys($users);
} elseif (!empty($_GET['user'])) {
    $targets = [$_GET['user']];
} else {
    echo "<p>用法：<code>?token=DATA_ACCESS_TOKEN&amp;user=用户名</code> 或 <code>&amp;all=1</code></p>";
    exit;
}

$rows = [];
foreach ($targets as $uname) {
    if (!isset($users[$uname])) {
        $rows[] = ['username' => $uname, 'status' => '用户不存在', 'device_id' => '-', 'token' => '-'];
        continue;
    }

    // 复用已有 device_id，否则新分配 DEVxxx
    if (empty($users[$uname]['device_id'])) {
        $users[$uname]['device_id'] = 'DEV' . str_pad((string)$nextIdx, 3, '0', STR_PAD_LEFT);
        $nextIdx++;
    }

    // 生成全新令牌对（重发会覆盖旧哈希）
    $pair = nfc_generate_token_pair();
    $users[$uname]['device_token_hash']       = $pair['hash'];
    $users[$uname]['device_token_created_at'] = time();
    $users[$uname]['device_token_used']        = false;

    $rows[] = [
        'username'  => $uname,
        'status'    => '已生成',
        'device_id' => $users[$uname]['device_id'],
        'token'     => $pair['token'], // 明文，仅此展示一次
    ];
}

if (!secureWriteData(USERS_FILE, $users)) {
    echo "<p style='color:red'>写入 users.php 失败（请检查 data 目录写权限）</p>";
    exit;
}

nfc_debug_log('migrate', ['targets' => $targets, 'count' => count($rows)]);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>NFC 设备字段迁移结果</title>
    <style>
        body { font-family: "Microsoft YaHei", Arial, sans-serif; padding: 32px; background: #f5f6fa; color: #2c3e50; }
        h1 { font-size: 20px; }
        table { border-collapse: collapse; width: 100%; background: #fff; margin-top: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        th, td { border: 1px solid #e5e7eb; padding: 10px 12px; text-align: left; font-size: 13px; }
        th { background: #667eea; color: #fff; }
        code { background: #eef; padding: 2px 6px; border-radius: 4px; word-break: break-all; }
        .warn { background: #fff7e6; border: 1px solid #ffd591; padding: 12px 16px; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>NFC 设备凭证迁移结果</h1>
    <table>
        <thead>
            <tr><th>用户名</th><th>状态</th><th>device_id</th><th>明文令牌（仅此展示一次，写入 NFC 标签）</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['username']); ?></td>
                <td><?php echo htmlspecialchars($r['status']); ?></td>
                <td><code><?php echo htmlspecialchars($r['device_id']); ?></code></td>
                <td><code><?php echo htmlspecialchars($r['token']); ?></code></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="warn">
        <strong>重要：</strong> 把上表中的「明文令牌」写入对应 NFC 标签的 NDEF 记录，内容为完整 URL：<br>
        <code>https://你的域名/api/nfc/device_login.php?dev=DEVxxx&amp;tok=明文令牌</code><br>
        明文令牌仅在此页面显示一次；如需重新下发，再次运行本脚本即可（旧令牌会被覆盖失效）。
    </div>
</body>
</html>
