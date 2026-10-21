<?php

declare(strict_types=1);

namespace Gttechgr\Mcp\Tests;

use Gttechgr\Mcp\Agents\WebsiteAgent;
use Gttechgr\Mcp\Gttech;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request as ToolRequest;
use RuntimeException;

class HostSessionTest extends TestCase
{
    private const string PASSWORD = 's3cret-password';

    protected function defineEnvironment($app): void
    {
        $app['config']->set('gttech.url', 'https://gttech.test/mcp');
        $app['config']->set('gttech.token', 'secret-token');
        $app['config']->set('gttech.domain', 'demo.test');
        $app['config']->set('gttech.timeout', 180);
    }

    public function test_one_login_is_reused_and_the_password_stays_off_the_mcp_call(): void
    {
        $this->fake();
        config([
            'gttech.domain' => 'demo.test',
            'gttech.auth.email' => 'owner@demo.test',
            'gttech.auth.password' => self::PASSWORD,
            'gttech.auth.guard_paths' => ['/login', '/change-password'],
        ]);

        $tools = Gttech::tools();
        $first = $tools->get('seo-audit')?->handle(new ToolRequest([
            'url' => 'https://demo.test/expenses',
            'auth' => ['password' => self::PASSWORD],
        ]));
        $second = $tools->get('website-health')?->handle(new ToolRequest([
            'url' => 'https://demo.test/transactions',
        ]));

        $this->assertSame('Contact score 70', $first);
        $this->assertSame('Contact score 70', $second);
        $this->assertSame(1, $this->sentCount('POST', 'https://demo.test/login'));
        $this->assertSame(2, $this->callCount());

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            if (! is_array($body) || ($body['method'] ?? null) !== 'tools/call') {
                return false;
            }

            $encoded = json_encode($body['params']['arguments'] ?? []);

            return is_string($encoded)
                && str_contains($encoded, 'demo-session=second')
                && ! str_contains($encoded, self::PASSWORD)
                && ! str_contains($encoded, 'owner@demo.test');
        });
    }

    public function test_wrong_password_stops_before_the_scan(): void
    {
        $this->fake('/login');
        config([
            'gttech.domain' => 'demo.test',
            'gttech.auth.email' => 'owner@demo.test',
            'gttech.auth.password' => self::PASSWORD,
        ]);

        try {
            Gttech::tools()->get('seo-audit')?->handle(new ToolRequest([
                'url' => 'https://demo.test/expenses',
            ]));
            $this->fail('A wrong password called the scan.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('reason: invalid_credentials', $exception->getMessage());
            $this->assertStringContainsString('final_url: https://demo.test/login', $exception->getMessage());
            $this->assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
            $this->assertStringNotContainsString('owner@demo.test', $exception->getMessage());
        }

        $this->assertSame(0, $this->callCount());
    }

    public function test_password_change_stops_before_the_scan(): void
    {
        $this->fake('/change-password');
        config([
            'gttech.domain' => 'demo.test',
            'gttech.auth.email' => 'owner@demo.test',
            'gttech.auth.password' => self::PASSWORD,
        ]);

        try {
            Gttech::tools()->get('seo-audit')?->handle(new ToolRequest([
                'url' => 'https://demo.test/expenses',
            ]));
            $this->fail('A password change called the scan.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('reason: password_change_required', $exception->getMessage());
            $this->assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
        }

        $this->assertSame(0, $this->callCount());
    }

    public function test_a_different_host_is_not_signed_in(): void
    {
        $this->fake();
        config([
            'gttech.domain' => 'demo.test',
            'gttech.auth.email' => 'owner@demo.test',
            'gttech.auth.password' => self::PASSWORD,
        ]);

        Gttech::tools()->get('seo-audit')?->handle(new ToolRequest([
            'url' => 'https://other.test/pricing',
        ]));

        $this->assertSame(0, $this->sentCount('POST', 'https://demo.test/login'));
        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return is_array($body)
                && ($body['method'] ?? null) === 'tools/call'
                && ($body['params']['arguments']['url'] ?? null) === 'https://other.test/pricing'
                && ! array_key_exists('auth', $body['params']['arguments'] ?? []);
        });
    }

    public function test_instructions_do_not_repeat_the_password(): void
    {
        config([
            'gttech.auth.email' => 'owner@demo.test',
            'gttech.auth.password' => self::PASSWORD,
        ]);

        $instructions = (new WebsiteAgent)->instructions();

        $this->assertStringContainsString('authenticated: false', $instructions);
        $this->assertStringNotContainsString(self::PASSWORD, $instructions);
        $this->assertStringNotContainsString('owner@demo.test', $instructions);
    }

    private function fake(string $location = 'https://demo.test/'): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($location) {
            if ($request->url() === 'https://demo.test/login' && $request->method() === 'GET') {
                return Http::response(
                    '<form method="POST"><input type="hidden" name="_token" value="csrf-1"></form>',
                    200,
                    ['Set-Cookie' => 'demo-session=first; Path=/; HttpOnly'],
                );
            }

            if ($request->url() === 'https://demo.test/login' && $request->method() === 'POST') {
                return Http::response('', 302, [
                    'Location' => $location,
                    'Set-Cookie' => 'demo-session=second; Path=/; HttpOnly',
                ]);
            }

            $body = json_decode($request->body(), true);
            $method = is_array($body) ? ($body['method'] ?? null) : null;
            $id = is_array($body) ? ($body['id'] ?? 1) : 1;
            $result = match ($method) {
                'server/discover' => [
                    'supportedVersions' => ['2026-07-28'],
                    'capabilities' => ['tools' => ['listChanged' => false]],
                    'instructions' => 'Scan the page in the url.',
                ],
                'tools/list' => [
                    'tools' => [
                        $this->tool('seo-audit'),
                        $this->tool('website-health'),
                        $this->tool('page-experience'),
                        $this->tool('speed-revenue'),
                    ],
                ],
                'tools/call' => [
                    'content' => [
                        ['type' => 'text', 'text' => 'Contact score 70'],
                    ],
                    'isError' => false,
                ],
                default => null,
            };

            if ($result === null) {
                return Http::response('unexpected '.$request->url(), 500);
            }

            return Http::response([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ], 200, ['Content-Type' => 'application/json']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(string $name): array
    {
        return [
            'name' => $name,
            'description' => 'Measures '.$name,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => 'string'],
                    'auth' => ['type' => 'object'],
                ],
                'required' => ['url'],
            ],
        ];
    }

    private function sentCount(string $method, string $url): int
    {
        $count = 0;

        foreach (Http::recorded() as [$request]) {
            if ($request instanceof Request && $request->method() === $method && $request->url() === $url) {
                $count++;
            }
        }

        return $count;
    }

    private function callCount(): int
    {
        $count = 0;

        foreach (Http::recorded() as [$request]) {
            if (! $request instanceof Request) {
                continue;
            }

            $body = json_decode($request->body(), true);

            if (is_array($body) && ($body['method'] ?? null) === 'tools/call') {
                $count++;
            }
        }

        return $count;
    }
}
