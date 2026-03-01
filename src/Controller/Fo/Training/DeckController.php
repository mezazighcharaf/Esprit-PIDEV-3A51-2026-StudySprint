<?php

namespace App\Controller\Fo\Training;

use App\Entity\FlashcardReviewState;
use App\Repository\FlashcardDeckRepository;
use App\Repository\FlashcardRepository;
use App\Repository\FlashcardReviewStateRepository;
use App\Repository\UserRepository;
use App\Repository\SubjectRepository;
use App\Repository\ChapterRepository;
use App\Service\Sm2SchedulerService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AiGatewayService;
use App\Service\PdfExportService;

#[Route('/fo/training/decks', name: 'fo_training_decks_')]
class DeckController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, FlashcardDeckRepository $repository, SubjectRepository $subjectRepo, PaginatorInterface $paginator): Response
    {
        $q = $request->query->get('q');
        $subjectId = $request->query->getInt('subject') ?: null;
        $sort = $request->query->get('sort', 'newest');

        $queryBuilder = $repository->searchPublishedQuery($q, $subjectId, $sort);
        $pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 9, [
            'sort_field_allow_list' => [],
        ]);
        $subjects = $subjectRepo->findAll();

        return $this->render('fo/training/decks/index.html.twig', [
            'pagination' => $pagination,
            'subjects' => $subjects,
            'q' => $q,
            'subjectId' => $subjectId,
            'sort' => $sort,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        FlashcardDeckRepository $deckRepo,
        FlashcardReviewStateRepository $stateRepo,
        UserRepository $userRepo
    ): Response {
        $deck = $deckRepo->find($id);

        if (!$deck) {
            throw $this->createNotFoundException('Deck introuvable');
        }

        if (!$deck->isPublished()) {
            throw $this->createAccessDeniedException('Ce deck n\'est pas encore publié.');
        }

        $user = $this->getUser() ?? $userRepo->findOneBy([]);
        $dueCount = 0;

        if ($user) {
            $dueCount = $stateRepo->countDueCardsForUserAndDeck($user, $deck);
        }

        return $this->render('fo/training/decks/show.html.twig', [
            'deck' => $deck,
            'dueCount' => $dueCount,
        ]);
    }

    #[Route('/{id}/review', name: 'review', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function review(
        int $id,
        FlashcardDeckRepository $deckRepo,
        FlashcardRepository $flashcardRepo,
        FlashcardReviewStateRepository $stateRepo,
        UserRepository $userRepo,
        Sm2SchedulerService $sm2Service,
        EntityManagerInterface $em
    ): Response {
        $deck = $deckRepo->find($id);

        if (!$deck || !$deck->isPublished()) {
            throw $this->createNotFoundException('Deck introuvable ou non publié');
        }

        $user = $this->getUser() ?? $userRepo->findOneBy([]);
        if (!$user) {
            $this->addFlash('error', 'Aucun utilisateur disponible.');
            return $this->redirectToRoute('fo_training_decks_index');
        }

        // Get due cards first
        $dueStates = $stateRepo->findDueCardsForUserAndDeck($user, $deck, 1);

        if (!empty($dueStates)) {
            $state = $dueStates[0];
            $flashcard = $state->getFlashcard();
            $nextDates = $sm2Service->getNextReviewDates($state);

            return $this->render('fo/training/decks/review.html.twig', [
                'deck' => $deck,
                'flashcard' => $flashcard,
                'state' => $state,
                'nextDates' => $nextDates,
                'isNew' => false,
            ]);
        }

        // Get new cards if no due cards
        $newCards = $stateRepo->findNewCardsForUserAndDeck($user, $deck, 1);

        if (!empty($newCards)) {
            $flashcard = $newCards[0];

            // Create initial state for new card
            $state = $sm2Service->createInitialState($user, $flashcard);
            $em->persist($state);
            $em->flush();

            $nextDates = $sm2Service->getNextReviewDates($state);

            return $this->render('fo/training/decks/review.html.twig', [
                'deck' => $deck,
                'flashcard' => $flashcard,
                'state' => $state,
                'nextDates' => $nextDates,
                'isNew' => true,
            ]);
        }

        // No cards to review
        return $this->redirectToRoute('fo_training_decks_review_complete', ['id' => $id]);
    }

    #[Route('/{id}/review/grade', name: 'review_grade', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reviewGrade(
        int $id,
        Request $request,
        FlashcardDeckRepository $deckRepo,
        FlashcardReviewStateRepository $stateRepo,
        Sm2SchedulerService $sm2Service,
        EntityManagerInterface $em
    ): Response {
        $deck = $deckRepo->find($id);

        if (!$deck || !$deck->isPublished()) {
            throw $this->createNotFoundException('Deck introuvable ou non publié');
        }

        $stateId = $request->request->getInt('state_id');
        $button = $request->request->get('button', 'good');

        if (!$this->isCsrfTokenValid('grade_card_' . $stateId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_training_decks_review', ['id' => $id]);
        }

        $state = $stateRepo->find($stateId);

        if (!$state) {
            $this->addFlash('error', 'État de révision introuvable.');
            return $this->redirectToRoute('fo_training_decks_review', ['id' => $id]);
        }

        try {
            $quality = $sm2Service->buttonToQuality($button);
            $sm2Service->applyReview($state, $quality);
            $em->flush();

            return $this->redirectToRoute('fo_training_decks_review', ['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('fo_training_decks_review', ['id' => $id]);
        }
    }

    #[Route('/{id}/review/complete', name: 'review_complete', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function reviewComplete(
        int $id,
        FlashcardDeckRepository $deckRepo,
        FlashcardReviewStateRepository $stateRepo,
        UserRepository $userRepo
    ): Response {
        $deck = $deckRepo->find($id);

        if (!$deck) {
            throw $this->createNotFoundException('Deck introuvable');
        }

        $user = $this->getUser() ?? $userRepo->findOneBy([]);
        $dueCount = 0;
        $totalReviewed = 0;

        if ($user) {
            $dueCount = $stateRepo->countDueCardsForUserAndDeck($user, $deck);

            // Count reviewed today
            $today = new \DateTimeImmutable('today');
            $totalReviewed = (int) $stateRepo->createQueryBuilder('rs')
                ->select('COUNT(rs.id)')
                ->join('rs.flashcard', 'f')
                ->andWhere('rs.user = :user')
                ->andWhere('f.deck = :deck')
                ->andWhere('rs.lastReviewedAt >= :today')
                ->setParameter('user', $user)
                ->setParameter('deck', $deck)
                ->setParameter('today', $today)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $this->render('fo/training/decks/review_complete.html.twig', [
            'deck' => $deck,
            'dueCount' => $dueCount,
            'totalReviewed' => $totalReviewed,
        ]);
    }

    #[Route('/ai-generate', name: 'ai_generate_form', methods: ['GET'])]
    public function aiGenerateForm(
        SubjectRepository $subjectRepo,
        ChapterRepository $chapterRepo
    ): Response {
        $subjects = $subjectRepo->findAll();
        $chapters = $chapterRepo->findAll();

        return $this->render('fo/training/decks/ai_generate.html.twig', [
            'subjects' => $subjects,
            'chapters' => $chapters,
        ]);
    }

    #[Route('/ai-generate', name: 'ai_generate', methods: ['POST'])]
    public function aiGenerate(
        Request $request,
        AiGatewayService $aiGateway,
        SubjectRepository $subjectRepo,
        UserRepository $userRepo
    ): Response {
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        $token = $isAjax
            ? (json_decode($request->getContent(), true)['_token'] ?? '')
            : $request->request->get('_token');

        if (!$this->isCsrfTokenValid('ai_generate_deck', $token)) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
            }
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_training_decks_ai_generate_form');
        }

        $user = $this->getUser() ?? $userRepo->findOneBy([]);
        if (!$user) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Aucun utilisateur disponible'], 401);
            }
            $this->addFlash('error', 'Aucun utilisateur disponible.');
            return $this->redirectToRoute('fo_training_decks_index');
        }

        if ($isAjax) {
            $body = json_decode($request->getContent(), true);
            $subjectId = (int) ($body['subject_id'] ?? 0);
            $chapterId = isset($body['chapter_id']) ? (int) $body['chapter_id'] : null;
            $numCards = (int) ($body['num_cards'] ?? 10);
            $includeHints = (bool) ($body['include_hints'] ?? true);
            $topic = $body['topic'] ?? null;
        } else {
            $subjectId = $request->request->getInt('subject_id');
            $chapterId = $request->request->get('chapter_id') ? $request->request->getInt('chapter_id') : null;
            $numCards = $request->request->getInt('num_cards', 10);
            $includeHints = $request->request->getBoolean('include_hints', true);
            $topic = $request->request->get('topic');
        }

        $subject = $subjectRepo->find($subjectId);
        if (!$subject) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Matière non trouvée'], 404);
            }
            $this->addFlash('error', 'Matière non trouvée.');
            return $this->redirectToRoute('fo_training_decks_ai_generate_form');
        }

        // Call FastAPI AI Gateway
        try {
            $data = $aiGateway->generateFlashcards(
                $user->getId(),
                $subjectId,
                $chapterId,
                $numCards,
                $topic,
                $includeHints
            );

            $deckId = $data['deck_id'] ?? null;

            if ($deckId) {
                if ($isAjax) {
                    return new JsonResponse([
                        'success' => true,
                        'deck_id' => $deckId,
                        'cards_count' => $data['cards_count'] ?? 0,
                        'ai_log_id' => $data['ai_log_id'] ?? null,
                        'redirect_url' => $this->generateUrl('fo_training_decks_show', ['id' => $deckId]),
                    ]);
                }
                $this->addFlash('success', sprintf(
                    'Deck généré avec succès ! %d cartes créées.',
                    $data['cards_count'] ?? 0
                ));
                return $this->redirectToRoute('fo_training_decks_show', ['id' => $deckId]);
            }
        } catch (\Exception $e) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Service IA indisponible: ' . $e->getMessage()], 503);
            }
            $this->addFlash('error', 'Impossible de contacter le service IA: ' . $e->getMessage());
        }

        return $this->redirectToRoute('fo_training_decks_ai_generate_form');
    }

    #[Route('/{id}/export-pdf', name: 'export_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function exportPdf(int $id, FlashcardDeckRepository $deckRepo, PdfExportService $pdfService): Response
    {
        $deck = $deckRepo->find($id);

        if (!$deck || !$deck->isPublished()) {
            throw $this->createNotFoundException('Deck introuvable ou non publié');
        }

        $cards = $deck->getFlashcards()->count() > 0 ? $deck->getFlashcards()->toArray() : $deck->getCards()->toArray();

        return $pdfService->generateFromTemplate('pdf/flashcard_deck.html.twig', [
            'deck' => $deck,
            'cards' => $cards,
        ], 'flashcards-' . $deck->getId() . '.pdf');
    }
}
