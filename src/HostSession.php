<?php

declare(strict_types=1);

namespace Gttechgr\Mcp;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Signs in once per host for this process and keeps the session cookie.
 * The password stays in this project and is not sent to the GT Tech server.
 */
final class HostSession
{
    private const string USER_AGENT = 'Mozilla/5.0 (compatible; GTTechBot/1.0)';

    /** @var array<string, string> */
    private array $cookies = [];

    public function cookieFor(string $url): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $host = $this->hostOf($url);

        if ($host === null || ! $this->allows($host)) {
            return null;
        }

        if (isset($this->cookies[$host])) {
            return $this->cookies[$host];
        }

        $configured = config('gttech.auth.cookie');

        if (is_string($configured) && $configured !== '') {
            return $this->cookies[$host] = $configured;
        }

        return $this->cookies[$host] = $this->login($host, $this->pageUrl($url, $host));
    }

    private function enabled(): bool
    {
        $email = config('gttech.auth.email');
        $password = config('gttech.auth.password');
        $cookie = config('gttech.auth.cookie');

        return (is_string($email) && $email !== '' && is_string($password) && $password !== '')
            || (is_string($cookie) && $cookie !== '');
    }

    private function allows(string $host): bool
    {
        $domain = config('gttech.domain');

        if (! is_string($domain) || trim($domain) === '') {
            return true;
        }

        $configured = $this->hostOf($domain);

        return $configured === null || $this->bare($configured) === $this->bare($host);
    }

    private function login(string $host, string $pageUrl): string
    {
        $loginPath = $this->loginPath();
        $loginUrl = 'https://'.$host.$loginPath;
        $guards = $this->guardPaths();
        $cookies = [];
        $form = $this->getForm($loginUrl, $cookies, $host);
        $token = $this->csrf($form->body());

        if ($token === null) {
            $this->fail('login_failed', $pageUrl, $loginUrl);
        }

        $email = config('gttech.auth.email');
        $password = config('gttech.auth.password');

        $post = $this->send('POST', $loginUrl, $cookies, [
            'email' => is_string($email) ? $email : '',
            'password' => is_string($password) ? $password : '',
            '_token' => $token,
        ]);
        $this->absorb($cookies, $post);

        if ($post->status() === 419) {
            $this->fail('csrf_mismatch', $pageUrl, $loginUrl);
        }

        if (in_array($post->status(), [301, 302, 303, 307, 308], true)) {
            $final = $this->absolute($loginUrl, $post->header('Location')) ?? $loginUrl;

            if ($this->isGuard($final, $guards)) {
                $reason = $this->reason($final, $guards);
                $this->fail($reason === 'login_required' ? 'invalid_credentials' : $reason, $pageUrl, $final);
            }

            return $this->cookieHeader($cookies);
        }

        $this->fail('invalid_credentials', $pageUrl, $loginUrl);
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function getForm(string $url, array &$cookies, string $host): Response
    {
        $current = $url;
        $response = $this->send('GET', $current, $cookies);

        for ($hop = 0; $hop < 3; $hop++) {
            $this->absorb($cookies, $response);

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return $response;
            }

            $next = $this->absolute($current, $response->header('Location'));
            $nextHost = $next === null ? null : $this->hostOf($next);

            if ($next === null || $nextHost !== $host) {
                return $response;
            }

            $current = $next;
            $response = $this->send('GET', $current, $cookies);
        }

        $this->absorb($cookies, $response);

        return $response;
    }

    /**
     * @param  array<string, string>  $cookies
     * @param  array<string, string>  $body
     */
    private function send(string $method, string $url, array $cookies, array $body = []): Response
    {
        $pending = Http::timeout(15)
            ->withUserAgent(self::USER_AGENT)
            ->withoutRedirecting();

        if ($cookies !== []) {
            $pending = $pending->withHeaders([
                'Cookie' => $this->cookieHeader($cookies),
            ]);
        }

        if ($method === 'POST') {
            return $pending->asForm()->post($url, $body);
        }

        return $pending->get($url);
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function absorb(array &$cookies, Response $response): void
    {
        foreach ($response->headers() as $name => $values) {
            if (! is_string($name) || mb_strtolower($name) !== 'set-cookie' || ! is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (! is_string($value) || $value === '') {
                    continue;
                }

                $pair = trim(explode(';', $value, 2)[0]);

                if (! str_contains($pair, '=')) {
                    continue;
                }

                [$cookieName, $cookieValue] = explode('=', $pair, 2);
                $cookieName = trim($cookieName);

                if ($cookieName === '' || preg_match('/^[A-Za-z0-9_-]+$/', $cookieName) !== 1) {
                    continue;
                }

                $cookies[$cookieName] = $cookieValue;
            }
        }
    }

    private function csrf(string $html): ?string
    {
        $patterns = [
            '/name=["\']_token["\'][^>]*value=["\']([^"\']+)["\']/i',
            '/value=["\']([^"\']+)["\'][^>]*name=["\']_token["\']/i',
            '/name=["\']csrf-token["\'][^>]*content=["\']([^"\']+)["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $guards
     */
    private function isGuard(string $url, array $guards): bool
    {
        $path = $this->pathOf($url);

        foreach ($guards as $guard) {
            $guardPath = $this->pathOf('https://guard.test'.$this->slash($guard));

            if ($guardPath !== '/' && ($path === $guardPath || preg_match('#^/[a-z]{2}'.preg_quote($guardPath, '#').'$#', $path) === 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $guards
     */
    private function reason(string $url, array $guards): string
    {
        $path = $this->pathOf($url);

        foreach ($guards as $guard) {
            $guardPath = $this->pathOf('https://guard.test'.$this->slash($guard));

            if ($path === $guardPath && str_contains($guardPath, 'change-password')) {
                return 'password_change_required';
            }
        }

        return 'login_required';
    }

    private function slash(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '' || $path === '/') {
            return '/';
        }

        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    private function absolute(string $base, string $location): ?string
    {
        $location = trim($location);

        if ($location === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || ! is_string($parts['scheme']) || ! is_string($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        return str_starts_with($location, '/') ? $origin.$location : $origin.'/'.ltrim($location, '/');
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function cookieHeader(array $cookies): string
    {
        $pairs = [];

        foreach ($cookies as $name => $value) {
            $pairs[] = $name.'='.$value;
        }

        return implode('; ', $pairs);
    }

    private function loginPath(): string
    {
        $path = config('gttech.auth.login_path');

        if (! is_string($path) || trim($path) === '' || str_contains($path, '://') || str_contains($path, '..')) {
            return '/login';
        }

        $path = trim($path);

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    /**
     * @return list<string>
     */
    private function guardPaths(): array
    {
        $paths = config('gttech.auth.guard_paths');

        if (! is_array($paths)) {
            return ['/login', '/change-password'];
        }

        $clean = [];

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && ! str_contains($path, '..')) {
                $clean[] = $this->slash($path);
            }
        }

        return $clean === [] ? ['/login', '/change-password'] : $clean;
    }

    private function pageUrl(string $url, string $host): string
    {
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return 'https://'.$host.$this->pathOf('https://'.$url);
    }

    private function hostOf(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '://')) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? mb_strtolower($host) : null;
    }

    private function bare(string $host): string
    {
        $host = mb_strtolower($host);

        return str_starts_with($host, 'www.') ? mb_substr($host, 4) : $host;
    }

    private function fail(string $reason, string $requested, string $final): never
    {
        throw new RuntimeException(implode("\n", [
            'authenticated: false',
            'requested_url: '.$requested,
            'final_url: '.$final,
            'reason: '.$reason,
        ]));
    }
}
