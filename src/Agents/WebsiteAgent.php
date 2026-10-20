<?php

declare(strict_types=1);

namespace Gttechgr\Mcp\Agents;

use Gttechgr\Mcp\Gttech;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

#[MaxSteps(30)]
#[RepairToolCalls]
#[Timeout(180)]
class WebsiteAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        $domain = config('gttech.domain');
        $site = is_string($domain) && $domain !== '' ? $domain : 'the site named in the prompt';
        $pages = is_string($domain) && $domain !== ''
            ? "{$domain} scans the homepage. {$domain}/contact scans that page."
            : 'A bare host scans the homepage. A host with a path scans that page.';

        return <<<TEXT
        You measure {$site} with the GT Tech tools, one page at a time, and turn each report into changes for that page.

        Pass the page as url. {$pages}
        For each page call seo-audit, website-health, page-experience, and speed-revenue.
        speed-revenue needs business_type: local_service, ecommerce, restaurant, hospitality, saas, or other. Use other when the user does not name one.
        The tool result is the measurement. Repeat its scores and findings, then list the specific changes that raise them.
        TEXT;
    }

    public function tools(): iterable
    {
        return Gttech::tools()->values()->all();
    }
}
