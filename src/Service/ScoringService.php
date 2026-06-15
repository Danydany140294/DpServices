<?php

namespace App\Service;

use App\Entity\Lead;

class ScoringService
{
    public function calculate(Lead $lead): int
    {
        $score = 0;

        // Catégorie
        $category = $lead->getCategory()?->getName();
        $score += match(true) {
            str_contains((string)$category, 'Airbnb')         => 50,
            str_contains((string)$category, 'locative')       => 30,
            str_contains((string)$category, 'immobilière')    => 20,
            str_contains((string)$category, 'saisonnière')    => 20,
            str_contains((string)$category, 'Hôtellerie')     => 20,
            str_contains((string)$category, 'professionnel')  => 15,
            str_contains((string)$category, 'particulier')    => 10,
            default => 0,
        };

        // Note Google
        $rating = $lead->getGoogleRating();
        if ($rating >= 4.5) $score += 10;

        // Nombre d'avis
        $reviews = $lead->getGoogleReviews();
        if ($reviews >= 100)     $score += 20;
        elseif ($reviews >= 50)  $score += 10;

        // Site web renseigné
        if ($lead->getWebsite()) $score += 10;

        // Ville prioritaire
        $priorityCities = ['montpellier', 'nîmes', 'sète', 'béziers', 'perpignan'];
        if (in_array(strtolower((string)$lead->getCity()), $priorityCities)) $score += 10;

        return min($score, 100);
    }
}