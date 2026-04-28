# Vditor 富文本编辑器组件使用文档

## 📦 组件介绍

VditorEditor 是一个基于 Vditor 的富文本编辑器组件，提供类 WPS 的可视化编辑体验，支持 Markdown 即时渲染。

### 特性
- ✅ 即时渲染模式（类似 Typora）
- ✅ 类 WPS 的工具栏操作
- ✅ 支持 Markdown 源码模式切换
- ✅ 内置字数统计
- ✅ 图片上传支持
- ✅ 输出纯 Markdown 格式
- ✅ 完全本地化，无需 CDN

---

## 🚀 快速开始

### 1. 引入组件文件

在 HTML 的 `<head>` 中添加：

```html
<!-- Vditor 库（必须先引入，包含完整样式） -->
<link rel="stylesheet" href="../lib/vditor/index.css">
<script src="../lib/vditor/index.min.js"></script>

<!-- 编辑器组件（包含自定义样式和封装） -->
<link rel="stylesheet" href="../components/vditor-editor.css">
<script src="../components/vditor-editor.js"></script>
```

**注意：**
- 必须先引入 `lib/vditor/index.css`（包含图标、基础样式）
- 再引入 `components/vditor-editor.css`（自定义覆盖样式）

### 2. 添加编辑器容器

在需要编辑器的位置添加：

```html
<div id="vditor-editor"></div>
<input type="hidden" id="post-content">
```

### 3. 初始化编辑器

在 JavaScript 中初始化：

```javascript
function initEditor() {
    VditorEditor.init('vditor-editor', {
        height: 500,
        placeholder: '请输入内容...',
        counter: {
            enable: true,
            max: 3000
        },
        upload: {
            url: '../api/image_api/upload.php'
        },
        input: function(value) {
            // 实时更新隐藏字段
            document.getElementById('post-content').value = value;
        }
    });
}

// 页面加载时初始化
document.addEventListener('DOMContentLoaded', initEditor);
```

---

## 📖 API 文档

### 初始化

```javascript
VditorEditor.init(containerId, options)
```

**参数：**
- `containerId` (string): 容器元素 ID
- `options` (object): 配置选项（可选）

**返回：** Vditor 实例

---

### 获取内容

```javascript
const markdown = VditorEditor.getValue();
```

**返回：** Markdown 格式的字符串

---

### 设置内容

```javascript
VditorEditor.setValue('# Hello World\n\n这是内容');
```

**参数：**
- `value` (string): Markdown 格式的内容

---

### 获取 HTML

```javascript
const html = VditorEditor.getHTML();
```

**返回：** HTML 格式的字符串

---

### 插入内容

```javascript
VditorEditor.insertValue('**加粗文本**');
```

**参数：**
- `value` (string): 要插入的 Markdown 内容

---

### 聚焦/模糊

```javascript
VditorEditor.focus();   // 聚焦
VditorEditor.blur();    // 模糊
```

---

### 启用/禁用

```javascript
VditorEditor.enable();   // 启用编辑器
VditorEditor.disable();  // 禁用编辑器
```

---

### 销毁实例

```javascript
VditorEditor.destroy();
```

---

### 检查状态

```javascript
if (VditorEditor.isInitialized()) {
    console.log('编辑器已初始化');
}
```

---

## ⚙️ 配置选项

### 常用配置

```javascript
{
    // 编辑模式：'ir' (即时渲染) | 'sv' (分屏) | 'wysiwyg' (所见即所得)
    mode: 'ir',
    
    // 编辑器高度
    height: 500,
    
    // 占位符
    placeholder: '请输入内容...',
    
    // 缓存
    cache: { enable: false },
    
    // 工具栏配置
    toolbar: [
        'emoji', 'headings', 'bold', 'italic', 'strike',
        'list', 'ordered-list', 'quote',
        'link', 'image', 'table',
        'code', 'inline-code',
        'edit-mode', 'preview', 'fullscreen'
    ],
    
    // 字数统计
    counter: {
        enable: true,
        max: 3000
    },
    
    // 图片上传
    upload: {
        accept: 'image/*',
        url: '../api/image_api/upload.php',
        linkToImg: { enable: true },
        success: function(url) { return url; },
        error: function(msg) { console.error(msg); }
    },
    
    // 输入回调
    input: function(value) {
        console.log('当前内容:', value);
    }
}
```

