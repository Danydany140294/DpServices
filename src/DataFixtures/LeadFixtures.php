<?php

namespace App\DataFixtures;

use App\Entity\Lead;
use App\Entity\LeadCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LeadFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public static function getGroups(): array
    {
        return ['leads'];
    }

    public function getDependencies(): array
    {
        return [LeadCategoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $categories = $manager->getRepository(LeadCategory::class)->findAll();
        $catMap = [];
        foreach ($categories as $cat) {
            $catMap[$cat->getName()] = $cat;
        }

        $leads = [
            [
                'companyName' => 'Conciergerie du Soleil',
                'contactName' => 'Marie Dupont',
                'phone' => '06 12 34 56 78',
                'email' => 'contact@conciergerie-soleil.fr',
                'city' => 'Montpellier',
                'source' => 'Google Maps',
                'score' => 85,
                'status' => 'NEW',
                'hasAirbnb' => true,
                'googleRating' => 4.8,
                'googleReviews' => 124,
                'category' => 'Conciergerie Airbnb',
                'notes' => 'Très active sur Airbnb, plusieurs annonces premium.',
            ],
            [
                'companyName' => 'Gestion Immo Méditerranée',
                'contactName' => 'Paul Martin',
                'phone' => '04 67 89 01 23',
                'email' => 'paul@gim.fr',
                'city' => 'Sète',
                'source' => 'Google Maps',
                'score' => 62,
                'status' => 'CONTACTED',
                'hasAirbnb' => false,
                'googleRating' => 4.2,
                'googleReviews' => 57,
                'category' => 'Gestion locative',
                'notes' => 'Gérant 12 appartements, intéressé par nos services de ménage.',
                'nextFollowUp' => new \DateTime('+3 days'),
            ],
            [
                'companyName' => 'Agence Littoral Sud',
                'contactName' => 'Sophie Blanc',
                'phone' => '04 67 11 22 33',
                'email' => 'sophie@littoral-sud.fr',
                'city' => 'Palavas',
                'source' => 'Recommandation',
                'score' => 45,
                'status' => 'DISCUSSION',
                'hasAirbnb' => true,
                'googleRating' => 3.9,
                'googleReviews' => 31,
                'category' => 'Agence immobilière',
                'notes' => 'Rendez-vous téléphonique prévu.',
            ],
            [
                'companyName' => 'Les Clés du Languedoc',
                'contactName' => null,
                'phone' => '06 98 76 54 32',
                'email' => null,
                'city' => 'Nîmes',
                'source' => 'Google Maps',
                'score' => 78,
                'status' => 'QUOTE_SENT',
                'hasAirbnb' => true,
                'googleRating' => 4.6,
                'googleReviews' => 88,
                'category' => 'Location saisonnière',
                'notes' => 'Devis envoyé le 10/06, attente de réponse.',
                'nextFollowUp' => new \DateTime('yesterday'),
            ],
            [
                'companyName' => 'Villa Occitane Rentals',
                'contactName' => 'Jean-Pierre Fabre',
                'phone' => '06 55 44 33 22',
                'email' => 'jp@villa-occitane.fr',
                'city' => 'Millau',
                'source' => 'LinkedIn',
                'score' => 20,
                'status' => 'LOST',
                'hasAirbnb' => false,
                'googleRating' => 3.5,
                'googleReviews' => 12,
                'category' => 'Location saisonnière',
                'notes' => 'Pas intéressé pour le moment, à recontacter dans 6 mois.',
            ],
        ];

        foreach ($leads as $data) {
            $lead = new Lead();
            $lead->setCompanyName($data['companyName']);
            $lead->setContactName($data['contactName'] ?? null);
            $lead->setPhone($data['phone'] ?? null);
            $lead->setEmail($data['email'] ?? null);
            $lead->setCity($data['city']);
            $lead->setSource($data['source']);
            $lead->setScore($data['score']);
            $lead->setStatus($data['status']);
            $lead->setHasAirbnb($data['hasAirbnb']);
            $lead->setGoogleRating($data['googleRating'] ?? null);
            $lead->setGoogleReviews($data['googleReviews'] ?? null);
            $lead->setNotes($data['notes'] ?? null);
            $lead->setNextFollowUp($data['nextFollowUp'] ?? null);
            if (isset($catMap[$data['category']])) {
                $lead->setCategory($catMap[$data['category']]);
            }
            $manager->persist($lead);
        }

        $manager->flush();
    }
}