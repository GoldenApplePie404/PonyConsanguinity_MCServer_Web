<?php
/**
 * 数据初始化脚本
 * 用于首次部署时创建默认数据
 */

require_once 'config.php';
require_once 'helper.php';

// 初始化标志
$initialized = false;

// 初始化用户数据
function initUsers() {
    global $initialized;
    
    // 检查用户文件是否为空
    $users = read_json(USERS_FILE);
    
    if (empty($users)) {
        // 创建默认管理员账号
        $adminId = generate_uuid();
        $users['admin'] = [
            'id' => $adminId,
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'email' => 'admin@example.com',
            'created_at' => date('Y-m-d H:i:s'),
            'role' => 'admin'
        ];
        
        // 创建默认测试账号
        $testId = generate_uuid();
        $users['test'] = [
            'id' => $testId,
            'username' => 'test',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'email' => 'test@example.com',
            'created_at' => date('Y-m-d H:i:s'),
            'role' => 'user'
        ];
        
        // 保存用户数据
        if (write_json(USERS_FILE, $users)) {
            echo "✓ 初始化用户数据成功\n";
            echo "  - 默认管理员账号: admin / admin123\n";
            echo "  - 默认测试账号: test / test123\n";
            $initialized = true;
        } else {
            echo "✗ 初始化用户数据失败\n";
        }
    } else {
        echo "→ 用户数据已存在，跳过初始化\n";
    }
}

// 初始化帖子数据
function initPosts() {
    global $initialized;
    
    // 检查帖子文件是否为空
    $postsData = read_json(POSTS_FILE);
    $posts = $postsData['posts'] ?? [];
    
    if (empty($posts)) {
        // 创建示例帖子
        $postId = time();
        $contentFile = "$postId.md";
        
        // 创建帖子内容
        $content = "# 欢迎来到万驹同源服务器\n\n"
                 . "## 🎉 服务器简介\n"
                 . "万驹同源是一个完全公益的 Minecraft 服务器，致力于为广大玩家提供优质的游戏体验。\n\n"
                 . "## 📋 服务器规则\n"
                 . "1. 遵守服务器规则，文明游戏\n"
                 . "2. 禁止使用外挂和作弊工具\n"
                 . "3. 尊重其他玩家，友善相处\n"
                 . "4. 爱护服务器环境，禁止破坏他人建筑\n\n"
                 . "## 🎮 游戏特色\n"
                 . "- 多种游戏模式：生存、创造、小游戏\n"
                 . "- 丰富的插件系统\n"
                 . "- 定期举办活动\n"
                 . "- 友好的管理团队\n\n"
                 . "## 📞 联系方式\n"
                 . "- QQ群：569208814\n"
                 . "- 官网：https://mc.eqmemory.cn\n\n"
                 . "欢迎加入我们的大家庭！";
        
        // 写入帖子内容文件
        file_put_contents(CONTENT_DIR . "/$contentFile", $content);
        
        // 创建帖子对象
        $newPost = [
            'id' => (string)$postId,
            'title' => '欢迎来到万驹同源服务器',
            'author' => 'admin',
            'forum' => 'general',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'replies' => 0,
            'views' => 0,
            'content_file' => $contentFile
        ];
        
        // 添加到帖子列表
        array_unshift($posts, $newPost);
        $postsData['posts'] = $posts;
        
        // 保存帖子数据
        if (write_json(POSTS_FILE, $postsData)) {
            echo "✓ 初始化帖子数据成功\n";
            echo "  - 创建了示例欢迎帖子\n";
            $initialized = true;
        } else {
            echo "✗ 初始化帖子数据失败\n";
        }
    } else {
        echo "→ 帖子数据已存在，跳过初始化\n";
    }
}

// 初始化回复数据
function initReplies() {
    global $initialized;
    
    // 检查是否有帖子
    $postsData = read_json(POSTS_FILE);
    $posts = $postsData['posts'] ?? [];
    
    if (!empty($posts)) {
        // 为第一个帖子创建示例回复
        $firstPost = $posts[0];
        $postId = $firstPost['id'];
        $repliesFile = REPLIES_DIR . "/${postId}.json";
        
        if (!file_exists($repliesFile)) {
            // 创建示例回复
            $repliesData = [
                'replies' => [
                    [
                        'id' => time() . '-1',
                        'author' => 'test',
                        'content' => '这是一条示例回复，欢迎大家加入服务器！',
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ]
            ];
            
            // 保存回复数据
            if (file_put_contents($repliesFile, json_encode($repliesData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                // 更新帖子的回复数
                $firstPost['replies'] = 1;
                $postsData['posts'][0] = $firstPost;
                write_json(POSTS_FILE, $postsData);
                
                echo "✓ 初始化回复数据成功\n";
                echo "  - 为示例帖子创建了示例回复\n";
                $initialized = true;
            } else {
                echo "✗ 初始化回复数据失败\n";
            }
        } else {
            echo "→ 回复数据已存在，跳过初始化\n";
        }
    }
}

// 初始化会话数据
function initSessions() {
    // 会话文件已经在 config.php 中自动创建
    echo "→ 会话数据已就绪\n";
}

// 初始化目录结构
function initDirectories() {
    // 目录已经在相应的文件中自动创建
    echo "→ 目录结构已就绪\n";
}

// 主初始化函数
function initData() {
    echo "开始初始化数据...\n";
    echo "====================\n";
    
    // 初始化目录结构
    initDirectories();
    
    // 初始化会话数据
    initSessions();
    
    // 初始化用户数据
    initUsers();
    
    // 初始化帖子数据
    initPosts();
    
    // 初始化回复数据
    initReplies();
    
    echo "====================\n";
    
    global $initialized;
    if ($initialized) {
        echo "✅ 数据初始化完成！\n";
        echo "请使用以下账号登录：\n";
        echo "管理员账号: admin / admin123\n";
        echo "测试账号: test / test123\n";
    } else {
        echo "⚠️  数据已存在，跳过初始化\n";
        echo "如需重新初始化，请删除 data 目录后再次运行\n";
    }
}

// 运行初始化
initData();

// 输出HTML响应（如果通过浏览器访问）
if (!empty($_SERVER['HTTP_HOST'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>';
    echo '<html lang="zh-CN">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>数据初始化</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }';
    echo '.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }';
    echo 'h1 { color: #333; }';
    echo 'pre { background: #f8f8f8; padding: 15px; border-radius: 4px; border-left: 4px solid #4CAF50; }';
    echo '.success { color: #4CAF50; }';
    echo '.warning { color: #ff9800; }';
    echo '.error { color: #f44336; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="container">';
    echo '<h1>数据初始化结果</h1>';
    echo '<pre>';
    // 重新运行初始化并捕获输出
    ob_start();
    initData();
    $output = ob_get_clean();
    echo htmlspecialchars($output);
    echo '</pre>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
}
?>