---

## 💡 使用示例

### 示例 1：发帖页面

```html
<!-- HTML -->
<div class="form-group">
    <label>内容</label>
    <div id="vditor-editor"></div>
    <input type="hidden" id="post-content">
    <div class="char-count">
        <span id="content-char-count">0</span>/3000
    </div>
</div>

<!-- JavaScript -->
<script>
function initPostEditor() {
    VditorEditor.init('vditor-editor', {
        height: 500,
        counter: { enable: true, max: 3000 },
        upload: { url: '../api/image_api/upload.php' },
        input: function(value) {
            document.getElementById('post-content').value = value;
            const length = value.replace(/\s/g, '').length;
            document.getElementById('content-char-count').textContent = length;
        }
    });
}

function submitPost() {
    const content = VditorEditor.getValue();
    // 提交到服务器...
}
</script>
```

---

### 示例 2：编辑页面

```html
<!-- HTML -->
<div id="edit-vditor-editor"></div>

<!-- JavaScript -->
<script>
// 初始化编辑器
VditorEditor.init('edit-vditor-editor', {
    height: 400,
    placeholder: '编辑文章内容...'
});

// 打开编辑对话框时加载内容
function openEditModal(postId) {
    // 获取帖子内容
    fetchPostContent(postId).then(content => {
        VditorEditor.setValue(content);  // 设置初始内容
    });
}

// 保存编辑
function saveEdit() {
    const content = VditorEditor.getValue();
    // 提交到服务器...
}
</script>
```

---

### 示例 3：只读模式

```javascript
// 禁用编辑器（只读）
VditorEditor.disable();

// 启用编辑器
VditorEditor.enable();
```

---

## 🎨 样式定制

### 修改编辑器主题

```css
/* 在自定义 CSS 文件中添加 */
.vditor {
    border-color: #your-color !important;
}

.vditor-toolbar {
    background-color: #your-color !important;
}

.vditor-ir {
    background-color: #your-color !important;
}
```

### 暗黑模式支持

```css
body.dark-theme .vditor {
    --toolbar-background-color: #1d2125;
    --toolbar-icon-color: #b9b9b9;
    --textarea-background-color: #2f363d;
    --textarea-text-color: #d1d5da;
}
```

---

## ⚠️ 注意事项

1. **必须先引入 Vditor 库**
   ```html
   <script src="../lib/vditor/index.min.js"></script>
   ```

2. **容器必须在初始化前存在**
   ```javascript
   // ❌ 错误：容器还不存在
   VditorEditor.init('vditor-editor');
   
   // ✅ 正确：在 DOM 加载后初始化
   document.addEventListener('DOMContentLoaded', function() {
       VditorEditor.init('vditor-editor');
   });
   ```

3. **切换页面时销毁实例**
   ```javascript
   // 如果是 SPA，在页面切换时销毁
   VditorEditor.destroy();
   ```

4. **图片上传接口**
   - 确保 `upload.php` 存在且可访问
   - 返回格式：`{ "code": 0, "msg": "", "data": { "errFiles": [], "succMap": {"file1.jpg": "url1"} } }`

---

## 🔧 故障排除

### 问题 1：编辑器不显示

**检查：**
- Vditor 库是否正确引入
- 容器元素是否存在
- 控制台是否有错误信息

### 问题 2：图片上传失败

**检查：**
- 上传路径是否正确
- PHP 接口是否可访问
- 文件权限设置

### 问题 3：样式异常

**解决：**
- 确保组件 CSS 已引入
- 检查是否有其他 CSS 冲突
- 使用浏览器开发者工具检查

---

## 📝 更新日志

### v1.0.0 (2026-04-03)
- ✅ 初始版本发布
- ✅ 支持即时渲染模式
- ✅ 完整的 API 封装
- ✅ 本地化部署

---

## 📞 技术支持

如有问题，请查看：
- [Vditor 官方文档](https://b3log.org/vditor/)
- [GitHub 仓库](https://github.com/Vanessa219/vditor)

---

**最后更新：** 2026-04-03
