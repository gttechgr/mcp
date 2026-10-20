<?php

declare(strict_types=1);

namespace Gttechgr\Mcp\Tools;

use Laravel\Ai\Tools\McpTool;

final class ScanTool extends McpTool
{
    public function name(): string
    {
        return $this->tool->name;
    }
}
