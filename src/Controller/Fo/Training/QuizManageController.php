<?php

namespace App\Controller\Fo\Training;

use App\Entity\Quiz;
use App\Entity\User;
use App\Repository\QuizRepository;
use App\Repository\SubjectRepository;
use App\Repository\ChapterRepository;
use App\Repository\UserRepository;
use App\Service\QuizTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fo/training/quizzes/manage', name: 'fo_training_quizzes_manage_')]
/**
 * @method \App\Entity\User|null getUser()
 */
class QuizManageController extends AbstractController
{
    #[Route('/my-quizzes', name: 'my_quizzes', methods: ['GET'])]
    public function myQuizzes(
        QuizRepository $quizRepo,
        UserRepository $userRepo
    ): Response {
        /** @var User $user */
        $user = $this->getUser() ?? $userRepo->findOneBy([]);

        $quizzes = $quizRepo->findBy(['owner' => $user], ['createdAt' => 'DESC']);

        return $this->render('fo/training/quizzes/my_quizzes.html.twig', [
            'quizzes' => $quizzes,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET'])]
    public function selectTemplate(QuizTemplateService $templateService): Response
    {
        $templates = $templateService->getAllTemplates();

        return $this->render('fo/training/quizzes/select_template.html.twig', [
            'templates' => $templates,
        ]);
    }

    #[Route('/new/{templateKey}', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        string $templateKey,
        Request $request,
        QuizTemplateService $templateService,
        SubjectRepository $subjectRepo,
        ChapterRepository $chapterRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $template = $templateService->getTemplate($templateKey);
        
        if (!$template) {
            $this->addFlash('error', 'Template introuvable.');
            return $this->redirectToRoute('fo_training_quizzes_manage_new');
        }

        /** @var User $user */
        $user = $this->getUser() ?? $userRepo->findOneBy([]);

        $subjects = $subjectRepo->findAll();

        if ($request->isMethod('POST')) {
            $title = trim($request->request->get('title', ''));
            $subjectId = $request->request->getInt('subject_id');
            $chapterId = $request->request->getInt('chapter_id') ?: null;
            $difficulty = $request->request->get('difficulty', Quiz::DIFFICULTY_MEDIUM);
            $questionsData = $request->request->all('questions');

            if (!$title || !$subjectId || empty($questionsData)) {
                $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
                return $this->redirectToRoute('fo_training_quizzes_manage_create', ['templateKey' => $templateKey]);
            }

            $subject = $subjectRepo->find($subjectId);
            $chapter = $chapterId ? $chapterRepo->find($chapterId) : null;

            if (!$subject) {
                $this->addFlash('error', 'Matière invalide.');
                return $this->redirectToRoute('fo_training_quizzes_manage_create', ['templateKey' => $templateKey]);
            }

            // Process questions
            $questions = [];
            foreach ($questionsData as $index => $questionData) {
                $q = [
                    'id' => (int) $index + 1,
                    'question' => trim($questionData['question'] ?? ''),
                    'type' => $questionData['type'] ?? 'multiple_choice',
                ];

                if ($q['type'] === 'multiple_choice' || $q['type'] === 'true_false') {
                    $q['options'] = array_values(array_filter($questionData['options'] ?? []));
                    $q['correctIndex'] = (int) ($questionData['correctIndex'] ?? 0);
                } else {
                    $q['correctAnswer'] = trim($questionData['correctAnswer'] ?? '');
                }

                if ($q['question']) {
                    $questions[] = $q;
                }
            }

            if (empty($questions)) {
                $this->addFlash('error', 'Ajoutez au moins une question complète.');
                return $this->redirectToRoute('fo_training_quizzes_manage_create', ['templateKey' => $templateKey]);
            }

            $quiz = new Quiz();
            $quiz->setOwner($user);
            $quiz->setSubject($subject);
            $quiz->setChapter($chapter);
            $quiz->setTitle($title);
            $quiz->setDifficulty($difficulty);
            $quiz->setTemplateKey($templateKey);
            $quiz->setQuestions($questions);
            $quiz->setIsPublished(false);

            $em->persist($quiz);
            $em->flush();

            $this->addFlash('success', 'Quiz créé avec succès ! Vous pouvez le publier depuis "Mes Quiz".');
            return $this->redirectToRoute('fo_training_quizzes_manage_my_quizzes');
        }

        $emptyQuestions = $templateService->generateEmptyQuestions($templateKey);

        return $this->render('fo/training/quizzes/create.html.twig', [
            'template' => $template,
            'templateKey' => $templateKey,
            'emptyQuestions' => $emptyQuestions,
            'subjects' => $subjects,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        int $id,
        Request $request,
        QuizRepository $quizRepo,
        SubjectRepository $subjectRepo,
        ChapterRepository $chapterRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $quiz = $quizRepo->find($id);
        
        if (!$quiz) {
            throw $this->createNotFoundException('Quiz introuvable.');
        }

        $this->denyAccessUnlessGranted('QUIZ_EDIT', $quiz);

        $subjects = $subjectRepo->findAll();

        if ($request->isMethod('POST')) {
            $title = trim($request->request->get('title', ''));
            $subjectId = $request->request->getInt('subject_id');
            $chapterId = $request->request->getInt('chapter_id') ?: null;
            $difficulty = $request->request->get('difficulty', Quiz::DIFFICULTY_MEDIUM);
            $questionsData = $request->request->all('questions');

            $subject = $subjectRepo->find($subjectId);
            $chapter = $chapterId ? $chapterRepo->find($chapterId) : null;

            $questions = [];
            foreach ($questionsData as $index => $questionData) {
                $q = [
                    'id' => (int) $index + 1,
                    'question' => trim($questionData['question'] ?? ''),
                    'type' => $questionData['type'] ?? 'multiple_choice',
                ];

                if ($q['type'] === 'multiple_choice' || $q['type'] === 'true_false') {
                    $q['options'] = array_values(array_filter($questionData['options'] ?? []));
                    $q['correctIndex'] = (int) ($questionData['correctIndex'] ?? 0);
                } else {
                    $q['correctAnswer'] = trim($questionData['correctAnswer'] ?? '');
                }

                if ($q['question']) {
                    $questions[] = $q;
                }
            }

            $quiz->setTitle($title);
            $quiz->setSubject($subject);
            $quiz->setChapter($chapter);
            $quiz->setDifficulty($difficulty);
            $quiz->setQuestions($questions);

            $em->flush();

            $this->addFlash('success', 'Quiz modifié avec succès.');
            return $this->redirectToRoute('fo_training_quizzes_manage_my_quizzes');
        }

        return $this->render('fo/training/quizzes/edit.html.twig', [
            'quiz' => $quiz,
            'subjects' => $subjects,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        Request $request,
        QuizRepository $quizRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $quiz = $quizRepo->find($id);
        
        if (!$quiz) {
            throw $this->createNotFoundException('Quiz introuvable.');
        }

        $this->denyAccessUnlessGranted('QUIZ_DELETE', $quiz);

        if (!$this->isCsrfTokenValid('delete_quiz_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_training_quizzes_manage_my_quizzes');
        }

        $em->remove($quiz);
        $em->flush();

        $this->addFlash('success', 'Quiz supprimé.');
        return $this->redirectToRoute('fo_training_quizzes_manage_my_quizzes');
    }

    #[Route('/{id}/toggle-publish', name: 'toggle_publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function togglePublish(
        int $id,
        Request $request,
        QuizRepository $quizRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $quiz = $quizRepo->find($id);
        
        if (!$quiz) {
            throw $this->createNotFoundException('Quiz introuvable.');
        }

        /** @var User $user */
        $user = $this->getUser() ?? $userRepo->findOneBy([]);
        if ($quiz->getOwner()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('toggle_publish_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_training_quizzes_manage_my_quizzes');
        }

        $quiz->setIsPublished(!$quiz->isPublished());
        $em->flush();

        $this->addFlash('success', $quiz->isPublished() ? 'Quiz publié.' : 'Quiz dépublié.');
        return $this->redirectToRoute('fo_training_quizzes_manage_my_quizzes');
    }
}
