<?php

namespace App\Controller\Bo;

use App\Entity\Quiz;
use App\Form\Bo\QuizType;
use App\Repository\QuizRepository;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\CsvExportService;

#[Route('/bo/quizzes', name: 'bo_quizzes_')]
class QuizController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, QuizRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir = strtolower($request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;
        $allowedSort = ['id', 'title', 'difficulty', 'isPublished', 'createdAt'];
        if (!in_array($sort, $allowedSort)) $sort = 'id';

        $qb = $repo->createQueryBuilder('qz');
        if ($q) $qb->where('qz.title LIKE :search')->setParameter('search', "%$q%");
        $qb->orderBy("qz.$sort", $dir);

        $total = (int) (clone $qb)->select('COUNT(qz.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('bo/quizzes/index.html.twig', [
            'items' => $items, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'page' => $page, 'totalPages' => (int) ceil($total / $perPage), 'total' => $total,
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(QuizRepository $repo, CsvExportService $csv): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $quizzes = $repo->findBy([], ['id' => 'DESC']);

        $rows = array_map(fn($qz) => [
            $qz->getId(),
            $qz->getTitle(),
            $qz->getDifficulty(),
            count($qz->getQuestions() ?? []),
            $qz->isPublished() ? 'Oui' : 'Non',
            $qz->getSubject()?->getName() ?? '-',
            $qz->getCreatedAt()?->format('d/m/Y H:i'),
        ], $quizzes);

        return $csv->export(
            'quizzes_export_' . date('Y-m-d') . '.csv',
            ['ID', 'Titre', 'Difficulté', 'Nb Questions', 'Publié', 'Matière', 'Créé le'],
            $rows
        );
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new Quiz();
        $form = $this->createForm(QuizType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $questionsJson = $form->get('questionsJson')->getData();
            if ($questionsJson) {
                $decoded = json_decode($questionsJson, true);
                if (is_array($decoded)) {
                    $item->setQuestions($decoded);
                }
            }
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Quiz créé.');
            return $this->redirectToRoute('bo_quizzes_index');
        }
        return $this->render('bo/quizzes/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Quiz $item, QuizAttemptRepository $attemptRepo): Response
    {
        $attempts = $attemptRepo->findByQuiz($item);
        $avgScore = $attemptRepo->getAverageScoreForQuiz($item);
        $completedCount = $attemptRepo->getCompletedCountForQuiz($item);

        // Calculate pass rate (score >= 50)
        $passedCount = 0;
        foreach ($attempts as $attempt) {
            if ($attempt->isCompleted() && $attempt->getScore() >= 50) {
                $passedCount++;
            }
        }
        $passRate = $completedCount > 0 ? ($passedCount / $completedCount) * 100 : 0;

        return $this->render('bo/quizzes/show.html.twig', [
            'item' => $item,
            'attempts' => $attempts,
            'avgScore' => $avgScore,
            'completedCount' => $completedCount,
            'passRate' => $passRate,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Quiz $item, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(QuizType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $questionsJson = $form->get('questionsJson')->getData();
            if ($questionsJson) {
                $decoded = json_decode($questionsJson, true);
                if (is_array($decoded)) {
                    $item->setQuestions($decoded);
                }
            }
            $em->flush();
            $this->addFlash('success', 'Quiz modifié.');
            return $this->redirectToRoute('bo_quizzes_index');
        }
        return $this->render('bo/quizzes/edit.html.twig', ['form' => $form, 'item' => $item]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Quiz $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Quiz supprimé.');
        }
        return $this->redirectToRoute('bo_quizzes_index');
    }
}
