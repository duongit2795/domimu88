/* ============================================================
 *  Lấy số dư MU ngay trong trình duyệt của bạn.
 *  Chạy trên IP của chính bạn (Việt Nam) nên KHÔNG bị chặn khu vực,
 *  không cần proxy, không cần hosting.
 *
 *  CÁCH DÙNG:
 *   1. Mở trang và ĐĂNG NHẬP như bình thường (mu.sng22.vip / mu.baokm188.com).
 *   2. Mở Console:
 *        - Máy tính Chrome: F12 -> tab Console
 *        - iPhone: khó mở console; xem phần "Bookmarklet" ở cuối file.
 *   3. Dán TOÀN BỘ đoạn dưới đây (từ (async... đến cuối) vào Console, Enter.
 *   4. Kết quả số dư + thông tin in ra ngay trong Console.
 * ============================================================ */

(async () => {
  const API = 'https://djmuc.vinamilk3.net';

  // 1) Gom mọi token khả nghi trong localStorage / sessionStorage / cookie.
  const tokens = new Set();
  const scan = (store) => {
    try {
      for (let i = 0; i < store.length; i++) {
        const k = store.key(i), v = store.getItem(k);
        if (!v) continue;
        if (/token|auth|jwt|sid|session/i.test(k) && v.length >= 8 && v.length < 4000) tokens.add(v.replace(/^"|"$/g, ''));
        // đôi khi token nằm trong 1 object JSON
        if (v[0] === '{') {
          try {
            const o = JSON.parse(v);
            for (const kk in o) if (/token|auth|jwt/i.test(kk) && typeof o[kk] === 'string' && o[kk].length >= 8) tokens.add(o[kk]);
          } catch (e) {}
        }
      }
    } catch (e) {}
  };
  scan(localStorage); scan(sessionStorage);
  console.log('%cTìm thấy ' + tokens.size + ' token khả nghi trong bộ nhớ trình duyệt.', 'color:#2463eb');

  // 2) Các cách gửi token để thử.
  const headerNames = ['token', 'Authorization', 'X-Token', 'access-token', 'accessToken', 'authorization'];
  const build = (tok, hname) => {
    const h = { 'Accept': 'application/json, text/plain, */*' };
    h[hname] = hname.toLowerCase() === 'authorization' ? ('Bearer ' + tok) : tok;
    return h;
  };

  const ok = (j) => j && (j.success === true || j.code === 0 || j.code === 200 || j.code === '0');

  // 3) Gọi 1 endpoint với mọi cách auth cho tới khi thành công.
  async function call(path) {
    const url = API + path;
    // 3a) dùng cookie phiên (nếu API xác thực bằng cookie)
    try {
      const r = await fetch(url, { credentials: 'include', headers: { 'Accept': 'application/json' } });
      const j = await r.json().catch(() => null);
      if (ok(j)) return { path, via: 'cookie', json: j };
    } catch (e) {}
    // 3b) thử từng token với từng tên header
    for (const tok of tokens) {
      for (const hn of headerNames) {
        try {
          const r = await fetch(url, { headers: build(tok, hn) });
          const j = await r.json().catch(() => null);
          if (ok(j)) return { path, via: hn + '=' + tok.slice(0, 10) + '…', json: j };
        } catch (e) {}
      }
    }
    return null;
  }

  // 4) Lấy thông tin tài khoản (endpoint đã biết chắc).
  const info = await call('/api/front/user/info');
  if (!info) {
    console.error('Không gọi được /api/front/user/info. Bạn đã đăng nhập trên tab này chưa? ' +
      'Nếu rồi mà vẫn lỗi, chụp lại Console gửi cho mình.');
    return;
  }
  console.log('%c✓ Đăng nhập OK qua: ' + info.via, 'color:green;font-weight:bold');
  console.log('Thông tin tài khoản:', info.json.t || info.json);

  // 5) Tìm số dư: trong info trước, rồi thử các endpoint số dư.
  const balKeys = ['balance', 'availableBalance', 'usableBalance', 'money', 'totalBalance', 'amount', 'credit', 'point'];
  const findBal = (o) => {
    if (!o || typeof o !== 'object') return undefined;
    for (const k in o) if (balKeys.some(b => b.toLowerCase() === k.toLowerCase()) && typeof o[k] !== 'object') return o[k];
    for (const k in o) { const r = findBal(o[k]); if (r !== undefined) return r; }
    return undefined;
  };

  let bal = findBal(info.json);
  let balResp = bal !== undefined ? info : null;

  if (bal === undefined) {
    const balPaths = ['/api/front/user/balance', '/api/front/user/getBalance', '/api/front/user/amount',
      '/api/front/account/balance', '/api/front/fund/balance', '/api/front/user/wallet', '/api/front/user/money'];
    for (const p of balPaths) {
      const r = await call(p);
      if (r) { const b = findBal(r.json); if (b !== undefined) { bal = b; balResp = r; break; } }
    }
  }

  if (bal !== undefined) {
    console.log('%c>>> SỐ DƯ: ' + bal + '  (từ ' + balResp.path + ')', 'color:#0a7d33;font-size:18px;font-weight:bold');
  } else {
    console.warn('Không tự tìm thấy số dư. Mở tab Network, bấm vào phần hiển thị số dư trên web, ' +
      'tìm request trả về số tiền rồi gửi URL đó cho mình để bổ sung.');
  }
  return bal;
})();

/* ============================================================
 *  BOOKMARKLET (dùng trên điện thoại, khó mở Console):
 *  Tạo 1 bookmark mới, phần địa chỉ (URL) dán nguyên đoạn:
 *
 *  javascript:(async()=>{const s=document.createElement('script');s.src='https://mu.baokm188.com/balance-popup.js?'+Date.now();document.body.appendChild(s);})();
 *
 *  Rồi mở trang (đã đăng nhập) và bấm bookmark đó.
 *  (Cần file balance-popup.js — báo mình nếu bạn muốn dùng cách này,
 *   mình sẽ làm bản hiện kết quả ra popup thay vì Console.)
 * ============================================================ */
