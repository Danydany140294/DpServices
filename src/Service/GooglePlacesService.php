<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GooglePlacesService
{
    private const KEYWORDS = [
        'conciergerie airbnb' => [
            'conciergerie',
            'conciergerie airbnb',
            'gestion airbnb',
            'conciergerie location courte durée',
            'conciergerie appartement',
            'gestion locatif courte durée',
            'welcome management',
            'conciergerie touristique',
            'gestion clés',
            'remise clés',
        ],
        'gestion locative' => [
            'gestion locative',
            'administrateur de biens',
            'gestionnaire locatif',
            'syndic copropriété',
            'gérance immobilière',
            'gestion patrimoine immobilier',
            'gestion appartement',
            'mandataire immobilier',
        ],
        'agence immobilière' => [
            'agence immobilière',
            'agence immo',
            'promoteur immobilier',
            'transaction immobilière',
            'réseau immobilier',
            'chasseur immobilier',
            'estimation immobilière',
        ],
        'location saisonnière' => [
            'location saisonnière',
            'location vacances',
            'gîte',
            'chambre hôtes',
            'villa vacances',
            'maison vacances',
            'appartement vacances',
            'meublé tourisme',
            'résidence tourisme',
            'hébergement touristique',
        ],
        'hôtellerie' => [
            'hôtel',
            'boutique hôtel',
            'apart-hôtel',
            'résidence hôtelière',
            'camping',
            'auberge',
            'pension',
        ],
        'ménage professionnel' => [
    'nettoyage',
    'entreprise nettoyage',
    'société nettoyage',
    'nettoyage bureaux',
    'nettoyage locaux',
    'nettoyage industriel',
    'nettoyage vitres',
    'nettoyage après travaux',
    'nettoyage copropriété',
    'facility services',
],
'ménage particulier' => [
    'aide ménagère',
    'aide domicile',
    'services domicile',
    'garde maison',
    'employé maison',
    'entretien maison',
    'nettoyage maison',
    'repassage',
],
        'services immobiliers' => [
            'diagnostiqueur immobilier',
            'état des lieux',
            'photographe immobilier',
            'home staging',
            'décorateur intérieur',
            'architecte intérieur',
            'rénovation appartement',
            'travaux appartement',
        ],
    ];

    public function __construct(
        private HttpClientInterface $client,
    ) {}

    public function searchPlaces(string $query, string $city): array
    {
        $keywords = self::KEYWORDS[$query] ?? [$query];
        $results = [];
        $seenIds = [];

        foreach ($keywords as $keyword) {
            $response = $this->client->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'headers' => [
                    'User-Agent' => 'DPServices/1.0 contact@dpservices.fr',
                ],
                'query' => [
                    'q' => $keyword . ' ' . $city . ' France',
                    'format' => 'json',
                    'limit' => 10,
                    'addressdetails' => 1,
                ]
            ]);

            $data = $response->toArray();

            foreach ($data as $place) {
                $name = $place['name'] ?? '';
                $placeId = $place['place_id'] ?? '';

                if (empty($name) || in_array($placeId, $seenIds)) continue;

                $seenIds[] = $placeId;
                $results[] = [
                    'name' => $name,
                    'address' => $place['display_name'] ?? '',
                    'rating' => null,
                    'reviews' => null,
                    'place_id' => $placeId,
                    'types' => [$place['type'] ?? ''],
                    'keyword' => $keyword,
                ];
            }

            // Pause pour respecter les limites Nominatim (1 req/sec)
            usleep(1100000);
        }

        return $results;
    }
}