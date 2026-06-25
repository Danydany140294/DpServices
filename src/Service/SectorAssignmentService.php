<?php

namespace App\Service;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;

/**
 * Service d'affectation automatique d'un salarié à une mission,
 * basé sur le secteur géographique du bien (Property).
 *
 * Règle métier : 1 salarié fixe par secteur (Nimes / Montpellier).
 *
 * Logique de résolution du secteur d'un bien :
 *   1. Si Property.sector est renseigné, on l'utilise directement.
 *   2. Sinon, on déduit le secteur depuis Property.city (sans accent, insensible à la casse).
 *   3. Si aucun secteur n'est déterminable, aucune affectation automatique n'est faite.
 *
 * NOTE IMPORTANTE : la comparaison des secteurs (Property.sector <-> User.sector)
 * est volontairement insensible à la casse et aux accents, car les données existantes
 * ne sont pas uniformes (ex: User.sector = "nimes" en minuscule, alors que le secteur
 * "canonique" utilisé ailleurs dans le code est "Nimes"). On compare donc toujours
 * des versions normalisées, plutôt que d'imposer une casse stricte en base.
 */
class SectorAssignmentService
{
    private const SECTOR_NIMES = 'Nimes';
    private const SECTOR_MONTPELLIER = 'Montpellier';

    /** @var array<string, string> Mapping ville/mot-clé (normalisé) => secteur canonique */
    private const KEYWORD_TO_SECTOR = [
        'nimes' => self::SECTOR_NIMES,
        'montpellier' => self::SECTOR_MONTPELLIER,
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Détermine le secteur d'un bien : sector si renseigné, sinon déduit de city.
     * Retourne toujours la forme canonique (ex: "Nimes"), peu importe la casse d'origine.
     */
    public function resolveSector(Property $property): ?string
    {
        if (!empty($property->getSector())) {
            return $this->canonicalizeSector($property->getSector());
        }

        $city = $property->getCity();
        if (empty($city)) {
            return null;
        }

        return $this->guessSectorFromText($city);
    }

    /**
     * Déduit un secteur à partir d'un texte libre (ex : titre d'un événement Google
     * comme "Ménage Nîmes" ou "Ménage immeuble Nîmes (appartement 1 et 3)").
     * Recherche la présence d'un mot-clé de secteur connu dans le texte,
     * insensible à la casse et aux accents. Retourne la forme canonique.
     */
    public function guessSectorFromText(string $text): ?string
    {
        $normalized = $this->normalize($text);

        foreach (self::KEYWORD_TO_SECTOR as $keyword => $sector) {
            if (str_contains($normalized, $keyword)) {
                return $sector;
            }
        }

        return null;
    }

    /**
     * Ramène un secteur quelconque (ex: "nimes", "NIMES", "Nîmes") à sa forme canonique
     * ("Nimes"). Retourne la valeur d'origine si elle ne correspond à aucun secteur connu.
     */
    private function canonicalizeSector(string $sector): string
    {
        $normalized = $this->normalize($sector);

        return self::KEYWORD_TO_SECTOR[$normalized] ?? $sector;
    }

    /**
     * Trouve le salarié (User) rattaché à un secteur donné, en comparant
     * de façon insensible à la casse et aux accents (voir note de classe).
     * Retourne null si aucun salarié n'est trouvé pour ce secteur,
     * ou si plusieurs salariés partagent le même secteur (cas ambigu, log d'alerte).
     */
    public function findCleanerForSector(string $sector): ?User
    {
        $targetNormalized = $this->normalize($sector);

        // findBy() est sensible à la casse en SQL standard : on filtre nous-mêmes
        // en PHP après récupération de tous les salariés ayant un secteur renseigné,
        // ce qui reste très léger (faible volume d'utilisateurs).
        $allCleaners = $this->userRepository->createQueryBuilder('u')
            ->where('u.sector IS NOT NULL')
            ->getQuery()
            ->getResult();

        $matches = array_values(array_filter(
            $allCleaners,
            fn (User $user) => $this->normalize((string) $user->getSector()) === $targetNormalized
        ));

        if (count($matches) === 0) {
            $this->logger->warning('SectorAssignmentService: aucun salarié trouvé pour le secteur', [
                'sector' => $sector,
            ]);

            return null;
        }

        if (count($matches) > 1) {
            $this->logger->warning('SectorAssignmentService: plusieurs salariés trouvés pour le même secteur, le premier est utilisé', [
                'sector' => $sector,
                'nb_users' => count($matches),
            ]);
        }

        return $matches[0];
    }

    /**
     * Détermine et retourne directement le salarié à affecter pour un bien donné.
     * Combine resolveSector() + findCleanerForSector().
     */
    public function findCleanerForProperty(Property $property): ?User
    {
        $sector = $this->resolveSector($property);

        if ($sector === null) {
            $this->logger->warning('SectorAssignmentService: secteur indéterminable pour le bien', [
                'property_id' => $property->getId(),
                'property_name' => $property->getName(),
                'city' => $property->getCity(),
            ]);

            return null;
        }

        return $this->findCleanerForSector($sector);
    }

    /**
     * Normalise une chaîne pour la comparaison : minuscules, sans accents, sans espaces superflus.
     */
    private function normalize(string $value): string
    {
        $value = trim($value);
        $value = mb_strtolower($value);

        $transliteration = [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ];

        return strtr($value, $transliteration);
    }
}