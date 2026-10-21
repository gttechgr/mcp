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
        $this->assertNull(config('gttech.auth.email'));
        $this->assertNull(config('gttech.auth.password'));
        $this->assertSame('/login', config('gttech.auth.login_path'));
        $this->assertSame(['/login', '/change-password'], config('gttech.auth.guard_paths'));
    }
}
