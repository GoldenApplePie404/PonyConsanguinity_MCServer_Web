<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__) . '/config/config.php';
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => '配置加载失败: ' . $e->getMessage()
    ));
    exit;
}

if (!function_exists('get_db_config')) {
    echo json_encode(array(
        'success' => false,
        'message' => 'get_db_config 函数未定义'
    ));
    exit;
}

$config = get_db_config();

$conn = @mysqli_connect(
    $config['hostname'],
    $config['username'],
    $config['password'],
    $config['database'],
    $config['port']
);

if (!$conn) {
    echo json_encode(array(
        'success' => false,
        'message' => '数据库连接失败: ' . mysqli_connect_error()
    ));
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');

function getTables($conn) {
    $tables = array();
    $result = mysqli_query($conn, 'SHOW TABLES');
    
    if ($result) {
        while ($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }
    }
    
    return $tables;
}

function getTableStructure($conn, $tableName) {
    $columns = array();
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "DESCRIBE `$tableName`");
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = $row;
        }
    }
    
    return $columns;
}

function getTableData($conn, $tableName, $page = 1, $limit = 10) {
    $offset = ($page - 1) * $limit;
    $data = array();
    $tableName = mysqli_real_escape_string($conn, $tableName);
    
    $result = mysqli_query($conn, "SELECT * FROM `$tableName` LIMIT $limit OFFSET $offset");
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    
    $countResult = mysqli_query($conn, "SELECT COUNT(*) as total FROM `$tableName`");
    $countRow = mysqli_fetch_assoc($countResult);
    $total = $countRow ? $countRow['total'] : 0;
    
    return array(
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    );
}

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
                $structure = getTableStructure($conn, $_GET['table']);
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
                $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
                $data = getTableData($conn, $_GET['table'], $page, $limit);
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
    $tables = getTables($conn);
    echo json_encode(array(
        'success' => true,
        'data' => array(
            'database' => $config['database'],
            'tables' => $tables
        )
    ));
}

mysqli_close($conn);
?>
