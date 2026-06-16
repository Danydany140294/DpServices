<?php

namespace App\Service;

use App\Entity\Lead;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class LeadEmailService
{
    public function __construct(private MailerInterface $mailer) {}

    public function sendProspectionEmail(Lead $lead, string $subject, string $body): void
    {
        if (!$lead->getEmail()) {
            throw new \Exception('Ce prospect n\'a pas d\'adresse email.');
        }

        $email = (new Email())
            ->from('dpservicesud@gmail.com')
            ->to($lead->getEmail())
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }

    public function sendGroupEmail(array $leads, string $subject, string $body): int
    {
        $sent = 0;
        foreach ($leads as $lead) {
            if ($lead->getEmail()) {
                try {
                    $this->sendProspectionEmail($lead, $subject, $body);
                    $sent++;
                } catch (\Throwable $e) {
                    // Continue
                }
            }
        }
        return $sent;
    }
}