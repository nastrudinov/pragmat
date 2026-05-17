<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden - Insufficient permissions',
                'required_roles' => $roles,
                'user_role' => $user->role
            ], 403);
        }
        
        return $next($request);
    }
}