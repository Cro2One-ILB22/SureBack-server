<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Log
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function terminate(Request $request, $response)
    {
        info('Request: ' . $request);
        $responseJson = $response instanceof \Illuminate\Http\JsonResponse ? $response : null;
        if ($responseJson) {
            info('Response JSON: ' . $responseJson);
        } else {
            info('Response HTML code: ' . $response->status());
        }
        if (defined('LARAVEL_START')) {
            info('Request took ' . (microtime(true) - LARAVEL_START) . ' seconds' . PHP_EOL);
        }

        return $response;
    }
}
