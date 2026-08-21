/**
 * Captcha 滑块组件（自建，无第三方依赖）
 *
 * 用法：
 *   const captcha = new CaptchaSlider(containerId, options);
 *   captcha.init();                       // 初始化并加载挑战
 *   captcha.getToken();                   // 返回 Promise<captcha_token>（已通过验证）
 *   captcha.reset();                      // 手动重置
 *
 * options:
 *   apiBase: 默认 '../api/captcha.php'（子页在 pages/ 下用 '../'）
 *
 * 防机器：前端只负责渲染与交互，真实校验在后端
 * （位置误差 + 轨迹人类特征 + 一次性 token）。
 */
(function (window, document) {
    'use strict';

    class CaptchaSlider {
        constructor(containerId, options) {
            this.container = typeof containerId === 'string'
                ? document.getElementById(containerId)
                : containerId;
            if (!this.container) {
                throw new Error('CaptchaSlider: 容器不存在: ' + containerId);
            }

            this.apiBase = (options && options.apiBase) || '../api/captcha.php';

            this.state = 'idle';     // idle | loading | ready | verified | error
            this.challengeId = '';
            this.targetPct = 0;
            this.captchaToken = '';
            this.trail = [];
            this.isDragging = false;
            this.trackStartX = 0;
            this.lastX = 0;
            this.lastTime = 0;

            this._build();
            this._bindEvents();
        }

        /* ── 内部：构建 DOM ─────────────────────────── */

        _build() {
            this.container.classList.add('captcha-box');
            this.container.innerHTML = `
                <div class="captcha-wrap">
                    <div class="captcha-track" id="captcha-track">
                        <div class="captcha-bg">
                            <span class="captcha-shine"></span>
                        </div>
                        <div class="captcha-gap" id="captcha-gap"></div>
                        <div class="captcha-piece" id="captcha-piece">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    <div class="captcha-status" id="captcha-status">
                        <span class="captcha-hint">
                            <i class="fas fa-mouse-pointer"></i>
                            按住滑块，拖动到缺口处
                        </span>
                    </div>
                    <div class="captcha-loading" id="captcha-loading">
                        <i class="fas fa-spinner fa-spin"></i> 加载验证…
                    </div>
                </div>
            `;

            this.track = this.container.querySelector('#captcha-track');
            this.gap = this.container.querySelector('#captcha-gap');
            this.piece = this.container.querySelector('#captcha-piece');
            this.status = this.container.querySelector('#captcha-status');
            this.hint = this.container.querySelector('.captcha-hint');
            this.loading = this.container.querySelector('#captcha-loading');
        }

        /* ── 内部：事件绑定 ─────────────────────────── */

        _bindEvents() {
            // 鼠标
            this.piece.addEventListener('mousedown', (e) => this._startDrag(e));
            // 触摸
            this.piece.addEventListener('touchstart', (e) => this._startDrag(e), { passive: false });

            document.addEventListener('mousemove', (e) => this._moveDrag(e));
            document.addEventListener('touchmove', (e) => this._moveDrag(e), { passive: false });

            document.addEventListener('mouseup', (e) => this._endDrag(e));
            document.addEventListener('touchend', (e) => this._endDrag(e));
        }

        /* ── 内部：拖拽逻辑 ─────────────────────────── */

        _getClientX(e) {
            if (e.touches && e.touches.length > 0) {
                return e.touches[0].clientX;
            }
            if (e.changedTouches && e.changedTouches.length > 0) {
                return e.changedTouches[0].clientX;
            }
            return e.clientX;
        }

        _startDrag(e) {
            if (this.state !== 'ready') {
                e.preventDefault();
                return;
            }
            e.preventDefault();
            this.isDragging = true;
            this.trail = [];
            this.trackStartX = this._getClientX(e);
            this.lastX = this.piece.offsetLeft;
            this.lastTime = Date.now();

            this.piece.classList.add('dragging');
            this._setStatus('hint', '拖动滑块…');
        }

        _moveDrag(e) {
            if (!this.isDragging || this.state !== 'ready') {
                return;
            }
            e.preventDefault();

            const now = Date.now();
            const dx = this._getClientX(e) - this.trackStartX;

            // 限制在轨道范围内（轨道宽 - 手柄宽）
            const maxX = this.track.clientWidth - this.piece.clientWidth;
            let x = Math.max(0, Math.min(maxX, dx));

            this.piece.style.left = x + 'px';

            // 记录轨迹（节流：约 15ms 一个点，避免超大数组）
            if (now - this.lastTime >= 15) {
                this.trail.push({ x: x, y: 0, t: now });
                this.lastTime = now;
            }
        }

        _endDrag(e) {
            if (!this.isDragging) {
                return;
            }
            e.preventDefault();
            this.isDragging = false;
            this.piece.classList.remove('dragging');

            const x = this.piece.offsetLeft;
            const trackWidth = this.track.clientWidth - this.piece.clientWidth;
            const xPct = trackWidth > 0 ? (x / trackWidth) * 100 : 0;

            // 补最后一点
            const now = Date.now();
            this.trail.push({ x: x, y: 0, t: now });

            // 起点补 0（从最左开始）
            if (this.trail.length > 0 && this.trail[0].x > 0) {
                this.trail.unshift({ x: 0, y: 0, t: this.trail[0].t - 1 });
            }

            this._verify(xPct);
        }

        /* ── 内部：提交校验 ─────────────────────────── */

        async _verify(xPct) {
            this._setStatus('loading', '验证中…');
            this.state = 'loading';

            try {
                const res = await fetch(this.apiBase, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'verify',
                        challenge_id: this.challengeId,
                        x_pct: xPct,
                        trail: this.trail
                    })
                });
                const data = await res.json();

                if (data.success && data.data && data.data.captcha_token) {
                    this.captchaToken = data.data.captcha_token;
                    this.state = 'verified';
                    this._setStatus('success', '验证通过');
                    this.piece.classList.add('verified');
                    this.piece.style.pointerEvents = 'none';
                    this._emit('verified', this.captchaToken);
                } else {
                    this._fail(data.message || '验证失败，请重试');
                }
            } catch (err) {
                console.error('滑块验证请求错误:', err);
                this._fail('网络错误，请重试');
            }
        }

        /* ── 内部：失败处理 ─────────────────────────── */

        _fail(msg) {
            this.state = 'error';
            this.captchaToken = '';
            this._setStatus('error', msg || '验证失败，请重试');
            // 延迟后重置滑块
            setTimeout(() => this.reset(), 900);
        }

        /* ── 内部：状态显示 ─────────────────────────── */

        _setStatus(type, text) {
            if (!this.status) return;
            this.status.className = 'captcha-status ' + type;
            this.hint.innerHTML = text;

            // 简单图标映射
            if (type === 'success') {
                this.hint.innerHTML = '<i class="fas fa-check-circle"></i> ' + text;
            } else if (type === 'error') {
                this.hint.innerHTML = '<i class="fas fa-times-circle"></i> ' + text;
            } else if (type === 'loading') {
                this.hint.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + text;
            }
        }

        /* ── 公开接口 ───────────────────────────────── */

        /**
         * 初始化：加载挑战并渲染缺口
         */
        async init() {
            this._setStatus('loading', '加载验证…');
            this.state = 'loading';

            try {
                const res = await fetch(this.apiBase, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create' })
                });
                const data = await res.json();

                if (!data.success || !data.data || !data.data.challenge_id) {
                    throw new Error(data.message || '挑战创建失败');
                }

                this.challengeId = data.data.challenge_id;
                this.targetPct = data.data.target_pct;

                this._renderGap();
                this.state = 'ready';
                this._setStatus('hint', '按住滑块，拖动到缺口处');
                this._emit('ready');
            } catch (err) {
                console.error('滑块挑战加载失败:', err);
                this.state = 'error';
                this._setStatus('error', '验证加载失败，请刷新重试');
            }
        }

        /**
         * 渲染缺口位置
         */
        _renderGap() {
            const trackWidth = this.track.clientWidth;
            const gapWidth = this.piece.clientWidth || 40;
            const gapX = (this.targetPct / 100) * (trackWidth - gapWidth);
            this.gap.style.left = gapX + 'px';

            // 重绘时若轨道未布局完成，延迟一次
            if (trackWidth === 0) {
                setTimeout(() => this._renderGap(), 100);
            }
        }

        /**
         * 渲染缺口位置（容器由隐藏变为可见时需调用，例如弹窗打开）
         */
        render() {
            this._renderGap();
        }

        /**
         * 获取验证令牌
         *
         * @return {Promise<string>} 通过验证返回 token；未通过 reject
         */
        getToken() {
            return new Promise((resolve, reject) => {
                if (this.state === 'verified' && this.captchaToken) {
                    resolve(this.captchaToken);
                } else if (this.state === 'idle' || this.state === 'loading') {
                    // 等待 ready/verified
                    const check = setInterval(() => {
                        if (this.state === 'verified') {
                            clearInterval(check);
                            resolve(this.captchaToken);
                        } else if (this.state === 'error' || this.state === 'idle') {
                            clearInterval(check);
                            reject(new Error('请先完成滑块验证'));
                        }
                    }, 100);
                } else {
                    reject(new Error('请先完成滑块验证'));
                }
            });
        }

        /**
         * 重置滑块（新挑战）
         */
        reset() {
            this.state = 'idle';
            this.challengeId = '';
            this.captchaToken = '';
            this.trail = [];
            this.isDragging = false;

            this.piece.classList.remove('verified', 'dragging');
            this.piece.style.left = '0px';
            this.piece.style.pointerEvents = '';
            this._setStatus('hint', '按住滑块，拖动到缺口处');
            this._emit('reset');

            this.init();
        }

        /* ── 内部：事件回调 ─────────────────────────── */

        _emit(type, payload) {
            this.container.dispatchEvent(new CustomEvent('captcha:' + type, {
                detail: payload
            }));
        }
    }

    // 暴露到全局
    window.CaptchaSlider = CaptchaSlider;

})(window, document);
