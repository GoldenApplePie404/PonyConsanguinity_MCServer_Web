<?php
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';
require_once '../includes/auth_helper.php';

set_cors_headers();
set_security_headers();

// 确保目录存在
if (!is_dir(RECRUITMENT_DIR)) {
    mkdir(RECRUITMENT_DIR, 0755, true);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // 提交招募申请
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的请求数据']);
            break;
        }

        $username = $data['username'] ?? '';
        $position = $data['position'] ?? '';
        $contact  = $data['contact'] ?? '';
        $experience = $data['experience'] ?? '';
        $portfolio  = $data['portfolio'] ?? '';
        $message    = $data['message'] ?? '';

        if (!$username || !$position || !$contact) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '请填写必填字段']);
            break;
        }

        $allowedPositions = ['builder', 'developer', 'artist', 'assistant', 'tester', 'designer'];
        if (!in_array($position, $allowedPositions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的岗位类型']);
            break;
        }

        $application = [
            'id'        => uniqid('recruit_'),
            'username'  => $username,
            'position'  => $position,
            'contact'   => $contact,
            'experience'=> $experience,
            'portfolio' => $portfolio,
            'message'   => $message,
            'status'    => 'pending',
            'adminNote' => '',
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ];

        $file = RECRUITMENT_DIR . '/' . $application['id'] . '.json';
        $result = file_put_contents($file, json_encode($application, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => '提交失败，请稍后重试']);
            break;
        }

        echo json_encode(['success' => true, 'message' => '申请已提交，我们会尽快审核']);
        break;

    case 'GET':
        // 管理员查看所有申请
        $session = AuthHelper::requireAdmin();

        $files = glob(RECRUITMENT_DIR . '/*.json');
        $applications = [];
        foreach ($files as $file) {
            $app = json_decode(file_get_contents($file), true);
            if ($app) {
                $applications[] = $app;
            }
        }

        // 按创建时间倒序
        usort($applications, function($a, $b) {
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });

        echo json_encode(['success' => true, 'data' => $applications]);
        break;

    case 'PUT':
        // 管理员审核申请（更新状态）
        $session = AuthHelper::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? '';
        $status = $data['status'] ?? '';
        $adminNote = $data['adminNote'] ?? '';

        if (!$id || !in_array($status, ['pending', 'approved', 'rejected'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的请求参数']);
            break;
        }

        $file = RECRUITMENT_DIR . '/' . $id . '.json';
        if (!file_exists($file)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => '申请不存在']);
            break;
        }

        $app = json_decode(file_get_contents($file), true);
        $app['status'] = $status;
        $app['adminNote'] = $adminNote;
        $app['updatedAt'] = date('Y-m-d H:i:s');

        file_put_contents($file, json_encode($app, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode(['success' => true, 'message' => '状态已更新']);
        break;

    case 'DELETE':
        // 管理员删除申请
        $session = AuthHelper::requireAdmin();

        $id = $_GET['id'] ?? '';
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '缺少申请 ID']);
            break;
        }

        $file = RECRUITMENT_DIR . '/' . $id . '.json';
        if (!file_exists($file)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => '申请不存在']);
            break;
        }

        unlink($file);
        echo json_encode(['success' => true, 'message' => '已删除']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => '不支持的请求方法']);
        break;
}
