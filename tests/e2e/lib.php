<?php
/**
 * Small HTTP client for the end-to-end tests: cookie jar per session, form
 * parsing (so CSRF tokens, honeypot fields and defaults are submitted exactly
 * as a browser would), and helpers for reading pages.
 */
declare(strict_types=1);

final class E2EResponse
{
    public int $status;
    public array $headers;
    public string $body;
    public string $url;
    public array $rawHeaders;

    public function __construct(int $status, array $headers, string $body, string $url, array $rawHeaders)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
        $this->url = $url;
        $this->rawHeaders = $rawHeaders;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function has(string $text): bool
    {
        return strpos($this->body, $text) !== false;
    }

    public function text(): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('#<(script|style)[^>]*>.*?</\1>#si', '', $this->body)), ENT_QUOTES, 'UTF-8')));
    }
}

final class E2EClient
{
    public string $base;
    private array $cookies = [];
    public array $extraHeaders = [];

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/') . '/';
    }

    public function abs(string $path): string
    {
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        $u = parse_url($this->base);
        $origin = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
        return $path !== '' && $path[0] === '/' ? $origin . $path : $this->base . $path;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function setCookie(string $name, string $value): void
    {
        $this->cookies[$name] = $value;
    }

    public function request(string $method, string $path, $data = null, bool $follow = true, array $headers = []): E2EResponse
    {
        $url = $this->abs($path);
        for ($hop = 0; $hop < 8; $hop++) {
            $ch = curl_init($url);
            $raw = [];
            $hdrs = array_merge($this->extraHeaders, $headers);
            if ($this->cookies) {
                $hdrs[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => $k . '=' . $v, array_keys($this->cookies), $this->cookies));
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60, CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $hdrs, CURLOPT_USERAGENT => 'CNPL-E2E-Tests/1.0',
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$raw) {
                    $raw[] = rtrim($line, "\r\n");
                    return strlen($line);
                },
            ]);
            if ($data !== null && $method !== 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            $body = (string) curl_exec($ch);
            if (curl_errno($ch)) {
                throw new RuntimeException('HTTP error: ' . curl_error($ch));
            }
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            $h = [];
            foreach ($raw as $line) {
                if (strpos($line, ':') === false) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                $lk = strtolower($k);
                if ($lk === 'set-cookie') {
                    $pair = explode(';', $v)[0];
                    [$cn, $cv] = array_pad(explode('=', $pair, 2), 2, '');
                    if ($cv === '' || stripos($v, 'expires=Thu, 01 Jan 1970') !== false || stripos($v, 'Max-Age=0') !== false) {
                        unset($this->cookies[$cn]);
                    } else {
                        $this->cookies[$cn] = $cv;
                    }
                }
                $h[$lk] = isset($h[$lk]) && $lk !== 'set-cookie' ? $h[$lk] . ', ' . $v : $v;
            }
            $resp = new E2EResponse($status, $h, $body, $url, $raw);
            if ($follow && in_array($status, [301, 302, 303, 307], true) && isset($h['location'])) {
                $url = $this->abs($h['location']);
                if ($status !== 307) {
                    $method = 'GET';
                    $data = null;
                }
                continue;
            }
            return $resp;
        }
        throw new RuntimeException('Too many redirects');
    }

    public function get(string $path, bool $follow = true): E2EResponse
    {
        return $this->request('GET', $path, null, $follow);
    }

    public function post(string $path, array $fields, bool $follow = true, array $headers = []): E2EResponse
    {
        $hasFile = false;
        foreach ($fields as $v) {
            if ($v instanceof CURLFile) {
                $hasFile = true;
            }
        }
        return $this->request('POST', $path, $hasFile ? self::flatten($fields) : http_build_query($fields), $follow, $headers);
    }

    private static function flatten(array $fields, string $prefix = ''): array
    {
        $out = [];
        foreach ($fields as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
            if (is_array($v)) {
                $out += self::flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    }

    /**
     * Read a form from an HTML page as a browser would submit it.
     * $match selects the form: a substring of its action, an id (#id), a class (.name), or null for the first POST form.
     */
    public static function form(string $html, ?string $match = null): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        foreach ($doc->getElementsByTagName('form') as $form) {
            $action = $form->getAttribute('action');
            $method = strtolower($form->getAttribute('method') ?: 'get');
            if ($match === null && $method !== 'post') {
                continue;
            }
            if ($match !== null && $match[0] === '#' && $form->getAttribute('id') !== substr($match, 1)) {
                continue;
            }
            if ($match !== null && $match[0] === '.' && !in_array(substr($match, 1), preg_split('/\s+/', $form->getAttribute('class')), true)) {
                continue;
            }
            if ($match !== null && $match[0] !== '#' && $match[0] !== '.' && strpos($action, $match) === false) {
                continue;
            }
            $fields = [];
            $xp = new DOMXPath($doc);
            foreach ($xp->query('.//input|.//select|.//textarea', $form) as $el) {
                $name = $el->getAttribute('name');
                if ($name === '' || $el->hasAttribute('disabled')) {
                    continue;
                }
                $tag = strtolower($el->tagName);
                if ($tag === 'input') {
                    $type = strtolower($el->getAttribute('type') ?: 'text');
                    if (in_array($type, ['submit', 'button', 'file', 'image', 'reset'], true)) {
                        continue;
                    }
                    if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
                        continue;
                    }
                    self::put($fields, $name, $el->getAttribute('value') ?: ($type === 'checkbox' ? 'on' : ''));
                } elseif ($tag === 'select') {
                    $val = null;
                    foreach ($xp->query('.//option', $el) as $o) {
                        if ($val === null || $o->hasAttribute('selected')) {
                            $val = $o->hasAttribute('value') ? $o->getAttribute('value') : $o->textContent;
                            if ($o->hasAttribute('selected')) {
                                break;
                            }
                        }
                    }
                    self::put($fields, $name, (string) $val);
                } else {
                    self::put($fields, $name, $el->textContent);
                }
            }
            return ['action' => $action, 'method' => $method, 'fields' => $fields];
        }
        throw new RuntimeException('Form not found' . ($match ? ': ' . $match : ''));
    }

    private static function put(array &$fields, string $name, string $value): void
    {
        if (substr($name, -2) === '[]') {
            $fields[substr($name, 0, -2)][] = $value;
        } else {
            $fields[$name] = $value;
        }
    }

    /** Load a page, fill its form with $values and submit it. */
    public function submit(string $page, array $values, ?string $match = null, bool $follow = true, float $waitSeconds = 0): E2EResponse
    {
        $r = $this->get($page);
        $f = self::form($r->body, $match);
        if ($waitSeconds > 0) {
            usleep((int) ($waitSeconds * 1e6));
        }
        foreach ($values as $k => $v) {
            if ($v === null) {
                unset($f['fields'][$k]);
            } else {
                $f['fields'][$k] = $v;
            }
        }
        $action = $f['action'];
        if ($action === '' || $action[0] === '#') {
            $action = $r->url;
        } elseif ($action[0] === '?') {
            $action = strtok($r->url, '?') . $action;
        } elseif (strpos($action, 'http') !== 0 && $action[0] !== '/') {
            $action = preg_replace('#[^/]*$#', '', strtok($r->url, '?')) . $action;
        }
        return $this->post($action, $f['fields'], $follow);
    }

    public function login(string $user, string $password): E2EResponse
    {
        return $this->submit('account/login.php', ['identifier' => $user, 'password' => $password]);
    }

    public function csrf(string $html): string
    {
        return preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
    }
}

/** Compute a TOTP code (RFC 6238) the way an authenticator app would. */
function e2e_totp(string $b32, ?int $time = null): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32))) as $c) {
        $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $key = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $key .= chr(bindec($byte));
        }
    }
    $step = intdiv($time ?? time(), 30);
    $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $step), $key, true);
    $o = ord($hash[19]) & 0x0F;
    $v = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
    return str_pad((string) ($v % 1000000), 6, '0', STR_PAD_LEFT);
}
