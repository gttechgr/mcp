<?php

declare(strict_types=1);

namespace Gttechgr\Mcp;

use Gttechgr\Mcp\Tools\ScanTool;
use Illuminate\Support\Collection;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Facades\Mcp;
use RuntimeException;

final class Gttech
{
    public const CLIENT = 'gttech';

    /**
     * The scan tools exposed by the GT Tech MCP server.
     *
     * @return Collection<string, ScanTool>
     */
    public static function tools(): Collection
    {
        $token = config('gttech.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Set GTTECH_TOKEN to the Sanctum token from the GT Tech admin profile.');
        }

        return Mcp::client(self::CLIENT)->tools()->map(
            fn (Tool $tool): ScanTool => new ScanTool($tool),
        );
    }
}
