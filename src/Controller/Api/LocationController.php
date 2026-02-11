<?php

namespace App\Controller\Api;

use App\Service\SchoolDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class LocationController extends AbstractController
{
    public function __construct(
        private SchoolDataProvider $schoolDataProvider
    ) {}

    #[Route('/location/schools/{countryCode}', name: 'location_schools', methods: ['GET'])]
    public function getSchools(string $countryCode): JsonResponse
    {
        $schools = $this->schoolDataProvider->getEtablissements($countryCode);
        return $this->json($schools);
    }

    #[Route('/location/levels/{countryCode}', name: 'location_levels', methods: ['GET'])]
    public function getLevels(string $countryCode): JsonResponse
    {
        $levels = $this->schoolDataProvider->getNiveaux($countryCode);
        return $this->json($levels);
    }
}
