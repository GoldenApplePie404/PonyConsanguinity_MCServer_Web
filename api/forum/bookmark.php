<?php
/**
 * 帖子收藏 API
 * GET: 获取收藏列表 / 收藏状态
 * POST: 收藏/取消收藏
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
// 优先使用会话中的用户名（避免用户数据中 username 字段与键不一致）
$username = $sessionUsername ?: ($user['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $postId = $_GET['post_id'] ?? '';
    $action = $_GET['action'] ?? '';

    if ($action === 'list' && $username) {
        // 返回用户所有收藏的帖子（含帖子信息）
        $bookmarksData = read_json(BOOKMARKS_FILE);
        $userBookmarks = $bookmarksData[$username] ?? [];

        $postsData = secureReadData(POSTS_FILE);
        $allPosts = $postsData['posts'] ?? [];
        $bookmarkedPosts = [];
        foreach ($allPosts as $p) {
            if (in_array($p['id'], $userBookmarks)) {
                $bookmarkedPosts[] = [
                    'id' => $p['id'],
                    'title' => $p['title'] ?? '无标题',
                    'author' => $p['author'] ?? '',
                    'created_at' => $p['created_at'] ?? '',
                ];
            }
        }
        echo json_encode([
            'success' => true,
            'data' => [
                'bookmarks' => $bookmarkedPosts,
                'count' => count($bookmarkedPosts)
            ]
        ]);
        exit;
    }

    if (!empty($postId)) {
        // 查询指定帖子的收藏状态
        $bookmarksData = read_json(BOOKMARKS_FILE);
        $userBookmarks = $bookmarksData[$username] ?? [];

        // 计算总收藏数
        $total = 0;
        foreach ($bookmarksData as $u => $ids) {
            if (in_array($postId, $ids)) $total++;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'bookmarked' => $username ? in_array($postId, $userBookmarks) : false,
                'total' => $total
            ]
        ]);
        exit;
    }

    // 无参数时返回当前用户收藏总数
    if ($username) {
        $bookmarksData = read_json(BOOKMARKS_FILE);
        $userBookmarks = $bookmarksData[$username] ?? [];
        echo json_encode([
            'success' => true,
            'data' => ['count' => count($userBookmarks)]
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

    $bookmarksData = read_json(BOOKMARKS_FILE);
    if (!isset($bookmarksData[$username])) $bookmarksData[$username] = [];

    $idx = array_search($postId, $bookmarksData[$username]);
    $bookmarked = $idx !== false;

    if ($action === 'add' && !$bookmarked) {
        $bookmarksData[$username][] = $postId;
        $bookmarked = true;
    } elseif ($action === 'remove' && $bookmarked) {
        array_splice($bookmarksData[$username], $idx, 1);
        $bookmarked = false;
    } elseif ($action === 'toggle') {
        if ($bookmarked) {
            array_splice($bookmarksData[$username], $idx, 1);
            $bookmarked = false;
        } else {
            $bookmarksData[$username][] = $postId;
            $bookmarked = true;
        }
    }

    if (empty($bookmarksData[$username])) unset($bookmarksData[$username]);
    write_json(BOOKMARKS_FILE, $bookmarksData);

    // 计算该帖子总收藏数
    $total = 0;
    foreach ($bookmarksData as $u => $ids) {
        if (in_array($postId, $ids)) $total++;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'bookmarked' => $bookmarked,
            'total' => $total
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
