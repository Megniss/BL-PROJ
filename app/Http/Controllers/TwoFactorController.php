<?php

namespace App\Http\Controllers;

use App\Support\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    // otrais solis pēc paroles
    public function verifyLogin(Request $request)
    {
        $data = $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $user = TwoFactor::verify($data['challenge'], trim($data['code']), 'login');

        if (is_string($user)) {
            return response()->json(['error' => $user], 422);
        }

        if ($user->is_blocked) {
            return response()->json(['blocked' => true], 403);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
            'device_token' => !empty($data['remember']) ? TwoFactor::rememberDevice($user) : null,
        ]);
    }

    public function resendLogin(Request $request)
    {
        $request->validate(['challenge' => ['required', 'string']]);

        if (!$this->send(fn() => TwoFactor::resend($request->challenge, 'login'))) {
            return response()->json(['error' => 'expired'], 422);
        }

        return response()->json(['sent' => true]);
    }

    // ieslēgšana - vispirms jāapstiprina, ka e-pasts pienāk
    public function sendEnableCode(Request $request)
    {
        $challenge = $this->send(fn() => TwoFactor::start($request->user(), 'enable'));

        return $challenge === null
            ? response()->json(['error' => 'mail'], 503)
            : response()->json(['challenge' => $challenge]);
    }

    public function enable(Request $request)
    {
        $data = $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $user = TwoFactor::verify($data['challenge'], trim($data['code']), 'enable');

        if (is_string($user) || $user->id !== $request->user()->id) {
            return response()->json(['error' => is_string($user) ? $user : 'invalid'], 422);
        }

        $user->forceFill(['two_factor_enabled' => true, 'two_factor_prompted' => true])->save();

        return response()->json(['two_factor_enabled' => true]);
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['errors' => ['password' => ['Password is incorrect.']]], 422);
        }

        $user->forceFill(['two_factor_enabled' => false])->save();
        TwoFactor::forget($user);

        return response()->json(['two_factor_enabled' => false]);
    }

    // "Ne tagad" pēc reģistrācijas vai pirmā login
    public function dismissPrompt(Request $request)
    {
        $request->user()->forceFill(['two_factor_prompted' => true])->save();

        return response()->json(['two_factor_prompted' => true]);
    }

    // ja e-pastu neizdodas nosūtīt, atgriež null
    private function send(callable $action)
    {
        try {
            return $action();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}
