# GT Tech for Laravel

Measure a Laravel site through the GT Tech MCP server. The four scans register as Laravel AI tools, so an agent can call them for each page.

## Install

```bash
composer require gttechgr/mcp
```

Add the site and the token to `.env`. Create the token on the GT Tech admin profile. Its host list must allow this domain, or stay empty to allow every host.

```dotenv
GTTECH_DOMAIN=example.com
GTTECH_TOKEN=your-sanctum-token
```

`GTTECH_MCP_URL` defaults to `https://gttech.gr/mcp`.

## Use the tools

`WebsiteAgent` already exposes `seo-audit`, `website-health`, `page-experience`, and `speed-revenue`.

```php
use Gttechgr\Mcp\Agents\WebsiteAgent;

echo (new WebsiteAgent)->prompt('Scan the homepage and /contact. List the SEO, speed, and page-experience changes for each page.');
```

`example.com` scans the homepage. `example.com/contact` scans that page. `speed-revenue` also needs `business_type`: `local_service`, `ecommerce`, `restaurant`, `hospitality`, `saas`, or `other`.

Add the same tools to an existing agent:

```php
use Gttechgr\Mcp\Gttech;

public function tools(): iterable
{
    return [
        ...Gttech::tools(),
    ];
}
```

The tools return the measured report. The agent uses that report to decide the changes in this project.
