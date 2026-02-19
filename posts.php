<?php
require_once 'config.php';
require_once 'helper.php';

// 获取帖子列表
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $posts = [];
    $files = glob(POSTS_DIR . '/*.txt');

    foreach ($files as $file) {
        $post_id = basename($file, '.txt');
        $content = file_get_contents($file);
        $lines = explode("\n", $content, 2);
        $metadata = json_decode($lines[0], true);
        $metadata['id'] = $post_id;
        $posts[] = $metadata;
    }

    // 按创建时间倒序排列
    usort($posts, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    json_response(true, '获取成功', ['posts' => $posts], 200);
}

// 创建新帖子
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证 Token
    $token = get_token();
    $session = verify_token($token);

    if (!$session) {
        json_response(false, '未登录', null, 401);
    }

    $data = get_post_data();
    $title = trim($data['title'] ?? '');
    $content = trim($data['content'] ?? '');
    $category = $data['category'] ?? '普通讨论';

    if (empty($title) || empty($content)) {
        json_response(false, '标题和内容不能为空', null, 400);
    }

    // 创建帖子
    $post_id = generate_uuid();
    $metadata = [
        'title' => $title,
        'author' => $session['username'],
        'author_id' => $session['user_id'],
        'category' => $category,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'views' => 0,
        'replies' => 0
    ];

    $post_file = POSTS_DIR . '/' . $post_id . '.txt';
    $file_content = json_encode($metadata, JSON_UNESCAPED_UNICODE) . "\n" . $content;

    if (file_put_contents($post_file, $file_content)) {
        $metadata['id'] = $post_id;
        json_response(true, '发布成功', ['post' => $metadata], 201);
    } else {
        json_response(false, '发布失败', null, 500);
    }
}

json_response(false, '不支持的请求方法', null, 405);
?>
