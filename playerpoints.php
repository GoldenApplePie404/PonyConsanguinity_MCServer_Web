<?php
// 数据库连接配置
$config = array(
    'hostname' => '115.231.176.218',
    'port' => 3306,
    'database' => 'mcsqlserver',
    'username' => 'mcsqlserver',
    'password' => 'gapmcsql_2026'
);

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

// 获取表结构
function getTableStructure($conn, $tableName) {
    $columns = array();
    $result = mysqli_query($conn, "DESCRIBE $tableName");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row;
    }
    
    return $columns;
}

// 获取表数据
function getTableData($conn, $tableName, $limit = 10) {
    $data = array();
    $result = mysqli_query($conn, "SELECT * FROM $tableName LIMIT $limit");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    
    return $data;
}

// 获取组合的playerpoints数据
function getCombinedPlayerPoints($conn, $page = 1, $limit = 10) {
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
        case 'get_table_structure':
            if (isset($_GET['table'])) {
                $tableName = $_GET['table'];
                $structure = getTableStructure($conn, $tableName);
                echo json_encode(array(
                    'success' => true,
                    'data' => array('structure' => $structure)
                ));
            } else {
                echo json_encode(array(
                    'success' => false,
                    'message' => '缺少表名参数'
                ));
            }
            break;
            
        case 'get_table_data':
            if (isset($_GET['table'])) {
                $tableName = $_GET['table'];
                $data = getTableData($conn, $tableName);
                echo json_encode(array(
                    'success' => true,
                    'data' => array('data' => $data)
                ));
            } else {
                echo json_encode(array(
                    'success' => false,
                    'message' => '缺少表名参数'
                ));
            }
            break;
            
        case 'get_combined_data':
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            $combinedData = getCombinedPlayerPoints($conn, $page, $limit);
            echo json_encode(array(
                'success' => true,
                'data' => $combinedData
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
    // 默认返回三个表的基本信息
    $tables = array('playerpoints_migrations', 'playerpoints_points', 'playerpoints_username_cache');
    $tableInfo = array();
    
    foreach ($tables as $table) {
        $structure = getTableStructure($conn, $table);
        $data = getTableData($conn, $table);
        $tableInfo[$table] = array(
            'structure' => $structure,
            'data' => $data
        );
    }
    
    echo json_encode(array(
        'success' => true,
        'data' => array(
            'tables' => $tables,
            'table_info' => $tableInfo
        )
    ));
}

// 关闭连接
mysqli_close($conn);
?>