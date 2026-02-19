<?php

namespace App\Controller\Bo;

use App\Repository\AiGenerationLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AiGatewayService;

#[Route('/bo/ai-monitoring', name: 'bo_ai_monitoring_')]
class AiMonitoringController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        AiGenerationLogRepository $logRepo,
        AiGatewayService $aiGateway
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        // Get stats from database
        $totalLogs = $logRepo->count([]);
        $successLogs = $logRepo->count(['status' => 'success']);
        $failedLogs = $logRepo->count(['status' => 'failed']);
        $pendingLogs = $logRepo->count(['status' => 'pending']);

        $failureRate = $totalLogs > 0 ? round(($failedLogs / $totalLogs) * 100, 1) : 0;

        // Get recent logs
        $recentLogs = $logRepo->findBy([], ['createdAt' => 'DESC'], 20);

        // Calculate average latency
        $qb = $logRepo->createQueryBuilder('l');
        $avgLatency = $qb
            ->select('AVG(l.latencyMs)')
            ->where('l.latencyMs IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        // Stats by feature
        $statsByFeature = $logRepo->createQueryBuilder('l')
            ->select('l.feature, COUNT(l.id) as count, AVG(l.latencyMs) as avgLatency')
            ->groupBy('l.feature')
            ->getQuery()
            ->getResult();

        // Try to get FastAPI stats
        $apiStats = null;
        try {
            $apiStats = $aiGateway->getStats();
        } catch (\Exception $e) {
            // API not available, use DB stats only
        }

        // Get feedback stats
        $feedbackStats = $logRepo->createQueryBuilder('l')
            ->select('AVG(l.userFeedback) as avgFeedback, COUNT(l.id) as totalFeedback')
            ->where('l.userFeedback IS NOT NULL')
            ->getQuery()
            ->getSingleResult();

        // Top 5 most frequent errors
        $topErrors = $logRepo->createQueryBuilder('l')
            ->select('l.errorMessage, COUNT(l.id) as cnt')
            ->where('l.status = :failed')
            ->andWhere('l.errorMessage IS NOT NULL')
            ->andWhere("l.errorMessage != ''")
            ->setParameter('failed', 'failed')
            ->groupBy('l.errorMessage')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('bo/ai_monitoring/dashboard.html.twig', [
            'totalLogs' => $totalLogs,
            'successLogs' => $successLogs,
            'failedLogs' => $failedLogs,
            'pendingLogs' => $pendingLogs,
            'failureRate' => $failureRate,
            'avgLatency' => $avgLatency ? round($avgLatency, 0) : 0,
            'recentLogs' => $recentLogs,
            'statsByFeature' => $statsByFeature,
            'apiStats' => $apiStats,
            'avgFeedback' => $feedbackStats['avgFeedback'] ? round($feedbackStats['avgFeedback'], 2) : null,
            'totalFeedback' => $feedbackStats['totalFeedback'],
            'topErrors' => $topErrors,
        ]);
    }

    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function logs(
        Request $request,
        AiGenerationLogRepository $logRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $feature = $request->query->get('feature');
        $status = $request->query->get('status');
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 50;

        $qb = $logRepo->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC');

        if ($feature) {
            $qb->andWhere('l.feature = :feature')
               ->setParameter('feature', $feature);
        }

        if ($status) {
            $qb->andWhere('l.status = :status')
               ->setParameter('status', $status);
        }

        $totalLogs = (int) (clone $qb)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $logs = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalLogs / $perPage);

        return $this->render('bo/ai_monitoring/logs.html.twig', [
            'logs' => $logs,
            'totalLogs' => $totalLogs,
            'page' => $page,
            'totalPages' => $totalPages,
            'feature' => $feature,
            'status' => $status,
        ]);
    }
}
