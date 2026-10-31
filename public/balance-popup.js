/* ============================================================
 *  balance-popup.js — lấy số dư MU và hiện ra ô nổi trên trang.
 *  Dùng cho điện thoại (không mở được Console).
 *
 *  CÁCH DÙNG (iPhone Safari / Chrome Android):
 *   1. Tạo 1 Bookmark bất kỳ, rồi SỬA địa chỉ của nó thành đúng đoạn
 *      javascript: trong file bookmarklet.txt.
 *   2. Mở trang MU và ĐĂNG NHẬP (mu.sng22.vip).
 *   3. Bấm vào bookmark đó -> ô kết quả hiện lên giữa màn hình.
 *
 *  Script này tự tìm token trong trình duyệt và gọi API djmuc.vinamilk3.net
 *  bằng chính phiên đăng nhập của bạn, nên không bị chặn khu vực.
 * ============================================================ */
(async () => {
  const API = 'https://djmuc.vinamilk3.net';

  // ---- ô kết quả nổi ----
  document.getElementById('mu-balance-box')?.remove();
  const box = document.createElement('div');
  box.id = 'mu-balance-box';
  box.style.cssText = 'position:fixed;left:50%;top:12%;transform:translateX(-50%);z-index:2147483647;' +
    'max-width:92vw;width:380px;max-height:76vh;overflow:auto;background:#fff;color:#1d2433;' +
    'border-radius:14px;box-shadow:0 10px 40px #0005;padding:16px 16px 20px;font:14px/1.5 system-ui,sans-serif';
  box.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">' +
    '<b style="font-size:16px">Số dư MU</b><button id="mu-x" style="border:0;background:#eee;border-radius:8px;' +
    'width:30px;height:30px;font-size:18px;cursor:pointer">×</button></div><div id="mu-body">Đang kiểm tra…</div>';
  document.body.appendChild(box);
  box.querySelector('#mu-x').onclick = () => box.remove();
  const body = box.querySelector('#mu-body');
  const say = (html) => { body.innerHTML = html; };

  // ---- tìm token ----
  const tokens = new Set();
  const scan = (store) => {
    try {
      for (let i = 0; i < store.length; i++) {
        const k = store.key(i), v = store.getItem(k);
        if (!v) continue;
        if (/token|auth|jwt|sid|session/i.test(k) && v.length >= 8 && v.length < 4000) tokens.add(v.replace(/^"|"$/g, ''));
        if (v[0] === '{') { try { const o = JSON.parse(v); for (const kk in o) if (/token|auth|jwt/i.test(kk) && typeof o[kk] === 'string' && o[kk].length >= 8) tokens.add(o[kk]); } catch (e) {} }
      }
    } catch (e) {}
  };
  scan(localStorage); scan(sessionStorage);

  const headerNames = ['token', 'Authorization', 'X-Token', 'access-token', 'accessToken', 'authorization'];
  const build = (tok, hn) => { const h = { 'Accept': 'application/json, text/plain, */*' }; h[hn] = hn.toLowerCase() === 'authorization' ? ('Bearer ' + tok) : tok; return h; };
  const ok = (j) => j && (j.success === true || j.code === 0 || j.code === 200 || j.code === '0');

  async function call(path) {
    const url = API + path;
    try { const r = await fetch(url, { credentials: 'include', headers: { 'Accept': 'application/json' } }); const j = await r.json().catch(() => null); if (ok(j)) return { path, via: 'cookie', json: j }; } catch (e) {}
    for (const tok of tokens) for (const hn of headerNames) {
      try { const r = await fetch(url, { headers: build(tok, hn) }); const j = await r.json().catch(() => null); if (ok(j)) return { path, via: hn, json: j }; } catch (e) {}
    }
    return null;
  }

  const balKeys = ['balance', 'availableBalance', 'usableBalance', 'money', 'totalBalance', 'amount', 'credit', 'point'];
  const findBal = (o) => {
    if (!o || typeof o !== 'object') return undefined;
    for (const k in o) if (balKeys.some(b => b.toLowerCase() === k.toLowerCase()) && typeof o[k] !== 'object') return o[k];
    for (const k in o) { const r = findBal(o[k]); if (r !== undefined) return r; }
    return undefined;
  };

  const esc = (s) => String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

  // ---- thực thi ----
  const info = await call('/api/front/user/info');
  if (!info) {
    say('<div style="color:#9b1c1c">Không gọi được API.<br>• Bạn đã <b>đăng nhập</b> trên tab này chưa?<br>• Đang mở đúng trang MU?<br>Thử đăng nhập lại rồi bấm bookmark lần nữa.</div>');
    return;
  }
  const t = info.json.t || info.json;
  let bal = findBal(info.json);
  let from = info.path;
  if (bal === undefined) {
    for (const p of ['/api/front/user/balance', '/api/front/user/getBalance', '/api/front/user/amount', '/api/front/account/balance', '/api/front/fund/balance', '/api/front/user/wallet', '/api/front/user/money']) {
      const r = await call(p); if (r) { const b = findBal(r.json); if (b !== undefined) { bal = b; from = p; break; } }
    }
  }

  let html = '';
  if (bal !== undefined) html += '<div style="font-size:26px;font-weight:800;color:#0a7d33;margin:4px 0 10px">' + esc(bal) + '</div>';
  else html += '<div style="color:#9b1c1c;margin-bottom:8px">Đăng nhập OK nhưng chưa tự tìm thấy số dư. Xem thông tin dưới đây hoặc gửi mình URL API số dư.</div>';
  html += '<div style="font-size:12px;color:#555">Tài khoản: <b>' + esc(t.userName || '') + '</b>' + (t.nickName ? ' — ' + esc(t.nickName) : '') + '</div>';
  html += '<div style="font-size:12px;color:#555">Qua: ' + esc(info.via) + (bal !== undefined ? ' · nguồn ' + esc(from) : '') + '</div>';
  html += '<pre style="margin-top:10px;background:#0f172a;color:#e2e8f0;padding:10px;border-radius:8px;white-space:pre-wrap;word-break:break-all;font-size:11px">' + esc(JSON.stringify(t, null, 2)) + '</pre>';
  say(html);
})();
