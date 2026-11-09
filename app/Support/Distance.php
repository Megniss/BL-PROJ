<?php

namespace App\Support;

use App\Models\User;

// vienkāršota attāluma formula, Latvijas mērogā pietiek
class Distance
{
    private const KM_PER_DEGREE = 111.32;

    // skaitļi ielikti tieši, jo PDO tos padod kā tekstu un SQLite salīdzina nepareizi
    public static function sql(User $from, string $latCol, string $lngCol): string
    {
        $lat = self::num((float) $from->latitude);
        $lng = self::num((float) $from->longitude);
        $cos2 = self::num(cos(deg2rad((float) $from->latitude)) ** 2);

        return "(($latCol - $lat) * ($latCol - $lat) + ($lngCol - $lng) * ($lngCol - $lng) * $cos2)";
    }

    // robeža, ar ko salīdzināt sql() rezultātu
    public static function limit(int $km): string
    {
        return self::num(($km / self::KM_PER_DEGREE) ** 2);
    }

    private static function num(float $value): string
    {
        return sprintf('%.10F', $value);
    }

    public static function toKm(?float $squared): ?float
    {
        return $squared === null ? null : round(sqrt($squared) * self::KM_PER_DEGREE, 1);
    }

    public static function between(User $a, User $b): ?float
    {
        if (!$a->hasLocation() || !$b->hasLocation()) {
            return null;
        }

        $cos = cos(deg2rad((float) $a->latitude));
        $dLat = (float) $a->latitude - (float) $b->latitude;
        $dLng = ((float) $a->longitude - (float) $b->longitude) * $cos;

        return self::toKm($dLat * $dLat + $dLng * $dLng);
    }
}
