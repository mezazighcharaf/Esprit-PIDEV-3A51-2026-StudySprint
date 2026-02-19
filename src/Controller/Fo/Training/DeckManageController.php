<?php

namespace App\Controller\Fo\Training;

use App\Entity\Flashcard;
use App\Entity\FlashcardDeck;
use App\Repository\FlashcardDeckRepository;
use App\Repository\FlashcardRepository;
use App\Repository\SubjectRepository;
use App\Repository\ChapterRepository;
use App\Repository\UserRepository;
use App\Service\FlashcardTipsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fo/training/decks/manage', name: 'fo_training_decks_manage_')]
class DeckManageController extends AbstractController
{
    #[Route('/my-decks', name: 'my_decks', methods: ['GET'])]
    public function myDecks(
        FlashcardDeckRepository $deckRepo,
        UserRepository $userRepo
    ): Response {
        $user = $this->getUser() ?? $userRepo->findOneBy([]);
        
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non connecté.');
            return $this->redirectToRoute('fo_training_decks_index');
        }

        $decks = $deckRepo->findBy(['owner' => $user], ['createdAt' => 'DESC']);

        return $this->render('fo/training/decks/my_decks.html.twig', [
            'decks' => $decks,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        FlashcardTipsService $tipsService,
        SubjectRepository $subjectRepo,
        ChapterRepository $chapterRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser() ?? $userRepo->findOneBy([]);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non connecté.');
            return $this->redirectToRoute('fo_training_decks_index');
        }

        $subjects = $subjectRepo->findAll();
        $tips = $tipsService->getTips(3);

        if ($request->isMethod('POST')) {
            $title = trim($request->request->get('title', ''));
            $subjectId = $request->request->getInt('subject_id');
            $chapterId = $request->request->getInt('chapter_id') ?: null;
            $cardsData = $request->request->all('cards');

            if (!$title || !$subjectId) {
                $this->addFlash('error', 'Titre et matière obligatoires.');
                return $this->redirectToRoute('fo_training_decks_manage_new');
            }

            $subject = $subjectRepo->find($subjectId);
            $chapter = $chapterId ? $chapterRepo->find($chapterId) : null;

            if (!$subject) {
                $this->addFlash('error', 'Matière invalide.');
                return $this->redirectToRoute('fo_training_decks_manage_new');
            }

            $deck = new FlashcardDeck();
            $deck->setOwner($user);
            $deck->setSubject($subject);
            $deck->setChapter($chapter);
            $deck->setTitle($title);
            $deck->setIsPublished(false);

            $em->persist($deck);

            // Add flashcards
            $position = 0;
            foreach ($cardsData as $cardData) {
                $front = trim($cardData['front'] ?? '');
                $back = trim($cardData['back'] ?? '');
                $hint = trim($cardData['hint'] ?? '') ?: null;

                if ($front && $back) {
                    $flashcard = new Flashcard();
                    $flashcard->setDeck($deck);
                    $flashcard->setFront($front);
                    $flashcard->setBack($back);
                    $flashcard->setHint($hint);
                    $flashcard->setPosition($position++);
                    
                    $em->persist($flashcard);
                    $deck->addFlashcard($flashcard);
                }
            }

            if ($position === 0) {
                $this->addFlash('error', 'Ajoutez au moins une flashcard complète (recto + verso).');
                return $this->redirectToRoute('fo_training_decks_manage_new');
            }

            $em->flush();

            $this->addFlash('success', sprintf('Deck "%s" créé avec %d flashcard(s) !', $title, $position));
            return $this->redirectToRoute('fo_training_decks_manage_my_decks');
        }

        return $this->render('fo/training/decks/create.html.twig', [
            'subjects' => $subjects,
            'tips' => $tips,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        int $id,
        Request $request,
        FlashcardDeckRepository $deckRepo,
        FlashcardRepository $flashcardRepo,
        SubjectRepository $subjectRepo,
        ChapterRepository $chapterRepo,
        UserRepository $userRepo,
        FlashcardTipsService $tipsService,
        EntityManagerInterface $em
    ): Response {
        $deck = $deckRepo->find($id);
        
        if (!$deck) {
            throw $this->createNotFoundException('Deck introuvable.');
        }

        $this->denyAccessUnlessGranted('DECK_EDIT', $deck);

        $subjects = $subjectRepo->findAll();
        $tips = $tipsService->getTips(2);

        if ($request->isMethod('POST')) {
            $title = trim($request->request->get('title', ''));
            $subjectId = $request->request->getInt('subject_id');
            $chapterId = $request->request->getInt('chapter_id') ?: null;
            $cardsData = $request->request->all('cards');

            $subject = $subjectRepo->find($subjectId);
            $chapter = $chapterId ? $chapterRepo->find($chapterId) : null;

            $deck->setTitle($title);
            $deck->setSubject($subject);
            $deck->setChapter($chapter);

            // Remove old flashcards
            foreach ($deck->getFlashcards() as $card) {
                $em->remove($card);
            }
            $deck->getFlashcards()->clear();

            // Add updated flashcards
            $position = 0;
            foreach ($cardsData as $cardData) {
                $front = trim($cardData['front'] ?? '');
                $back = trim($cardData['back'] ?? '');
                $hint = trim($cardData['hint'] ?? '') ?: null;

                if ($front && $back) {
                    $flashcard = new Flashcard();
                    $flashcard->setDeck($deck);
                    $flashcard->setFront($front);
                    $flashcard->setBack($back);
                    $flashcard->setHint($hint);
                    $flashcard->setPosition($position++);
                    
                    $em->persist($flashcard);
                    $deck->addFlashcard($flashcard);
                }
            }

            $em->flush();

            $this->addFlash('success', 'Deck modifié avec succès.');
            return $this->redirectToRoute('fo_training_decks_manage_my_decks');
        }

        return $this->render('fo/training/decks/edit.html.twig', [
            'deck' => $deck,
            'subjects' => $subjects,
            'tips' => $tips,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        Request $request,
        FlashcardDeckRepository $deckRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $deck = $deckRepo->find($id);
        
        if (!$deck) {
            throw $this->createNotFoundException('Deck introuvable.');
        }

        $this->denyAccessUnlessGranted('DECK_DELETE', $deck);

        if (!$this->isCsrfTokenValid('delete_deck_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_training_decks_manage_my_decks');
        }

        $em->remove($deck);
        $em->flush();

        $this->addFlash('success', 'Deck supprimé.');
        return $this->redirectToRoute('fo_training_decks_manage_my_decks');
    }

    #[Route('/{id}/toggle-publish', name: 'toggle_publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function togglePublish(
        int $id,
        Request $request,
        FlashcardDeckRepository $deckRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $deck = $deckRepo->find($id);
        
        if (!$deck) {
            throw $this->createNotFoundException('Deck introuvable.');
        }

        $this->denyAccessUnlessGranted('DECK_EDIT', $deck);

        if (!$this->isCsrfTokenValid('toggle_publish_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_training_decks_manage_my_decks');
        }

        $deck->setIsPublished(!$deck->isPublished());
        $em->flush();

        $this->addFlash('success', $deck->isPublished() ? 'Deck publié.' : 'Deck dépublié.');
        return $this->redirectToRoute('fo_training_decks_manage_my_decks');
    }
}
