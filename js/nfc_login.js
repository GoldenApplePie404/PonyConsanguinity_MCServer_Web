/**
 * NFC 主动请求登录（Flow②）
 * ─────────────────────────────────────────────────────────────
 * 模块化设计：本文件仅被 pages/login.html 以 <script> 引入，不改动原登录逻辑。
 *
 * 流程：
 *   用户点「碰一下 NFC 登录」→ 检测 navigator.nfc 支持 → new NDEFReader().scan()
 *   → onreading 解出 NDEF 记录（url / text）→ 提取 dev & tok
 *   → POST ../api/nfc/device_login.php
 *   → 成功则写 localStorage（与站点会话体系键名一致）→ 跳 profile.html
 *
 * 依赖：api/nfc/device_login.php（POST 返回 {success, data:{token,username,role,dev}}）
 *       站点全局 showMessage()（若缺失则降级为 console+alert）
 *
 * 安全上下文要求：navigator.nfc 仅在 https:// 或 http://localhost 下可用。
 *   - 生产环境 https://mcpc.goldenapplepie.xyz 满足；
 *   - 本地测试可用 ngrok/cloudflared 隧道（https）或 adb reverse + http://localhost。
 *   - 纯 LAN http://192.168.x.x 下 navigator.nfc 为 undefined，此时请直接用 Flow①（卡片碰一下手机，系统读 NDEF 自动跳页）。
 */
(function () {
  'use strict';

  // 与站点其他登录方式保持一致的 localStorage 键名（详见 nfc_login_success.html）
  const LS = {
    currentUser: 'currentUser',
    isLoggedIn: 'isLoggedIn',
    authToken: 'authToken',
    username: 'username',
    nfcDevice: 'nfcDevice',
  };

  function nfcShowMsg(msg, type) {
    if (typeof showMessage === 'function') {
      showMessage(msg, type || 'info');
    } else {
      console.log('[NFC]', type || 'info', msg);
      alert(msg);
    }
  }

  function setBtnState(state) {
    const btn = document.getElementById('nfc-login-btn');
    if (!btn) return;
    if (state === 'scanning') {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 靠近设备中…';
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-wifi"></i> 碰一下 NFC 登录';
    }
  }

  // text 记录：首字节状态位（高 1 位编码 + 低 6 位语言码长度），随后语言码，再文本
  function decodeNdefText(record) {
    const dec = new TextDecoder('utf-8');
    const buf = record.data;                 // ArrayBuffer
    if (!buf || buf.byteLength < 1) return '';
    const view = new DataView(buf);
    const status = view.getUint8(0);
    const langLen = status & 0x3f;
    return dec.decode(buf.slice(1 + langLen));
  }

  function extractDevTok(payload) {
    try {
      const u = new URL(payload);
      return { dev: u.searchParams.get('dev'), tok: u.searchParams.get('tok') };
    } catch (e) {
      return { dev: null, tok: null };
    }
  }

  function storeSession(d) {
    localStorage.setItem(LS.currentUser, JSON.stringify({
      username: d.username,
      role: d.role,
      token: d.token,
    }));
    localStorage.setItem(LS.isLoggedIn, 'true');
    localStorage.setItem(LS.authToken, d.token);
    localStorage.setItem(LS.username, d.username);
    if (d.dev) localStorage.setItem(LS.nfcDevice, d.dev);
  }

  async function doNfcLogin() {
    if (!('NDEFReader' in window)) {
      nfcShowMsg('当前浏览器不支持 Web NFC：请用 Android Chrome，或直接将设备碰一下手机（系统会自动跳登录页）', 'info');
      return;
    }
    try {
      const reader = new NDEFReader();
      await reader.scan();
      nfcShowMsg('已开启 NFC，请将骥忆云笺设备靠近手机背部…', 'info');
      setBtnState('scanning');

      // 超时兜底：20s 内没读到任何标签，明确提示卡在哪（避免静默"就没了"）
      const scanTimer = setTimeout(() => {
        if (!done) {
          nfcShowMsg('20 秒内未检测到 NFC 标签。请确认：① 设备已按 t 进入模拟卡模式（串口显示「持续等待」）；② 手机背部已贴紧 PN532 模块线圈；③ 手机 NFC 已开启。', 'error');
        }
      }, 20000);

      let done = false;   // 防止一次碰触触发多次处理
      reader.onreading = async (event) => {
        if (done) return;
        clearTimeout(scanTimer);
        try {
          const msg = event.message;
          let dev = null, tok = null;
          for (const rec of msg.records) {
            let payload = '';
            if (rec.recordType === 'url') {
              payload = new TextDecoder().decode(rec.data);
            } else if (rec.recordType === 'text') {
              payload = decodeNdefText(rec);
            }
            // 其它类型（smart-poster / 未知）暂不深解析，避免误读
            if (payload) {
              const r = extractDevTok(payload);
              if (r.dev && r.tok) { dev = r.dev; tok = r.tok; break; }
            }
          }
          if (!dev || !tok) {
            nfcShowMsg('未从 NFC 标签读取到有效的设备登录信息', 'error');
            setBtnState('idle');
            return;
          }
          done = true;

          const resp = await fetch('../api/nfc/device_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ dev, tok }),
          });
          const data = await resp.json();
          if (data && data.success) {
            storeSession(data.data);
            nfcShowMsg('登录成功！欢迎回来，' + data.data.username, 'success');
            setTimeout(() => { window.location.href = 'profile.html'; }, 1000);
          } else {
            nfcShowMsg((data && data.message) || 'NFC 设备登录失败', 'error');
            setBtnState('idle');
          }
        } catch (e) {
          console.error('[NFC] read handler error', e);
          nfcShowMsg('读取 NFC 失败：' + (e && e.message ? e.message : e), 'error');
          setBtnState('idle');
        }
      };

      reader.onerror = (err) => {
        console.error('[NFC] reader error', err);
        nfcShowMsg('NFC 读取发生错误', 'error');
        setBtnState('idle');
      };
    } catch (e) {
      console.error('[NFC] scan start error', e);
      if (e && e.name === 'NotAllowedError') {
        nfcShowMsg('需要授权 NFC 权限才能使用此功能', 'error');
      } else if (e && e.name === 'NotSupportedError') {
        nfcShowMsg('此设备不支持 NFC', 'error');
      } else {
        nfcShowMsg('无法启动 NFC：' + (e && e.message ? e.message : e), 'error');
      }
      setBtnState('idle');
    }
  }

  // 暴露给 login.html 的 onclick="handleNfcLogin()"
  window.handleNfcLogin = doNfcLogin;
})();
