<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service d'envoi de SMS aux salariés (femmes de ménage), via l'API Brevo.
 * Reprend la même logique que LeadSmsService (V2), adaptée à l'entité User
 * plutôt que Lead, pour ne pas coupler les deux usages.
 */
class CleanerSmsService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $brevoApiKey,
    ) {
    }

    /**
     * Envoie un SMS à un salarié. Ne lève pas d'exception bloquante si le
     * salarié n'a pas de téléphone : l'appelant doit pouvoir continuer
     * (notification in-app envoyée même si le SMS échoue).
     */
    public function sendSms(User $recipient, string $message): void
    {
        if (!$recipient->getPhone()) {
            throw new \Exception(sprintf(
                '%s %s n\'a pas de numéro de téléphone renseigné.',
                $recipient->getFirstname(),
                $recipient->getLastname()
            ));
        }

        $phone = $this->normalizePhoneNumber($recipient->getPhone());

        $response = $this->httpClient->request('POST', 'https://api.brevo.com/v3/transactionalSMS/sms', [
            'headers' => [
                'api-key' => $this->brevoApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'sender' => 'DPServices',
                'recipient' => $phone,
                'content' => $message,
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \Exception('Erreur envoi SMS : ' . $response->getContent(false));
        }
    }

    /**
     * Normalise un numéro de téléphone français vers le format international
     * attendu par Brevo (ex: "0673297542" -> "+33673297542").
     * Si le numéro est déjà au format international (+33... ou 0033...),
     * il est conservé tel quel.
     */
    private function normalizePhoneNumber(string $phone): string
    {
        $phone = trim($phone);
        $phone = preg_replace('/[\s.\-()]/', '', $phone);

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '0033')) {
            return '+' . substr($phone, 2);
        }

        if (str_starts_with($phone, '0')) {
            return '+33' . substr($phone, 1);
        }

        return $phone;
    }
}