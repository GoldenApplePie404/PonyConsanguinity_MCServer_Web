<?php
/**
 * 帖子点赞 API
 * GET: 获取点赞列表 / 点赞状态
 * POST: 点赞/取消点赞
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/../secure_data.php';
require_once __DIR__ . '/../../includes/auth_helper.php';

set_cors_headers();
set_security_headers();
header('Content-Type: application/json; charset=utf-8');

$user = AuthHelper::getCurrentUser();
$sessionUsername = AuthHelper::getUsernameFromToken();
$username = $sessionUsername ?: ($user['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $postId = $_GET['post_id'] ?? '';
    $action = $_GET['action'] ?? '';

    if ($action === 'list' && $username) {
        // 返回用户所有点赞过的帖子ID
        $likesData = read_json(LIKES_FILE);
        $userLikes = $likesData[$username] ?? [];
        echo json_encode([
            'success' => true,
            'data' => [
                'likes' => $userLikes,
                'count' => count($userLikes)
            ]
        ]);
        exit;
    }

    if ($action === 'stats') {
        // 返回所有帖子的点赞数统计
        $likesData = read_json(LIKES_FILE);
        $stats = [];
        foreach ($likesData as $user => $postIds) {
            foreach ($postIds as $pid) {
                $stats[$pid] = ($stats[$pid] ?? 0) + 1;
            }
        }
        echo json_encode([
            'success' => true,
            'data' => ['stats' => $stats]
        ]);
        exit;
    }

    if (!empty($postId)) {
        // 查询指定帖子的点赞状态和总数
        $likesData = read_json(LIKES_FILE);
        $userLikes = $likesData[$username] ?? [];
        $total = 0;
        foreach ($likesData as $u => $ids) {
            if (in_array($postId, $ids)) $total++;
        }
        echo json_encode([
            'success' => true,
            'data' => [
                'liked' => $username ? in_array($postId, $userLikes) : false,
                'total' => $total
            ]
        ]);
        exit;
    }

    // 无参数时返回当前用户点赞总数
    if ($username) {
        $likesData = read_json(LIKES_FILE);
        $userLikes = $likesData[$username] ?? [];
        echo json_encode([
            'success' => true,
            'data' => ['total' => count($userLikes)]
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => '缺少参数']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$username) {
        echo json_encode(['success' => false, 'message' => '请先登录']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = $input['post_id'] ?? '';
    $action = $input['action'] ?? 'toggle';

    if (empty($postId)) {
        echo json_encode(['success' => false, 'message' => '缺少帖子 ID']);
        exit;
    }

    $likesData = read_json(LIKES_FILE);
    if (!isset($likesData[$username])) $likesData[$username] = [];

    $idx = array_search($postId, $likesData[$username]);
    $liked = $idx !== false;

    if ($action === 'add' && !$liked) {
        $likesData[$username][] = $postId;
        $liked = true;
    } elseif ($action === 'remove' && $liked) {
        array_splice($likesData[$username], $idx, 1);
        $liked = false;
    } elseif ($action === 'toggle') {
        if ($liked) {
            array_splice($likesData[$username], $idx, 1);
            $liked = false;
        } else {
            $likesData[$username][] = $postId;
            $liked = true;
        }
    }

    if (empty($likesData[$username])) unset($likesData[$username]);
    write_json(LIKES_FILE, $likesData);

    // 计算该帖子总点赞数
    $total = 0;
    foreach ($likesData as $u => $ids) {
        if (in_array($postId, $ids)) $total++;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'liked' => $liked,
            'total' => $total
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
