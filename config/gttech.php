<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | MCP server
    |--------------------------------------------------------------------------
    |
    | The installed application calls this endpoint with the Sanctum token.
    |
    */

    'url' => env('GTTECH_MCP_URL', 'https://gttech.gr/mcp'),

    'token' => env('GTTECH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Site to measure
    |--------------------------------------------------------------------------
    |
    | The agent passes this host to the tools. Add a path to measure one page,
    | for example example.com/contact. The token host list must allow this host.
    |
    */

    'domain' => env('GTTECH_DOMAIN'),

    'timeout' => env('GTTECH_TIMEOUT', 180),

];
