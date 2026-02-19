<?php
/**
 * API 帮助函数
 * 提供通用的 API 响应函数
 */

/**
 * 获取 POST 请求数据
 *
 * @return array POST 请求数据
 */
function get_post_data() {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);
    return $data ? $data : $_POST;
}

/**
 * 读取 JSON 文件
 *
 * @param string $filePath 文件路径
 * @return array JSON 数据
 */
function read_json($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    $content = file_get_contents($filePath);
    return json_decode($content, true) ?: [];
}

/**
 * 写入 JSON 文件
 *
 * @param string $filePath 文件路径
 * @param array $data 要写入的数据
 * @return bool 是否写入成功
 */
function write_json($filePath, $data) {
    // 确保目录存在
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents($filePath, $content) !== false;
}

/**
 * 生成 UUID
 *
 * @return string UUID
 */
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/**
 * 返回 JSON 响应
 *
 * @param bool $success 是否成功
 * @param string $message 响应消息
 * @param mixed $data 响应数据
 * @param int $code HTTP 状态码
 */
function json_response($success, $message, $data = null, $code = 200) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    http_response_code($code);

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
