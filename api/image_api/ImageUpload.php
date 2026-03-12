<?php
// ImageUpload.php
// 图片上传类，封装图片上传相关操作
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once dirname(__DIR__) . '/config.php';

class ImageUpload {
    private $uploadDir;
    private $allowedTypes;
    private $maxSize;
    private $errors = [];
    private $compressEnabled;
    private $compressQuality;
    private $maxWidth;
    private $maxHeight;
    
    /**
     * 构造函数
     * @param string $uploadDir 上传目录
     * @param array $allowedTypes 允许的文件类型
     * @param int $maxSize 最大文件大小（字节）
     * @param bool $compressEnabled 是否启用压缩
     * @param int $compressQuality 压缩质量（1-100）
     * @param int $maxWidth 最大宽度
     * @param int $maxHeight 最大高度
     */
    public function __construct($uploadDir = null, $allowedTypes = null, $maxSize = null, $compressEnabled = true, $compressQuality = 85, $maxWidth = 1920, $maxHeight = 1080) {
        $this->uploadDir = $uploadDir ?? dirname(dirname(__DIR__)) . '/assets/img/text_img';
        $this->allowedTypes = $allowedTypes ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $this->maxSize = $maxSize ?? 5 * 1024 * 1024; // 默认5MB
        $this->compressEnabled = $compressEnabled;
        $this->compressQuality = $compressQuality;
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
        
        $this->ensureUploadDir();
    }
    
