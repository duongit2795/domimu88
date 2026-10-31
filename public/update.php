<?php
/**
 * Cập nhật code bằng cách tải file mới nhất từ GitHub qua HTTPS (không cần git / proc_open).
 *
 * Bảo vệ bằng mã bí mật – đặt 1 trong 2 cách:
 *   - Biến môi trường MU_UPDATE_TOKEN
 *   - File config.local.php ở thư mục gốc (đã .gitignore):
 *       <?php return ['update_token' => 'chuoi-bi-mat-dai'];
 *
 * Dùng: http://.../update.php?token=chuoi-bi-mat-dai
 *       hoặc dòng lệnh: php public/update.php
 */

const REPO   = 'duongit2795/domimu88';
const BRANCH = 'claude/mu-balance-php';
// Danh sách file được cập nhật (đường dẫn tương đối gốc dự án).
const FILES = ['lib.php', 'balance.php', 'public/index.php', 'public/update.php'];

$root = realpath(__DIR__ . '/..');
$cli  = PHP_SAPI === 'cli';

$cfg    = is_file($root . '/config.local.php') ? (array) require $root . '/config.local.php' : [];
$secret = getenv('MU_UPDATE_TOKEN') ?: ($cfg['update_token'] ?? '');
$branch = getenv('MU_UPDATE_BRANCH') ?: ($cfg['update_branch'] ?? BRANCH);

if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    $given = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
    if (strlen($secret) < 12) {
        http_response_code(503);
        exit("Chưa cấu hình mã bí mật (>= 12 ký tự) trong MU_UPDATE_TOKEN hoặc config.local.php.\n");
    }
    if (!hash_equals($secret, $given)) {
        http_response_code(403);
        exit("Sai mã bí mật.\n");
    }
}

/** Tải 1 URL, ưu tiên cURL, fallback file_get_contents. */
function fetch(string $url): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'domimu88-updater',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false) return [0, '', $err];
        return [$code, $body, ''];
    }
    if (ini_get('allow_url_fopen')) {
        $ctx  = stream_context_create(['http' => ['timeout' => 30, 'header' => "User-Agent: domimu88-updater\r\n"]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $code = (int) $m[1];
        if ($body === false) return [0, '', 'file_get_contents thất bại'];
        return [$code, $body, ''];
    }
    return [0, '', 'Không có cURL lẫn allow_url_fopen – hosting chặn kết nối ra ngoài.'];
}

$branch = trim($branch);
echo "Thư mục: $root\nNhánh:  $branch\nNguồn:  https://raw.githubusercontent.com/" . REPO . "/$branch/\n\n";

$ok = true;
$changed = 0;
foreach (FILES as $rel) {
    $url = 'https://raw.githubusercontent.com/' . REPO . '/' . str_replace('%2F', '/', rawurlencode($branch)) . '/' . $rel;
    [$code, $body, $err] = fetch($url);
    if ($code !== 200 || $body === '') {
        $ok = false;
        echo "✗ $rel — tải lỗi (HTTP $code) $err\n";
        continue;
    }
    if (stripos($body, '<?php') === false) {
        $ok = false;
        echo "✗ $rel — nội dung không hợp lệ, bỏ qua để khỏi hỏng file.\n";
        continue;
    }
    $dest = $root . '/' . $rel;
    @mkdir(dirname($dest), 0755, true);
    $old = is_file($dest) ? file_get_contents($dest) : null;
    if ($old === $body) {
        echo "= $rel — không đổi\n";
        continue;
    }
    // Ghi an toàn: ra file tạm rồi đổi tên.
    $tmp = $dest . '.tmp' . getmypid();
    if (file_put_contents($tmp, $body) === false || !@rename($tmp, $dest)) {
        @unlink($tmp);
        $ok = false;
        echo "✗ $rel — KHÔNG ghi được (thiếu quyền ghi?).\n";
        continue;
    }
    $changed++;
    echo "✓ $rel — đã cập nhật (" . strlen($body) . " bytes)\n";
}

echo "\n" . ($ok ? "Hoàn tất. $changed file được cập nhật.\n" : "Có lỗi — xem các dòng ✗ ở trên.\n");
if (!$cli && !$ok) http_response_code(500);
