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