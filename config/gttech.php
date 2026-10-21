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

    /*
    |--------------------------------------------------------------------------
    | Authenticated pages
    |--------------------------------------------------------------------------
    |
    | When email and password are set, the package signs in once per host and
    | sends only the session cookie to GT Tech. Leave them empty for a public scan.
    |
    */

    'auth' => [
        'email' => env('GTTECH_AUTH_EMAIL'),
        'password' => env('GTTECH_AUTH_PASSWORD'),
        'cookie' => env('GTTECH_AUTH_COOKIE'),
        'login_path' => env('GTTECH_AUTH_LOGIN_PATH', '/login'),
        'guard_paths' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('GTTECH_AUTH_GUARD_PATHS', '/login,/change-password')),
        ))),
    ],

];
