<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UseSandboxDatabase
{
    public function handle(Request $request, Closure $next): Response
    {
        // Hard gate: sandbox must be explicitly enabled.
        if (!((bool) env('SANDBOX_ENABLED', false))) {
            abort(404);
        }

        // Switch the application's default DB connection for this request only.
        Config::set('database.default', 'sqlite_sandbox');

        // Ensure Spatie Settings uses the sandbox connection too.
        Config::set('settings.repositories.database.connection', 'sqlite_sandbox');

        // Purge to guarantee we don't reuse a non-sandbox connection from a previous request.
        DB::purge('sqlite_sandbox');
        DB::purge(); // purge default, which we just switched

        return $next($request);
    }
}

