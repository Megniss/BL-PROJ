<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AdminAuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 900;
    private const UNLOCK_HOURS = 8;

    public function status(Request $request)
    {
        $user = $request->user();

        if (!$user->is_admin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'unlocked' => $user->adminUnlocked(),
            'must_change' => $user->admin_password_change,
        ]);
    }

    // admin paroles ievadīšana
    public function unlock(Request $request)
    {
        $user = $request->user();

        if (!$user->is_admin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate(['admin_password' => ['required', 'string']]);

        $key = 'admin-unlock:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json([
                'throttled' => true,
                'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
            ], 429);
        }

        if (!$user->admin_password || !Hash::check($request->admin_password, $user->admin_password)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            AdminLog::create([
                'admin_id' => $user->id,
                'action' => 'admin_unlock_failed',
                'target_type' => 'user',
                'target_id' => $user->id,
                'target_name' => $user->name,
            ]);

            return response()->json([
                'errors' => ['admin_password' => ['Admin password is incorrect.']],
            ], 422);
        }

        RateLimiter::clear($key);

        $user->currentAccessToken()->forceFill([
            'admin_until' => now()->addHours(self::UNLOCK_HOURS),
        ])->save();

        return response()->json([
            'unlocked' => true,
            'must_change' => $user->admin_password_change,
        ]);
    }

    // aizver admin paneli, piem. pārejot uz profilu
    public function lock(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token && $token->admin_until) {
            $token->forceFill(['admin_until' => null])->save();
        }

        return response()->json(['unlocked' => false]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => [$user->admin_password_change ? 'nullable' : 'required', 'string'],
            'new_password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        // pagaidu paroli tikko ievadīja, tāpēc veco neprasa
        if (!$user->admin_password_change && !Hash::check($request->current_password, $user->admin_password)) {
            return response()->json([
                'errors' => ['current_password' => ['Current admin password is incorrect.']],
            ], 422);
        }

        $user->forceFill([
            'admin_password' => $request->new_password,
            'admin_password_change' => false,
        ])->save();

        AdminLog::create([
            'admin_id' => $user->id,
            'action' => 'change_admin_password',
            'target_type' => 'user',
            'target_id' => $user->id,
            'target_name' => $user->name,
        ]);

        return response()->json(['must_change' => false]);
    }
}
