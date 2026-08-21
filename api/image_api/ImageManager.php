<?php
// ImageManager.php
// 图片管理类，封装图片管理相关操作

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once 'ImageUpload.php';

class ImageManager {
    private $uploadDir;
    private $upload;
    private $imagesFile;
    
    /**
     * 构造函数
     * @param string $uploadDir 上传目录
     * @param string $imagesFile 图片数据文件路径
     * @param bool $compressEnabled 是否启用压缩
     * @param int $compressQuality 压缩质量
     * @param int $maxWidth 最大宽度
     * @param int $maxHeight 最大高度
     */
    public function __construct($uploadDir = null, $imagesFile = null, $compressEnabled = true, $compressQuality = 85, $maxWidth = 1920, $maxHeight = 1080) {
        $this->uploadDir = $uploadDir ?? dirname(dirname(__DIR__)) . '/assets/img/text_img';
        $this->imagesFile = $imagesFile ?? dirname(dirname(__DIR__)) . '/data/images.json';
        $this->upload = new ImageUpload($this->uploadDir, null, null, $compressEnabled, $compressQuality, $maxWidth, $maxHeight);
        $this->ensureImagesFile();
    }
    
    /**
     * 确保图片数据文件存在
     */
    private function ensureImagesFile() {
        $dir = dirname($this->imagesFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (!file_exists($this->imagesFile)) {
            file_put_contents($this->imagesFile, json_encode([]));
        }
    }
    
    /**
     * 获取所有图片数据
     * @return array
     */
    private function getImagesData() {
        $data = file_get_contents($this->imagesFile);
        return json_decode($data, true) ?? [];
    }
    
    /**
     * 保存图片数据
     * @param array $data 图片数据
     * @return bool
     */
    private function saveImagesData($data) {
        return file_put_contents($this->imagesFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    /**
     * 上传图片并保存记录
     * @param array $file 文件信息
     * @param int $userId 用户ID
     * @param string $username 用户名
     * @param string $prefix 文件名前缀
     * @return array 上传结果
     */
    public function uploadImage($file, $userId, $username, $prefix = 'img') {
        $result = $this->upload->upload($file, $prefix);
        
        if ($result['success']) {
            $imageData = [
                'id' => $this->generateImageId(),
                'user_id' => $userId,
                'username' => $username,
                'filename' => $result['data']['filename'],
                'original_name' => $result['data']['original_name'],
                'url' => $result['data']['url'],
                'size' => $result['data']['size'],
                'original_size' => $result['data']['original_size'] ?? $result['data']['size'],
                'type' => $result['data']['type'],
                'width' => $result['data']['width'],
                'height' => $result['data']['height'],
                'mime' => $result['data']['mime'],
                'compressed' => $result['data']['compressed'] ?? false,
                'compression_ratio' => $result['data']['compression_ratio'] ?? 0,
                'upload_time' => date('Y-m-d H:i:s'),
                'status' => 'active'
            ];
            
            $this->saveImageRecord($imageData);
            
            $result['data']['id'] = $imageData['id'];
        }
        
        return $result;
    }
    
    /**
     * 批量上传图片并保存记录
     * @param array $files 文件信息数组
     * @param int $userId 用户ID
     * @param string $username 用户名
     * @param string $prefix 文件名前缀
     * @return array 上传结果
     */
    public function uploadImages($files, $userId, $username, $prefix = 'img') {
        $results = [];
        $successCount = 0;
        
        foreach ($files as $file) {
            $result = $this->uploadImage($file, $userId, $username, $prefix);
            $results[] = $result;
            if ($result['success']) {
                $successCount++;
            }
        }
        
        return [
            'success' => $successCount > 0,
            'message' => "成功上传 {$successCount}/" . count($files) . " 张图片",
            'data' => $results
        ];
    }
    
    /**
     * 保存图片记录
     * @param array $imageData 图片数据
     */
    private function saveImageRecord($imageData) {
        $images = $this->getImagesData();
        $images[$imageData['id']] = $imageData;
        $this->saveImagesData($images);
    }
    
    /**
     * 生成图片ID
     * @return string
     */
    private function generateImageId() {
        return 'img_' . time() . '_' . bin2hex(random_bytes(4));
    }
    
    /**
     * 获取图片信息
     * @param string $imageId 图片ID
     * @return array|null
     */
    public function getImage($imageId) {
        $images = $this->getImagesData();
        return $images[$imageId] ?? null;
    }
    
    /**
     * 获取用户的所有图片
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserImages($userId) {
        $images = $this->getImagesData();
        $userImages = [];
        
        foreach ($images as $image) {
            if ($image['user_id'] == $userId) {
                $userImages[] = $image;
            }
        }
        
        return $userImages;
    }
    
    /**
     * 获取所有图片列表
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array
     */
    public function getAllImages($limit = 50, $offset = 0) {
        $images = $this->getImagesData();
        $imageList = array_values($images);
        
        return array_slice($imageList, $offset, $limit);
    }
    
    /**
     * 删除图片
     * @param string $imageId 图片ID
     * @param int $userId 用户ID（用于权限验证）
     * @return array
     */
    public function deleteImage($imageId, $userId) {
        $image = $this->getImage($imageId);
        
        if (!$image) {
            return [
                'success' => false,
                'message' => '图片不存在'
            ];
        }
        
        if ($image['user_id'] != $userId) {
            return [
                'success' => false,
                'message' => '无权删除此图片'
            ];
        }
        
        $deleted = $this->upload->deleteFile($image['filename']);
        
        if ($deleted) {
            $images = $this->getImagesData();
            unset($images[$imageId]);
            $this->saveImagesData($images);
            
            return [
                'success' => true,
                'message' => '删除成功'
            ];
        }
        
        return [
            'success' => false,
            'message' => '删除失败'
        ];
    }
    
    /**
     * 更新图片状态
     * @param string $imageId 图片ID
     * @param string $status 状态
     * @return bool
     */
    public function updateImageStatus($imageId, $status) {
        $images = $this->getImagesData();
        
        if (!isset($images[$imageId])) {
            return false;
        }
        
        $images[$imageId]['status'] = $status;
        return $this->saveImagesData($images);
    }
    
    /**
     * 获取图片统计信息
     * @param int $userId 用户ID（可选）
     * @return array
     */
    public function getStatistics($userId = null) {
        $images = $this->getImagesData();
        $stats = [
            'total' => count($images),
            'total_size' => 0,
            'by_type' => [],
            'by_user' => []
        ];
        
        foreach ($images as $image) {
            if ($userId && $image['user_id'] != $userId) {
                continue;
            }
            
            $stats['total_size'] += $image['size'];
            
            if (!isset($stats['by_type'][$image['type']])) {
                $stats['by_type'][$image['type']] = 0;
            }
            $stats['by_type'][$image['type']]++;
            
            if (!isset($stats['by_user'][$image['username']])) {
                $stats['by_user'][$image['username']] = 0;
            }
            $stats['by_user'][$image['username']]++;
        }
        
        return $stats;
    }
    
    /**
     * 清理未使用的图片
     * @param int $days 天数
     * @return array
     */
    public function cleanupUnusedImages($days = 30) {
        $images = $this->getImagesData();
        $deletedCount = 0;
        $currentTime = time();
        
        foreach ($images as $imageId => $image) {
            $uploadTime = strtotime($image['upload_time']);
            $daysSinceUpload = ($currentTime - $uploadTime) / (24 * 60 * 60);
            
            if ($daysSinceUpload > $days && $image['status'] === 'unused') {
                $this->upload->deleteFile($image['filename']);
                unset($images[$imageId]);
                $deletedCount++;
            }
        }
        
        $this->saveImagesData($images);
        
        return [
            'success' => true,
            'message' => "清理了 {$deletedCount} 张未使用的图片",
            'deleted_count' => $deletedCount
        ];
    }
}
?>