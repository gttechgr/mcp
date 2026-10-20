<?php

declare(strict_types=1);

namespace Gttechgr\Mcp\Tests;

use Gttechgr\Mcp\GttechServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            GttechServiceProvider::class,
        ];
    }
}
