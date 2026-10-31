<?php
/**
 * Thư viện dùng chung: gọi HTTP qua proxy, tìm API, đăng nhập, lấy số dư/thông tin.
 */

const MU_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

class MuClient
{
    public string $base;   // trang giao diện (nơi tải HTML/JS, dùng làm Origin/Referer)
    public string $api;    // host API thật, vd https://djmuc.vinamilk3.net
    public ?string $proxy;
    public string $jar;
    public array $log = [];
    public ?string $token = null;

    public function __construct(?string $proxy, string $base = 'https://mu.sng22.vip', ?string $api = 'https://djmuc.vinamilk3.net', ?string $jar = null)
    {
        $this->base  = rtrim($base, '/');
        $this->api   = rtrim($api ?: $base, '/');
        $this->proxy = $proxy ? self::normalizeProxy($proxy) : null;
        $this->jar   = $jar ?? tempnam(sys_get_temp_dir(), 'mu_');
    }

    /** Ghép đường dẫn API vào host API thật. */
    public function apiUrl(string $u): string
    {
        if (preg_match('#^https?://#', $u)) return $u;
        if (str_starts_with($u, '//')) return 'https:' . $u;
        return $this->api . '/' . ltrim($u, '/');
    }

    /** Nhận cả dạng host:port:user:pass lẫn http://user:pass@host:port */
    public static function normalizeProxy(string $p): string
    {
        $p = trim($p);
        if (str_contains($p, '://')) return $p;
        $parts = explode(':', $p);
        if (count($parts) === 4) return "http://{$parts[2]}:{$parts[3]}@{$parts[0]}:{$parts[1]}";
        return 'http://' . $p;
    }

    public function url(string $u, ?string $page = null): string
    {
        if (preg_match('#^https?://#', $u)) return $u;
        if (str_starts_with($u, '//')) return 'https:' . $u;
        if (str_starts_with($u, '/')) return $this->base . $u;
        $page ??= $this->base . '/';
        return preg_replace('#/[^/]*$#', '/', $page) . $u;
    }

