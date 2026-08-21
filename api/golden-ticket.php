<?php
// 黄金券排行榜接口（PlayerPoints 数据库只读）
// 数据库凭据统一从 config/config.php 读取（复用 get_db_config()），禁止在本文件硬编码任何明文凭据。
require_once 'config.php';
require_once 'helper.php';

// 设置 CORS 和安全头
set_cors_headers();
set_security_headers();

// 从统一配置获取数据库连接参数（与 api/playerpoints.php 同模式）
$config = get_db_config();

// 连接数据库
$conn = mysqli_connect(
    $config['hostname'],
    $config['username'],
    $config['password'],
    $config['database'],
    $config['port']
);

// 检查连接
if (!$conn) {
    die(json_encode(array(
        'success' => false,
        'message' => '数据库连接失败: ' . mysqli_connect_error()
    )));
}

// 设置字符集
mysqli_set_charset($conn, 'utf8mb4');

// 获取黄金券排行榜数据
function getGoldenTicketRanking($conn, $page = 1, $limit = 10) {
    $offset = ($page - 1) * $limit;
    $data = array();
    
    $query = "
        SELECT 
            u.username, 
            p.uuid, 
            p.points 
        FROM 
            playerpoints_points p
        LEFT JOIN 
            playerpoints_username_cache u ON p.uuid = u.uuid
        ORDER BY 
            p.points DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $result = mysqli_query($conn, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    
    // 获取总记录数
    $countQuery = "SELECT COUNT(*) as total FROM playerpoints_points";
    $countResult = mysqli_query($conn, $countQuery);
    $countRow = mysqli_fetch_assoc($countResult);
    $total = $countRow['total'];
    
    return array(
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    );
}

// 处理API请求
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch ($action) {
        case 'get_ranking':
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            $rankingData = getGoldenTicketRanking($conn, $page, $limit);
            echo json_encode(array(
                'success' => true,
                'data' => $rankingData
            ));
            break;
            
        default:
            echo json_encode(array(
                'success' => false,
                'message' => '无效的操作'
            ));
            break;
    }
} else {
    // 默认返回排行榜数据
    $rankingData = getGoldenTicketRanking($conn);
    echo json_encode(array(
        'success' => true,
        'data' => $rankingData
    ));
}

// 关闭连接
mysqli_close($conn);
?>