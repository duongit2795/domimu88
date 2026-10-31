<?php
/**
 * mu.sng22.vip – tìm API, đăng nhập và lấy số dư tài khoản.
 *
 * Bước 1 – tìm API (quét HTML + JS của trang mobile):
 *   MU_PROXY="http://user:pass@host:port" php balance.php discover
 *
 * Bước 2 – đăng nhập và lấy số dư (điền đường dẫn API tìm được ở bước 1):
 *   MU_PROXY=... MU_USER=... MU_PASS=... \
 *   MU_LOGIN_PATH=/api/xxx/login MU_BALANCE_PATH=/api/xxx/balance \
 *   php balance.php balance
 *
 * Biến môi trường tùy chọn:
 *   MU_BASE            mặc định https://mu.sng22.vip
 *   MU_LOGIN_FORMAT    json | form             (mặc định json)
 *   MU_USER_FIELD      tên trường tài khoản    (mặc định username)
 *   MU_PASS_FIELD      tên trường mật khẩu     (mặc định password)
 *   MU_LOGIN_EXTRA     JSON thêm vào body đăng nhập, vd '{"captcha":""}'
 *   MU_BALANCE_METHOD  GET | POST              (mặc định GET)
 *   MU_TOKEN_HEADER    header gửi token        (mặc định Authorization)
 *   MU_TOKEN_PREFIX    tiền tố token           (mặc định "Bearer ")
 *   MU_DEBUG=1         in toàn bộ response
 */

const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

function env(string $k, ?string $def = null): ?string
{
    $v = getenv($k);
    return ($v === false || $v === '') ? $def : $v;
}

function base(): string
{
    return rtrim(env('MU_BASE', 'https://mu.sng22.vip'), '/');
}

function cookieJar(): string
{
    return sys_get_temp_dir() . '/mu_sng22_cookies.txt';
}

/** Gửi request qua proxy, trả về [status, body]. */
function http(string $method, string $url, $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_COOKIEJAR      => cookieJar(),
        CURLOPT_COOKIEFILE     => cookieJar(),
        CURLOPT_ENCODING       => '',
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => array_merge([
            'Accept: application/json, text/plain, */*',
            'Origin: ' . base(),
            'Referer: ' . base() . '/home/mobile.html',
        ], $headers),
    ];
    if ($proxy = env('MU_PROXY')) {
        $opts[CURLOPT_PROXY] = $proxy; // http://, socks5:// ... đều được
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    if ($res === false) {
        fwrite(STDERR, "Lỗi cURL: " . curl_error($ch) . " ($url)\n");
        exit(1);
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $res];
}

function absUrl(string $u, string $page): string
{
    if (preg_match('#^https?://#', $u)) return $u;
    if (str_starts_with($u, '//')) return 'https:' . $u;
    if (str_starts_with($u, '/')) return base() . $u;
    return preg_replace('#/[^/]*$#', '/', $page) . $u;
}

/* ------------------------------------------------------------------ */

function discover(): void
{
    $page = base() . '/home/mobile.html';
    [$code, $html] = http('GET', $page);
    echo "HTML $page -> HTTP $code, " . strlen($html) . " bytes\n";
    if (str_contains($html, 'Truy cập bị cấm')) {
        fwrite(STDERR, "Bị chặn theo khu vực – kiểm tra lại MU_PROXY.\n");
        exit(1);
    }

    preg_match_all('#(?:src|href)=["\']([^"\']+\.js[^"\']*)["\']#i', $html, $m);
    $queue = array_unique(array_map(fn($u) => absUrl($u, $page), $m[1]));
    $seen = [];
    $sources = ['mobile.html' => $html];

    // tải JS, và các chunk JS được nhắc tới bên trong (1 cấp)
    while ($queue && count($seen) < 80) {
        $u = array_shift($queue);
        if (isset($seen[$u]) || str_contains($u, 'cdn-cgi')) continue;
        $seen[$u] = true;
        [$c, $js] = http('GET', $u);
        echo "JS  $u -> HTTP $c, " . strlen($js) . " bytes\n";
        if ($c !== 200) continue;
        $sources[$u] = $js;
        if (preg_match_all('#["\']([\w./-]+\.js)["\']#', $js, $mm)) {
            foreach ($mm[1] as $chunk) {
                $abs = absUrl($chunk, $u);
                if (!isset($seen[$abs]) && str_starts_with($abs, base())) $queue[] = $abs;
            }
        }
    }

    $paths = [];
    $kw = '(login|logout|balance|money|wallet|amount|credit|member|user|account|token|captcha|api)';
    foreach ($sources as $src) {
        preg_match_all('#["\'`](/?[A-Za-z0-9_\-]+(?:/[A-Za-z0-9_\-.]+)+)["\'`]#', $src, $mm);
        foreach ($mm[1] as $p) {
            if (preg_match("#$kw#i", $p) && !preg_match('#\.(js|css|png|jpe?g|gif|svg|woff2?|ttf|mp3|webp)$#i', $p)) {
                $paths[$p] = true;
            }
        }
        // baseURL / domain API
        preg_match_all('#https?://[A-Za-z0-9.\-]+(?::\d+)?(?:/[A-Za-z0-9_\-/]*)?#', $src, $hm);
        foreach ($hm[0] as $h) {
            if (!preg_match('#(w3\.org|github|cloudflare|google|apple\.com|mozilla)#', $h)) $paths['[host] ' . $h] = true;
        }
    }
    ksort($paths);
    echo "\n===== Các đường dẫn API khả nghi =====\n";
    echo implode("\n", array_keys($paths)) . "\n";

    $dir = __DIR__ . '/discovered';
    @mkdir($dir);
    foreach ($sources as $u => $src) {
        file_put_contents($dir . '/' . preg_replace('#[^A-Za-z0-9._-]#', '_', basename(parse_url($u, PHP_URL_PATH) ?: $u)), $src);
    }
    echo "\nĐã lưu HTML/JS vào $dir để xem chi tiết.\n";
}

