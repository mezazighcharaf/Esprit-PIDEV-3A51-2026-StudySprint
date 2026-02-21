<?php

namespace App\Controller\Bo;

use App\Repository\UserRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/statistiques')]
class StatisticsController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    #[Route('', name: 'admin_statistics')]
    public function index(): Response
    {
        $stats = $this->getStatistics();

        return $this->render('bo/statistics/index.html.twig', [
            'stats' => $stats,
        ]);
    }

    #[Route('/export-pdf', name: 'admin_statistics_pdf')]
    public function exportPdf(): Response
    {
        $stats = $this->getStatistics();

        // Configure Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($pdfOptions);
        
        // Render HTML for PDF
        $html = $this->renderView('bo/statistics/pdf.html.twig', [
            'stats' => $stats,
            'generatedAt' => new \DateTime(),
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="statistiques_studysprint_'.date('Y-m-d').'.pdf"',
        ]);
    }

    private function getStatistics(): array
    {
        // Fetch raw statistics from Repository
        $registrationStats = $this->userRepository->countByRegistrationYear();
        $ageStatsRaw = $this->userRepository->countStudentsByAgeRange();
        $countryStats = $this->userRepository->countUsersByCountry();
        $profExperienceStats = $this->userRepository->countProfessorExperience();
        $userKpis = $this->userRepository->getUsersKpiData();

        // Process Age Distribution for Chart.js
        $ageLabels = ['Moins de 18', '18-25', '26-35', 'Plus de 35'];
        $maleData = array_fill(0, 4, 0);
        $femaleData = array_fill(0, 4, 0);

        foreach ($ageStatsRaw as $row) {
            $labelIndex = array_search($row['ageRange'], $ageLabels);
            if ($labelIndex !== false) {
                if (($row['sex'] ?? 'H') === 'H') {
                    $maleData[$labelIndex] = (int) $row['count'];
                } elseif (($row['sex'] ?? '') === 'F') {
                    $femaleData[$labelIndex] = (int) $row['count'];
                }
            }
        }

        return [
            'registrations' => $registrationStats,
            'age_distribution' => [
                'labels' => $ageLabels,
                'datasets' => [
                    ['label' => 'Hommes', 'data' => $maleData],
                    ['label' => 'Femmes', 'data' => $femaleData],
                ]
            ],
            'countries' => $countryStats,
            'professor_experience' => $profExperienceStats,
            'professor_hierarchy' => $this->userRepository->countProfessorsByCountryAndEstablishment(),
            'student_hierarchy' => $this->userRepository->countStudentsByCountryAndEstablishment(),
            'user_kpis' => $userKpis,
        ];
    }
}
