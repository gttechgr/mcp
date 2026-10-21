<?php

declare(strict_types=1);

namespace Gttechgr\Mcp\Tools;

use Gttechgr\Mcp\HostSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\McpTool;
use Laravel\Ai\Tools\Request;

final class ScanTool extends McpTool
{
    public function name(): string
    {
        return $this->tool->name;
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();
        unset($arguments['auth']);

        $url = $arguments['url'] ?? null;
        $cookie = app(HostSession::class)->cookieFor(is_string($url) ? $url : '');

        if ($cookie !== null) {
            $auth = ['cookie' => $cookie];
            $guards = config('gttech.auth.guard_paths');

            if (is_array($guards) && $guards !== []) {
                $paths = [];

                foreach ($guards as $guard) {
                    if (is_string($guard) && $guard !== '') {
                        $paths[] = $guard;
                    }
                }

                if ($paths !== []) {
                    $auth['guard_paths'] = implode(',', $paths);
                }
            }

            $arguments['auth'] = $auth;
        }

        return $this->convertResult($this->tool->call($arguments));
    }

    public function schema(JsonSchema $schema): array
    {
        $properties = parent::schema($schema);
        unset($properties['auth']);

        return $properties;
    }
}
