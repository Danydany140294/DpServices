<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer
    ) {}

    public function sendWelcome(string $to, string $firstname, string $password): void
    {
        $email = (new Email())
            ->from('dpservicesud@gmail.com')
            ->to($to)
            ->subject('Bienvenue sur DP Services')
            ->html(
                '<h1>Bienvenue ' . $firstname . ' !</h1>' .
                '<p>Votre compte a été créé sur DP Services.</p>' .
                '<p><strong>Email :</strong> ' . $to . '</p>' .
                '<p><strong>Mot de passe :</strong> ' . $password . '</p>' .
                '<p>Connectez-vous sur <a href="http://localhost:8080/login">DP Services</a></p>'
            );

        $this->mailer->send($email);
    }

    public function sendMissionAssigned(string $to, string $firstname, string $property, string $date, string $time): void
    {
        $email = (new Email())
            ->from('dpservicesud@gmail.com')
            ->to($to)
            ->subject('Nouvelle mission assignée — ' . $property)
            ->html(
                '<h1>Nouvelle mission, ' . $firstname . ' !</h1>' .
                '<p>Une nouvelle mission vous a été assignée :</p>' .
                '<ul>' .
                '<li><strong>Logement :</strong> ' . $property . '</li>' .
                '<li><strong>Date :</strong> ' . $date . '</li>' .
                '<li><strong>Heure :</strong> ' . $time . '</li>' .
                '</ul>' .
                '<p>Connectez-vous sur DP Services pour voir les détails.</p>'
            );

        $this->mailer->send($email);
    }

    public function sendMissionCompleted(string $to, string $firstname, string $property, string $date): void
    {
        $email = (new Email())
            ->from('dpservicesud@gmail.com')
            ->to($to)
            ->subject('Mission terminée — ' . $property)
            ->html(
                '<h1>Mission terminée !</h1>' .
                '<p>La mission suivante a été marquée comme terminée :</p>' .
                '<ul>' .
                '<li><strong>Logement :</strong> ' . $property . '</li>' .
                '<li><strong>Date :</strong> ' . $date . '</li>' .
                '</ul>'
            );

        $this->mailer->send($email);
    }
}