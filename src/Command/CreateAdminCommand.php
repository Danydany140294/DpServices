<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un compte administrateur (email + mot de passe saisis interactivement, jamais en dur dans le code).',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Création d\'un compte administrateur');

        // Comparer directement une colonne JSON avec '=' ne fonctionne pas
        // sous PostgreSQL (SQLSTATE 42883) : on récupère tous les users et
        // on filtre en PHP plutôt qu'en SQL, pour rester compatible avec
        // n'importe quel moteur (MySQL/PostgreSQL/SQLite).
        $allUsers = $this->entityManager->getRepository(User::class)->findAll();
        $existingAdmin = null;
        foreach ($allUsers as $u) {
            if (in_array('ROLE_ADMIN', $u->getRoles(), true)) {
                $existingAdmin = $u;
                break;
            }
        }

        if ($existingAdmin !== null) {
            $io->warning(sprintf('Un admin existe déjà : %s. Cette commande va tout de même en créer un nouveau si tu continues.', $existingAdmin->getEmail()));
            if (!$io->confirm('Continuer quand même ?', false)) {
                return Command::SUCCESS;
            }
        }

        $email = $io->ask('Email de l\'administrateur');
        $firstname = $io->ask('Prénom');
        $lastname = $io->ask('Nom');

        $passwordQuestion = new Question('Mot de passe (min. 12 caractères, ne s\'affiche pas à l\'écran)');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $password = $this->getHelper('question')->ask($input, $output, $passwordQuestion);

        if ($password === null || strlen($password) < 12) {
            $io->error('Mot de passe trop court (minimum 12 caractères).');

            return Command::FAILURE;
        }

        $confirmQuestion = new Question('Confirme le mot de passe');
        $confirmQuestion->setHidden(true);
        $confirmQuestion->setHiddenFallback(false);
        $confirm = $this->getHelper('question')->ask($input, $output, $confirmQuestion);

        if ($password !== $confirm) {
            $io->error('Les mots de passe ne correspondent pas.');

            return Command::FAILURE;
        }

        $admin = new User();
        $admin->setEmail($email);
        $admin->setFirstname($firstname);
        $admin->setLastname($lastname);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, $password));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Compte administrateur créé : %s', $email));

        return Command::SUCCESS;
    }
}