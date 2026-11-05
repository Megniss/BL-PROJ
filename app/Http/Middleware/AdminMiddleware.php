<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    // "admin:setup" ļauj tikt cauri arī tad, ja jānomaina pagaidu parole
    public function handle(Request $request, Closure $next, ?string $mode = null)
    {
        $user = $request->user();

        if (!$user || !$user->is_admin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!$user->adminUnlocked()) {
            return response()->json(['message' => 'Admin password required.', 'admin_locked' => true], 403);
        }

        if ($user->admin_password_change && $mode !== 'setup') {
            return response()->json(['message' => 'Admin password must be changed.', 'must_change' => true], 403);
        }

        return $next($request);
    }
}
