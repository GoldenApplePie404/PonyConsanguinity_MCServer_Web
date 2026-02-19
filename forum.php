<?php
header('Content-Type: application/json; charset=utf-8');

// 引入配置文件
require_once 'config.php';
require_once 'secure_data.php';

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // 处理GET请求
        $action = $_GET['action'] ?? '';
        
        if ($action === 'get_replies' && isset($_GET['postId'])) {
            // 获取帖子回复
            $postId = $_GET['postId'];
            $repliesFile = REPLIES_DIR . "/${postId}.json";
            
            if (file_exists($repliesFile)) {
                $replies = read_json($repliesFile);
                echo json_encode([
                    'success' => true,
                    'data' => $replies
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => ['replies' => []]
                ]);
            }
        } else {
            // 获取帖子列表
            $posts = secureReadData(POSTS_FILE);
            echo json_encode([
                'success' => true,
                'data' => $posts
            ]);
        }
        break;
        
    case 'POST':
        // 处理POST请求
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        
        if ($action === 'reply') {
            // 创建回复
            if (!isset($data['postId'], $data['content'], $data['author'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '缺少必要参数'
                ]);
                exit;
            }
            
            $postId = $data['postId'];
            $content = $data['content'];
            $author = $data['author'];
            
            // 检查帖子是否存在
            $postsData = secureReadData(POSTS_FILE);
            $posts = $postsData['posts'] ?? [];
            $postExists = false;
            
            foreach ($posts as &$post) {
                if ($post['id'] === $postId) {
                    $postExists = true;
                    $post['replies'] = ($post['replies'] ?? 0) + 1;
                    break;
                }
            }
            
            if (!$postExists) {
                echo json_encode([
                    'success' => false,
                    'message' => '帖子不存在'
                ]);
                exit;
            }
            
            // 保存更新后的帖子数据
            secureWriteData(POSTS_FILE, $postsData);
            
            // 加载现有回复
            $repliesFile = REPLIES_DIR . "/${postId}.json";
            
            // 初始化回复数据
            $repliesData = ['replies' => []];
            if (file_exists($repliesFile)) {
                $repliesData = read_json($repliesFile);
                if (!isset($repliesData['replies'])) {
                    $repliesData['replies'] = [];
                }
            }
            $replies = $repliesData['replies'];
            
            // 创建新回复
            $newReply = [
                'id' => time() . '-' . rand(1000, 9999),
                'author' => $author,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // 添加新回复
            array_push($replies, $newReply);
            $repliesData['replies'] = $replies;
            
            // 保存回复数据
            $success = file_put_contents($repliesFile, json_encode($repliesData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            if ($success === false) {
                echo json_encode([
                    'success' => false,
                    'message' => '无法保存回复文件',
                    'error' => 'file_put_contents failed',
                    'repliesFile' => $repliesFile
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $newReply,
                'repliesFile' => $repliesFile,
                'repliesCount' => count($replies)
            ]);
        } elseif ($action === 'delete_reply') {
            // 删除回复
            if (!isset($data['postId'], $data['replyId'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '缺少必要参数'
                ]);
                exit;
            }
            
            $postId = $data['postId'];
            $replyId = $data['replyId'];
            
            // 获取当前用户
            $headers = getallheaders();
            $token = $headers['Authorization'] ?? '';
            
            if (strpos($token, 'Bearer ') === 0) {
                $token = substr($token, 7);
            }
            
            if (empty($token)) {
                echo json_encode([
                    'success' => false,
                    'message' => '请先登录'
                ]);
                exit;
            }
            
            $sessions = secureReadData(SESSIONS_FILE);
            $currentUser = $sessions[$token] ?? null;
            
            if (!$currentUser) {
                echo json_encode([
                    'success' => false,
                    'message' => '请先登录'
                ]);
                exit;
            }
            
            // 加载回复数据
            $repliesFile = REPLIES_DIR . "/${postId}.json";
            
            if (!file_exists($repliesFile)) {
                echo json_encode([
                    'success' => false,
                    'message' => '回复不存在'
                ]);
                exit;
            }
            
            $repliesData = read_json($repliesFile);
            $replies = $repliesData['replies'] ?? [];
            
            // 查找要删除的回复
            $replyIndex = -1;
            $replyAuthor = '';
            
            foreach ($replies as $index => $reply) {
                if ($reply['id'] === $replyId) {
                    $replyIndex = $index;
                    $replyAuthor = $reply['author'];
                    break;
                }
            }
            
            if ($replyIndex === -1) {
                echo json_encode([
                    'success' => false,
                    'message' => '回复不存在'
                ]);
                exit;
            }
            
            // 检查权限：管理员可以删除所有回复，普通用户只能删除自己的回复
            if ($currentUser['role'] !== 'admin' && $currentUser['username'] !== $replyAuthor) {
                echo json_encode([
                    'success' => false,
                    'message' => '无权限删除此回复'
                ]);
                exit;
            }
            
            // 删除回复
            array_splice($replies, $replyIndex, 1);
            $repliesData['replies'] = $replies;
            
            // 保存回复数据
            $success = file_put_contents($repliesFile, json_encode($repliesData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            if ($success === false) {
                echo json_encode([
                    'success' => false,
                    'message' => '无法保存回复文件'
                ]);
                exit;
            }
            
            // 更新帖子的回复数量
            $postsData = secureReadData(POSTS_FILE);
            $posts = $postsData['posts'] ?? [];
            
            foreach ($posts as &$post) {
                if ($post['id'] === $postId) {
                    $post['replies'] = max(0, ($post['replies'] ?? 0) - 1);
                    break;
                }
            }
            
            secureWriteData(POSTS_FILE, $postsData);
            
            echo json_encode([
                'success' => true,
                'message' => '删除成功'
            ]);
        } elseif ($action === 'edit_post') {
            // 编辑帖子
            if (!isset($data['postId'], $data['title'], $data['content'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '缺少必要参数'
                ]);
                exit;
            }
            
            $postId = $data['postId'];
            $title = $data['title'];
            $content = $data['content'];
            
            // 获取当前用户
            $headers = getallheaders();
            $token = $headers['Authorization'] ?? '';
            
            if (strpos($token, 'Bearer ') === 0) {
                $token = substr($token, 7);
            }
            
            if (empty($token)) {
                echo json_encode([
                    'success' => false,
                    'message' => '请先登录'
                ]);
                exit;
            }
            
            $sessions = secureReadData(SESSIONS_FILE);
            $currentUser = $sessions[$token] ?? null;
            
            if (!$currentUser) {
                echo json_encode([
                    'success' => false,
                    'message' => '请先登录'
                ]);
                exit;
            }
            
            // 加载帖子数据
            $postsData = secureReadData(POSTS_FILE);
            $posts = $postsData['posts'] ?? [];
            
            // 查找要编辑的帖子
            $postIndex = -1;
            $postAuthor = '';
            $contentFile = '';
            
            foreach ($posts as $index => $post) {
                if ($post['id'] === $postId) {
                    $postIndex = $index;
                    $postAuthor = $post['author'];
                    $contentFile = $post['content_file'];
                    break;
                }
            }
            
            if ($postIndex === -1) {
                echo json_encode([
                    'success' => false,
                    'message' => '帖子不存在'
                ]);
                exit;
            }
            
            // 检查权限：只有作者可以编辑自己的帖子
            if ($currentUser['username'] !== $postAuthor) {
                echo json_encode([
                    'success' => false,
                    'message' => '无权限编辑此帖子'
                ]);
                exit;
            }
            
            // 更新帖子标题和更新时间
            $posts[$postIndex]['title'] = $title;
            $posts[$postIndex]['updated_at'] = date('Y-m-d H:i:s');
            $postsData['posts'] = $posts;
            
            // 保存帖子数据
            $success = secureWriteData(POSTS_FILE, $postsData);
            
            if (!$success) {
                echo json_encode([
                    'success' => false,
                    'message' => '无法保存帖子数据'
                ]);
                exit;
            }
            
            // 更新帖子内容文件
            $contentFilePath = CONTENT_DIR . "/${contentFile}";
            $success = file_put_contents($contentFilePath, $content);
            
            if ($success === false) {
                echo json_encode([
                    'success' => false,
                    'message' => '无法保存帖子内容'
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'message' => '修改成功'
            ]);
        } else {
            // 创建新帖子
            if (!isset($data['title'], $data['content'], $data['author'], $data['forum'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '缺少必要参数'
                ]);
                exit;
            }
            
            // 生成帖子ID
            $postId = time();
            $contentFile = "$postId.md";
            
            // 创建帖子内容文件
            file_put_contents(CONTENT_DIR . "/$contentFile", $data['content']);
            
            // 加载现有帖子
            $postsData = secureReadData(POSTS_FILE);
            $posts = $postsData['posts'] ?? [];
            
            // 创建新帖子
            $newPost = [
                'id' => (string)$postId,
                'title' => $data['title'],
                'author' => $data['author'],
                'forum' => $data['forum'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'replies' => 0,
                'views' => 0,
                'content_file' => $contentFile
            ];
            
            // 添加新帖子
            array_unshift($posts, $newPost);
            $postsData['posts'] = $posts;
            
            // 保存帖子数据
            secureWriteData(POSTS_FILE, $postsData);
            
            echo json_encode([
                'success' => true,
                'data' => $newPost
            ]);
        }
        break;
        
    case 'DELETE':
        // 删除帖子
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['postId'])) {
            echo json_encode([
                'success' => false,
                'message' => '缺少帖子ID'
            ]);
            exit;
        }
        
        $postId = $data['postId'];
        
        // 加载现有帖子
        $postsData = secureReadData(POSTS_FILE);
        $posts = $postsData['posts'] ?? [];
        
        // 查找要删除的帖子
        $postIndex = -1;
        $contentFile = '';
        
        foreach ($posts as $index => $post) {
            if ($post['id'] === $postId) {
                $postIndex = $index;
                $contentFile = $post['content_file'];
                break;
            }
        }
        
        if ($postIndex === -1) {
            echo json_encode([
                'success' => false,
                'message' => '帖子不存在'
            ]);
            exit;
        }
        
        // 删除帖子内容文件
        if ($contentFile && file_exists(CONTENT_DIR . "/$contentFile")) {
            unlink(CONTENT_DIR . "/$contentFile");
        }
        
        // 删除相关回复
        $repliesFile = REPLIES_DIR . "/${postId}.json";
        if (file_exists($repliesFile)) {
            unlink($repliesFile);
        }
        
        // 从数组中删除帖子
        array_splice($posts, $postIndex, 1);
        $postsData['posts'] = $posts;
        
        // 保存帖子数据
        secureWriteData(POSTS_FILE, $postsData);
        
        echo json_encode([
            'success' => true,
            'message' => '删除成功'
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => '不支持的请求方法'
        ]);
        break;
}

// 读取JSON文件
function read_json($file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }
    return [];
}

// 写入JSON文件
function write_json($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
?>