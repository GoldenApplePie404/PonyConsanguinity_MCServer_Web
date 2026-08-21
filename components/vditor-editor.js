/**
 * Vditor 富文本编辑器组件
 * 提供类 WPS 的可视化编辑体验，支持 Markdown 即时渲染
 * 
 * 使用方法：
 * 1. 在 HTML 中添加容器：<div id="vditor-editor"></div>
 * 2. 初始化：VditorEditor.init('vditor-editor', options)
 * 3. 获取内容：VditorEditor.getValue()
 * 4. 设置内容：VditorEditor.setValue(content)
 */

(function(window) {
    'use strict';

    // 编辑器实例
    let editorInstance = null;

    // 默认配置
    const defaultConfig = {
        mode: 'ir',  // 即时渲染模式
        height: 500,
        cache: { enable: false },
        placeholder: '分享一下你的想法吧~',
        toolbar: [
            'emoji', 'headings', 'bold', 'italic', 'strike', 'line', 'br',
            'list', 'ordered-list', 'check', 'outdent', 'indent',
            'quote', 'link', 'image', 'table',
            'code', 'inline-code', 'insert-after',
            'edit-mode', 'preview', 'fullscreen'
        ],
        counter: {
            enable: true,
            max: 3000
        },
        // 使用本地资源，指定完整的 CDN 路径（指向本地）
        cdn: '../lib/vditor',
        // 指定语言
        lang: 'zh_CN',
        // 指定图标类型
        icon: 'ant',
        // 主题配置
        theme: {
            current: 'light',
            list: ['light', 'dark']
        },
        // 图片上传配置
        upload: {
            accept: 'image/*',
            url: '../api/image_api/upload.php',
            linkToImg: {
                enable: true
            },
            max: 10 * 1024 * 1024,  // 10MB
            filename: function(name) {
                return name.replace(/[^(a-zA-Z0-9\u4e00-\u9fa5\.)]/g, '').replace(/[\?\\/:|<>\*\[\]\(\)\$%\{\}@~]/g, '').replace(/\s/g, '');
            },
            success: function(url) {
                return url;
            },
            error: function(msg) {
                console.error('图片上传失败:', msg);
            }
        }
    };

    /**
     * 初始化编辑器
     * @param {string} containerId - 容器元素 ID
     * @param {object} customConfig - 自定义配置（可选）
     * @returns {object} 编辑器实例
     */
    function init(containerId, customConfig = {}) {
        if (!containerId) {
            console.error('VditorEditor: 必须指定容器 ID');
            return null;
        }

        // 如果已存在实例，先销毁
        if (editorInstance) {
            destroy();
        }

        // 合并配置
        const config = { ...defaultConfig, ...customConfig };

        // 创建编辑器
        try {
            editorInstance = new Vditor(containerId, config);
            console.log('Vditor 编辑器初始化成功:', containerId);
            return editorInstance;
        } catch (error) {
            console.error('Vditor 编辑器初始化失败:', error);
            return null;
        }
    }

    /**
     * 获取编辑器内容（Markdown 格式）
     * @returns {string} Markdown 内容
     */
    function getValue() {
        if (!editorInstance) {
            console.warn('VditorEditor: 编辑器未初始化');
            return '';
        }
        return editorInstance.getValue();
    }

    /**
     * 设置编辑器内容
     * @param {string} value - Markdown 内容
     */
    function setValue(value) {
        if (!editorInstance) {
            console.warn('VditorEditor: 编辑器未初始化');
            return;
        }
        editorInstance.setValue(value);
    }

    /**
     * 获取 HTML 内容
     * @returns {string} HTML 内容
     */
    function getHTML() {
        if (!editorInstance) {
            console.warn('VditorEditor: 编辑器未初始化');
            return '';
        }
        return editorInstance.getHTML();
    }

    /**
     * 插入内容到光标位置
     * @param {string} value - 要插入的内容
     */
    function insertValue(value) {
        if (!editorInstance) {
            console.warn('VditorEditor: 编辑器未初始化');
            return;
        }
        editorInstance.insertValue(value);
    }

    /**
     * 聚焦编辑器
     */
    function focus() {
        if (!editorInstance) {
            console.warn('VditorEditor: 编辑器未初始化');
            return;
        }
        editorInstance.focus();
    }

    /**
     * 模糊编辑器
     */
    function blur() {
        if (!editorInstance) {
            console.warn('VditorEditor: 编辑器未初始化');
            return;
        }
        editorInstance.blur();
    }

    /**
     * 禁用编辑器
     */
    function disable() {
        if (!editorInstance) {
            console.warn('VditorEditor: 编辑器未初始化');
            return;
        }
        editorInstance.disable();
    }

    /**
     * 启用编辑器
     */
    function enable() {
        if (!editorInstance) {
            console.warn('VditorEditor: 编辑器未初始化');
            return;
        }
        editorInstance.enable();
    }

    /**
     * 销毁编辑器实例
     */
    function destroy() {
        if (editorInstance) {
            editorInstance.destroy();
            editorInstance = null;
            console.log('Vditor 编辑器已销毁');
        }
    }

    /**
     * 检查编辑器是否已初始化
     * @returns {boolean}
     */
    function isInitialized() {
        return editorInstance !== null;
    }

    /**
     * 获取编辑器实例（用于高级操作）
     * @returns {object} Vditor 实例
     */
    function getInstance() {
        return editorInstance;
    }

    /**
     * 设置字数统计回调
     * @param {function} callback - 回调函数 (length, maxLength)
     */
    function setCharCountCallback(callback) {
        if (!editorInstance) {
            console.warn('VditorEditor: 编辑器未初始化');
            return;
        }
        
        // 通过监听 input 事件实现
        editorInstance.vditor.svElement?.addEventListener('input', function() {
            const value = getValue();
            const length = value.replace(/\s/g, '').length;
            const maxLength = defaultConfig.counter?.max || 3000;
            callback(length, maxLength);
        });
    }

    // 暴露公共 API
    window.VditorEditor = {
        init,
        getValue,
        setValue,
        getHTML,
        insertValue,
        focus,
        blur,
        disable,
        enable,
        destroy,
        isInitialized,
        getInstance,
        setCharCountCallback
    };

    console.log('VditorEditor 组件已加载');

})(window);
