<?php
// src/DataFixtures/TarifPrestationFixtures.php

namespace App\DataFixtures;

use App\Entity\TarifPrestation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class TarifPrestationFixtures extends Fixture implements FixtureGroupInterface
{


    public static function getGroups(): array
    {
        return ['tarifs'];
    }

    public function load(ObjectManager $manager): void
    {
        $tarifs = [
            // Ménage Standard
            ['nom' => 'Ménage Standard ≤45m²',     'categorie' => 'menage_standard', 'prix' => 35,  'unite' => 'par prestation', 'surface_min' => 0,   'surface_max' => 45,  'sur_devis' => false],
            ['nom' => 'Ménage Standard 45-65m²',    'categorie' => 'menage_standard', 'prix' => 45,  'unite' => 'par prestation', 'surface_min' => 45,  'surface_max' => 65,  'sur_devis' => false],
            ['nom' => 'Ménage Standard 65-90m²',    'categorie' => 'menage_standard', 'prix' => 55,  'unite' => 'par prestation', 'surface_min' => 65,  'surface_max' => 90,  'sur_devis' => false],
            ['nom' => 'Ménage Standard 90m²+',      'categorie' => 'menage_standard', 'prix' => 0,   'unite' => 'par prestation', 'surface_min' => 90,  'surface_max' => null,'sur_devis' => true],

            // Options recommandées
            ['nom' => 'Option Linge',               'categorie' => 'option',          'prix' => 9,   'unite' => 'par prestation', 'surface_min' => null,'surface_max' => null,'sur_devis' => false,
             'description' => 'Lavage, pliage, installation lit'],

            // Prestations spécifiques
            ['nom' => 'Entretien canapé/Matelas/Tapis', 'categorie' => 'specifique', 'prix' => 60,  'unite' => 'par prestation', 'surface_min' => null,'surface_max' => null,'sur_devis' => false,
             'description' => 'Nettoyage à la shampouineuse, détachage, rafraîchissement textile'],
            ['nom' => 'Ménage approfondi',          'categorie' => 'specifique',      'prix' => 180, 'unite' => 'par prestation', 'surface_min' => null,'surface_max' => null,'sur_devis' => false,
             'description' => 'Nettoyage en profondeur, vitres, détartrage, plinthes, placards'],
            ['nom' => 'Logement très sale/dégradé', 'categorie' => 'specifique',      'prix' => 450, 'unite' => 'par prestation', 'surface_min' => null,'surface_max' => null,'sur_devis' => false,
             'description' => 'Remise en état complète, désinfection, évacuation déchets'],

            // Options Conciergerie
            ['nom' => 'Check-in / Check-out',       'categorie' => 'conciergerie',    'prix' => 25,  'unite' => 'par passage',    'surface_min' => null,'surface_max' => null,'sur_devis' => false],
            ['nom' => 'Stock consommables',         'categorie' => 'conciergerie',    'prix' => 7,   'unite' => 'par mois',       'surface_min' => null,'surface_max' => null,'sur_devis' => false,
             'description' => "Jusqu'à 25 rotations"],
            ['nom' => 'Main d\'œuvre ménage',       'categorie' => 'conciergerie',    'prix' => 15,  'unite' => 'par heure',      'surface_min' => null,'surface_max' => null,'sur_devis' => false],
        ];

        foreach ($tarifs as $t) {
            $tarif = new TarifPrestation();
            $tarif->setNom($t['nom']);
            $tarif->setCategorie($t['categorie']);
            $tarif->setPrix($t['prix']);
            $tarif->setUnite($t['unite']);
            $tarif->setSurfaceMin($t['surface_min'] ?? null);
            $tarif->setSurfaceMax($t['surface_max'] ?? null);
            $tarif->setSurDevis($t['sur_devis']);
            $tarif->setDescription($t['description'] ?? null);
            $manager->persist($tarif);
        }

        $manager->flush();
    }
}