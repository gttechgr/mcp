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

## Authenticated pages

Set the account when pages sit behind the site login. The package signs in once, like a browser, and sends only the session cookie to GT Tech. The password stays in this project.

```dotenv
GTTECH_AUTH_EMAIL=owner@example.com
GTTECH_AUTH_PASSWORD=secret
GTTECH_AUTH_LOGIN_PATH=/login
GTTECH_AUTH_GUARD_PATHS=/login,/change-password
```

`GTTECH_AUTH_COOKIE` can hold a ready `name=value` session instead of the email and password. Leave the auth settings empty to keep the public scan.

A result of `authenticated: false` means the scan did not reach the page. `invalid_credentials`, `csrf_mismatch`, and `password_change_required` are the reasons. The page is not scored.
