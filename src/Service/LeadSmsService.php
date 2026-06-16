<?php

namespace App\Service;

use App\Entity\Lead;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LeadSmsService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $brevoApiKey
    ) {}

    public function sendSms(Lead $lead, string $message): void
    {
        if (!$lead->getPhone()) {
            throw new \Exception('Ce prospect n\'a pas de numéro de téléphone.');
        }

        $response = $this->httpClient->request('POST', 'https://api.brevo.com/v3/transactionalSMS/sms', [
            'headers' => [
                'api-key' => $this->brevoApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'sender' => 'DPServices',
                'recipient' => $lead->getPhone(),
                'content' => $message,
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \Exception('Erreur envoi SMS : ' . $response->getContent(false));
        }
    }

    public function sendGroupSms(array $leads, string $message): int
    {
        $sent = 0;
        foreach ($leads as $lead) {
            if ($lead->getPhone()) {
                try {
                    $this->sendSms($lead, $message);
                    $sent++;
                } catch (\Throwable $e) {
                    // Continue
                }
            }
        }
        return $sent;
    }
}