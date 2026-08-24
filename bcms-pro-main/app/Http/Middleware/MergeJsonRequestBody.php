<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ensures JSON POST/PUT/PATCH bodies are merged into the request bag.
 * Some hosts deliver a JSON Content-Length but an empty bag when parsing fails early.
 */
class MergeJsonRequestBody
{
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $content = $request->getContent();
        if (! is_string($content) || $content === '') {
            return $next($request);
        }

        $trimmed = ltrim($content);
        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return $next($request);
        }

        $decoded = json_decode($content, true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $request->merge($decoded);
        }

        return $next($request);
    }
}
