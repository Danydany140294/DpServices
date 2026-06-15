<?php

namespace App\Command;

use App\Repository\LeadRepository;
use App\Service\GooglePlacesService;
use App\Service\ScoringService;
use App\Entity\Lead;
use App\Repository\LeadCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:nightly-search', description: 'Recherche nocturne automatique de prospects')]
class NightlySearchCommand extends Command
{
    public function __construct(
        private GooglePlacesService $places,
        private LeadRepository $leadRepo,
        private LeadCategoryRepository $categoryRepo,
        private EntityManagerInterface $em,
        private ScoringService $scoring,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cities = ['Montpellier', 'Nîmes', 'Sète', 'Béziers'];
        $types  = ['conciergerie airbnb', 'gestion locative', 'location saisonnière'];

        $imported = 0;
        $skipped  = 0;

        // Noms existants normalisés
        $existing = array_map(
            fn(Lead $l) => strtolower(trim($l->getCompanyName())),
            $this->leadRepo->findAll()
        );

        foreach ($cities as $city) {
            foreach ($types as $type) {
                $output->writeln("🔍 $type à $city...");
                $results = $this->places->searchPlaces($type, $city);

                foreach ($results as $place) {
                    $normalized = strtolower(trim($place['name']));
                    if (in_array($normalized, $existing)) {
                        $skipped++;
                        continue;
                    }

                    $categoryName = match(true) {
                        str_contains($type, 'airbnb')    => 'Conciergerie Airbnb',
                        str_contains($type, 'locative')  => 'Gestion locative',
                        default                          => 'Location saisonnière',
                    };
                    $category = $this->categoryRepo->findOneBy(['name' => $categoryName]);

                    $lead = new Lead();
                    $lead->setCompanyName($place['name']);
                    $lead->setCity($city);
                    $lead->setSource('Nightly Search');
                    $lead->setCategory($category);
                    $lead->setScore($this->scoring->calculate($lead));

                    $this->em->persist($lead);
                    $existing[] = $normalized;
                    $imported++;
                }
            }
        }

        $this->em->flush();
        $output->writeln("✅ $imported importés, $skipped ignorés (doublons).");
        return Command::SUCCESS;
    }
}