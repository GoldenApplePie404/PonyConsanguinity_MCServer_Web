<?php
/**
 * 用户个人内容查询 API
 * 根据用户名查询该用户的帖子和回复
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/../secure_data.php';
require_once __DIR__ . '/../../includes/auth_helper.php';

set_cors_headers();
set_security_headers();

header('Content-Type: application/json; charset=utf-8');

// 验证身份
$currentUser = AuthHelper::getCurrentUser();
if (!$currentUser) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$username = $currentUser['username'] ?? '';
if (empty($username)) {
    echo json_encode(['success' => false, 'message' => '无法获取用户名']);
    exit;
}

// 读取所有帖子
$postsData = secureReadData(POSTS_FILE);
$allPosts = $postsData['posts'] ?? [];

// 筛选当前用户的帖子
$myPosts = [];
foreach ($allPosts as $post) {
    if (($post['author'] ?? '') === $username) {
        // 读取帖子摘要（Markdown 文件前 200 字）
        $contentFile = CONTENT_DIR . '/' . ($post['content_file'] ?? $post['id'] . '.md');
        $summary = '';
        if (file_exists($contentFile)) {
            $raw = file_get_contents($contentFile);
            $summary = mb_substr($raw, 0, 200, 'UTF-8');
            // 清理 Markdown 标记
            $summary = preg_replace('/[#*>`_~\[\]()!-]/', '', $summary);
            $summary = trim(preg_replace('/\s+/', ' ', $summary));
            if (mb_strlen($raw) > 200) $summary .= '...';
        }
        $myPosts[] = [
            'id' => $post['id'],
            'title' => $post['title'] ?? '无标题',
            'forum' => $post['forum'] ?? 'general',
            'created_at' => $post['created_at'] ?? '',
            'replies' => $post['replies'] ?? 0,
            'views' => $post['views'] ?? 0,
            'summary' => $summary,
        ];
    }
}

// 按时间倒序排列
usort($myPosts, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

// 扫描所有回复文件，查找当前用户的回复
$myReplies = [];
$replyFiles = glob(REPLIES_DIR . '/*.json');
foreach ($replyFiles as $file) {
    $postId = basename($file, '.json');
    $repliesData = read_json($file);
    $replies = $repliesData['replies'] ?? [];
    foreach ($replies as $reply) {
        if (($reply['author'] ?? '') === $username) {
            // 找到对应的帖子标题
            $postTitle = '';
            foreach ($allPosts as $p) {
                if (($p['id'] ?? '') === $postId) {
                    $postTitle = $p['title'] ?? '无标题';
                    break;
                }
            }
            $myReplies[] = [
                'id' => $reply['id'],
                'post_id' => $postId,
                'post_title' => $postTitle,
                'content' => mb_substr($reply['content'] ?? '', 0, 150, 'UTF-8'),
                'created_at' => $reply['created_at'] ?? '',
            ];
        }
    }
}

// 按时间倒序排列
usort($myReplies, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

echo json_encode([
    'success' => true,
    'data' => [
        'posts' => $myPosts,
        'replies' => $myReplies,
        'stats' => [
            'post_count' => count($myPosts),
            'reply_count' => count($myReplies),
        ]
    ]
]);
