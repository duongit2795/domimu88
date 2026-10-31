<?php
/**
 * Thư viện gọi API MU88: đăng nhập (mật khẩu MD5) và lấy số dư.
 * Dùng chung cho index.php. Hỗ trợ proxy (tùy chọn).
 */

const MU88_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

class Mu88
{
    public string $loginUrl;
    public string $origin;
    public ?string $proxy;
    public array $log = [];

    public function __construct(?string $proxy = null, string $loginUrl = 'https://mu.sng22.vip/api/front/wl/index/login')
    {
        $this->loginUrl = $loginUrl;
        $this->origin   = self::originOf($loginUrl);
        $this->proxy    = $proxy ? self::normProxy($proxy) : null;
    }

    public static function originOf(string $url): string
    {
        $p = parse_url($url);
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
    }

    /** host:port:user:pass  hoặc  http://user:pass@host:port */
    public static function normProxy(string $p): string
    {
        $p = trim($p);
        if ($p === '') return '';
        if (str_contains($p, '://')) return $p;
        $a = explode(':', $p);
        if (count($a) === 4) return "http://{$a[2]}:{$a[3]}@{$a[0]}:{$a[1]}";
        return 'http://' . $p;
    }

    /** @return array{0:int,1:string} [http, body] */
    public function req(string $method, string $url, ?string $body = null, array $headers = []): array
    {
        $ch = curl_init($url);
        $base = [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: vi-VN,vi;q=0.9,en;q=0.8',
            'Origin: ' . $this->origin,
            'Referer: ' . $this->origin . '/home/index.html',
            'X-Requested-With: XMLHttpRequest',
        ];
        $opt = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => MU88_UA,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => array_merge($base, $headers),
        ];
        if ($this->proxy) { $opt[CURLOPT_PROXY] = $this->proxy; $opt[CURLOPT_HTTPPROXYTUNNEL] = true; }
        if ($body !== null) $opt[CURLOPT_POSTFIELDS] = $body;
        curl_setopt_array($ch, $opt);
        $res = curl_exec($ch);
        if ($res === false) { $e = curl_error($ch); curl_close($ch); $this->log[] = "$method $url -> LỖI: $e"; throw new RuntimeException("Kết nối lỗi: $e"); }
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ip   = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);
        $this->log[] = "$method $url -> HTTP $code (tới $ip)";
        return [$code, $res];
    }

    /** Kiểm tra proxy: IP/quốc gia đi ra, và có vào được host đăng nhập không. */
    public function checkProxy(): array
    {
        $out = ['proxy' => $this->proxy ? preg_replace('#//[^@]*@#', '//***@', $this->proxy) : '(IP máy chủ)'];
        try {
            [$c, $txt] = $this->req('GET', 'https://www.cloudflare.com/cdn-cgi/trace', null, ['Accept: text/plain']);
            preg_match('/^ip=(.*)$/m', $txt, $m1); preg_match('/^loc=(.*)$/m', $txt, $m2);
            $out['ip'] = $m1[1] ?? '?'; $out['country'] = $m2[1] ?? '?';
        } catch (Throwable $e) { $out['error'] = 'Không kết nối được proxy: ' . $e->getMessage(); return $out; }
        try {
            [$c, $res] = $this->req('GET', $this->origin . '/home/index.html', null, ['Accept: text/html']);
            $blocked = str_contains($res, 'Truy cập bị cấm') || str_contains($res, 'Access Denied');
            $out['site_http'] = $c; $out['blocked'] = $blocked;
            $out['ok'] = !$blocked;
            $out['verdict'] = $blocked
                ? ($out['country'] === 'VN' ? 'IP Việt Nam NHƯNG vẫn bị chặn (IP datacenter). Cần proxy dân cư/4G.' : 'IP ' . $out['country'] . ' không phải Việt Nam.')
                : 'Proxy vào được mu.sng22.vip — đăng nhập được.';
        } catch (Throwable $e) { $out['error'] = $e->getMessage(); }
        return $out;
    }

    /** Đăng nhập. $password là mật khẩu GỐC (sẽ tự MD5) hoặc đã MD5 (32 hex). */
    public function login(string $account, string $password): array
    {
        $md5 = (strlen($password) === 32 && ctype_xdigit($password)) ? strtolower($password) : md5($password);
        $body = json_encode(['account' => $account, 'password' => $md5, 'loginSrc' => 0]);
        [$code, $res] = $this->req('POST', $this->loginUrl, $body, ['Content-Type: application/json;charset=UTF-8']);
        if (str_contains($res, 'Truy cập bị cấm') || str_contains($res, 'Access Denied')) {
            throw new RuntimeException('Bị chặn khu vực (HTTP ' . $code . '). Máy chủ không vào được mu.sng22.vip — cần proxy dân cư/4G Việt Nam.');
        }
        $j = json_decode($res, true);
        if (!is_array($j)) throw new RuntimeException('Phản hồi không phải JSON (HTTP ' . $code . '): ' . mb_substr(strip_tags($res), 0, 120));
        if (!($j['success'] === true || ($j['code'] ?? null) === 0)) {
            throw new RuntimeException('Đăng nhập thất bại: ' . ($j['msg'] ?? ('code ' . ($j['code'] ?? '?'))));
        }
        return $j['t'] ?? $j;
    }

    /** Lấy số dư bằng token. $cfg: ['url'=>, 'method'=>GET|POST, 'header'=>'token', 'prefix'=>'']. */
    public function balance(string $token, array $cfg): array
    {
        $header = $cfg['header'] ?: 'token';
        $prefix = $cfg['prefix'] ?? '';
        $method = strtoupper($cfg['method'] ?: 'GET');
        $hdr = [$header . ': ' . $prefix . $token, 'Content-Type: application/json;charset=UTF-8'];
        $urls = !empty($cfg['url']) ? [$cfg['url']] : [
            'https://mu.sng22.vip/api/front/user/balance',
            'https://mu.sng22.vip/api/front/user/getBalance',
            'https://mu.sng22.vip/api/front/user/amount',
            'https://mu.sng22.vip/api/front/user/info',
            'https://mu.sng22.vip/api/front/game/balance',
        ];
        foreach ($urls as $u) {
            foreach ([$method, $method === 'GET' ? 'POST' : 'GET'] as $m) {
                try { [$c, $res] = $this->req($m, $u, $m === 'POST' ? '{}' : null, $hdr); }
                catch (Throwable $e) { continue; }
                $j = json_decode($res, true);
                if (is_array($j) && ($j['success'] === true || ($j['code'] ?? null) === 0)) {
                    $bal = self::findBal($j);
                    if ($bal !== null) return ['bal' => $bal, 'url' => $u, 'raw' => $j];
                    if (!empty($cfg['url'])) return ['bal' => null, 'url' => $u, 'raw' => $j];
                }
            }
        }
        return ['bal' => null, 'url' => null, 'raw' => null];
    }

    public static function findBal($d)
    {
        $keys = ['balance','availablebalance','available_balance','usablebalance','usable_balance','money','totalbalance','total_balance','amount','credit','point','gold','coin'];
        if (!is_array($d)) return null;
        foreach ($d as $k => $v) if (is_string($k) && in_array(strtolower($k), $keys, true) && !is_array($v) && $v !== '' && $v !== null) return $v;
        foreach ($d as $v) if (is_array($v)) { $r = self::findBal($v); if ($r !== null) return $r; }
        return null;
    }
}
