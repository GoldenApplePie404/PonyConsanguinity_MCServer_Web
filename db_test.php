<?php
// 数据库连接配置
$config = array(
    'hostname' => '115.231.176.218', // 数据库主机名
    'port' => 3306, // 数据库端口
    'database' => 'mcsqlserver', // 数据库名称
    'username' => 'mcsqlserver', // 数据库用户名
    'password' => 'gapmcsql_2026' // 数据库密码
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

// 获取数据库表结构
function getTables($conn) {
    $tables = array();
    $result = mysqli_query($conn, 'SHOW TABLES');
    
    while ($row = mysqli_fetch_row($result)) {
        $tableName = $row[0];
        $tables[] = $tableName;
    }
    
    return $tables;
}

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
function getTableData($conn, $tableName, $page = 1, $limit = 10) {
    $offset = ($page - 1) * $limit;
    $data = array();
    $result = mysqli_query($conn, "SELECT * FROM $tableName LIMIT $limit OFFSET $offset");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    
    // 获取总记录数
    $countQuery = "SELECT COUNT(*) as total FROM $tableName";
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
        case 'get_tables':
            $tables = getTables($conn);
            echo json_encode(array(
                'success' => true,
                'data' => array('tables' => $tables)
            ));
            break;
            
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
                $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
                $data = getTableData($conn, $tableName, $page, $limit);
                echo json_encode(array(
                    'success' => true,
                    'data' => $data
                ));
            } else {
                echo json_encode(array(
                    'success' => false,
                    'message' => '缺少表名参数'
                ));
            }
            break;
            
        default:
            echo json_encode(array(
                'success' => false,
                'message' => '无效的操作'
            ));
            break;
    }
} else {
    // 默认返回数据库信息
    $tables = getTables($conn);
    echo json_encode(array(
        'success' => true,
        'data' => array(
            'database' => $config['database'],
            'tables' => $tables
        )
    ));
}

// 关闭连接
mysqli_close($conn);
?>