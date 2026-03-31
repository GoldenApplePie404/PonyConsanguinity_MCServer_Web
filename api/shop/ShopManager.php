<?php
/**
 * 商品管理类
 * 负责商品的加载、解析、库存管理
 */

class ShopManager {
    private $shopItemsDir;
    private $items = [];
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->shopItemsDir = __DIR__ . '/../../data/shop_items';
        $this->loadAllItems();
    }
    
    /**
     * 加载所有商品
     */
    private function loadAllItems() {
        if (!is_dir($this->shopItemsDir)) {
            error_log('Shop items directory not found: ' . $this->shopItemsDir);
            return;
        }
        
        $files = glob($this->shopItemsDir . '/*.json');
        
        foreach ($files as $file) {
            try {
                $jsonContent = file_get_contents($file);
                $itemData = json_decode($jsonContent, true);
                
                if ($itemData && isset($itemData['item_id'])) {
                    $this->items[$itemData['item_id']] = $itemData;
                }
            } catch (Exception $e) {
                error_log('Error loading item file ' . $file . ': ' . $e->getMessage());
            }
        }
    }
    
    /**
     * 获取所有商品
     * @param string|null $category 分类筛选（可选）
     * @param bool $onlyOnSale 是否只获取上架商品
     * @return array
     */
    public function getAllItems($category = null, $onlyOnSale = true) {
        $items = $this->items;
        
        // 筛选上架状态
        if ($onlyOnSale) {
            $items = array_filter($items, function($item) {
                return $item['status'] === 'on_sale';
            });
        }
        
        // 分类筛选
        if ($category && $category !== 'all') {
            $items = array_filter($items, function($item) use ($category) {
                return $item['type'] === $category;
            });
        }
        
        return array_values($items);
    }
    
    /**
     * 获取单个商品
     * @param string $itemId 商品 ID
     * @return array|null
     */
    public function getItem($itemId) {
        return isset($this->items[$itemId]) ? $this->items[$itemId] : null;
    }
    
    /**
     * 检查商品是否可以购买
     * @param array $item 商品数据
     * @param int $userLevel 用户等级
     * @param int $purchaseCount 已购买数量
     * @return array ['can_purchase' => bool, 'reason' => string]
     */
    public function checkPurchaseEligibility($item, $userLevel, $purchaseCount = 0) {
        // 检查上架状态
        if ($item['status'] !== 'on_sale') {
            return [
                'can_purchase' => false,
                'reason' => '该商品已下架'
            ];
        }
        
        // 检查等级限制
        if ($item['min_level'] > 0 && $userLevel < $item['min_level']) {
            return [
                'can_purchase' => false,
                'reason' => "需要达到 {$item['min_level']} 级才能购买"
            ];
        }
        
        // 检查库存
        if ($item['stock'] > 0 && $item['stock'] <= $purchaseCount) {
            return [
                'can_purchase' => false,
                'reason' => '商品已售罄'
            ];
        }
        
        // 检查限购
        if ($item['max_purchase'] > 0 && $purchaseCount >= $item['max_purchase']) {
            return [
                'can_purchase' => false,
                'reason' => "已达到限购数量 ({$item['max_purchase']}件)"
            ];
        }
        
        return [
            'can_purchase' => true,
            'reason' => ''
        ];
    }
    
    /**
     * 获取商品分类列表
     * @return array
     */
    public function getCategories() {
        $categories = [];
        
        foreach ($this->items as $item) {
            if (!in_array($item['type'], $categories)) {
                $categories[] = $item['type'];
            }
        }
        
        return $categories;
    }
    
    /**
     * 添加新商品
     * @param array $itemData 商品数据
     * @return bool
     */
    public function addItem($itemData) {
        if (!isset($itemData['item_id'])) {
            return false;
        }
        
        $filePath = $this->shopItemsDir . '/' . $itemData['item_id'] . '.json';
        
        try {
            $jsonContent = json_encode($itemData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($filePath, $jsonContent);
            $this->items[$itemData['item_id']] = $itemData;
            return true;
        } catch (Exception $e) {
            error_log('Error adding item: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新商品
     * @param string $itemId 商品 ID
     * @param array $itemData 商品数据
     * @return bool
     */
    public function updateItem($itemId, $itemData) {
        if (!isset($this->items[$itemId])) {
            return false;
        }
        
        $itemData['item_id'] = $itemId;
        return $this->addItem($itemData);
    }
    
    /**
     * 删除商品
     * @param string $itemId 商品 ID
     * @return bool
     */
    public function deleteItem($itemId) {
        if (!isset($this->items[$itemId])) {
            return false;
        }
        
        $filePath = $this->shopItemsDir . '/' . $itemId . '.json';
        
        try {
            unlink($filePath);
            unset($this->items[$itemId]);
            return true;
        } catch (Exception $e) {
            error_log('Error deleting item: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 减少商品库存
     * @param string $itemId 商品 ID
     * @param int $quantity 数量
     * @return bool
     */
    public function reduceStock($itemId, $quantity = 1) {
        if (!isset($this->items[$itemId])) {
            return false;
        }
        
        $item = $this->items[$itemId];
        
        // 无限库存不减少
        if ($item['stock'] == -1) {
            return true;
        }
        
        if ($item['stock'] < $quantity) {
            return false;
        }
        
        $item['stock'] -= $quantity;
        return $this->updateItem($itemId, $item);
    }
}
