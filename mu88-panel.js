/* ============================================================
 *  mu88-panel.js — Bảng quản lý tài khoản MU88, chạy TRONG tab mu.sng22.vip.
 *  Vì chạy cùng nguồn trong trình duyệt của bạn nên không dính CORS
 *  và đi qua Cloudflare bình thường (dùng chính phiên của bạn).
 *
 *  CÁCH DÙNG:
 *   1. Mở tab https://mu.sng22.vip (đã vào được web).
 *   2. F12 -> Console. Nếu hỏi, gõ: allow pasting  rồi Enter.
 *   3. Dán toàn bộ file này, Enter. Một bảng nổi hiện ra.
 *   4. Thêm tài khoản (tk/mk). Nó tự đăng nhập và hiện số dư.
 *
 *  Dữ liệu (mã băm MD5 mật khẩu + kết quả) lưu trong localStorage
 *  của trang, chỉ ở máy bạn.
 * ============================================================ */
(function () {
  if (window.__mu88Panel) { window.__mu88Panel.style.display = 'block'; return; }

  /* ---- MD5 (chuẩn) ---- */
  function md5(s){function rl(n,c){return(n<<c)|(n>>>(32-c))}function au(x,y){var l=(x&0xFFFF)+(y&0xFFFF),m=(x>>16)+(y>>16)+(l>>16);return(m<<16)|(l&0xFFFF)}
  function cmn(q,a,b,x,s,t){return au(rl(au(au(a,q),au(x,t)),s),b)}function ff(a,b,c,d,x,s,t){return cmn((b&c)|(~b&d),a,b,x,s,t)}
  function gg(a,b,c,d,x,s,t){return cmn((b&d)|(c&~d),a,b,x,s,t)}function hh(a,b,c,d,x,s,t){return cmn(b^c^d,a,b,x,s,t)}
  function ii(a,b,c,d,x,s,t){return cmn(c^(b|~d),a,b,x,s,t)}
  function tb(str){var n=str.length,bl=[];for(var i=0;i<(n<<3);i+=8)bl[i>>5]=(bl[i>>5]||0)|((str.charCodeAt(i/8)&0xFF)<<(i%32));bl[n>>2]=(bl[n>>2]||0)|(0x80<<((n%4)*8));bl[(((n+8)>>6)*16)+14]=n*8;return bl}
  var x=tb(unescape(encodeURIComponent(s))),a=1732584193,b=-271733879,c=-1732584194,d=271733878;
  for(var i=0;i<x.length;i+=16){var oa=a,ob=b,oc=c,od=d;for(var j=i;j<i+16;j++)x[j]=x[j]||0;
  a=ff(a,b,c,d,x[i],7,-680876936);d=ff(d,a,b,c,x[i+1],12,-389564586);c=ff(c,d,a,b,x[i+2],17,606105819);b=ff(b,c,d,a,x[i+3],22,-1044525330);
  a=ff(a,b,c,d,x[i+4],7,-176418897);d=ff(d,a,b,c,x[i+5],12,1200080426);c=ff(c,d,a,b,x[i+6],17,-1473231341);b=ff(b,c,d,a,x[i+7],22,-45705983);
  a=ff(a,b,c,d,x[i+8],7,1770035416);d=ff(d,a,b,c,x[i+9],12,-1958414417);c=ff(c,d,a,b,x[i+10],17,-42063);b=ff(b,c,d,a,x[i+11],22,-1990404162);
  a=ff(a,b,c,d,x[i+12],7,1804603682);d=ff(d,a,b,c,x[i+13],12,-40341101);c=ff(c,d,a,b,x[i+14],17,-1502002290);b=ff(b,c,d,a,x[i+15],22,1236535329);
  a=gg(a,b,c,d,x[i+1],5,-165796510);d=gg(d,a,b,c,x[i+6],9,-1069501632);c=gg(c,d,a,b,x[i+11],14,643717713);b=gg(b,c,d,a,x[i],20,-373897302);
  a=gg(a,b,c,d,x[i+5],5,-701558691);d=gg(d,a,b,c,x[i+10],9,38016083);c=gg(c,d,a,b,x[i+15],14,-660478335);b=gg(b,c,d,a,x[i+4],20,-405537848);
  a=gg(a,b,c,d,x[i+9],5,568446438);d=gg(d,a,b,c,x[i+14],9,-1019803690);c=gg(c,d,a,b,x[i+3],14,-187363961);b=gg(b,c,d,a,x[i+8],20,1163531501);
  a=gg(a,b,c,d,x[i+13],5,-1444681467);d=gg(d,a,b,c,x[i+2],9,-51403784);c=gg(c,d,a,b,x[i+7],14,1735328473);b=gg(b,c,d,a,x[i+12],20,-1926607734);
  a=hh(a,b,c,d,x[i+5],4,-378558);d=hh(d,a,b,c,x[i+8],11,-2022574463);c=hh(c,d,a,b,x[i+11],16,1839030562);b=hh(b,c,d,a,x[i+14],23,-35309556);
  a=hh(a,b,c,d,x[i+1],4,-1530992060);d=hh(d,a,b,c,x[i+4],11,1272893353);c=hh(c,d,a,b,x[i+7],16,-155497632);b=hh(b,c,d,a,x[i+10],23,-1094730640);
  a=hh(a,b,c,d,x[i+13],4,681279174);d=hh(d,a,b,c,x[i],11,-358537222);c=hh(c,d,a,b,x[i+3],16,-722521979);b=hh(b,c,d,a,x[i+6],23,76029189);
  a=hh(a,b,c,d,x[i+9],4,-640364487);d=hh(d,a,b,c,x[i+12],11,-421815835);c=hh(c,d,a,b,x[i+15],16,530742520);b=hh(b,c,d,a,x[i+2],23,-995338651);
  a=ii(a,b,c,d,x[i],6,-198630844);d=ii(d,a,b,c,x[i+7],10,1126891415);c=ii(c,d,a,b,x[i+14],15,-1416354905);b=ii(b,c,d,a,x[i+5],21,-57434055);
  a=ii(a,b,c,d,x[i+12],6,1700485571);d=ii(d,a,b,c,x[i+3],10,-1894986606);c=ii(c,d,a,b,x[i+10],15,-1051523);b=ii(b,c,d,a,x[i+1],21,-2054922799);
  a=ii(a,b,c,d,x[i+8],6,1873313359);d=ii(d,a,b,c,x[i+15],10,-30611744);c=ii(c,d,a,b,x[i+6],15,-1560198380);b=ii(b,c,d,a,x[i+13],21,1309151649);
  a=ii(a,b,c,d,x[i+4],6,-145523070);d=ii(d,a,b,c,x[i+11],10,-1120210379);c=ii(c,d,a,b,x[i+2],15,718787259);b=ii(b,c,d,a,x[i+9],21,-343485551);
  a=au(a,oa);b=au(b,ob);c=au(c,oc);d=au(d,od);}
  function hex(n){var s='',j;for(j=0;j<4;j++)s+=('0'+((n>>(j*8))&0xFF).toString(16)).slice(-2);return s}
  return hex(a)+hex(b)+hex(c)+hex(d);}

  /* ---- Lưu trữ ---- */
  var LS = 'mu88_panel';
  var state = load();
  function load(){ try{ return JSON.parse(localStorage.getItem(LS)) || {accounts:[], bal:{url:'',method:'GET',header:'token',prefix:''}} }catch(e){ return {accounts:[], bal:{url:'',method:'GET',header:'token',prefix:''}} } }
  function save(){ try{ localStorage.setItem(LS, JSON.stringify(state)) }catch(e){} }

  /* ---- Gọi API (cùng nguồn) ---- */
  var ORIGIN = location.origin; // https://mu.sng22.vip
  function okJson(j){ return j && (j.success===true || j.code===0); }
  async function login(acc){
    var r = await fetch(ORIGIN + '/api/front/wl/index/login', {
      method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json, text/plain, */*'},
      body: JSON.stringify({account: acc.account, password: acc.hash, loginSrc: 0})
    });
    var j = await r.json();
    if(!okJson(j)) throw new Error((j&&j.msg) ? j.msg : ('HTTP '+r.status));
    return j.t || j;
  }
  var BK=['balance','availableBalance','usableBalance','money','totalBalance','amount','credit','point','gold'];
  function findBal(o){ if(!o||typeof o!='object')return undefined;
    for(var k in o) if(BK.some(function(b){return b.toLowerCase()===k.toLowerCase()}) && typeof o[k]!='object') return o[k];
    for(var k in o){ var r=findBal(o[k]); if(r!==undefined) return r; } }
  async function getBal(token){
    var cfg = state.bal||{}; var H={'Accept':'application/json, text/plain, */*'};
    if(token) H[cfg.header||'token'] = (cfg.prefix||'') + token;
    var urls = cfg.url ? [cfg.url] : [
      ORIGIN+'/api/front/user/balance', ORIGIN+'/api/front/user/getBalance',
      ORIGIN+'/api/front/user/amount', ORIGIN+'/api/front/user/info', ORIGIN+'/api/front/game/balance'];
    for(var i=0;i<urls.length;i++){
      for(var m=0;m<2;m++){ var method = m? (cfg.method==='GET'?'POST':'GET') : (cfg.method||'GET');
        try{ var r = await fetch(urls[i], {method:method, headers:H, body: method==='POST'?'{}':undefined});
          var j = await r.json().catch(function(){return null});
          if(okJson(j)){ var b=findBal(j); if(b!==undefined) return {bal:b, raw:j}; if(cfg.url) return {bal:undefined, raw:j}; } }catch(e){}
      }
    }
    return {bal:undefined, raw:null};
  }
  async function refresh(i){
    var a = state.accounts[i]; a.status='wait'; render();
    try{
      var t = await login(a);
      a.fullName = t.fullName || t.userName || ''; a.nick = t.nickName || ''; a.userId = t.userId || t.uid || '';
      var token = t.token || ''; var r = {bal: findBal(t), raw: t};
      if(r.bal===undefined) r = await getBal(token);
      a.bal = r.bal; a.raw = r.raw; a.status = (r.bal!==undefined)?'ok':'nobal'; a.err=''; a.time=Date.now();
    }catch(e){ a.status='err'; a.err=e.message; a.time=Date.now(); }
    save(); render();
  }
  async function refreshAll(){ for(var i=0;i<state.accounts.length;i++){ await refresh(i); } }

  /* ---- Giao diện ---- */
  var P = document.createElement('div'); window.__mu88Panel = P;
  P.style.cssText='position:fixed;right:16px;top:16px;z-index:2147483647;width:380px;max-width:94vw;max-height:88vh;overflow:auto;background:#141b2b;color:#e6ecf5;border:1px solid #2a3550;border-radius:12px;box-shadow:0 12px 40px #0008;font:14px/1.5 system-ui,sans-serif';
  document.body.appendChild(P);
  var esc=function(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})};
  var numf=function(n){return (typeof n==='number'||(n&&!isNaN(n)))?Number(n).toLocaleString('vi-VN'):esc(n)};

  function render(){
    var rows = state.accounts.map(function(a,i){
      var tag={ok:['#11351f','#49d17e','OK'],err:['#3a1f1f','#ff8a8a','Lỗi'],wait:['#33300f','#f0c550','...'],nobal:['#33300f','#f0c550','Chưa có API số dư'],idle:['#222b3f','#8aa0c0','Chưa chạy']}[a.status||'idle'];
      return '<tr>'+
        '<td style="padding:8px 6px;border-top:1px solid #243049"><b>'+esc(a.account)+'</b>'+(a.note?'<div style="color:#8aa0c0;font-size:12px">'+esc(a.note)+'</div>':'')+(a.fullName?'<div style="color:#8aa0c0;font-size:12px">'+esc(a.fullName)+(a.nick?' · '+esc(a.nick):'')+'</div>':'')+'</td>'+
        '<td style="padding:8px 6px;border-top:1px solid #243049;text-align:right;font-weight:700;font-size:15px">'+(a.bal!=null?numf(a.bal):'—')+'</td>'+
        '<td style="padding:8px 6px;border-top:1px solid #243049;text-align:right;white-space:nowrap">'+
          '<span style="font-size:11px;padding:2px 7px;border-radius:99px;background:'+tag[0]+';color:'+tag[1]+'">'+tag[2]+'</span>'+
          (a.status==='err'&&a.err?'<div style="color:#ff8a8a;font-size:11px;max-width:120px">'+esc(a.err)+'</div>':'')+
          '<div style="margin-top:4px"><button data-r="'+i+'" style="cursor:pointer;border:0;border-radius:6px;background:#33405c;color:#fff;padding:3px 8px;margin:1px">↻</button>'+
          '<button data-d="'+i+'" style="cursor:pointer;border:0;border-radius:6px;background:#3a2330;color:#ff9a9a;padding:3px 8px;margin:1px">✕</button></div>'+
        '</td></tr>';
    }).join('');
    P.innerHTML =
      '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #2a3550">'+
        '<b>Quản lý tài khoản MU88</b>'+
        '<button id="mu-x" style="cursor:pointer;border:0;background:#222b3f;color:#e6ecf5;border-radius:8px;width:28px;height:28px">×</button></div>'+
      '<div style="padding:12px 14px">'+
        '<div style="display:flex;gap:6px;flex-wrap:wrap">'+
          '<input id="mu-acc" placeholder="Tài khoản" style="flex:1;min-width:110px;padding:8px;border:1px solid #2a3550;border-radius:8px;background:#0d131f;color:#e6ecf5">'+
          '<input id="mu-pw" type="password" placeholder="Mật khẩu" style="flex:1;min-width:110px;padding:8px;border:1px solid #2a3550;border-radius:8px;background:#0d131f;color:#e6ecf5"></div>'+
        '<input id="mu-note" placeholder="Ghi chú (tùy chọn)" style="width:100%;margin-top:6px;padding:8px;border:1px solid #2a3550;border-radius:8px;background:#0d131f;color:#e6ecf5;box-sizing:border-box">'+
        '<div style="display:flex;gap:8px;margin-top:8px">'+
          '<button id="mu-add" style="flex:1;cursor:pointer;border:0;border-radius:8px;background:#2f6bff;color:#fff;padding:9px;font-weight:600">+ Thêm &amp; đăng nhập</button>'+
          '<button id="mu-all" style="cursor:pointer;border:0;border-radius:8px;background:#33405c;color:#fff;padding:9px 12px">↻ Tất cả</button></div>'+
        (state.accounts.length? '<table style="width:100%;border-collapse:collapse;margin-top:10px"><tr style="color:#8aa0c0;font-size:11px;text-transform:uppercase"><td style="padding:4px 6px">Tài khoản</td><td style="padding:4px 6px;text-align:right">Số dư</td><td style="padding:4px 6px;text-align:right">TT</td></tr>'+rows+'</table>'
          : '<div style="color:#8aa0c0;font-size:13px;margin-top:12px">Chưa có tài khoản. Thêm ở trên.</div>')+
        '<details style="margin-top:12px"><summary style="color:#8aa0c0;font-size:12px;cursor:pointer">Cài đặt API số dư</summary>'+
          '<input id="mu-burl" placeholder="URL số dư (vd /api/front/user/balance)" value="'+esc(state.bal.url||'')+'" style="width:100%;margin-top:6px;padding:7px;border:1px solid #2a3550;border-radius:8px;background:#0d131f;color:#e6ecf5;box-sizing:border-box">'+
          '<div style="display:flex;gap:6px;margin-top:6px">'+
            '<select id="mu-bm" style="flex:1;padding:7px;border:1px solid #2a3550;border-radius:8px;background:#0d131f;color:#e6ecf5"><option'+(state.bal.method==='GET'?' selected':'')+'>GET</option><option'+(state.bal.method==='POST'?' selected':'')+'>POST</option></select>'+
            '<input id="mu-bh" placeholder="header token" value="'+esc(state.bal.header||'token')+'" style="flex:1;padding:7px;border:1px solid #2a3550;border-radius:8px;background:#0d131f;color:#e6ecf5">'+
            '<input id="mu-bp" placeholder="tiền tố" value="'+esc(state.bal.prefix||'')+'" style="width:70px;padding:7px;border:1px solid #2a3550;border-radius:8px;background:#0d131f;color:#e6ecf5"></div>'+
          '<button id="mu-bs" style="margin-top:6px;cursor:pointer;border:0;border-radius:8px;background:#33405c;color:#fff;padding:7px 10px;font-size:13px">Lưu</button>'+
        '</details>'+
        '<div style="color:#8aa0c0;font-size:11px;margin-top:10px">Chỉ lưu mã băm MD5 mật khẩu, tại máy bạn.</div>'+
      '</div>';
    P.querySelector('#mu-x').onclick=function(){ P.style.display='none'; };
    P.querySelector('#mu-add').onclick=function(){
      var acc=P.querySelector('#mu-acc').value.trim(), pw=P.querySelector('#mu-pw').value, note=P.querySelector('#mu-note').value.trim();
      if(!acc||!pw){ alert('Nhập tài khoản và mật khẩu'); return; }
      state.accounts = state.accounts.filter(function(a){return a.account!==acc});
      state.accounts.push({account:acc, hash:md5(pw), note:note, status:'idle'}); save();
      render(); refresh(state.accounts.length-1);
    };
    P.querySelector('#mu-all').onclick=refreshAll;
    P.querySelector('#mu-bs').onclick=function(){
      state.bal={url:P.querySelector('#mu-burl').value.trim(), method:P.querySelector('#mu-bm').value, header:P.querySelector('#mu-bh').value.trim()||'token', prefix:P.querySelector('#mu-bp').value}; save(); alert('Đã lưu cài đặt số dư');
    };
    P.querySelectorAll('[data-r]').forEach(function(b){ b.onclick=function(){ refresh(+b.getAttribute('data-r')); }; });
    P.querySelectorAll('[data-d]').forEach(function(b){ b.onclick=function(){ var i=+b.getAttribute('data-d'); if(confirm('Xóa '+state.accounts[i].account+'?')){ state.accounts.splice(i,1); save(); render(); } }; });
  }
  render();
  console.log('%cĐã mở bảng quản lý MU88 (góc trên phải).','color:#49d17e;font-size:14px');
})();
