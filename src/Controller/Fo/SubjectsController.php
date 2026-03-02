<?php

namespace App\Controller\Fo;

use App\Entity\Subject;
use App\Entity\Chapter;
use App\Entity\User;
use App\Form\Fo\SubjectType;
use App\Form\Fo\ChapterType;
use App\Repository\SubjectRepository;
use App\Repository\ChapterRepository;
use App\Repository\UserRepository;
use App\Service\WikipediaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fo/subjects', name: 'fo_subjects_')]
/**
 * @method \App\Entity\User|null getUser()
 */
class SubjectsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(SubjectRepository $repository): Response
    {
        $subjects = $repository->findAllWithChapters();

        return $this->render('fo/subjects/index.html.twig', [
            'subjects' => $subjects,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        $subject = new Subject();
        $form = $this->createForm(SubjectType::class, $subject);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User|null $currentUser */
            $currentUser = $this->getUser() ?? $userRepo->findOneBy([]);
            if (!$currentUser) {
                $this->addFlash('error', 'Utilisateur non connecté.');
                return $this->redirectToRoute('fo_subjects_index');
            }

            $subject->setCreatedBy($currentUser);
            $em->persist($subject);
            $em->flush();

            $this->addFlash('success', 'Matière créée avec succès !');
            return $this->redirectToRoute('fo_subjects_show', ['id' => $subject->getId()]);
        }

        return $this->render('fo/subjects/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, SubjectRepository $subjectRepo, ChapterRepository $chapterRepo, WikipediaService $wikipedia): Response
    {
        $subject = $subjectRepo->find($id);

        if (!$subject) {
            throw $this->createNotFoundException('Matière introuvable');
        }

        $chapters = $chapterRepo->findBy(['subject' => $subject], ['orderNo' => 'ASC']);

        $wikipediaSummary = $wikipedia->getSummary($subject->getName());

        return $this->render('fo/subjects/show.html.twig', [
            'subject'          => $subject,
            'chapters'         => $chapters,
            'wikipediaSummary' => $wikipediaSummary,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, SubjectRepository $repository, EntityManagerInterface $em): Response
    {
        $subject = $repository->find($id);

        if (!$subject) {
            throw $this->createNotFoundException('Matière introuvable');
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        $currentUserId = $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isSubjectOwner = $subject->getCreatedBy()->getId() === $currentUserId;
        if (!$isAdmin && !$isSubjectOwner) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette matière.');
        }

        $form = $this->createForm(SubjectType::class, $subject);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Matière modifiée avec succès !');
            return $this->redirectToRoute('fo_subjects_show', ['id' => $subject->getId()]);
        }

        return $this->render('fo/subjects/edit.html.twig', [
            'subject' => $subject,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, SubjectRepository $repository, EntityManagerInterface $em): Response
    {
        $subject = $repository->find($id);

        if (!$subject) {
            throw $this->createNotFoundException('Matière introuvable');
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        $currentUserId = $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isSubjectOwner = $subject->getCreatedBy()->getId() === $currentUserId;
        if (!$isAdmin && !$isSubjectOwner) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette matière.');
        }

        if ($this->isCsrfTokenValid('delete_subject_' . $subject->getId(), $request->request->get('_token'))) {
            $em->remove($subject);
            $em->flush();
            $this->addFlash('success', 'Matière supprimée avec succès !');
        }

        return $this->redirectToRoute('fo_subjects_index');
    }

    // === CHAPTER CRUD ===

    #[Route('/{subjectId}/chapters/new', name: 'chapter_new', methods: ['GET', 'POST'], requirements: ['subjectId' => '\d+'])]
    public function newChapter(int $subjectId, Request $request, SubjectRepository $subjectRepo, UserRepository $userRepo, EntityManagerInterface $em): Response
    {
        $subject = $subjectRepo->find($subjectId);
        
        if (!$subject) {
            throw $this->createNotFoundException('Matière introuvable');
        }

        $chapter = new Chapter();
        $chapter->setSubject($subject);
        
        $form = $this->createForm(ChapterType::class, $chapter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User|null $currentUser */
            $currentUser = $this->getUser() ?? $userRepo->findOneBy([]);
            if (!$currentUser) {
                $this->addFlash('error', 'Utilisateur non connecté.');
                return $this->redirectToRoute('fo_subjects_show', ['id' => $subjectId]);
            }

            $file = $form->get('attachmentFile')->getData();
            if ($file) {
                $filename = uniqid('chap_') . '.' . $file->guessExtension();
                $file->move($this->getParameter('kernel.project_dir') . '/public/uploads/chapters', $filename);
                $chapter->setAttachmentUrl('/uploads/chapters/' . $filename);
            }

            $chapter->setCreatedBy($currentUser);
            $em->persist($chapter);
            $em->flush();

            $this->addFlash('success', 'Chapitre créé avec succès !');
            return $this->redirectToRoute('fo_subjects_show', ['id' => $subjectId]);
        }

        return $this->render('fo/subjects/chapter_new.html.twig', [
            'subject' => $subject,
            'form' => $form,
        ]);
    }

    #[Route('/{subjectId}/chapters/{chapterId}/edit', name: 'chapter_edit', methods: ['GET', 'POST'], requirements: ['subjectId' => '\d+', 'chapterId' => '\d+'])]
    public function editChapter(int $subjectId, int $chapterId, Request $request, SubjectRepository $subjectRepo, ChapterRepository $chapterRepo, EntityManagerInterface $em): Response
    {
        $subject = $subjectRepo->find($subjectId);
        $chapter = $chapterRepo->find($chapterId);
        
        if (!$subject || !$chapter || $chapter->getSubject()->getId() !== $subject->getId()) {
            throw $this->createNotFoundException('Chapitre ou matière introuvable');
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        $currentUserId = $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isSubjectOwner = $subject->getCreatedBy()->getId() === $currentUserId;
        $isChapterOwner = $chapter->getCreatedBy()->getId() === $currentUserId;

        // Autoriser tout utilisateur connecté (ROLE_USER), en plus des propriétaires et admin
        if (!$this->isGranted('ROLE_USER')) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce chapitre.');
        }

        $form = $this->createForm(ChapterType::class, $chapter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('attachmentFile')->getData();
            if ($file) {
                $filename = uniqid('chap_') . '.' . $file->guessExtension();
                $file->move($this->getParameter('kernel.project_dir') . '/public/uploads/chapters', $filename);
                $chapter->setAttachmentUrl('/uploads/chapters/' . $filename);
            }
            $em->flush();
            $this->addFlash('success', 'Chapitre modifié avec succès !');
            return $this->redirectToRoute('fo_subjects_show', ['id' => $subjectId]);
        }

        return $this->render('fo/subjects/chapter_edit.html.twig', [
            'subject' => $subject,
            'chapter' => $chapter,
            'form' => $form,
        ]);
    }

    #[Route('/{subjectId}/chapters/{chapterId}/delete', name: 'chapter_delete', methods: ['POST'], requirements: ['subjectId' => '\d+', 'chapterId' => '\d+'])]
    public function deleteChapter(int $subjectId, int $chapterId, Request $request, ChapterRepository $chapterRepo, EntityManagerInterface $em): Response
    {
        $chapter = $chapterRepo->find($chapterId);
        
        if (!$chapter || $chapter->getSubject()->getId() !== $subjectId) {
            throw $this->createNotFoundException('Chapitre introuvable');
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        $currentUserId = $currentUser?->getId();
        $isSubjectOwner = $chapter->getSubject()->getCreatedBy()->getId() === $currentUserId;
        $isChapterOwner = $chapter->getCreatedBy()->getId() === $currentUserId;
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        if (!$isAdmin && !$isSubjectOwner && !$isChapterOwner) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce chapitre.');
        }

        if ($this->isCsrfTokenValid('delete_chapter_' . $chapter->getId(), $request->request->get('_token'))) {
            $em->remove($chapter);
            $em->flush();
            $this->addFlash('success', 'Chapitre supprimé avec succès !');
        }

        return $this->redirectToRoute('fo_subjects_show', ['id' => $subjectId]);
    }
}
