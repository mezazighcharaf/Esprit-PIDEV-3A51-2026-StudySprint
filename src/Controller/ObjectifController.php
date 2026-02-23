<?php

namespace App\Controller;

use App\Entity\Objectif;
use App\Entity\Tache;
use App\Entity\User;
use App\Form\ObjectifType;
use App\Service\AIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/objectif')]
class ObjectifController extends AbstractController
{
    #[Route('/', name: 'app_objectif_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $objectifs = $entityManager->getRepository(Objectif::class)->findBy(['user' => $this->getUser()]);

        return $this->render('objectif/index.html.twig', [
            'objectifs' => $objectifs,
        ]);
    }

    #[Route('/new', name: 'app_objectif_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $objectif = new Objectif();
        $objectif->setUser($this->getUser());

        $form = $this->createForm(ObjectifType::class, $objectif);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($objectif);
            $entityManager->flush();

            return $this->redirectToRoute('app_objectif_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('objectif/new.html.twig', [
            'objectif' => $objectif,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_objectif_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $objectif = $entityManager->getRepository(Objectif::class)->find($id);

        if (!$objectif) {
            throw $this->createNotFoundException('L\'objectif n\'existe pas.');
        }

        if ($objectif->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('objectif/show.html.twig', [
            'objectif' => $objectif,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_objectif_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $objectif = $entityManager->getRepository(Objectif::class)->find($id);

        if (!$objectif) {
            throw $this->createNotFoundException('L\'objectif n\'existe pas.');
        }

        if ($objectif->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ObjectifType::class, $objectif);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_objectif_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('objectif/edit.html.twig', [
            'objectif' => $objectif,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_objectif_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $objectif = $entityManager->getRepository(Objectif::class)->find($id);

        if (!$objectif) {
            throw $this->createNotFoundException('L\'objectif n\'existe pas.');
        }

        if ($objectif->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete' . $objectif->getId(), $request->request->get('_token'))) {
            $entityManager->remove($objectif);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_objectif_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/generate-tasks', name: 'app_objectif_generate_tasks', methods: ['POST'])]
    public function generateTasks(int $id, EntityManagerInterface $entityManager, AIService $aiService): Response
    {
        $objectif = $entityManager->getRepository(Objectif::class)->find($id);

        if (!$objectif) {
            throw $this->createNotFoundException('L\'objectif n\'existe pas.');
        }

        if ($objectif->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $generatedTasks = $aiService->generateTasksFromObjective($objectif);

        foreach ($generatedTasks as $taskData) {
            $tache = new Tache();
            $tache->setTitre($taskData['titre']);
            $tache->setDuree($taskData['duree']);
            $tache->setPriorite($taskData['priorite']);
            $tache->setStatut('A_FAIRE');
            $tache->setDate(new \DateTime()); // Default to today
            $tache->setObjectif($objectif);
            $entityManager->persist($tache);
        }

        $entityManager->flush();

        $this->addFlash('success', '✨ Magie ! Votre objectif a été décomposé en ' . count($generatedTasks) . ' tâches.');

        return $this->redirectToRoute('app_objectif_show', ['id' => $objectif->getId()]);
    }
}
