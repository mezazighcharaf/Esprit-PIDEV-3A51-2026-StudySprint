<?php

namespace App\Controller\Fo\Training;

use App\Entity\QuizAttempt;
use App\Entity\User;
use App\Repository\QuizRepository;
use App\Repository\QuizAttemptRepository;
use App\Repository\UserRepository;
use App\Repository\SubjectRepository;
use App\Repository\ChapterRepository;
use App\Service\QuizScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\AiGatewayService;
use App\Service\BadgeService;
use App\Service\StreakService;
use App\Service\NotificationService;
use App\Service\PdfExportService;
use App\Service\QrCodeService;
use App\Entity\QuizRating;
use App\Repository\QuizRatingRepository;

#[Route('/fo/training/quizzes', name: 'fo_training_quizzes_')]
/**
 * @method \App\Entity\User|null getUser()
 */
class QuizController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, QuizRepository $repository, SubjectRepository $subjectRepo, PaginatorInterface $paginator): Response
    {
        $q = $request->query->get('q');
        $difficulty = $request->query->get('difficulty');
        $subjectId = $request->query->getInt('subject') ?: null;
        $sort = $request->query->get('sort', 'newest');

        $queryBuilder = $repository->searchPublishedQuery($q, $difficulty, $subjectId, $sort);
        $pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 9, [
            'sort_field_allow_list' => [],
        ]);
        $subjects = $subjectRepo->findAll();

        return $this->render('fo/training/quizzes/index.html.twig', [
            'pagination' => $pagination,
            'subjects' => $subjects,
            'q' => $q,
            'difficulty' => $difficulty,
            'subjectId' => $subjectId,
            'sort' => $sort,
        ]);
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(QuizAttemptRepository $attemptRepo, UserRepository $userRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser() ?? $userRepo->findOneBy([]);

        $attempts = $attemptRepo->findByUser($user, 50);

        return $this->render('fo/training/quizzes/history.html.twig', [
            'attempts' => $attempts,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, QuizRepository $repository, QuizRatingRepository $ratingRepo): Response
    {
        $quiz = $repository->find($id);

        if (!$quiz) {
            throw $this->createNotFoundException('Quiz introuvable');
        }

        if (!$quiz->isPublished()) {
            throw $this->createAccessDeniedException('Ce quiz n\'est pas encore publié.');
        }

        $avgRating = $ratingRepo->getAverageScore($quiz);
        $ratingCount = $ratingRepo->getRatingCount($quiz);
        $userRating = null;
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser) {
            $userRating = $ratingRepo->findByUserAndQuiz($currentUser, $quiz);
        }

        return $this->render('fo/training/quizzes/show.html.twig', [
            'quiz' => $quiz,
            'avgRating' => $avgRating,
            'ratingCount' => $ratingCount,
            'userRating' => $userRating,
        ]);
    }

    #[Route('/{id}/start', name: 'start', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function start(
        int $id,
        Request $request,
        QuizRepository $quizRepo,
        QuizAttemptRepository $attemptRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $quiz = $quizRepo->find($id);

        if (!$quiz || !$quiz->isPublished()) {
            throw $this->createNotFoundException('Quiz introuvable ou non publié');
        }

        if (!$this->isCsrfTokenValid('start_quiz_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_training_quizzes_show', ['id' => $id]);
        }

        /** @var User $user */
        $user = $this->getUser() ?? $userRepo->findOneBy([]);

        // Check for existing incomplete attempt
        $existingAttempt = $attemptRepo->findIncompleteByUserAndQuiz($user, $quiz);
        if ($existingAttempt) {
            return $this->redirectToRoute('fo_training_quizzes_play', [
                'id' => $id,
                'attempt' => $existingAttempt->getId(),
            ]);
        }

        // Create new attempt
        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);
        $attempt->setTotalQuestions(count($quiz->getQuestions()));

        $em->persist($attempt);
        $em->flush();

        return $this->redirectToRoute('fo_training_quizzes_play', [
            'id' => $id,
            'attempt' => $attempt->getId(),
        ]);
    }

    #[Route('/{id}/play', name: 'play', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function play(
        int $id,
        Request $request,
        QuizRepository $quizRepo,
        QuizAttemptRepository $attemptRepo
    ): Response {
        $quiz = $quizRepo->find($id);

        if (!$quiz || !$quiz->isPublished()) {
            throw $this->createNotFoundException('Quiz introuvable ou non publié');
        }

        $attemptId = $request->query->getInt('attempt');
        $attempt = $attemptRepo->find($attemptId);

        if (!$attempt || $attempt->getQuiz()->getId() !== $quiz->getId()) {
            $this->addFlash('error', 'Tentative invalide.');
            return $this->redirectToRoute('fo_training_quizzes_show', ['id' => $id]);
        }

        if ($attempt->isCompleted()) {
            return $this->redirectToRoute('fo_training_quizzes_result', [
                'id' => $id,
                'attempt' => $attempt->getId(),
            ]);
        }

        return $this->render('fo/training/quizzes/play.html.twig', [
            'quiz' => $quiz,
            'attempt' => $attempt,
        ]);
    }

    #[Route('/{id}/submit', name: 'submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function submit(
        int $id,
        Request $request,
        QuizRepository $quizRepo,
        QuizAttemptRepository $attemptRepo,
        QuizScoringService $scoringService,
        EntityManagerInterface $em,
        NotificationService $notificationService,
        BadgeService $badgeService,
        StreakService $streakService
    ): Response {
        $quiz = $quizRepo->find($id);

        if (!$quiz || !$quiz->isPublished()) {
            throw $this->createNotFoundException('Quiz introuvable ou non publié');
        }

        $attemptId = $request->request->getInt('attempt_id');
        $attempt = $attemptRepo->find($attemptId);

        if (!$attempt || $attempt->getQuiz()->getId() !== $quiz->getId()) {
            $this->addFlash('error', 'Tentative invalide.');
            return $this->redirectToRoute('fo_training_quizzes_show', ['id' => $id]);
        }

        if ($attempt->isCompleted()) {
            return $this->redirectToRoute('fo_training_quizzes_result', [
                'id' => $id,
                'attempt' => $attempt->getId(),
            ]);
        }

        if (!$this->isCsrfTokenValid('submit_quiz_' . $attemptId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_training_quizzes_play', [
                'id' => $id,
                'attempt' => $attemptId,
            ]);
        }

        // Parse answers from request
        $answers = [];
        foreach ($request->request->all() as $key => $value) {
            if (str_starts_with($key, 'question_')) {
                $questionIndex = (int) substr($key, 9);
                $answers[$questionIndex] = (string) $value;
            }
        }

        try {
            $scoringService->scoreAttempt($attempt, $answers);
            $em->flush();

            $this->addFlash('success', sprintf(
                'Quiz terminé ! Score: %.1f%% (%d/%d)',
                $attempt->getScore(),
                $attempt->getCorrectCount(),
                $attempt->getTotalQuestions()
            ));

            // Streak
            $user = $attempt->getUser();
            $streakService->recordActivity($user);

            // Notification
            $passed = $attempt->getScore() >= 50;
            $notificationService->create(
                $user,
                $passed ? 'Quiz réussi !' : 'Quiz terminé',
                sprintf('%s - Score: %.1f%%', $quiz->getTitle(), $attempt->getScore()),
                $passed ? 'success' : 'info',
                $this->generateUrl('fo_training_quizzes_result', ['id' => $id, 'attempt' => $attempt->getId()])
            );

            // Check badges
            $newBadges = $badgeService->checkAndAwardBadges($user);
            foreach ($newBadges as $badge) {
                $notificationService->create(
                    $user,
                    'Nouveau badge : ' . $badge->getName(),
                    $badge->getDescription(),
                    'success'
                );
            }

            return $this->redirectToRoute('fo_training_quizzes_result', [
                'id' => $id,
                'attempt' => $attempt->getId(),
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('fo_training_quizzes_play', [
                'id' => $id,
                'attempt' => $attemptId,
            ]);
        }
    }

    #[Route('/{id}/result', name: 'result', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function result(
        int $id,
        Request $request,
        QuizRepository $quizRepo,
        QuizAttemptRepository $attemptRepo,
        QuizScoringService $scoringService,
        QuizRatingRepository $ratingRepo
    ): Response {
        $quiz = $quizRepo->find($id);

        if (!$quiz) {
            throw $this->createNotFoundException('Quiz introuvable');
        }

        $attemptId = $request->query->getInt('attempt');
        $attempt = $attemptRepo->find($attemptId);

        if (!$attempt || $attempt->getQuiz()->getId() !== $quiz->getId()) {
            $this->addFlash('error', 'Tentative invalide.');
            return $this->redirectToRoute('fo_training_quizzes_show', ['id' => $id]);
        }

        if (!$attempt->isCompleted()) {
            return $this->redirectToRoute('fo_training_quizzes_play', [
                'id' => $id,
                'attempt' => $attemptId,
            ]);
        }

        $results = $scoringService->getDetailedResults($attempt);

        $userRating = null;
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser) {
            $userRating = $ratingRepo->findByUserAndQuiz($currentUser, $quiz);
        }

        return $this->render('fo/training/quizzes/result.html.twig', [
            'quiz' => $quiz,
            'attempt' => $attempt,
            'results' => $results,
            'userRating' => $userRating,
        ]);
    }

    #[Route('/ai-generate', name: 'ai_generate_form', methods: ['GET'])]
    public function aiGenerateForm(
        SubjectRepository $subjectRepo,
        ChapterRepository $chapterRepo
    ): Response {
        $subjects = $subjectRepo->findAll();
        $chapters = $chapterRepo->findAll();

        return $this->render('fo/training/quizzes/ai_generate.html.twig', [
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

        if (!$this->isCsrfTokenValid('ai_generate_quiz', $token)) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
            }
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_training_quizzes_ai_generate_form');
        }

        /** @var User $user */
        $user = $this->getUser() ?? $userRepo->findOneBy([]);

        if ($isAjax) {
            $body = json_decode($request->getContent(), true);
            $subjectId = (int) ($body['subject_id'] ?? 0);
            $chapterId = isset($body['chapter_id']) ? (int) $body['chapter_id'] : null;
            $numQuestions = (int) ($body['num_questions'] ?? 5);
            $difficulty = $body['difficulty'] ?? 'MEDIUM';
            $topic = $body['topic'] ?? null;
        } else {
            $subjectId = $request->request->getInt('subject_id');
            $chapterId = $request->request->get('chapter_id') ? $request->request->getInt('chapter_id') : null;
            $numQuestions = $request->request->getInt('num_questions', 5);
            $difficulty = $request->request->get('difficulty', 'MEDIUM');
            $topic = $request->request->get('topic');
        }

        $subject = $subjectRepo->find($subjectId);
        if (!$subject) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Matière non trouvée'], 404);
            }
            $this->addFlash('error', 'Matière non trouvée.');
            return $this->redirectToRoute('fo_training_quizzes_ai_generate_form');
        }

        // Call FastAPI AI Gateway
        try {
            $data = $aiGateway->generateQuiz(
                $user->getId(),
                $subjectId,
                $chapterId,
                $numQuestions,
                $difficulty,
                $topic
            );

            $quizId = $data['quiz_id'];

            if ($quizId) {
                if ($isAjax) {
                    return new JsonResponse([
                        'success' => true,
                        'quiz_id' => $quizId,
                        'questions_count' => $data['questions_count'],
                        'ai_log_id' => $data['ai_log_id'],
                        'redirect_url' => $this->generateUrl('fo_training_quizzes_show', ['id' => $quizId]),
                    ]);
                }
                $this->addFlash('success', sprintf(
                    'Quiz généré avec succès ! %d questions créées.',
                    $data['questions_count']
                ));
                return $this->redirectToRoute('fo_training_quizzes_show', ['id' => $quizId]);
            }
        } catch (\Exception $e) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Service IA indisponible: ' . $e->getMessage()], 503);
            }
            $this->addFlash('error', 'Impossible de contacter le service IA: ' . $e->getMessage());
        }

        return $this->redirectToRoute('fo_training_quizzes_ai_generate_form');
    }

    #[Route('/{id}/rate', name: 'rate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function rate(
        Request $request,
        QuizRepository $quizRepo,
        QuizRatingRepository $ratingRepo,
        EntityManagerInterface $em,
        int $id
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $quiz = $quizRepo->find($id);
        if (!$quiz) {
            throw $this->createNotFoundException('Quiz introuvable.');
        }

        if (!$this->isCsrfTokenValid('rate_quiz_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_training_quizzes_show', ['id' => $id]);
        }

        $score = (int) $request->request->get('score', 0);
        if ($score < 1 || $score > 5) {
            $this->addFlash('error', 'Note invalide (1-5).');
            return $this->redirectToRoute('fo_training_quizzes_show', ['id' => $id]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $rating = $ratingRepo->findByUserAndQuiz($user, $quiz);

        if (!$rating) {
            $rating = new QuizRating();
            $rating->setUser($user);
            $rating->setQuiz($quiz);
            $em->persist($rating);
        }

        $rating->setScore($score);
        $em->flush();

        $this->addFlash('success', 'Merci pour votre note !');
        return $this->redirectToRoute('fo_training_quizzes_show', ['id' => $id]);
    }

    #[Route('/{id}/certificate', name: 'certificate_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function certificatePdf(
        int $id,
        Request $request,
        QuizRepository $quizRepo,
        QuizAttemptRepository $attemptRepo,
        QuizScoringService $scoringService,
        PdfExportService $pdfService
    ): Response {
        $quiz = $quizRepo->find($id);
        if (!$quiz) {
            throw $this->createNotFoundException('Quiz introuvable.');
        }

        $attemptId = $request->query->getInt('attempt');
        $attempt = $attemptRepo->find($attemptId);
        if (!$attempt || $attempt->getQuiz()->getId() !== $quiz->getId()) {
            throw $this->createNotFoundException('Tentative introuvable.');
        }

        $results = $scoringService->getDetailedResults($attempt);
        if (!$results['passed']) {
            $this->addFlash('error', 'Le certificat n\'est disponible que pour les quiz réussis.');
            return $this->redirectToRoute('fo_training_quizzes_result', ['id' => $id, 'attempt' => $attemptId]);
        }

        $user = $attempt->getUser();

        return $pdfService->generateFromTemplate('pdf/certificate.html.twig', [
            'quiz' => $quiz,
            'user' => $user,
            'score' => number_format($results['percentage'], 1),
            'correctCount' => $results['correctCount'],
            'totalQuestions' => $results['totalQuestions'],
            'date' => $attempt->getStartedAt(),
        ], 'certificat-' . $quiz->getId() . '.pdf');
    }

    #[Route('/{id}/recap-pdf', name: 'recap_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function recapPdf(
        int $id,
        Request $request,
        QuizRepository $quizRepo,
        QuizAttemptRepository $attemptRepo,
        QuizScoringService $scoringService,
        PdfExportService $pdfService
    ): Response {
        $quiz = $quizRepo->find($id);
        if (!$quiz) {
            throw $this->createNotFoundException('Quiz introuvable.');
        }

        $attemptId = $request->query->getInt('attempt');
        $attempt = $attemptRepo->find($attemptId);
        if (!$attempt || $attempt->getQuiz()->getId() !== $quiz->getId()) {
            throw $this->createNotFoundException('Tentative introuvable.');
        }

        $results = $scoringService->getDetailedResults($attempt);
        $user = $attempt->getUser();

        return $pdfService->generateFromTemplate('pdf/quiz_recap.html.twig', [
            'quiz' => $quiz,
            'user' => $user,
            'attempt' => $attempt,
            'results' => $results,
        ], 'recap-quiz-' . $quiz->getId() . '.pdf');
    }

    #[Route('/{id}/qrcode', name: 'qrcode', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function qrCode(int $id, QuizRepository $quizRepo, QrCodeService $qrService): Response
    {
        $quiz = $quizRepo->find($id);
        if (!$quiz) {
            throw $this->createNotFoundException('Quiz introuvable.');
        }

        $url = $this->generateUrl('fo_training_quizzes_show', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
        return $qrService->generateResponse($url);
    }
}
