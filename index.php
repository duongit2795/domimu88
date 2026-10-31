<?php
/**
 * Quản lý tài khoản MU88 (PHP). Thêm tài khoản -> đăng nhập -> hiện số dư.
 * Mọi file để chung 1 thư mục (hợp hosting có open_basedir).
 */
require __DIR__ . '/lib88.php';

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$num = fn($n) => is_numeric($n) ? number_format((float)$n, (floor($n)==$n?0:2), ',', '.') : $h($n);

/* ---------- Lưu trữ (file PHP trả về mảng, truy cập web trực tiếp sẽ không lộ dữ liệu) ---------- */
$DATA = __DIR__ . '/mu88-data.php';
if (!is_writable(__DIR__) && !is_file($DATA)) $DATA = sys_get_temp_dir() . '/mu88-data.php';
function store_load(string $f): array {
    if (is_file($f)) { $d = @include $f; if (is_array($d)) return $d; }
    return ['proxy' => '', 'bal' => ['url'=>'','method'=>'GET','header'=>'token','prefix'=>''], 'accounts' => []];
}
function store_save(string $f, array $d): bool {
    $php = "<?php\n// Dữ liệu MU88 - KHÔNG chia sẻ file này.\nreturn " . var_export($d, true) . ";\n";
    $tmp = $f . '.tmp' . getmypid();
    return @file_put_contents($tmp, $php) !== false && @rename($tmp, $f);
}

$S = store_load($DATA);
$msg = ''; $err = '';

