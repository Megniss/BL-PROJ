<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\TwoFactorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactor
{
    public const CODE_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const DEVICE_MINUTES = 10;

    // izveido jaunu kodu, nosūta to un atgriež pieprasījuma atslēgu
    public static function start(User $user, string $purpose): string
    {
        DB::table('two_factor_challenges')
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->delete();

        $token = Str::random(40);

        DB::table('two_factor_challenges')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'code_hash' => self::sendCode($user),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::CODE_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    // jauns kods tam pašam pieprasījumam
    public static function resend(string $token, string $purpose): bool
    {
        $challenge = self::find($token, $purpose);
        if (!$challenge) {
            return false;
        }

        DB::table('two_factor_challenges')->where('id', $challenge->id)->update([
            'code_hash' => self::sendCode(User::find($challenge->user_id)),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_MINUTES),
            'updated_at' => now(),
        ]);

        return true;
    }

    // lietotājs, ja kods pareizs, citādi 'invalid' vai 'expired'
    public static function verify(string $token, string $code, string $purpose): User|string
    {
        $challenge = self::find($token, $purpose);
        if (!$challenge) {
            return 'expired';
        }

        if (!Hash::check($code, $challenge->code_hash)) {
            $attempts = $challenge->attempts + 1;

            // pēc 5 kļūdām jāsāk no jauna
            if ($attempts >= self::MAX_ATTEMPTS) {
                DB::table('two_factor_challenges')->where('id', $challenge->id)->delete();
                return 'expired';
            }

            DB::table('two_factor_challenges')->where('id', $challenge->id)->update(['attempts' => $attempts]);
            return 'invalid';
        }

        DB::table('two_factor_challenges')->where('id', $challenge->id)->delete();

        return User::find($challenge->user_id);
    }

    public static function rememberDevice(User $user): string
    {
        $token = Str::random(60);

        DB::table('trusted_devices')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::DEVICE_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    public static function isTrusted(User $user, ?string $token): bool
    {
        if (!$token) {
            return false;
        }

        return DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->exists();
    }

    public static function forget(User $user): void
    {
        DB::table('two_factor_challenges')->where('user_id', $user->id)->delete();
        DB::table('trusted_devices')->where('user_id', $user->id)->delete();
    }

    private static function find(string $token, string $purpose): ?object
    {
        return DB::table('two_factor_challenges')
            ->where('token_hash', hash('sha256', $token))
            ->where('purpose', $purpose)
            ->where('expires_at', '>', now())
            ->first();
    }

    private static function sendCode(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->notify(new TwoFactorCode($code, self::CODE_MINUTES));

        return Hash::make($code);
    }
}
