<?php
// Areas on the illustrative city map (1000 x 600 plane). The member's "home" is the centre; 100 units ≈ 1 km.
const HOME_X = 500;
const HOME_Y = 300;
const UNITS_PER_KM = 100;

const PLACES = [
    'riverside' => ['name' => 'Riverside Park', 'x' => 700, 'y' => 180],
    'downtown' => ['name' => 'Downtown', 'x' => 340, 'y' => 300],
    'citysquare' => ['name' => 'City Square', 'x' => 570, 'y' => 370],
    'oldtown' => ['name' => 'Old Town', 'x' => 290, 'y' => 140],
    'harbour' => ['name' => 'Harbour Front', 'x' => 440, 'y' => 470],
    'campus' => ['name' => 'University District', 'x' => 720, 'y' => 440],
];

function place(string $id): array
{
    return PLACES[$id] ?? PLACES['citysquare'];
}

function place_name(string $id): string
{
    return place($id)['name'];
}

function distance_km(string $placeId): float
{
    $p = place($placeId);
    return hypot($p['x'] - HOME_X, $p['y'] - HOME_Y) / UNITS_PER_KM;
}