    /**
     * 确保上传目录存在
     */
    private function ensureUploadDir() {
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                throw new Exception('无法创建上传目录');
            }
        }
        
        if (!is_writable($this->uploadDir)) {
            throw new Exception('上传目录不可写');
        }
    }
    
    /**
     * 上传图片
     * @param array $file $_FILES数组中的文件信息
     * @param string $prefix 文件名前缀
     * @return array 上传结果
     */
    public function upload($file, $prefix = 'img') {
        try {
            $this->validateFile($file);
            
            $fileInfo = $this->processFile($file, $prefix);
            
            return [
                'success' => true,
                'message' => '上传成功',
                'data' => $fileInfo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $this->errors
            ];
        }
    }
    
    /**
     * 批量上传图片
     * @param array $files $_FILES数组中的文件信息
     * @param string $prefix 文件名前缀
     * @return array 上传结果
     */
    public function uploadMultiple($files, $prefix = 'img') {
        $results = [];
        $successCount = 0;
        
        foreach ($files as $file) {
            $result = $this->upload($file, $prefix);
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
     * 验证文件
     * @param array $file 文件信息
     * @throws Exception
     */
    private function validateFile($file) {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception('无效的文件参数');
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('没有文件被上传');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('文件大小超过限制');
            default:
                throw new Exception('未知上传错误');
        }
        
        if ($file['size'] > $this->maxSize) {
            throw new Exception('文件大小超过 ' . ($this->maxSize / 1024 / 1024) . 'MB 限制');
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            throw new Exception('不支持的文件类型，仅支持：' . implode(', ', $this->allowedTypes));
        }
        
        if (!$this->isValidImage($file['tmp_name'])) {
            throw new Exception('无效的图片文件');
        }
    }
    
    /**
     * 验证是否为有效图片
     * @param string $filePath 文件路径
     * @return bool
     */
    private function isValidImage($filePath) {
        $imageInfo = @getimagesize($filePath);
        return $imageInfo !== false;
    }
    
    /**
     * 处理文件
     * @param array $file 文件信息
     * @param string $prefix 文件名前缀
     * @return array 文件信息
     */
    private function processFile($file, $prefix) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = $this->generateFileName($prefix, $extension);
        $filePath = $this->uploadDir . '/' . $fileName;
        
        // 先保存上传的文件
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('文件保存失败');
        }
        
        // 获取图片信息
        $imageInfo = getimagesize($filePath);
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $originalSize = filesize($filePath);
        
        // 如果需要压缩，执行压缩
        $finalExtension = $extension;
        $finalFileName = $fileName;
        $finalFilePath = $filePath;
        
        $shouldCompress = $this->shouldCompress($filePath, $extension);
        
        if ($this->compressEnabled && $shouldCompress) {
            $compressedInfo = $this->compressImage($filePath, $extension);
            if ($compressedInfo) {
                $finalWidth = $compressedInfo['width'];
                $finalHeight = $compressedInfo['height'];
                $finalSize = $compressedInfo['size'];
                $compressionRatio = round((1 - $finalSize / $originalSize) * 100, 2);
                
                // 如果格式转换了，更新文件名和路径
                if (!empty($compressedInfo['converted']) && !empty($compressedInfo['new_extension'])) {
                    $finalExtension = $compressedInfo['new_extension'];
                    $finalFileName = preg_replace('/\.png$/i', '.jpg', $fileName);
                    $finalFilePath = $filePath; // compressImage已经修改了路径
                    
                    // 删除原PNG文件
                    if (file_exists($this->uploadDir . '/' . $fileName)) {
                        unlink($this->uploadDir . '/' . $fileName);
                    }
                }
            } else {
                $finalWidth = $originalWidth;
                $finalHeight = $originalHeight;
                $finalSize = $originalSize;
                $compressionRatio = 0;
            }
        } else {
            $finalWidth = $originalWidth;
            $finalHeight = $originalHeight;
            $finalSize = $originalSize;
            $compressionRatio = 0;
        }
        
        return [
            'filename' => $finalFileName,
            'original_name' => $file['name'],
            'path' => $finalFilePath,
            'url' => $this->getFileUrl($finalFileName),
            'size' => $finalSize,
            'original_size' => $originalSize,
            'type' => $finalExtension,
            'width' => $finalWidth,
            'height' => $finalHeight,
            'mime' => $imageInfo['mime'],
            'compressed' => $this->compressEnabled && $compressionRatio > 0,
            'compression_ratio' => $compressionRatio
        ];
    }
    
    /**
     * 判断是否需要压缩
     * @param string $filePath 文件路径
     * @param string $extension 文件扩展名
     * @return bool
     */
    private function shouldCompress($filePath, $extension) {
        // GIF不压缩（保持动画）
        if ($extension === 'gif') {
            return false;
        }
        
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return false;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $fileSize = filesize($filePath);
        
        // 如果图片尺寸超过限制，需要压缩
        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            return true;
        }
        
        // 如果文件大于1MB，也进行压缩
        if ($fileSize > 1024 * 1024) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 压缩图片
     * @param string $filePath 文件路径
     * @param string $extension 文件扩展名
     * @return array|null 压缩后的信息
     */
    private function compressImage($filePath, $extension) {
        // 获取原始图片信息
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return null;
        }
        
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $originalSize = filesize($filePath);
        
        // 计算新的尺寸
        $newDimensions = $this->calculateNewDimensions($originalWidth, $originalHeight);
        $newWidth = $newDimensions['width'];
        $newHeight = $newDimensions['height'];
        
        // 判断是否需要尺寸调整
        $needResize = ($newWidth != $originalWidth || $newHeight != $originalHeight);
        
        // 判断是否需要质量压缩（文件大于1MB）
        $needQualityCompress = ($originalSize > 1024 * 1024);
        
        // 如果既不需要调整尺寸，也不需要质量压缩，直接返回
        if (!$needResize && !$needQualityCompress) {
            return null;
        }
        
        // 创建源图像（类型改为GdImage）
        $sourceImage = $this->createImageFromFile($filePath, $extension);
        if (!$sourceImage) {
            return null;
        }
        
        // 对于大文件PNG（大于1MB），尝试转换为JPEG以获得更好的压缩效果
        $convertToJpeg = false;
        if ($extension === 'png' && $originalSize > 1024 * 1024) {
            // 检查是否有透明度
            $convertToJpeg = !$this->hasTransparency($sourceImage);
            if ($convertToJpeg) {
                $extension = 'jpg'; // 转换格式
                // 修改文件路径为.jpg
                $filePath = preg_replace('/\.png$/i', '.jpg', $filePath);
            }
        }
        
        // 如果需要调整尺寸，或者转换为JPEG，才需要重新创建图像
        if ($needResize || $convertToJpeg) {
            // 创建目标图像（类型改为GdImage）
            $destinationImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // 处理PNG透明度（仅在保持PNG格式时）
            if ($extension === 'png') {
                imagealphablending($destinationImage, false);
                imagesavealpha($destinationImage, true);
                $transparent = imagecolorallocatealpha($destinationImage, 255, 255, 255, 127);
                imagefilledrectangle($destinationImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            // 调整大小（如果需要）或复制原图
            imagecopyresampled(
                $destinationImage, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $originalWidth, $originalHeight
            );
            
            // 移除imagedestroy()：PHP 8.0+ 自动回收GdImage对象内存
            // imagedestroy($sourceImage);
            
            // 保存压缩后的图片
            $result = $this->saveImage($destinationImage, $filePath, $extension);
            
            // 移除imagedestroy()：PHP 8.0+ 自动回收GdImage对象内存
            // imagedestroy($destinationImage);
        } else {
            // 不需要调整尺寸，也不需要转换格式，只是质量压缩
            // 对于PNG，直接使用最高压缩级别重新保存
            // 移除imagedestroy()：PHP 8.0+ 自动回收GdImage对象内存
            // imagedestroy($sourceImage);
            
            // 重新加载并保存以应用压缩
            $result = $this->recompressPng($filePath);
        }
        
        if ($result) {
            $finalSize = filesize($filePath);
            return [
                'width' => $newWidth,
                'height' => $newHeight,
                'size' => $finalSize,
                'converted' => $convertToJpeg,
                'new_extension' => $convertToJpeg ? 'jpg' : null
            ];
        }
        
        return null;
    }
    
    /**
     * 重新压缩PNG图片
     * @param string $filePath 文件路径
     * @return bool
     */
    private function recompressPng($filePath) {
        $image = imagecreatefrompng($filePath);
        if (!$image) {
            return false;
        }
        
        // 保存为最高压缩级别的PNG
        $result = imagepng($image, $filePath, 9);
        // 移除imagedestroy()：PHP 8.0+ 自动回收GdImage对象内存
        // imagedestroy($image);
        
        return $result;
    }
    
    /**
     * 检查图像是否有透明度
     * @param GdImage $image 图像对象（修改类型注解）
     * @return bool
     */
    private function hasTransparency($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // 检查四个角和中心的像素
        $checkPoints = [
            [0, 0],
            [$width - 1, 0],
            [0, $height - 1],
            [$width - 1, $height - 1],
            [intval($width / 2), intval($height / 2)]
        ];
        
        foreach ($checkPoints as $point) {
            $rgba = imagecolorat($image, $point[0], $point[1]);
            $alpha = ($rgba >> 24) & 0x7F;
            if ($alpha > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 计算新的尺寸
     * @param int $originalWidth 原始宽度
     * @param int $originalHeight 原始高度
     * @return array 新的尺寸
     */
    private function calculateNewDimensions($originalWidth, $originalHeight) {
        // 如果图片尺寸在限制范围内，不调整
        if ($originalWidth <= $this->maxWidth && $originalHeight <= $this->maxHeight) {
            return [
                'width' => $originalWidth,
                'height' => $originalHeight
            ];
        }
        
        // 计算缩放比例
        $ratio = min($this->maxWidth / $originalWidth, $this->maxHeight / $originalHeight);
        
        return [
            'width' => round($originalWidth * $ratio),
            'height' => round($originalHeight * $ratio)
        ];
    }
    
    /**
     * 从文件创建图像对象（修改返回值类型注解）
     * @param string $filePath 文件路径
     * @param string $extension 文件扩展名
     * @return GdImage|false
     */
    private function createImageFromFile($filePath, $extension) {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return imagecreatefromjpeg($filePath);
            case 'png':
                return imagecreatefrompng($filePath);
            case 'gif':
                return imagecreatefromgif($filePath);
            case 'webp':
                return imagecreatefromwebp($filePath);
            default:
                return false;
        }
    }
    
    /**
     * 保存图像到文件（修改参数类型注解）
     * @param GdImage $image 图像对象
     * @param string $filePath 文件路径
     * @param string $extension 文件扩展名
     * @return bool
     */
    private function saveImage($image, $filePath, $extension) {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return imagejpeg($image, $filePath, $this->compressQuality);
            case 'png':
                // PNG使用0-9的压缩级别，将0-100的质量转换为0-9
                // 注意：PNG压缩级别9是最高压缩，0是无压缩
                $pngQuality = 9; // 使用最高压缩级别
                return imagepng($image, $filePath, $pngQuality);
            case 'gif':
                return imagegif($image, $filePath);
            case 'webp':
                return imagewebp($image, $filePath, $this->compressQuality);
            default:
                return false;
        }
    }
    
    /**
     * 生成唯一文件名
     * @param string $prefix 文件名前缀
     * @param string $extension 文件扩展名
     * @return string
     */
    private function generateFileName($prefix, $extension) {
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        return "{$prefix}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * 获取文件URL
     * @param string $fileName 文件名
     * @return string
     */
    private function getFileUrl($fileName) {
        $relativePath = str_replace(dirname(dirname(__DIR__)), '', $this->uploadDir);
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = '/' . ltrim($relativePath, '/') . '/' . $fileName;
        
        // 使用SITE_URL生成完整的URL，自动区分本地测试环境和生产环境
        if (defined('SITE_URL')) {
            return SITE_URL . $relativePath;
        }
        
        return $relativePath;
    }
    
    /**
     * 删除文件
     * @param string $fileName 文件名
     * @return bool
     */
    public function deleteFile($fileName) {
        $filePath = $this->uploadDir . '/' . $fileName;
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        return unlink($filePath);
    }
    
    /**
     * 获取错误信息
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * 设置允许的文件类型
     * @param array $types 文件类型数组
     */
    public function setAllowedTypes($types) {
        $this->allowedTypes = $types;
    }
    
    /**
     * 设置最大文件大小
     * @param int $size 文件大小（字节）
     */
    public function setMaxSize($size) {
        $this->maxSize = $size;
    }
}
?>