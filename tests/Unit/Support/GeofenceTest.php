<?php

namespace Tests\Unit\Support;

use App\Support\Geofence;
use PHPUnit\Framework\TestCase;

class GeofenceTest extends TestCase
{
    public function test_distance_between_identical_points_is_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, Geofence::distanceMeters(-6.337, 107.277, -6.337, 107.277), 0.001);
    }

    public function test_distance_from_school_at_school_coordinates_is_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, Geofence::distanceFromSchoolMeters(Geofence::SCHOOL_LAT, Geofence::SCHOOL_LNG), 0.001);
    }

    public function test_distance_across_a_quarter_of_the_equator_matches_a_quarter_circumference(): void
    {
        // Two equator points 90 degrees of longitude apart are exactly a
        // quarter of the way around the Earth - independently verifiable as
        // pi * R / 2 for the same R this class uses, unlike an arbitrary
        // real-world city pair.
        $expected = M_PI * 6_371_000 / 2;

        $this->assertEqualsWithDelta($expected, Geofence::distanceMeters(0, 0, 0, 90), 1);
    }

    public function test_distance_one_degree_latitude_is_about_111_km(): void
    {
        $distanceMeters = Geofence::distanceMeters(0, 0, 1, 0);

        $this->assertEqualsWithDelta(111_195, $distanceMeters, 100);
    }
}
