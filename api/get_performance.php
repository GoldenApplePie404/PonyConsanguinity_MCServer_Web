<?php
// 防止直接访问
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

// 设置响应头
header('Content-Type: application/json');

// 数据文件路径
$dataFile = __DIR__ . '/../data/performance_data.json';

// 读取现有数据
$existingData = [];
if (file_exists($dataFile)) {
    $existingContent = file_get_contents($dataFile);
    if ($existingContent) {
        $existingData = json_decode($existingContent, true) ?: [];
    }
}

// 确保数据是数组
if (!is_array($existingData)) {
    $existingData = [];
}

// 转换数据格式为Chart.js需要的格式
$chartData = [
    'labels' => [],
    'players' => [],
    'cpu' => [],
    'memory' => []
];

foreach ($existingData as $item) {
    $chartData['labels'][] = $item['time_label'] ?? '';
    $chartData['players'][] = $item['players'] ?? 0;
    $chartData['cpu'][] = $item['cpu'] ?? 0;
    $chartData['memory'][] = $item['memory'] ?? 0;
}

echo json_encode([
    'success' => true,
    'data' => $chartData,
    'raw_data' => $existingData
]);