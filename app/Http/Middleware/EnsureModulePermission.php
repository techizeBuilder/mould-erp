<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('module:sales,create')
 */
class EnsureModulePermission
{
    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        abort_unless($request->user()?->canModule($module, $action), 403, 'You do not have permission to perform this action.');

        return $next($request);
    }
}
