<?php
/**
 * Thư viện dùng chung: gọi HTTP qua proxy, tìm API, đăng nhập, lấy số dư/thông tin.
 */

const MU_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

class MuClient
{
    public string $base;
    public ?string $proxy;
    public string $jar;
    public array $log = [];
    public ?string $token = null;

    public function __construct(?string $proxy, string $base = 'https://mu.sng22.vip', ?string $jar = null)
    {
        $this->base  = rtrim($base, '/');
        $this->proxy = $proxy ? self::normalizeProxy($proxy) : null;
        $this->jar   = $jar ?? tempnam(sys_get_temp_dir(), 'mu_');
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

    /** @return array{0:int,1:string} */
    public function request(string $method, string $url, $body = null, array $headers = []): array
    {
        $ch = curl_init($url);
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
            $headers[] = 'token: ' . $this->token;
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => MU_UA,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_ENCODING       => '',
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => array_merge([
                'Accept: application/json, text/plain, */*',
                'Origin: ' . $this->base,
                'Referer: ' . $this->base . '/home/mobile.html',
            ], $headers),
        ];
        if ($this->proxy) $opts[CURLOPT_PROXY] = $this->proxy;
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
        curl_setopt_array($ch, $opts);
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Lỗi kết nối: $err ($url)");
        }
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $this->log[] = "$method $url -> HTTP $code";
        return [$code, $res];
    }

    /** Quét HTML + JS của trang mobile, trả về danh sách đường dẫn API khả nghi. */
    public function discover(?string $saveDir = null): array
    {
        $page = $this->base . '/home/mobile.html';
        [, $html] = $this->request('GET', $page);
        if (str_contains($html, 'Truy cập bị cấm')) {
            throw new RuntimeException('Trang chặn theo khu vực – proxy không phải IP Việt Nam hoặc không hoạt động.');
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
        $uf = $opt['user_field'] ?? 'username';
        $pf = $opt['pass_field'] ?? 'password';
        $payload = [$uf => $user, $pf => $pass] + ($opt['extra'] ?? []);
        $formats = isset($opt['format']) ? [$opt['format']] : ['json', 'form'];
        $last = null;
        foreach ($formats as $fmt) {
            [$c, $res] = $fmt === 'form'
                ? $this->request('POST', $this->url($path), http_build_query($payload), ['Content-Type: application/x-www-form-urlencoded'])
                : $this->request('POST', $this->url($path), json_encode($payload), ['Content-Type: application/json;charset=UTF-8']);
            $data = json_decode($res, true);
            $last = ['http' => $c, 'format' => $fmt, 'raw' => is_array($data) ? $data : mb_substr($res, 0, 500)];
            if (is_array($data) && self::looksOk($data)) {
                $this->token = self::findKey($data, ['token', 'accesstoken', 'access_token', 'sessionid', 'session_id', 'sid']);
                return ['ok' => true, 'path' => $path] + $last;
            }
        }
        return ['ok' => false, 'path' => $path] + $last;
    }

    public function get(string $path, string $method = 'GET'): array
    {
        [$c, $res] = $this->request($method, $this->url($path), $method === 'POST' ? '{}' : null,
            ['Content-Type: application/json;charset=UTF-8']);
        $data = json_decode($res, true);
        return ['http' => $c, 'path' => $path, 'ok' => is_array($data) && self::looksOk($data),
                'raw' => is_array($data) ? $data : mb_substr($res, 0, 500)];
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
        return self::findKey($data, ['balance', 'money', 'totalbalance', 'total_balance', 'amount', 'credit', 'gold', 'coin', 'usermoney', 'user_money']);
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
    $c = new MuClient($in['proxy'] ?? null);
    $out = ['steps' => []];
    try {
        $paths = [];
        if (empty($in['login_path']) || empty($in['balance_path'])) {
            $paths = $c->discover($in['save_dir'] ?? null);
            $out['discovered'] = $paths;
        }
        $c->request('GET', $c->base . '/home/mobile.html');

        $loginCands = !empty($in['login_path']) ? [$in['login_path']] : MuClient::pick($paths, 'login');
        if (!$loginCands) throw new RuntimeException('Không tìm thấy API đăng nhập – nhập tay đường dẫn login.');
        $opt = array_filter([
            'user_field' => $in['user_field'] ?? null,
            'pass_field' => $in['pass_field'] ?? null,
        ]);
        foreach ($loginCands as $p) {
            $r = $c->login($p, $in['user'], $in['pass'], $opt);
            $out['steps'][] = ['Đăng nhập' => $r];
            if ($r['ok']) { $out['login'] = $r; break; }
        }
        if (empty($out['login'])) throw new RuntimeException('Đăng nhập thất bại – xem response bên dưới.');

        $infoCands = !empty($in['info_path']) ? [$in['info_path']] : MuClient::pick($paths, 'info');
        foreach ($infoCands as $p) {
            $r = $c->get($p);
            if (!$r['ok']) $r = $c->get($p, 'POST');
            $out['steps'][] = ['Thông tin' => $r];
            if ($r['ok']) { $out['info'] = $r; break; }
        }

        $balCands = !empty($in['balance_path']) ? [$in['balance_path']] : MuClient::pick($paths, 'balance');
        foreach ($balCands as $p) {
            $r = $c->get($p);
            if (!$r['ok']) $r = $c->get($p, 'POST');
            $out['steps'][] = ['Số dư' => $r];
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