/* ---------- Xử lý hành động ---------- */
$action = $_POST['action'] ?? '';
if ($action !== '') {
    if ($action === 'save_proxy' || $action === 'check_proxy') {
        $S['proxy'] = trim($_POST['proxy'] ?? '');
        store_save($DATA, $S);
        if ($action === 'check_proxy') {
            $api = new Mu88($S['proxy'] ?: null);
            $proxyCheck = $api->checkProxy();
        } else { $msg = 'Đã lưu proxy.'; }
    } elseif ($action === 'save_settings') {
        $S['proxy'] = trim($_POST['proxy'] ?? '');
        $S['bal'] = [
            'url'    => trim($_POST['bal_url'] ?? ''),
            'method' => ($_POST['bal_method'] ?? 'GET') === 'POST' ? 'POST' : 'GET',
            'header' => trim($_POST['bal_header'] ?? '') ?: 'token',
            'prefix' => $_POST['bal_prefix'] ?? '',
        ];
        $msg = 'Đã lưu cài đặt.';
    } elseif ($action === 'add') {
        $acc = trim($_POST['acc'] ?? ''); $pw = $_POST['pw'] ?? ''; $note = trim($_POST['note'] ?? '');
        if ($acc === '' || $pw === '') $err = 'Nhập tài khoản và mật khẩu.';
        else {
            $S['accounts'] = array_values(array_filter($S['accounts'], fn($a) => $a['account'] !== $acc));
            $S['accounts'][] = ['account' => $acc, 'hash' => md5($pw), 'note' => $note, 'status' => 'idle'];
            $msg = 'Đã thêm ' . $acc . '.';
            $refreshIdx = count($S['accounts']) - 1;
        }
    } elseif ($action === 'delete') {
        $i = (int)($_POST['i'] ?? -1);
        if (isset($S['accounts'][$i])) { $msg = 'Đã xóa ' . $S['accounts'][$i]['account'] . '.'; array_splice($S['accounts'], $i, 1); }
    } elseif ($action === 'refresh') {
        $refreshIdx = (int)($_POST['i'] ?? -1);
    } elseif ($action === 'refresh_all') {
        $refreshAll = true;
    }

    // Thực hiện đăng nhập + lấy số dư
    $todo = [];
    if (isset($refreshIdx) && isset($S['accounts'][$refreshIdx])) $todo = [$refreshIdx];
    elseif (!empty($refreshAll)) $todo = array_keys($S['accounts']);

    foreach ($todo as $i) {
        $a = &$S['accounts'][$i];
        $api = new Mu88($S['proxy'] ?: null);
        try {
            $t = $api->login($a['account'], $a['hash']);
            $a['fullName'] = $t['fullName'] ?? ($t['userName'] ?? '');
            $a['nick']     = $t['nickName'] ?? '';
            $a['userId']   = $t['userId'] ?? ($t['uid'] ?? '');
            $token = $t['token'] ?? '';
            $bal = Mu88::findBal($t);
            $raw = $t;
            if ($bal === null) { $r = $api->balance($token, $S['bal']); $bal = $r['bal']; if ($r['raw']) $raw = $r['raw']; }
            $a['bal']    = $bal;
            $a['raw']    = $raw;
            $a['status'] = $bal !== null ? 'ok' : 'nobal';
            $a['err']    = '';
            $a['time']   = time();
        } catch (Throwable $e) {
            $a['status'] = 'err'; $a['err'] = $e->getMessage(); $a['time'] = time();
        }
        unset($a);
    }
    store_save($DATA, $S);
    if ($err === '' && $action !== 'check_proxy') { // PRG; giữ trang khi có lỗi nhập liệu hoặc đang xem kết quả kiểm tra proxy
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?done=' . ($action === 'add' ? 'add' : 'ok'));
        exit;
    }
}
if (isset($_GET['done'])) $msg = $_GET['done'] === 'add' ? 'Đã thêm và thử đăng nhập.' : 'Đã cập nhật.';
$writable = is_writable(dirname($DATA));
?><!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản lý tài khoản MU88</title>
<style>
  :root{--bg:#0f1420;--card:#1a2233;--line:#2a3550;--txt:#e6ecf5;--mut:#8aa0c0;--pri:#2f6bff;--ok:#19b562;--bad:#ff5a5a;--warn:#f0a500}
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--txt);font:15px/1.5 system-ui,Segoe UI,Roboto,sans-serif;padding:14px}
  .wrap{max-width:900px;margin:0 auto} h1{font-size:20px;margin:0 0 2px} .sub{color:var(--mut);font-size:13px;margin-bottom:14px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:14px}
  label{display:block;font-size:12px;color:var(--mut);margin:8px 0 4px}
  input,select{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:#0d131f;color:var(--txt);font-size:15px}
  .row{display:flex;gap:10px;flex-wrap:wrap}.row>div{flex:1;min-width:140px}
  button{border:0;border-radius:8px;padding:10px 14px;font-size:14px;font-weight:600;cursor:pointer;color:#fff;background:var(--pri)}
  button.sec{background:#33405c}button.danger{background:#3a2330;color:#ff9a9a}button.sm{padding:6px 10px;font-size:13px}
  .bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}
  table{width:100%;border-collapse:collapse;margin-top:6px;font-size:14px}
  th,td{text-align:left;padding:10px 8px;border-bottom:1px solid var(--line);vertical-align:middle}
  th{color:var(--mut);font-weight:600;font-size:12px;text-transform:uppercase}
  .bal{font-weight:700;font-size:16px;text-align:right}.right{text-align:right}.nowrap{white-space:nowrap}
  .tag{font-size:11px;padding:2px 8px;border-radius:999px;display:inline-block}
  .t-ok{background:#11351f;color:#49d17e}.t-bad{background:#3a1f1f;color:#ff8a8a}.t-wait{background:#33300f;color:#f0c550}.t-idle{background:#222b3f;color:#8aa0c0}
  .muted{color:var(--mut);font-size:12px}.flash{padding:10px 12px;border-radius:8px;margin-bottom:12px}
  .flash.ok{background:#11351f;color:#8ff0b5}.flash.err{background:#3a1f1f;color:#ff9a9a}
  details summary{cursor:pointer;color:var(--mut);font-size:13px}
  pre{background:#0d131f;border:1px solid var(--line);border-radius:8px;padding:10px;overflow:auto;font-size:12px;max-height:280px;white-space:pre-wrap;word-break:break-all}
  a{color:#7aa2ff}
</style></head><body>
<div class="wrap">
  <h1>Quản lý tài khoản MU88</h1>
  <div class="sub">Thêm tài khoản → tự đăng nhập <code>mu.sng22.vip</code> (mật khẩu MD5) → hiện số dư. Dữ liệu lưu trên máy chủ.</div>

  <?php if ($msg): ?><div class="flash ok"><?= $h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash err"><?= $h($err) ?></div><?php endif; ?>
  <?php if (!$writable): ?><div class="flash err">Thư mục không ghi được — dữ liệu không lưu được. Cấp quyền ghi cho thư mục, hoặc dữ liệu sẽ nằm tạm ở <?= $h(dirname($DATA)) ?>.</div><?php endif; ?>

  <div class="card">
    <b>Proxy đăng nhập</b>
    <form method="post">
      <label>Proxy Việt Nam — để trống = dùng IP máy chủ. Dạng <code>host:port:user:pass</code> hoặc <code>http://user:pass@host:port</code></label>
      <input name="proxy" value="<?= $h($S['proxy']) ?>" placeholder="vd: 1.2.3.4:8000:user:pass">
      <div class="bar">
        <button type="submit" name="action" value="check_proxy" class="sec">Kiểm tra proxy</button>
        <button type="submit" name="action" value="save_proxy">Lưu proxy</button>
        <span class="muted"><?= $S['proxy'] ? 'Đang dùng proxy' : 'Đang dùng IP máy chủ' ?></span>
      </div>
    </form>
    <?php if (!empty($proxyCheck)): $pc = $proxyCheck; ?>
      <?php if (!empty($pc['error'])): ?>
        <div class="flash err" style="margin-top:12px"><?= $h($pc['error']) ?></div>
      <?php else: ?>
        <div class="flash <?= !empty($pc['ok']) ? 'ok' : 'err' ?>" style="margin-top:12px">
          <b><?= $h($pc['verdict'] ?? '') ?></b><br>
          IP đi ra: <b><?= $h($pc['ip'] ?? '?') ?></b> · Quốc gia: <b><?= $h($pc['country'] ?? '?') ?></b>
          <?php if (isset($pc['site_http'])): ?> · mu.sng22.vip: HTTP <?= $h($pc['site_http']) ?> <?= !empty($pc['blocked']) ? '(BỊ CHẶN)' : '(OK)' ?><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <b>Thêm tài khoản</b>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <div class="row">
        <div><label>Tài khoản</label><input name="acc" autocomplete="off" required></div>
        <div><label>Mật khẩu</label><input name="pw" type="password" autocomplete="new-password" required></div>
        <div><label>Ghi chú</label><input name="note" autocomplete="off"></div>
      </div>
      <div class="bar">
        <button type="submit">+ Thêm &amp; đăng nhập</button>
        <button type="submit" form="fall" class="sec">↻ Làm mới tất cả</button>
        <span class="muted"><?= count($S['accounts']) ?> tài khoản</span>
      </div>
    </form>
    <form id="fall" method="post"><input type="hidden" name="action" value="refresh_all"></form>
  </div>

  <div class="card">
    <table>
      <thead><tr><th>Tài khoản</th><th>Tên / Nick</th><th class="right">Số dư</th><th>Trạng thái</th><th class="right">Thao tác</th></tr></thead>
      <tbody>
      <?php if (!$S['accounts']): ?>
        <tr><td colspan="5" class="muted" style="padding:14px 4px">Chưa có tài khoản nào.</td></tr>
      <?php else: foreach ($S['accounts'] as $i => $a):
        $tag = ['ok'=>['t-ok','OK'],'err'=>['t-bad','Lỗi'],'nobal'=>['t-wait','Chưa có API số dư'],'idle'=>['t-idle','Chưa chạy']][$a['status'] ?? 'idle']; ?>
        <tr>
          <td><b><?= $h($a['account']) ?></b><?php if(!empty($a['note'])): ?><div class="muted"><?= $h($a['note']) ?></div><?php endif; ?></td>
          <td><?= $h($a['fullName'] ?? '') ?><?php if(!empty($a['nick'])): ?><div class="muted"><?= $h($a['nick']) ?></div><?php endif; ?></td>
          <td class="bal"><?= isset($a['bal']) && $a['bal']!==null ? $num($a['bal']) : '—' ?></td>
          <td><span class="tag <?= $tag[0] ?>"><?= $tag[1] ?></span>
            <?php if(!empty($a['time'])): ?><div class="muted"><?= date('H:i:s', $a['time']) ?></div><?php endif; ?>
            <?php if(($a['status']??'')==='err' && !empty($a['err'])): ?><div class="muted" style="color:#ff8a8a"><?= $h($a['err']) ?></div><?php endif; ?>
          </td>
          <td class="right nowrap">
            <form method="post" style="display:inline"><input type="hidden" name="action" value="refresh"><input type="hidden" name="i" value="<?= $i ?>"><button class="sm sec" title="Làm mới">↻</button></form>
            <form method="post" style="display:inline" onsubmit="return confirm('Xóa <?= $h($a['account']) ?>?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="i" value="<?= $i ?>"><button class="sm danger">Xóa</button></form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <details <?= $S['proxy'] || $S['bal']['url'] ? 'open' : '' ?>>
      <summary>Cài đặt (proxy + API số dư)</summary>
      <form method="post">
        <input type="hidden" name="action" value="save_settings">
        <label>URL API số dư (bạn gửi sau; để trống = thử tự dò)</label>
        <input name="bal_url" value="<?= $h($S['bal']['url']) ?>" placeholder="vd: https://mu.sng22.vip/api/front/user/balance">
        <div class="row">
          <div><label>Phương thức</label><select name="bal_method"><option <?= $S['bal']['method']==='GET'?'selected':'' ?>>GET</option><option <?= $S['bal']['method']==='POST'?'selected':'' ?>>POST</option></select></div>
          <div><label>Header token</label><input name="bal_header" value="<?= $h($S['bal']['header']) ?>" placeholder="token"></div>
          <div><label>Tiền tố token</label><input name="bal_prefix" value="<?= $h($S['bal']['prefix']) ?>" placeholder="(để trống)"></div>
        </div>
        <div class="bar"><button class="sec sm" type="submit">Lưu cài đặt</button></div>
      </form>
    </details>
  </div>

  <div class="muted" style="font-size:12px">
    Chỉ lưu <b>mã băm MD5</b> của mật khẩu trên máy chủ. Nếu báo <b>“Bị chặn khu vực”</b>: hosting không vào được mu.sng22.vip, cần proxy dân cư/4G Việt Nam ở mục Cài đặt.
  </div>
</div>
</body></html>
