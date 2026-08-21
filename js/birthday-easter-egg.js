/**
 * 万驹同源 - 服务器生日彩蛋模块
 * 每年 5 月自动触发全站庆祝特效
 * 服务器起源：2020年5月
 */

(function () {
    const SERVER_FOUND_YEAR = 2020;
    const SERVER_FOUND_MONTH = 5; 
    // 如需精确到天，可设置 SERVER_FOUND_DAY（不设则整个月都触发）
    const SERVER_FOUND_DAY = null; // 例如: 15

    // 检查是否在生日期间
    function isBirthdayPeriod() {
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth() + 1; // getMonth() 返回 0-11
        const day = now.getDate();

        if (month !== SERVER_FOUND_MONTH) return false;
        if (SERVER_FOUND_DAY !== null && day !== SERVER_FOUND_DAY) return false;
        return true;
    }

    if (!isBirthdayPeriod()) return;

    document.addEventListener('DOMContentLoaded', function () {
        const anniversary = new Date().getFullYear() - SERVER_FOUND_YEAR;
        const serverAge = anniversary;

        // ── 注入样式 ──
        const style = document.createElement('style');
        style.textContent = `
            /* 生日横幅 */
            .bd-banner {
                position: relative;
                background: linear-gradient(135deg, #f9b238 0%, #e97a43 25%, #f9b238 50%, #e97a43 75%, #f9b238 100%);
                background-size: 200% 200%;
                animation: bdShimmer 3s ease-in-out infinite;
                color: #fff;
                text-align: center;
                padding: 10px 20px;
                font-size: 15px;
                font-weight: 700;
                letter-spacing: 1px;
                z-index: 1001;
                position: relative;
                overflow: hidden;
                box-shadow: 0 2px 12px rgba(249, 178, 56, 0.3);
            }
            @keyframes bdShimmer {
                0%, 100% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
            }
            .bd-banner-content {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .bd-banner .bd-cake {
                font-size: 22px;
                animation: bdBounce 1s ease-in-out infinite;
            }
            @keyframes bdBounce {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-6px); }
            }
            .bd-banner .bd-year {
                font-size: 20px;
                font-weight: 900;
                color: #fff;
                text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }

            /* 生日粒子 */
            .bd-particle {
                position: fixed;
                pointer-events: none;
                z-index: 10000;
                animation: bdFall linear forwards;
                font-size: 20px;
                opacity: 0;
            }
            @keyframes bdFall {
                0% { transform: translateY(-40px) rotate(0deg) scale(0.5); opacity: 0; }
                10% { opacity: 1; }
                90% { opacity: 0.8; }
                100% { transform: translateY(105vh) rotate(720deg) scale(1); opacity: 0; }
            }

            /* Logo 蛋糕装饰 */
            .bd-logo-cake {
                display: inline-block;
                font-size: 18px;
                margin-left: 6px;
                animation: bdBounce 1s ease-in-out infinite;
                cursor: pointer;
                vertical-align: middle;
            }

            /* 生日弹窗 */
            .bd-modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.7);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: bdFadeIn 0.5s ease;
            }
            @keyframes bdFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .bd-modal {
                background: linear-gradient(145deg, #1a1a2e, #2d2d44);
                border: 2px solid rgba(249, 178, 56, 0.4);
                border-radius: 20px;
                padding: 40px 36px;
                text-align: center;
                max-width: 420px;
                width: 90%;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(249, 178, 56, 0.2);
                animation: bdModalIn 0.5s ease;
                position: relative;
            }
            @keyframes bdModalIn {
                from { transform: scale(0.8) translateY(20px); opacity: 0; }
                to { transform: scale(1) translateY(0); opacity: 1; }
            }
            .bd-modal .bd-modal-icon {
                font-size: 56px;
                margin-bottom: 16px;
                animation: bdBounce 1s ease-in-out infinite;
            }
            .bd-modal h2 {
                font-size: 22px;
                color: #f9b238;
                margin: 0 0 10px;
                font-weight: 800;
            }
            .bd-modal p {
                color: rgba(255, 255, 255, 0.75);
                font-size: 14px;
                line-height: 1.8;
                margin: 0 0 8px;
            }
            .bd-modal .bd-days {
                color: #f9b238;
                font-size: 28px;
                font-weight: 900;
                margin: 12px 0;
            }
            .bd-modal .bd-days small {
                font-size: 14px;
                font-weight: 400;
                color: rgba(255, 255, 255, 0.6);
            }
            .bd-modal button {
                margin-top: 18px;
                padding: 10px 36px;
                border: none;
                border-radius: 25px;
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
                background: linear-gradient(135deg, #f9b238, #e97a43);
                color: #fff;
                transition: all 0.3s ease;
            }
            .bd-modal button:hover {
                transform: scale(1.05);
                box-shadow: 0 6px 20px rgba(249, 178, 56, 0.4);
            }

            /* 页脚彩蛋 */
            .bd-footer-note {
                color: #f9b238;
                font-weight: 600;
                font-size: 13px;
            }
        `;
        document.head.appendChild(style);

        // ── 1. 生日横幅 ──
        const banner = document.createElement('div');
        banner.className = 'bd-banner';
        banner.innerHTML = `
            <div class="bd-banner-content">
                <span class="bd-cake">🎂</span>
                <span>万驹同源 <span class="bd-year">${anniversary}</span> 周年快乐！</span>
                <span class="bd-cake">🎉</span>
            </div>
        `;
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            navbar.insertAdjacentElement('afterend', banner);
        } else {
            document.body.insertBefore(banner, document.body.firstChild);
        }

        // ── 2. 生日粒子 ──
        const particleEmojis = ['🎂', '🎉', '🎈', '🎁', '⭐', '✨', '🎀', '🍰', '🧁', '💫'];
        function spawnParticle() {
            const particle = document.createElement('div');
            particle.className = 'bd-particle';
            particle.textContent = particleEmojis[Math.floor(Math.random() * particleEmojis.length)];
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = -(Math.random() * 40) + 'px';
            particle.style.animationDuration = (Math.random() * 6 + 6) + 's';
            particle.style.animationDelay = Math.random() * 2 + 's';
            particle.style.fontSize = (Math.random() * 16 + 14) + 'px';
            document.body.appendChild(particle);

            particle.addEventListener('animationend', function () {
                particle.remove();
            });
        }
        // 每 800ms 生成一个粒子
        setInterval(spawnParticle, 800);
        // 初始生成一批
        for (let i = 0; i < 8; i++) {
            setTimeout(spawnParticle, i * 300);
        }

        // ── 3. Logo 蛋糕装饰 ──
        const logo = document.querySelector('.navbar .logo, .logo a, .logo');
        if (logo) {
            const cake = document.createElement('span');
            cake.className = 'bd-logo-cake';
            cake.textContent = '🎂';
            cake.title = `服务器 ${anniversary} 岁啦！`;
            logo.appendChild(cake);
        }

        // ── 4. 生日弹窗（当天只显示一次） ──
        const modalKey = 'bd_modal_shown_' + new Date().getFullYear();
        if (!sessionStorage.getItem(modalKey)) {
            // 计算服务器存活天数
            const foundDate = new Date(SERVER_FOUND_YEAR, SERVER_FOUND_MONTH - 1, SERVER_FOUND_DAY || 1);
            const today = new Date();
            const daysAlive = Math.floor((today - foundDate) / (1000 * 60 * 60 * 24));

            const overlay = document.createElement('div');
            overlay.className = 'bd-modal-overlay';
            overlay.innerHTML = `
                <div class="bd-modal">
                    <div class="bd-modal-icon">🎂</div>
                    <h2>万驹同源 ${anniversary} 周年！</h2>
                    <p>感谢每一位玩家的一路陪伴</p>
                    <div class="bd-days">${daysAlive} <small>天</small></div>
                    <p style="font-size:12px;color:rgba(255,255,255,0.4);">已陪伴大家 ${daysAlive} 个日夜</p>
                    <button id="bdCloseModal">继续探索</button>
                </div>
            `;
            document.body.appendChild(overlay);

            overlay.querySelector('#bdCloseModal').addEventListener('click', function () {
                overlay.remove();
                sessionStorage.setItem(modalKey, '1');
            });

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    overlay.remove();
                    sessionStorage.setItem(modalKey, '1');
                }
            });
        }

        // ── 5. 页脚彩蛋 ──
        const footer = document.querySelector('footer p, footer .copyright, .footer-copyright');
        if (footer) {
            const currentYear = new Date().getFullYear();
            const note = document.createElement('span');
            note.className = 'bd-footer-note';
            note.textContent = ` · 已陪伴大家 ${serverAge} 年`;
            // 更新年份范围
            const yearRange = footer.innerHTML.match(/2020[-\u2013\u2014]?\d{4}/);
            if (yearRange) {
                footer.innerHTML = footer.innerHTML.replace(yearRange[0], `2020-${currentYear}`);
            }
            footer.appendChild(note);
        }

        console.log(`%c🎂 万驹同源 ${anniversary} 周年快乐！%c 感谢一路有你 ❤️`,
            'font-size:18px;color:#f9b238;',
            'font-size:14px;color:#aaa;');
    });
})();