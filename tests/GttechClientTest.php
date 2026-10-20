<?php

declare(strict_types=1);

namespace Gttechgr\Mcp\Tests;

use Gttechgr\Mcp\Agents\WebsiteAgent;
use Gttechgr\Mcp\Gttech;
use Gttechgr\Mcp\Tools\ScanTool;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use RuntimeException;

class GttechClientTest extends TestCase
{
    public function test_the_agent_receives_the_scan_tools(): void
    {
        $this->fakeMcp();

        $tools = (new WebsiteAgent)->tools();
        $names = [];

        foreach ($tools as $tool) {
            $this->assertInstanceOf(ScanTool::class, $tool);
            $names[] = $tool->name();
        }

        $this->assertSame([
            'seo-audit',
            'website-health',
            'page-experience',
            'speed-revenue',
        ], $names);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://gttech.test/mcp'
                && $request->hasHeader('Authorization', 'Bearer secret-token');
        });
    }

    public function test_a_page_path_is_sent_to_the_scan(): void
    {
        $this->fakeMcp();

        $result = Gttech::tools()->get('seo-audit')?->handle(new Request([
            'url' => 'client.test/contact',
            'business_type' => 'other',
        ]));

        $this->assertSame('Contact score 70', $result);

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return is_array($body)
                && ($body['method'] ?? null) === 'tools/call'
                && ($body['params']['name'] ?? null) === 'seo-audit'
                && ($body['params']['arguments']['url'] ?? null) === 'client.test/contact';
        });
    }

    public function test_the_agent_names_the_configured_domain(): void
    {
        $instructions = (new WebsiteAgent)->instructions();

        $this->assertStringContainsString('client.test', $instructions);
        $this->assertStringContainsString('client.test/contact', $instructions);
    }

    public function test_tools_require_a_token(): void
    {
        config(['gttech.token' => '']);

        $this->expectException(RuntimeException::class);

        Gttech::tools();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('gttech.url', 'https://gttech.test/mcp');
        $app['config']->set('gttech.token', 'secret-token');
        $app['config']->set('gttech.domain', 'client.test');
        $app['config']->set('gttech.timeout', 180);
    }

    private function fakeMcp(): void
    {
        Http::preventStrayRequests();

        Http::fake(function ($request) {
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
                        $this->tool('account'),
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
                return Http::response('', 202);
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
                ],
                'required' => ['url'],
            ],
        ];
    }
}
