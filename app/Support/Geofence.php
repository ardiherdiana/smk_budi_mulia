<?php

namespace App\Support;

class Geofence
{
    const SCHOOL_LAT = -6.3370648;

    const SCHOOL_LNG = 107.2769934;

    const SCHOOL_RADIUS_METERS = 100;

    /** Great-circle distance between two lat/lng points, in meters. */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6_371_000;
        $toRad = fn (float $deg) => $deg * M_PI / 180;
        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function distanceFromSchoolMeters(float $lat, float $lng): float
    {
        return self::distanceMeters($lat, $lng, self::SCHOOL_LAT, self::SCHOOL_LNG);
    }
}
