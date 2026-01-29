<?php

namespace App\Http\Middleware;

use Arr;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAnyRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $roles): Response
    {
        $user = $request->user();
        $roleKeys = array_map('trim', explode('|', $roles));
        
        if(!$user || !$user->hasAnyRole($roleKeys)){
        return response()->json(['message' => 'Only staff, Head Department can access this route'], 403);
        }
            return $next($request);
        }
    
}
