<?php
require __DIR__ . '/../lib.php';

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$j = fn($d) => json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$in = [
    'user'         => $_POST['user'] ?? '',
    'pass'         => $_POST['pass'] ?? '',
    'token'        => trim($_POST['token'] ?? ''),
    'proxy'        => $_POST['proxy'] ?? (getenv('MU_PROXY') ?: ''),
    'api'          => trim($_POST['api'] ?? '') ?: 'https://djmuc.vinamilk3.net',
    'login_path'   => trim($_POST['login_path'] ?? ''),
    'balance_path' => trim($_POST['balance_path'] ?? ''),
    'info_path'    => trim($_POST['info_path'] ?? ''),
    'user_field'   => trim($_POST['user_field'] ?? ''),
    'pass_field'   => trim($_POST['pass_field'] ?? ''),
    'login_extra'  => trim($_POST['login_extra'] ?? ''),
];
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($in['token'] !== '' || ($in['user'] !== '' && $in['pass'] !== ''))) {
    $result = mu_run($in);
}
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tra cứu số dư</title>
<style>
  body{font-family:system-ui,sans-serif;background:#f3f5f9;margin:0;padding:16px;color:#1d2433}
  .box{max-width:720px;margin:0 auto;background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px #0001}
  h1{font-size:20px;margin:0 0 16px}
  label{display:block;font-size:13px;margin:10px 0 4px;color:#555}
  input{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccd;border-radius:8px;font-size:15px}
  button{margin-top:16px;width:100%;padding:12px;border:0;border-radius:8px;background:#2463eb;color:#fff;font-size:16px;cursor:pointer}
  details{margin-top:12px} summary{cursor:pointer;color:#2463eb}
  .bal{font-size:28px;font-weight:700;color:#0a7d33;margin:16px 0}
  .okbox{background:#e6f6ec;color:#0a5c2a;padding:10px;border-radius:8px;margin-top:16px;word-break:break-all}
  pre{white-space:pre-wrap;word-break:break-all}
  .err{background:#fde8e8;color:#9b1c1c;padding:10px;border-radius:8px;margin-top:16px}
  pre{background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;overflow:auto;font-size:12px;max-height:400px}
</style>
</head>
<body>
<div class="box">
  <h1>Đăng nhập &amp; xem số dư</h1>
  <form method="post" autocomplete="off">
    <label>Tài khoản web</label>
    <input name="user" value="<?= $h($in['user']) ?>">
    <label>Mật khẩu web</label>
    <input name="pass" type="password">
    <label>Token (tùy chọn – dán từ trình duyệt để bỏ qua đăng nhập)</label>
    <input name="token" value="<?= $h($in['token']) ?>" placeholder="để trống nếu dùng tài khoản/mật khẩu">
    <label>Proxy Việt Nam (chỉ cần khi chạy ở nơi bị chặn)</label>
    <input name="proxy" value="<?= $h($in['proxy']) ?>" placeholder="host:port:user:pass">
    <details>
      <summary>Nâng cao (API thật: djmuc.vinamilk3.net)</summary>
      <label>Host API</label><input name="api" value="<?= $h($in['api']) ?>">
      <label>API đăng nhập</label><input name="login_path" value="<?= $h($in['login_path']) ?>" placeholder="/api/front/user/login">
      <label>API số dư</label><input name="balance_path" value="<?= $h($in['balance_path']) ?>" placeholder="/api/front/user/balance">
      <label>API thông tin tài khoản</label><input name="info_path" value="<?= $h($in['info_path']) ?>" placeholder="/api/front/user/info">
      <label>Tên trường tài khoản</label><input name="user_field" value="<?= $h($in['user_field']) ?>" placeholder="userName">
      <label>Tên trường mật khẩu</label><input name="pass_field" value="<?= $h($in['pass_field']) ?>" placeholder="password">
      <label>Dữ liệu thêm khi đăng nhập (JSON)</label><input name="login_extra" value="<?= $h($in['login_extra']) ?>" placeholder='{"verifyCode":""}'>
    </details>
    <button type="submit">Đăng nhập &amp; lấy thông tin</button>
  </form>

<?php if ($result): ?>
  <?php if (!empty($result['proxy_check'])): $pc = $result['proxy_check']; ?>
    <div class="<?= $pc['country'] === 'VN' ? 'okbox' : 'err' ?>">Proxy <?= $h($pc['proxy']) ?> → IP <b><?= $h($pc['ip']) ?></b>, quốc gia <b><?= $h($pc['country']) ?></b></div>
  <?php endif; ?>
  <?php if (!empty($result['error'])): ?>
    <div class="err"><?= $h($result['error']) ?></div>
  <?php endif; ?>
  <?php if (isset($result['balance'])): ?>
    <div class="bal">Số dư: <?= $h($result['balance']) ?></div>
  <?php endif; ?>
  <?php if (!empty($result['info'])): ?>
    <h3>Thông tin tài khoản (<?= $h($result['info']['path']) ?>)</h3>
    <pre><?= $h($j($result['info']['raw'])) ?></pre>
  <?php endif; ?>
  <?php if (!empty($result['balance_resp'])): ?>
    <h3>Response số dư (<?= $h($result['balance_resp']['path']) ?>)</h3>
    <pre><?= $h($j($result['balance_resp']['raw'])) ?></pre>
  <?php endif; ?>
  <details><summary>Các bước đã thử</summary><pre><?= $h($j($result['steps'])) ?></pre></details>
  <?php if (!empty($result['discovered'])): ?>
    <details><summary>API tìm thấy trong JS (<?= count($result['discovered']) ?>)</summary><pre><?= $h(implode("\n", $result['discovered'])) ?></pre></details>
  <?php endif; ?>
  <details><summary>Nhật ký HTTP</summary><pre><?= $h(implode("\n", $result['http_log'])) ?></pre></details>
<?php endif; ?>
</div>
</body>
</html>