/* ------------------------------------------------------------------ */

/** Tìm đệ quy key đầu tiên khớp danh sách. */
function findKey($data, array $keys)
{
    if (!is_array($data)) return null;
    foreach ($data as $k => $v) {
        if (is_string($k) && in_array(strtolower($k), $keys, true) && !is_array($v)) return $v;
    }
    foreach ($data as $v) {
        if (is_array($v) && ($r = findKey($v, $keys)) !== null) return $r;
    }
    return null;
}

function balance(): void
{
    $user = env('MU_USER');
    $pass = env('MU_PASS');
    $loginPath = env('MU_LOGIN_PATH');
    $balPath = env('MU_BALANCE_PATH');
    if (!$user || !$pass || !$loginPath || !$balPath) {
        fwrite(STDERR, "Cần MU_USER, MU_PASS, MU_LOGIN_PATH, MU_BALANCE_PATH (chạy 'discover' trước).\n");
        exit(1);
    }
    @unlink(cookieJar());
    $debug = env('MU_DEBUG') === '1';

    // Mở trang trước để lấy cookie phiên
    http('GET', base() . '/home/mobile.html');

    $payload = [env('MU_USER_FIELD', 'username') => $user, env('MU_PASS_FIELD', 'password') => $pass];
    if ($extra = env('MU_LOGIN_EXTRA')) $payload += (array) json_decode($extra, true);

    if (env('MU_LOGIN_FORMAT', 'json') === 'form') {
        [$c, $res] = http('POST', absUrl($loginPath, base() . '/'), http_build_query($payload),
            ['Content-Type: application/x-www-form-urlencoded']);
    } else {
        [$c, $res] = http('POST', absUrl($loginPath, base() . '/'), json_encode($payload),
            ['Content-Type: application/json;charset=UTF-8']);
    }
    $login = json_decode($res, true);
    echo "Đăng nhập -> HTTP $c\n";
    if ($debug || !is_array($login)) echo $res . "\n";

    $token = findKey($login, ['token', 'accesstoken', 'access_token', 'sessionid', 'session_id', 'sid', 'auth']);
    $headers = [];
    if ($token) {
        $headers[] = env('MU_TOKEN_HEADER', 'Authorization') . ': ' . env('MU_TOKEN_PREFIX', 'Bearer ') . $token;
        echo "Token: " . substr((string) $token, 0, 12) . "...\n";
    } else {
        echo "Không thấy token trong response – dùng cookie phiên.\n";
        if (is_array($login)) echo json_encode($login, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }

    $method = strtoupper(env('MU_BALANCE_METHOD', 'GET'));
    [$c, $res] = http($method, absUrl($balPath, base() . '/'), $method === 'POST' ? '{}' : null,
        array_merge($headers, ['Content-Type: application/json;charset=UTF-8']));
    $bal = json_decode($res, true);
    echo "Số dư -> HTTP $c\n";
    echo (is_array($bal) ? json_encode($bal, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $res) . "\n";

    $value = findKey($bal, ['balance', 'money', 'amount', 'credit', 'totalbalance', 'total_balance', 'gold', 'coin']);
    echo "\n>>> SỐ DƯ: " . ($value ?? '(không tự nhận diện được – xem JSON ở trên)') . "\n";
}

/* ------------------------------------------------------------------ */

if (!extension_loaded('curl')) {
    fwrite(STDERR, "Cần bật extension php-curl.\n");
    exit(1);
}
match ($argv[1] ?? '') {
    'discover' => discover(),
    'balance'  => balance(),
    default    => print("Dùng: php balance.php discover | balance\n"),
};
