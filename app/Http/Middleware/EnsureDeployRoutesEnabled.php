<?php

namespace App\Http\Middleware;

use Closure;
use Dotenv\Dotenv;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureDeployRoutesEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->enabled(), 404);

        return $next($request);
    }

    private function enabled(): bool
    {
        // With the config cached, an edit to .env is ignored until the cache is
        // cleared, and clearing it is itself one of the deploy URLs. Reading
        // the switch straight from .env keeps it possible to turn the URLs on
        // or off at any time by editing that one line.
        if (app()->configurationIsCached()) {
            try {
                $values = Dotenv::parse((string) file_get_contents(app()->environmentFilePath()));

                if (array_key_exists('DEPLOY_ROUTES_ENABLED', $values)) {
                    return filter_var($values['DEPLOY_ROUTES_ENABLED'], FILTER_VALIDATE_BOOLEAN);
                }
            } catch (Throwable) {
                // No readable .env: fall back to the cached configuration.
            }
        }

        return (bool) config('hrms.deploy.routes_enabled');
    }
}
