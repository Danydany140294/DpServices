<?php

namespace App\DataFixtures;

use App\Entity\LeadCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class LeadCategoryFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['lead_categories'];
    }

    public function load(ObjectManager $manager): void
    {
        $categories = [
            ['name' => 'Conciergerie Airbnb', 'scoreBonus' => 50],
            ['name' => 'Gestion locative', 'scoreBonus' => 30],
            ['name' => 'Agence immobilière', 'scoreBonus' => 20],
            ['name' => 'Location saisonnière', 'scoreBonus' => 25],
            ['name' => 'Hôtellerie', 'scoreBonus' => 35],
            ['name' => 'Services immobiliers', 'scoreBonus' => 15],
            ['name' => 'Ménage professionnel', 'scoreBonus' => 40],
            ['name' => 'Ménage particulier', 'scoreBonus' => 20],
        ];

        foreach ($categories as $cat) {
            $category = new LeadCategory();
            $category->setName($cat['name']);
            $category->setScoreBonus($cat['scoreBonus']);
            $manager->persist($category);
        }

        $manager->flush();
    }
}