    /** @return array{0:int,1:string,2:string} [http, body, response-headers] */
    public function request(string $method, string $url, $body = null, array $headers = [], bool $wantHeaders = false): array
    {
        $ch = curl_init($url);
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
            $headers[] = 'token: ' . $this->token;
            $headers[] = 'X-Token: ' . $this->token;
        }
        $isPage = $method === 'GET' && preg_match('~(\.html?|/)([?#]|$)|^https?://[^/]+$~', $url);
        $base = $isPage
            ? ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
               'Upgrade-Insecure-Requests: 1', 'Sec-Fetch-Mode: navigate', 'Sec-Fetch-Dest: document']
            : ['Accept: application/json, text/plain, */*',
               'Origin: ' . $this->base,
               'Referer: ' . $this->base . '/home/mobile.html'];
        $base[] = 'Accept-Language: vi-VN,vi;q=0.9,en;q=0.8';
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => MU_UA,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_ENCODING       => '',
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => array_merge($base, $headers),
            CURLOPT_NOPROXY        => '',
        ];
        if ($this->proxy) {
            $opts[CURLOPT_PROXY] = $this->proxy;
            $opts[CURLOPT_HTTPPROXYTUNNEL] = true;
        }
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
        $rawHdr = '';
        if ($wantHeaders) {
            $opts[CURLOPT_HEADERFUNCTION] = function ($ch, $line) use (&$rawHdr) {
                $rawHdr .= $line;
                return strlen($line);
            };
        }
        curl_setopt_array($ch, $opts);
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Lỗi kết nối: $err ($url)");
        }
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $via = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);
        $this->log[] = "$method $url -> HTTP $code (qua $via)";
        return [$code, $res, $rawHdr];
    }

    /** Lấy token từ header phản hồi (token / authorization / x-token / access-token). */
    public static function headerToken(string $rawHdr): ?string
    {
        foreach (explode("\n", $rawHdr) as $line) {
            if (preg_match('/^(token|authorization|x-token|access[-_]?token)\s*:\s*(.+)$/i', trim($line), $m)) {
                return trim(preg_replace('/^Bearer\s+/i', '', $m[2]));
            }
        }
        return null;
    }

    /** Kiểm tra IP/quốc gia mà Cloudflare (và trang web) nhìn thấy khi đi qua proxy. */
    public function checkProxy(): array
    {
        [$c, $txt] = $this->request('GET', 'https://www.cloudflare.com/cdn-cgi/trace', null, ['Accept: text/plain']);
        preg_match('/^ip=(.*)$/m', $txt, $ip);
        preg_match('/^loc=(.*)$/m', $txt, $loc);
        return ['http' => $c, 'ip' => $ip[1] ?? '?', 'country' => $loc[1] ?? '?', 'proxy' => $this->proxy
            ? preg_replace('#//[^@]*@#', '//***@', $this->proxy) : '(không dùng)'];
    }

    /** Quét HTML + JS của trang mobile, trả về danh sách đường dẫn API khả nghi. */
    public function discover(?string $saveDir = null): array
    {
        $page = $this->base . '/home/mobile.html';
        [, $html] = $this->request('GET', $page);
        if (str_contains($html, 'Truy cập bị cấm')) {
            $chk = $this->checkProxy();
            throw new RuntimeException("Trang chặn theo khu vực. IP đi ra: {$chk['ip']} – quốc gia Cloudflare nhận: {$chk['country']}. "
                . ($chk['country'] === 'VN'
                    ? 'IP là Việt Nam nhưng vẫn bị chặn – có thể IP proxy nằm trong danh sách đen (IP datacenter). Thử proxy dân cư/4G Việt Nam khác.'
                    : 'Proxy không ra IP Việt Nam – kiểm tra lại proxy.'));
        }
        preg_match_all('#(?:src|href)=["\']([^"\']+\.js[^"\']*)["\']#i', $html, $m);
        $queue = array_values(array_unique(array_map(fn($u) => $this->url($u, $page), $m[1])));
        $seen = [];
        $sources = ['mobile.html' => $html];
        while ($queue && count($seen) < 60) {
            $u = array_shift($queue);
            if (isset($seen[$u]) || str_contains($u, 'cdn-cgi')) continue;
            $seen[$u] = true;
            [$c, $js] = $this->request('GET', $u);
            if ($c !== 200) continue;
            $sources[$u] = $js;
            if (preg_match_all('#["\']([\w./-]+\.js)["\']#', $js, $mm)) {
                foreach ($mm[1] as $chunk) {
                    $abs = $this->url($chunk, $u);
                    if (!isset($seen[$abs]) && str_starts_with($abs, $this->base)) $queue[] = $abs;
                }
            }
        }
        $paths = [];
        $kw = '(login|logout|balance|money|wallet|amount|credit|member|user|account|token|captcha|api)';
        foreach ($sources as $src) {
            preg_match_all('#["\'`](/?[A-Za-z0-9_\-]+(?:/[A-Za-z0-9_\-.]+)+)["\'`]#', $src, $mm);
            foreach ($mm[1] as $p) {
                if (preg_match("#$kw#i", $p) && !preg_match('#\.(js|css|png|jpe?g|gif|svg|woff2?|ttf|mp3|webp|html)$#i', $p)) {
                    $paths[$p] = true;
                }
            }
        }
        if ($saveDir) {
            @mkdir($saveDir, 0777, true);
            foreach ($sources as $u => $src) {
                $name = preg_replace('#[^A-Za-z0-9._-]#', '_', basename(parse_url($u, PHP_URL_PATH) ?: $u));
                file_put_contents("$saveDir/$name", $src);
            }
        }
        $list = array_keys($paths);
        sort($list);
        return $list;
    }

    /** Thử đăng nhập. Trả về response JSON (mảng) nếu thành công. */
    public function login(string $path, string $user, string $pass, array $opt = []): array
    {
        $uf = $opt['user_field'] ?? 'userName';
        $pf = $opt['pass_field'] ?? 'password';
        $payload = [$uf => $user, $pf => $pass] + ($opt['extra'] ?? []);
        $formats = isset($opt['format']) ? [$opt['format']] : ['json', 'form'];
        $last = null;
        foreach ($formats as $fmt) {
            [$c, $res, $hdr] = $fmt === 'form'
                ? $this->request('POST', $this->apiUrl($path), http_build_query($payload), ['Content-Type: application/x-www-form-urlencoded'], true)
                : $this->request('POST', $this->apiUrl($path), json_encode($payload), ['Content-Type: application/json;charset=UTF-8'], true);
            $data = json_decode($res, true);
            $last = ['http' => $c, 'format' => $fmt, 'raw' => is_array($data) ? $data : mb_substr($res, 0, 500)];
            if (is_array($data) && self::looksOk($data)) {
                // token có thể ở body, hoặc ở header (token/authorization), hoặc trong cookie
                $this->token = self::findKey($data, ['token', 'accesstoken', 'access_token', 'sessionid', 'session_id', 'sid'])
                    ?: self::headerToken($hdr);
                return ['ok' => true, 'path' => $path] + $last;
            }
        }
        return ['ok' => false, 'path' => $path] + $last;
    }

    public function get(string $path, string $method = 'GET'): array
    {
        [$c, $res] = $this->request($method, $this->apiUrl($path), $method === 'POST' ? '{}' : null,
            ['Content-Type: application/json;charset=UTF-8']);
        $data = json_decode($res, true);
        return ['http' => $c, 'path' => $path, 'ok' => is_array($data) && self::looksOk($data),
                'raw' => is_array($data) ? $data : mb_substr($res, 0, 500)];
    }

    /** Đường dẫn API suy từ cấu trúc /api/front/... đã biết. */
    public static function knownPaths(string $type): array
    {
        return [
            'login'   => ['/api/front/user/login', '/api/front/login', '/api/front/user/signin'],
            'info'    => ['/api/front/user/info', '/api/front/user/getInfo', '/api/front/member/info'],
            'balance' => ['/api/front/user/balance', '/api/front/user/getBalance', '/api/front/user/amount',
                          '/api/front/account/balance', '/api/front/fund/balance', '/api/front/user/wallet',
                          '/api/front/user/money', '/api/front/user/info',
                          // số dư game MU / nhà cung cấp bên thứ ba sau khi "thêm tài khoản MU"
                          '/api/front/game/balance', '/api/front/game/queryBalance', '/api/front/game/money',
                          '/api/front/user/gameBalance', '/api/front/user/thirdBalance', '/api/front/platform/balance',
                          '/api/front/user/platformBalance', '/api/front/game/list', '/api/front/user/gameAccount'],
        ][$type] ?? [];
    }

    public static function looksOk(array $d): bool
    {
        if (self::findKey($d, ['token', 'accesstoken', 'access_token'])) return true;
        foreach (['success', 'status', 'result'] as $k) {
            if (isset($d[$k]) && ($d[$k] === true || $d[$k] === 1 || $d[$k] === '1' || $d[$k] === 'success' || $d[$k] === 'ok')) return true;
        }
        foreach (['code', 'errcode', 'error_code', 'ret'] as $k) {
            if (array_key_exists($k, $d) && in_array((string) $d[$k], ['0', '200', '1'], true)) return true;
        }
        return false;
    }

    public static function findKey($data, array $keys)
    {
        if (!is_array($data)) return null;
        foreach ($data as $k => $v) {
            if (is_string($k) && in_array(strtolower($k), $keys, true) && !is_array($v) && $v !== '' && $v !== null) return $v;
        }
        foreach ($data as $v) {
            if (is_array($v) && ($r = self::findKey($v, $keys)) !== null) return $r;
        }
        return null;
    }

    public static function balanceOf($data)
    {
        return self::findKey($data, ['balance', 'availablebalance', 'available_balance', 'usablebalance', 'usable_balance',
            'money', 'totalbalance', 'total_balance', 'amount', 'credit', 'gold', 'coin', 'usermoney', 'user_money', 'point']);
    }

    /** Lọc ứng viên từ danh sách discover. */
    public static function pick(array $paths, string $type): array
    {
        $re = [
            'login'   => '#login#i',
            'balance' => '#(balance|money|wallet|credit)#i',
            'info'    => '#(user|member|account).*(info|detail|profile)|userinfo|getuser#i',
        ][$type];
        $out = array_values(array_filter($paths, fn($p) => preg_match($re, $p) && !preg_match('#(logout|register|forget|reset|withdraw|transfer|record|log$|history)#i', $p)));
        usort($out, fn($a, $b) => strlen($a) <=> strlen($b));
        return array_slice($out, 0, 6);
    }
}

