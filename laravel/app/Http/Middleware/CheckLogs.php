<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Log;
use Symfony\Component\HttpFoundation\Response;

class CheckLogs
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    { {

            if ($request->is('api/activity_logs') && $request->method() === 'GET') {
                return $next($request);
            }
            $userId = $request->user() ? $request->user()->id : 'Guest';
            $action = $request->method() . ' ' . $request->path();
            $ip = $request->ip();

            $time = now();

            Log::channel('activity')->info("User: {$userId}, Action: {$action}, IP: {$ip}, Time: {$time}");

            return $next($request);
        }
    }
}
