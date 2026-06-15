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

        case 'get_row':
            if (isset($_GET['table']) && isset($_GET['id'])) {
                $table = mysqli_real_escape_string($conn, $_GET['table']);
                $structure = getTableStructure($conn, $table);
                $pk = null;
                foreach ($structure as $col) { if ($col['Key'] === 'PRI') { $pk = $col['Field']; break; } }
                if (!$pk) { echo json_encode(['success' => false, 'message' => '无法检测主键']); break; }
                $id = mysqli_real_escape_string($conn, $_GET['id']);
                $r = mysqli_query($conn, "SELECT * FROM `$table` WHERE `$pk` = '$id' LIMIT 1");
                $row = $r ? mysqli_fetch_assoc($r) : null;
                echo json_encode(['success' => !!$row, 'data' => $row, 'structure' => $structure, 'pk' => $pk]);
            } else { echo json_encode(['success' => false, 'message' => '缺少参数']); }
            break;

        case 'insert':
            if (isset($_GET['table'])) {
                $table = mysqli_real_escape_string($conn, $_GET['table']);
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) { echo json_encode(['success' => false, 'message' => '无效的请求数据']); break; }
                $structure = getTableStructure($conn, $table);
                $cols = []; $vals = [];
                foreach ($structure as $col) {
                    $f = $col['Field'];
                    if ($col['Extra'] === 'auto_increment') continue;
                    if (isset($input[$f])) {
                        $cols[] = "`$f`";
                        $vals[] = "'" . mysqli_real_escape_string($conn, $input[$f]) . "'";
                    }
                }
                if (empty($cols)) { echo json_encode(['success' => false, 'message' => '无有效字段']); break; }
                $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
                $r = mysqli_query($conn, $sql);
                echo json_encode(['success' => !!$r, 'message' => $r ? '插入成功' : '插入失败: ' . mysqli_error($conn), 'id' => mysqli_insert_id($conn)]);
            } else { echo json_encode(['success' => false, 'message' => '缺少表名参数']); }
            break;

        case 'update':
            if (isset($_GET['table']) && isset($_GET['id'])) {
                $table = mysqli_real_escape_string($conn, $_GET['table']);
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) { echo json_encode(['success' => false, 'message' => '无效的请求数据']); break; }
                $structure = getTableStructure($conn, $table);
                $pk = null;
                foreach ($structure as $col) { if ($col['Key'] === 'PRI') { $pk = $col['Field']; break; } }
                if (!$pk) { echo json_encode(['success' => false, 'message' => '无法检测主键']); break; }
                $id = mysqli_real_escape_string($conn, $_GET['id']);
                $sets = [];
                foreach ($structure as $col) {
                    $f = $col['Field'];
                    if ($f === $pk) continue;
                    if (isset($input[$f])) $sets[] = "`$f` = '" . mysqli_real_escape_string($conn, $input[$f]) . "'";
                }
                if (empty($sets)) { echo json_encode(['success' => false, 'message' => '无更新字段']); break; }
                $sql = "UPDATE `$table` SET " . implode(',', $sets) . " WHERE `$pk` = '$id'";
                $r = mysqli_query($conn, $sql);
                echo json_encode(['success' => !!$r, 'message' => $r ? '更新成功' : '更新失败: ' . mysqli_error($conn)]);
            } else { echo json_encode(['success' => false, 'message' => '缺少参数']); }
            break;

        case 'delete':
            if (isset($_GET['table']) && isset($_GET['id'])) {
                $table = mysqli_real_escape_string($conn, $_GET['table']);
                $structure = getTableStructure($conn, $table);
                $pk = null;
                foreach ($structure as $col) { if ($col['Key'] === 'PRI') { $pk = $col['Field']; break; } }
                if (!$pk) { echo json_encode(['success' => false, 'message' => '无法检测主键']); break; }
                $id = mysqli_real_escape_string($conn, $_GET['id']);
                $r = mysqli_query($conn, "DELETE FROM `$table` WHERE `$pk` = '$id'");
                echo json_encode(['success' => !!$r, 'message' => $r ? '删除成功' : '删除失败: ' . mysqli_error($conn)]);
            } else { echo json_encode(['success' => false, 'message' => '缺少参数']); }
            break;

        case 'backup':
            header('Content-Type: application/octet-stream; charset=utf-8');
            header('Content-Disposition: attachment; filename="backup_' . $config['database'] . '_' . date('Ymd_His') . '.sql"');
            $tables = getTables($conn);
            $output = "-- 数据库备份: {$config['database']}\n-- 时间: " . date('Y-m-d H:i:s') . "\n-- 表数: " . count($tables) . "\n\n";
            foreach ($tables as $table) {
                $cr = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
                if ($cr) { $row = mysqli_fetch_row($cr); $output .= $row[1] . ";\n\n"; }
                $dr = mysqli_query($conn, "SELECT * FROM `$table`");
                if ($dr && mysqli_num_rows($dr) > 0) {
                    $output .= "INSERT INTO `$table` VALUES\n";
                    $rows = [];
                    while ($row = mysqli_fetch_row($dr)) {
                        $vals = array_map(function($v) use ($conn) {
                            return $v === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'";
                        }, $row);
                        $rows[] = '(' . implode(',', $vals) . ')';
                    }
                    $output .= implode(",\n", $rows) . ";\n\n";
                }
            }
            echo $output;
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
