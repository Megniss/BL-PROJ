<?php

namespace App\Support;

// lietotājiem ir tikai koordinātes, pilsētu atrod pēc tuvākās no šī saraksta
// ja tuvākā ir tālāk par 25 km - "cita vieta"
class LatvianCities
{
    public const CITIES = [
        'Rīga' => [56.95, 24.11],
        'Daugavpils' => [55.87, 26.54],
        'Liepāja' => [56.51, 21.01],
        'Jelgava' => [56.65, 23.72],
        'Jūrmala' => [56.97, 23.77],
        'Ventspils' => [57.39, 21.56],
        'Rēzekne' => [56.51, 27.33],
        'Valmiera' => [57.54, 25.43],
        'Jēkabpils' => [56.50, 25.86],
        'Ogre' => [56.82, 24.60],
        'Tukums' => [56.97, 23.15],
        'Cēsis' => [57.31, 25.27],
        'Salaspils' => [56.86, 24.35],
        'Kuldīga' => [56.97, 21.97],
        'Sigulda' => [57.15, 24.85],
        'Bauska' => [56.41, 24.19],
        'Talsi' => [57.25, 22.59],
        'Dobele' => [56.62, 23.28],
        'Madona' => [56.85, 26.22],
        'Gulbene' => [57.18, 26.75],
        'Saldus' => [56.66, 22.49],
        'Alūksne' => [57.42, 27.05],
        'Limbaži' => [57.51, 24.71],
        'Ludza' => [56.55, 27.72],
        'Krāslava' => [55.90, 27.17],
        'Aizkraukle' => [56.60, 25.25],
    ];

    public static function nearest(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) return null;

        $best = null;
        $bestKm = 25;
        foreach (self::CITIES as $name => [$cLat, $cLng]) {
            $km = self::km($lat, $lng, $cLat, $cLng);
            if ($km < $bestKm) {
                $best = $name;
                $bestKm = $km;
            }
        }

        return $best;
    }

    // attālums km (haversine)
    private static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * asin(sqrt($a));
    }
}
