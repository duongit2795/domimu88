<?php
/**
 * Cập nhật code từ GitHub qua HTTPS (không cần git). Bảo vệ bằng mã bí mật.
 * Đặt mã: file config.local.php  ->  <?php return ['update_token'=>'chuoi-dai-12+'];
 * Dùng: update.php?token=chuoi-dai-12+
 */
const REPO = 'duongit2795/domimu88';
const BRANCH = 'claude/mu-balance-php';
const FILES = ['index.php', 'lib88.php', 'update.php'];

$root = __DIR__;
$cli = PHP_SAPI === 'cli';
$cfg = is_file($root . '/config.local.php') ? (array) require $root . '/config.local.php' : [];
$secret = getenv('MU_UPDATE_TOKEN') ?: ($cfg['update_token'] ?? '');

if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    if (strlen($secret) < 12) { http_response_code(503); exit("Chưa cấu hình mã bí mật (>=12 ký tự) trong config.local.php.\n"); }
    if (!hash_equals($secret, (string)($_GET['token'] ?? ''))) { http_response_code(403); exit("Sai mã bí mật.\n"); }
}

function fetch(string $url): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>30, CURLOPT_USERAGENT=>'mu88-updater']);
        $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $e = curl_error($ch); curl_close($ch);
        return $b === false ? [0, '', $e] : [$c, $b, ''];
    }
    if (ini_get('allow_url_fopen')) {
        $b = @file_get_contents($url, false, stream_context_create(['http'=>['timeout'=>30,'header'=>"User-Agent: mu88-updater\r\n"]]));
        return $b === false ? [0, '', 'file_get_contents lỗi'] : [200, $b, ''];
    }
    return [0, '', 'Hosting chặn kết nối ra ngoài'];
}
function latest(string $rel): array {
    [$c, $b] = fetch('https://api.github.com/repos/' . REPO . '/contents/' . $rel . '?ref=' . rawurlencode(BRANCH));
    if ($c === 200) { $d = json_decode($b, true); if (isset($d['content']) && ($x = base64_decode(str_replace("\n", '', $d['content']), true)) !== false) return [200, $x]; }
    [$c, $b] = fetch('https://raw.githubusercontent.com/' . REPO . '/' . BRANCH . '/' . $rel);
    return [$c, $b];
}

echo "Thư mục: $root\n\n";
$ok = true; $n = 0;
foreach (FILES as $rel) {
    [$code, $body] = latest($rel);
    if ($code !== 200 || $body === '' || stripos($body, '<?php') === false) { $ok = false; echo "✗ $rel — tải lỗi (HTTP $code)\n"; continue; }
    $dest = $root . '/' . basename($rel);
    if (is_file($dest) && file_get_contents($dest) === $body) { echo "= $rel — không đổi\n"; continue; }
    $tmp = $dest . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $body) === false || !@rename($tmp, $dest)) { @unlink($tmp); $ok = false; echo "✗ $rel — KHÔNG ghi được\n"; continue; }
    $n++; echo "✓ $rel — cập nhật (" . strlen($body) . " bytes)\n";
}
echo "\n" . ($ok ? "Xong. $n file cập nhật.\n" : "Có lỗi — xem trên.\n");
if (!$cli && !$ok) http_response_code(500);
