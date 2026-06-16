<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:test-mail', description: 'Test envoi email Brevo')]
class TestMailCommand extends Command
{
    public function __construct(private MailerInterface $mailer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Envoi en cours...');

        $email = (new Email())
            ->from('dpservicesud@gmail.com')
            ->to('danoninolaissesa@gmail.com')
            ->subject('Test DP Services')
            ->html('<p>Test email depuis Symfony + Brevo</p>');

        try {
            $this->mailer->send($email);
            $output->writeln('✅ Email envoyé !');
        } catch (\Throwable $e) {
            $output->writeln('❌ Erreur : ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}