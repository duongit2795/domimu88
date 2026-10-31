<?php
/**
 * Kiểm tra nhanh 1 proxy: có vào được API MU không (trước khi dùng để lấy số dư).
 * Dán proxy -> báo IP, quốc gia, và thử tải trang API xem có bị chặn 403 không.
 */
require __DIR__ . '/../lib.php';

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$proxy = trim($_POST['proxy'] ?? (getenv('MU_PROXY') ?: ''));
$api   = trim($_POST['api'] ?? '') ?: 'https://djmuc.vinamilk3.net';
$r = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $proxy !== '') {
    $c = new MuClient($proxy, 'https://mu.sng22.vip', $api);
    $r = ['steps' => []];
    try {
        $chk = $c->checkProxy();
        $r['ip'] = $chk['ip'];
        $r['country'] = $chk['country'];
        $r['proxy'] = $chk['proxy'];
        // Thử gọi đúng API thật (endpoint info, chưa đăng nhập -> vẫn biết có bị chặn không).
        [$code, $body] = $c->request('GET', $c->api . '/api/front/user/info', null, ['Accept: application/json']);
        $blocked = str_contains($body, 'Truy cập bị cấm') || str_contains($body, 'Access Denied');
        $isJson  = json_decode($body, true) !== null;
        $r['api_http'] = $code;
        $r['blocked'] = $blocked;
        $r['looks_api'] = $isJson;
        $r['sample'] = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 0, 160);
        if ($blocked) {
            $r['verdict'] = 'bad';
            $r['msg'] = $chk['country'] === 'VN'
                ? 'IP là Việt Nam NHƯNG vẫn bị chặn (IP datacenter trong danh sách đen). Cần proxy DÂN CƯ hoặc 4G Việt Nam.'
                : 'Proxy ra IP ' . $chk['country'] . ', không phải Việt Nam. Đổi proxy khác.';
        } elseif ($isJson) {
            $r['verdict'] = 'good';
            $r['msg'] = 'Proxy VÀO ĐƯỢC API! Dùng proxy này ở trang chính để lấy số dư.';
        } else {
            $r['verdict'] = 'maybe';
            $r['msg'] = 'Không bị chặn rõ ràng nhưng phản hồi lạ (HTTP ' . $code . '). Xem mẫu bên dưới.';
        }
    } catch (Throwable $e) {
        $r['verdict'] = 'bad';
        $r['msg'] = 'Không kết nối được proxy: ' . $e->getMessage();
    }
    $r['log'] = $c->log;
}
?><!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kiểm tra proxy</title>
<style>
  body{font-family:system-ui,sans-serif;background:#f3f5f9;margin:0;padding:16px;color:#1d2433}
  .box{max-width:620px;margin:0 auto;background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px #0001}
  h1{font-size:19px;margin:0 0 14px}
  label{display:block;font-size:13px;margin:10px 0 4px;color:#555}
  input{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccd;border-radius:8px;font-size:15px}
  button{margin-top:16px;width:100%;padding:12px;border:0;border-radius:8px;background:#2463eb;color:#fff;font-size:16px}
  .good{background:#e6f6ec;color:#0a5c2a} .bad{background:#fde8e8;color:#9b1c1c} .maybe{background:#fff7e6;color:#8a5a00}
  .v{padding:12px;border-radius:8px;margin-top:16px;font-weight:600;word-break:break-all}
  table{width:100%;border-collapse:collapse;margin-top:12px;font-size:14px}
  td{padding:6px 8px;border-bottom:1px solid #eee} td:first-child{color:#777;width:40%}
  pre{background:#0f172a;color:#e2e8f0;padding:10px;border-radius:8px;white-space:pre-wrap;word-break:break-all;font-size:12px;margin-top:12px}
  a{color:#2463eb}
</style></head><body>
<div class="box">
  <h1>Kiểm tra proxy Việt Nam</h1>
  <form method="post">
    <label>Proxy (host:port:user:pass hoặc http://user:pass@host:port)</label>
    <input name="proxy" value="<?= $h($proxy) ?>" placeholder="vd: 1.2.3.4:8000:user:pass" autofocus>
    <label>Host API</label>
    <input name="api" value="<?= $h($api) ?>">
    <button type="submit">Kiểm tra</button>
  </form>
<?php if ($r): ?>
  <div class="v <?= $h($r['verdict'] ?? 'bad') ?>"><?= $h($r['msg'] ?? '') ?></div>
  <?php if (isset($r['ip'])): ?>
  <table>
    <tr><td>IP đi ra</td><td><b><?= $h($r['ip']) ?></b></td></tr>
    <tr><td>Quốc gia</td><td><b><?= $h($r['country']) ?></b></td></tr>
    <tr><td>Gọi API /user/info</td><td>HTTP <?= $h($r['api_http'] ?? '?') ?> · <?= !empty($r['blocked']) ? 'BỊ CHẶN' : (!empty($r['looks_api']) ? 'ra JSON (OK)' : 'phản hồi lạ') ?></td></tr>
  </table>
  <?php endif; ?>
  <?php if (!empty($r['sample'])): ?><pre><?= $h($r['sample']) ?></pre><?php endif; ?>
  <?php if (!empty($r['log'])): ?><details><summary>Nhật ký</summary><pre><?= $h(implode("\n", $r['log'])) ?></pre></details><?php endif; ?>
  <?php if (($r['verdict'] ?? '') === 'good'): ?>
    <p>→ Mở <a href="/">trang lấy số dư</a> và dùng đúng proxy này.</p>
  <?php endif; ?>
<?php endif; ?>
  <p style="font-size:12px;color:#888;margin-top:18px">Web chặn mọi IP máy chủ/datacenter. Chỉ proxy <b>dân cư (residential)</b> hoặc <b>4G/di động</b> Việt Nam mới vào được.</p>
</div></body></html>
