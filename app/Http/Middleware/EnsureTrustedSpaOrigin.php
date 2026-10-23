<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedSpaOrigin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if (
            ! $request->isMethodSafe()
            && $origin !== null
            && ! in_array($origin, config('cors.allowed_origins', []), true)
        ) {
            abort(403, 'Origine non autorizzata.');
        }

        return $next($request);
    }
}
