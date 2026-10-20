<?php

declare(strict_types=1);

namespace Gttechgr\Mcp\Tests;

class ConfigTest extends TestCase
{
    public function test_the_mcp_url_defaults_to_the_public_server(): void
    {
        $this->assertSame('https://gttech.gr/mcp', config('gttech.url'));
        $this->assertNull(config('gttech.token'));
        $this->assertNull(config('gttech.domain'));
    }
}
