<?php
/**
 * 问卷系统 API
 * 支持：创建/编辑/删除问卷、提交回答、查看结果、CSV导出
 * 题型：radio(单选)、checkbox(多选)、text(填空)
 */
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';
require_once '../includes/auth_helper.php';

set_cors_headers();
set_security_headers();
header('Content-Type: application/json; charset=utf-8');

$user = AuthHelper::getCurrentUser();
$username = $user['username'] ?? '';
$isAdmin = $user && ($user['role'] ?? '') === 'admin';

$method = $_SERVER['REQUEST_METHOD'];
$input = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// ── 初始化问卷文件 ──
function initQuestionnaires() {
    if (!file_exists(QUESTIONNAIRES_FILE)) {
        file_put_contents(QUESTIONNAIRES_FILE, json_encode(['questionnaires' => []], JSON_PRETTY_PRINT));
    }
}
initQuestionnaires();

function readQns() {
    return json_decode(file_get_contents(QUESTIONNAIRES_FILE), true) ?: ['questionnaires' => []];
}
function writeQns($data) {
    file_put_contents(QUESTIONNAIRES_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function qnId() {
    return 'qn_' . bin2hex(random_bytes(8));
}

switch ($action) {

    // ── 管理员：创建问卷 ──
    case 'create':
        if (!$isAdmin) json_response(false, '权限不足');
        $title = trim($input['title'] ?? '');
        $desc = trim($input['desc'] ?? '');
        $questions = $input['questions'] ?? [];
        if (empty($title)) json_response(false, '请输入问卷标题');
        if (empty($questions)) json_response(false, '请添加至少一道题目');

        // 验证题目
        foreach ($questions as $q) {
            if (empty($q['title'])) json_response(false, '题目内容不能为空');
            if (!in_array($q['type'], ['radio','checkbox','text'])) json_response(false, '题型无效');
            if (in_array($q['type'], ['radio','checkbox']) && empty($q['options'])) json_response(false, '选择题需提供选项');
        }

        $data = readQns();
        $id = qnId();
        $data['questionnaires'][] = [
            'id' => $id,
            'title' => $title,
            'desc' => $desc,
            'status' => 'active',
            'created_by' => $username,
            'created_at' => date('Y-m-d H:i:s'),
            'questions' => $questions,
            'responses' => []
        ];
        writeQns($data);
        json_response(true, '问卷创建成功', ['id' => $id]);

    // ── 管理员：编辑问卷 ──
    case 'update':
        if (!$isAdmin) json_response(false, '权限不足');
        $id = $input['id'] ?? '';
        $data = readQns();
        $found = false;
        foreach ($data['questionnaires'] as &$qn) {
            if ($qn['id'] === $id) {
                if (!empty($input['title'])) $qn['title'] = trim($input['title']);
                if (isset($input['desc'])) $qn['desc'] = trim($input['desc']);
                if (isset($input['questions'])) {
                    foreach ($input['questions'] as $q) {
                        if (empty($q['title'])) json_response(false, '题目内容不能为空');
                    }
                    $qn['questions'] = $input['questions'];
                    $qn['responses'] = []; // 重置回答
                }
                $found = true; break;
            }
        }
        if (!$found) json_response(false, '问卷不存在');
        writeQns($data);
        json_response(true, '问卷已更新');

    // ── 管理员：删除问卷 ──
    case 'delete':
        if (!$isAdmin) json_response(false, '权限不足');
        $id = $_GET['id'] ?? $input['id'] ?? '';
        $data = readQns();
        $data['questionnaires'] = array_values(array_filter($data['questionnaires'], fn($q) => $q['id'] !== $id));
        writeQns($data);
        json_response(true, '问卷已删除');

    // ── 管理员：开关问卷 ──
    case 'toggle':
        if (!$isAdmin) json_response(false, '权限不足');
        $id = $_GET['id'] ?? $input['id'] ?? '';
        $data = readQns();
        foreach ($data['questionnaires'] as &$qn) {
            if ($qn['id'] === $id) {
                $qn['status'] = $qn['status'] === 'active' ? 'closed' : 'active';
                writeQns($data);
                json_response(true, '状态已切换', ['status' => $qn['status']]);
            }
        }
        json_response(false, '问卷不存在');

    // ── 获取问卷列表 ──
    case 'list':
        $data = readQns();
        $list = [];
        foreach ($data['questionnaires'] as $qn) {
            $item = [
                'id' => $qn['id'],
                'title' => $qn['title'],
                'status' => $qn['status'],
                'created_at' => $qn['created_at'],
                'created_by' => $qn['created_by'],
                'question_count' => count($qn['questions']),
                'response_count' => count($qn['responses'])
            ];
            $list[] = $item;
        }
        usort($list, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        json_response(true, 'ok', ['questionnaires' => $list]);

    // ── 获取单个问卷（含题目；已回答用户看到结果） ──
    case 'get':
        $id = $_GET['id'] ?? '';
        $data = readQns();
        foreach ($data['questionnaires'] as $qn) {
            if ($qn['id'] === $id) {
                $result = [
                    'id' => $qn['id'],
                    'title' => $qn['title'],
                    'desc' => $qn['desc'],
                    'status' => $qn['status'],
                    'created_at' => $qn['created_at'],
                    'questions' => $qn['questions'],
                    'response_count' => count($qn['responses'])
                ];
                // 已登录用户查看自己的回答状态
                if ($username) {
                    $myResponse = null;
                    foreach ($qn['responses'] as $r) {
                        if ($r['user'] === $username) { $myResponse = $r; break; }
                    }
                    $result['my_response'] = $myResponse;
                    // 管理员或已回答用户看统计
                    if ($isAdmin || $myResponse) {
                        $result['stats'] = computeStats($qn);
                    }
                }
                json_response(true, 'ok', $result);
            }
        }
        json_response(false, '问卷不存在');

    // ── 提交回答 ──
    case 'submit':
        if (!$username) json_response(false, '请先登录');
        $id = $input['id'] ?? '';
        $answers = $input['answers'] ?? [];
        if (!$id || !$answers) json_response(false, '参数不完整');

        $data = readQns();
        $found = false;
        foreach ($data['questionnaires'] as &$qn) {
            if ($qn['id'] === $id) {
                if ($qn['status'] !== 'active') json_response(false, '问卷已关闭');
                // 检查是否已提交
                foreach ($qn['responses'] as $r) {
                    if ($r['user'] === $username) json_response(false, '您已提交过此问卷');
                }
                // 验证必填
                foreach ($qn['questions'] as $q) {
                    $required = $q['required'] ?? false;
                    $ans = $answers[$q['id']] ?? '';
                    if ($required && empty($ans)) json_response(false, '请完成所有必填题');
                }
                $qn['responses'][] = [
                    'user' => $username,
                    'submitted_at' => date('Y-m-d H:i:s'),
                    'answers' => $answers
                ];
                $found = true; break;
            }
        }
        if (!$found) json_response(false, '问卷不存在');
        writeQns($data);
        json_response(true, '提交成功');

    // ── 查看结果（管理员用） ──
    case 'results':
        if (!$isAdmin) json_response(false, '权限不足');
        $id = $_GET['id'] ?? '';
        $data = readQns();
        foreach ($data['questionnaires'] as $qn) {
            if ($qn['id'] === $id) {
                json_response(true, 'ok', [
                    'questionnaire' => $qn,
                    'stats' => computeStats($qn)
                ]);
            }
        }
        json_response(false, '问卷不存在');

    // ── 导出 CSV ──
    case 'export':
        $id = $_GET['id'] ?? '';
        $data = readQns();
        $found = null;
        foreach ($data['questionnaires'] as $qn) {
            if ($qn['id'] === $id) { $found = $qn; break; }
        }
        if (!$found) json_response(false, '问卷不存在');

        // 不输出 JSON，直接输出 CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="问卷_' . $found['title'] . '_' . date('Ymd') . '.csv"');
        header('Content-Encoding: UTF-8');
        // BOM for Excel
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // 表头：用户 + 提交时间 + 每题一列
        $headers = ['用户名', '提交时间'];
        foreach ($found['questions'] as $q) {
            $headers[] = $q['title'];
        }
        fputcsv($output, $headers);

        // 数据行
        foreach ($found['responses'] as $r) {
            $row = [$r['user'], $r['submitted_at']];
            foreach ($found['questions'] as $q) {
                $ans = $r['answers'][$q['id']] ?? '';
                if (is_array($ans)) $ans = implode('; ', $ans);
                $row[] = $ans;
            }
            fputcsv($output, $row);
        }
        fclose($output);
        exit;

    default:
        json_response(false, '未知操作');
}

// ── 计算统计数据 ──
function computeStats($qn) {
    $stats = [];
    $total = count($qn['responses']);
    foreach ($qn['questions'] as $q) {
        $s = ['type' => $q['type'], 'total' => $total];
        if (in_array($q['type'], ['radio', 'checkbox'])) {
            $counts = array_fill_keys($q['options'], 0);
            foreach ($qn['responses'] as $r) {
                $ans = $r['answers'][$q['id']] ?? [];
                if (!is_array($ans)) $ans = [$ans];
                foreach ($ans as $a) {
                    if (isset($counts[$a])) $counts[$a]++;
                }
            }
            $s['options'] = $counts;
        } elseif ($q['type'] === 'text') {
            $texts = [];
            foreach ($qn['responses'] as $r) {
                $t = $r['answers'][$q['id']] ?? '';
                if ($t) $texts[] = $t;
            }
            $s['texts'] = $texts;
        }
        $stats[$q['id']] = $s;
    }
    return $stats;
}
