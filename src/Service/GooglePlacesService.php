<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GooglePlacesService
{
    public function __construct(
        private HttpClientInterface $client,
    ) {}

    public function searchPlaces(string $query, string $city): array
{
    // Simplifier la query pour Nominatim
    $searchTerm = match(true) {
        str_contains($query, 'airbnb') => 'conciergerie',
        str_contains($query, 'locative') => 'gestion locative',
        str_contains($query, 'immobilière') => 'agence immobilière',
        default => 'location vacances',
    };

    $response = $this->client->request('GET', 'https://nominatim.openstreetmap.org/search', [
        'headers' => [
            'User-Agent' => 'DPServices/1.0 contact@dpservices.fr',
        ],
        'query' => [
            'q' => $searchTerm . ' ' . $city . ' France',
            'format' => 'json',
            'limit' => 20,
            'addressdetails' => 1,
        ]
    ]);

    $data = $response->toArray();

    if (empty($data)) {
        return [];
    }

    return array_values(array_filter(array_map(function ($place) {
        $name = $place['name'] ?? '';
        if (empty($name)) return null;

        return [
            'name' => $name,
            'address' => $place['display_name'] ?? '',
            'rating' => null,
            'reviews' => null,
            'place_id' => $place['place_id'] ?? '',
            'types' => [$place['type'] ?? ''],
        ];
    }, $data)));
}
}