/**
 * Toàn bộ quy trình: (tìm API nếu cần) → đăng nhập → thông tin → số dư.
 */
function mu_run(array $in): array
{
    $c = new MuClient(
        $in['proxy'] ?? null,
        $in['site'] ?? 'https://mu.sng22.vip',
        $in['api'] ?? 'https://djmuc.vinamilk3.net'
    );
    // Token sẵn có (lấy từ "Copy as cURL" trên trình duyệt) → bỏ qua bước đăng nhập.
    if (!empty($in['token'])) $c->token = $in['token'];

    $merge = fn(array $user, array $disc, string $type) =>
        array_values(array_unique(array_merge($user, MuClient::pick($disc, $type), MuClient::knownPaths($type))));

    $out = ['steps' => [], 'api' => $c->api];
    try {
        if (!empty($in['proxy'])) $out['proxy_check'] = $c->checkProxy();

        // Dò thêm đường dẫn từ JS nếu mở được trang (không bắt buộc – đã có knownPaths).
        $paths = [];
        try { $paths = $c->discover($in['save_dir'] ?? null); $out['discovered'] = $paths; }
        catch (Throwable $e) { $out['discover_note'] = $e->getMessage(); }

        // --- Đăng nhập (bỏ qua nếu đã có token) ---
        if (!$c->token) {
            $loginCands = $merge(array_filter([$in['login_path'] ?? '']), $paths, 'login');
            $opt = array_filter(['user_field' => $in['user_field'] ?? null, 'pass_field' => $in['pass_field'] ?? null]);
            if (!empty($in['login_extra'])) $opt['extra'] = (array) json_decode($in['login_extra'], true);
            foreach ($loginCands as $p) {
                $r = $c->login($p, $in['user'] ?? '', $in['pass'] ?? '', $opt);
                $out['steps'][] = ['Đăng nhập ' . $p => $r];
                if ($r['ok']) { $out['login'] = $r; break; }
            }
            if (empty($out['login'])) throw new RuntimeException('Đăng nhập thất bại ở mọi endpoint thử – xem "Các bước đã thử". Có thể sai đường dẫn login, cần captcha, hoặc mật khẩu được mã hoá phía trình duyệt.');
        }

        // --- Thông tin tài khoản ---
        foreach ($merge(array_filter([$in['info_path'] ?? '']), $paths, 'info') as $p) {
            $r = $c->get($p);
            if (!$r['ok']) $r = $c->get($p, 'POST');
            $out['steps'][] = ['Thông tin ' . $p => $r];
            if ($r['ok']) { $out['info'] = $r; break; }
        }

        // --- Số dư ---
        foreach ($merge(array_filter([$in['balance_path'] ?? '']), $paths, 'balance') as $p) {
            $r = $c->get($p);
            if (!$r['ok']) $r = $c->get($p, 'POST');
            $out['steps'][] = ['Số dư ' . $p => $r];
            if ($r['ok'] && MuClient::balanceOf($r['raw']) !== null) { $out['balance_resp'] = $r; break; }
        }
        $out['balance'] = MuClient::balanceOf($out['balance_resp']['raw'] ?? null)
            ?? MuClient::balanceOf($out['info']['raw'] ?? null)
            ?? MuClient::balanceOf($out['login']['raw'] ?? null);
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    } finally {
        $out['http_log'] = $c->log;
        @unlink($c->jar);
    }
    return $out;
}
