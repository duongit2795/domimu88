<?php
/**
 * Cập nhật code từ GitHub (git pull).
 *
 * Bảo vệ bằng mã bí mật – đặt 1 trong 2 cách:
 *   - Biến môi trường MU_UPDATE_TOKEN
 *   - File config.local.php ở thư mục gốc (đã .gitignore):
 *       <?php return ['update_token' => 'chuoi-bi-mat-dai'];
 *
 * Dùng:
 *   Trình duyệt: http://.../update.php?token=chuoi-bi-mat-dai
 *   Dòng lệnh:   php public/update.php
 */

$root = realpath(__DIR__ . '/..');
$cli = PHP_SAPI === 'cli';

$cfgFile = $root . '/config.local.php';
$cfg = is_file($cfgFile) ? (array) require $cfgFile : [];
$secret = getenv('MU_UPDATE_TOKEN') ?: ($cfg['update_token'] ?? '');
$branch = getenv('MU_UPDATE_BRANCH') ?: ($cfg['update_branch'] ?? '');

if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    $given = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
    if ($secret === '' || strlen($secret) < 12) {
        http_response_code(503);
        exit("Chưa cấu hình mã bí mật (>= 12 ký tự) trong MU_UPDATE_TOKEN hoặc config.local.php.\n");
    }
    if (!hash_equals($secret, $given)) {
        http_response_code(403);
        exit("Sai mã bí mật.\n");
    }
}

if (!function_exists('proc_open')) {
    exit("Máy chủ đã tắt proc_open – không chạy được git.\n");
}

function run(string $cmd, string $cwd): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($p), trim($out)];
}

$steps = [];
if ($branch === '') {
    [, $branch] = run('git rev-parse --abbrev-ref HEAD', $root);
}
$b = escapeshellarg($branch);
$steps[] = 'git rev-parse --short HEAD';
$steps[] = "git fetch origin $b";
$steps[] = "git pull --ff-only origin $b";
$steps[] = 'git log -1 --format="%h %ad %s" --date=iso';

$ok = true;
echo "Thư mục: $root\nNhánh: $branch\n\n";
foreach ($steps as $cmd) {
    [$code, $out] = run($cmd . ' 2>&1', $root);
    echo "$ $cmd\n" . ($out !== '' ? "$out\n" : '') . "\n";
    if ($code !== 0) {
        $ok = false;
        echo "LỖI (mã $code). Nếu có file bị sửa trên máy chủ, hãy xử lý tay (git status) rồi chạy lại.\n";
        break;
    }
}
if ($ok) echo "Cập nhật xong.\n";
if (!$cli && !$ok) http_response_code(500);
