<?php
require_once 'config.php';
require_once 'helper.php';

$post_id = $_GET['id'] ?? '';

if (empty($post_id)) {
    json_response(false, '缺少帖子ID', null, 400);
}

$post_file = POSTS_DIR . '/' . $post_id . '.txt';

// 获取帖子详情
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($post_file)) {
        json_response(false, '帖子不存在', null, 404);
    }

    $content = file_get_contents($post_file);
    $lines = explode("\n", $content, 2);
    $metadata = json_decode($lines[0], true);
    $metadata['id'] = $post_id;
    $metadata['content'] = $lines[1] ?? '';

    json_response(true, '获取成功', ['post' => $metadata], 200);
}

// 更新帖子
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // 验证 Token
    $token = get_token();
    $session = verify_token($token);

    if (!$session) {
        json_response(false, '未登录', null, 401);
    }

    if (!file_exists($post_file)) {
        json_response(false, '帖子不存在', null, 404);
    }

    // 读取现有帖子
    $content = file_get_contents($post_file);
    $lines = explode("\n", $content, 2);
    $metadata = json_decode($lines[0], true);

    // 检查权限
    if ($metadata['author'] !== $session['username']) {
        json_response(false, '无权限编辑此帖子', null, 403);
    }

    $data = get_post_data();
    $title = trim($data['title'] ?? '');
    $content_text = trim($data['content'] ?? '');

    if (empty($title) || empty($content_text)) {
        json_response(false, '标题和内容不能为空', null, 400);
    }

    // 更新帖子
    $metadata['title'] = $title;
    $metadata['updated_at'] = date('Y-m-d H:i:s');

    $new_content = json_encode($metadata, JSON_UNESCAPED_UNICODE) . "\n" . $content_text;

    if (file_put_contents($post_file, $new_content)) {
        $metadata['id'] = $post_id;
        json_response(true, '更新成功', ['post' => $metadata], 200);
    } else {
        json_response(false, '更新失败', null, 500);
    }
}

// 删除帖子
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // 验证 Token
    $token = get_token();
    $session = verify_token($token);

    if (!$session) {
        json_response(false, '未登录', null, 401);
    }

    if (!file_exists($post_file)) {
        json_response(false, '帖子不存在', null, 404);
    }

    // 读取现有帖子检查权限
    $content = file_get_contents($post_file);
    $lines = explode("\n", $content, 2);
    $metadata = json_decode($lines[0], true);

    // 检查权限
    if ($metadata['author'] !== $session['username']) {
        json_response(false, '无权限删除此帖子', null, 403);
    }

    // 删除帖子
    if (unlink($post_file)) {
        json_response(true, '删除成功', null, 200);
    } else {
        json_response(false, '删除失败', null, 500);
    }
}

json_response(false, '不支持的请求方法', null, 405);
?>
