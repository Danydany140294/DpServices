<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Admin
        $admin = new User();
        $admin->setEmail('admin@dpservices.fr');
        $admin->setFirstname('Dany');
        $admin->setLastname('Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin1234'));
        $manager->persist($admin);

        // Propriétaire
        $owner = new User();
        $owner->setEmail('owner@dpservices.fr');
        $owner->setFirstname('Jean');
        $owner->setLastname('Propriétaire');
        $owner->setRoles(['ROLE_OWNER']);
        $owner->setPassword($this->hasher->hashPassword($owner, 'owner1234'));
        $manager->persist($owner);

        // Femme de ménage
        $cleaner = new User();
        $cleaner->setEmail('cleaner@dpservices.fr');
        $cleaner->setFirstname('Marie');
        $cleaner->setLastname('Ménage');
        $cleaner->setRoles(['ROLE_CLEANER']);
        $cleaner->setPassword($this->hasher->hashPassword($cleaner, 'cleaner1234'));
        $manager->persist($cleaner);

        $manager->flush();
    }
}