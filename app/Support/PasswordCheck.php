<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

// paroles pārbaude jau ielogotam lietotājam (profils, 2FA izslēgšana, konta dzēšana)
// pēc 5 kļūdām 15 min vairs nevar mēģināt, lai ar nozagtu tokenu nevar minēt paroli
// limits ir uz lietotāju, nevis IP, un kopīgs visām trim vietām
class PasswordCheck
{
    const MAX_ATTEMPTS = 5;
    const LOCKOUT_SECONDS = 900;

    // null ja parole pareiza, citādi gatava kļūdas atbilde
    public static function fail(User $user, ?string $password, string $field = 'password', string $message = 'Password is incorrect.'): ?JsonResponse
    {
        $key = 'password-check:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return response()->json([
                'throttled' => true,
                'minutes' => $minutes,
                'errors' => [$field => ["Too many wrong passwords. Try again in {$minutes} min."]],
            ], 429);
        }

        if (!$password || !Hash::check($password, $user->password)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            return response()->json(['errors' => [$field => [$message]]], 422);
        }

        RateLimiter::clear($key);

        return null;
    }
}
