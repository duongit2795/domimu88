<?php
/**
 * Bản dòng lệnh.
 *   php balance.php discover                 -> liệt kê API tìm thấy
 *   php balance.php                          -> hỏi tài khoản/mật khẩu, đăng nhập, in thông tin + số dư
 * Biến môi trường: MU_PROXY, MU_API (mặc định https://djmuc.vinamilk3.net),
 *   MU_USER, MU_PASS, MU_TOKEN (dán token để bỏ qua đăng nhập),
 *   MU_LOGIN_PATH, MU_BALANCE_PATH, MU_INFO_PATH
 */
require __DIR__ . '/lib.php';

$proxy = getenv('MU_PROXY') ?: null;
$api   = getenv('MU_API') ?: 'https://djmuc.vinamilk3.net';
$token = getenv('MU_TOKEN') ?: '';

if (($argv[1] ?? '') === 'discover') {
    $c = new MuClient($proxy, 'https://mu.sng22.vip', $api);
    try {
        echo implode("\n", $c->discover(__DIR__ . '/discovered')) . "\n";
        echo "\nĐã lưu HTML/JS vào " . __DIR__ . "/discovered\n";
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
    exit;
}

$user = ''; $pass = '';
if ($token === '') {
    $user = getenv('MU_USER') ?: readline('Tài khoản: ');
    if (!($pass = getenv('MU_PASS'))) {
        echo 'Mật khẩu: ';
        system('stty -echo 2>/dev/null');
        $pass = trim((string) fgets(STDIN));
        system('stty echo 2>/dev/null');
        echo "\n";
    }
}

$r = mu_run([
    'user' => $user, 'pass' => $pass, 'token' => $token, 'proxy' => $proxy, 'api' => $api,
    'login_path' => getenv('MU_LOGIN_PATH') ?: '',
    'balance_path' => getenv('MU_BALANCE_PATH') ?: '',
    'info_path' => getenv('MU_INFO_PATH') ?: '',
]);
$j = fn($d) => json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!empty($r['info'])) echo "== Thông tin ({$r['info']['path']}) ==\n" . $j($r['info']['raw']) . "\n";
if (!empty($r['balance_resp'])) echo "== Số dư ({$r['balance_resp']['path']}) ==\n" . $j($r['balance_resp']['raw']) . "\n";
echo "\n>>> SỐ DƯ: " . ($r['balance'] ?? '(không lấy được)') . "\n";
if (!empty($r['error'])) {
    fwrite(STDERR, "\nLỗi: {$r['error']}\nCác bước:\n" . $j($r['steps']) . "\n" . implode("\n", $r['http_log']) . "\n");
    exit(1);
}
