<?php

namespace App\Controller\Bo;

use App\Entity\FlashcardDeck;
use App\Form\Bo\FlashcardDeckType;
use App\Repository\FlashcardDeckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bo/decks', name: 'bo_decks_')]
class FlashcardDeckController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, FlashcardDeckRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir = $request->query->get('dir', 'desc');
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;

        $allowedSort = ['id', 'title', 'createdAt'];
        if (!in_array($sort, $allowedSort)) {
            $sort = 'id';
        }
        if (!in_array($dir, ['asc', 'desc'])) {
            $dir = 'desc';
        }

        $qb = $repository->createQueryBuilder('d')
            ->leftJoin('d.owner', 'o')
            ->leftJoin('d.subject', 's');

        if ($q) {
            $qb->andWhere('d.title LIKE :q OR s.name LIKE :q OR o.email LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        $qb->orderBy('d.' . $sort, $dir);

        $total = (int) (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $decks = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('bo/training/deck/index.html.twig', [
            'decks' => $decks, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'page' => $page, 'totalPages' => (int) ceil($total / $perPage), 'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $deck = new FlashcardDeck();
        $form = $this->createForm(FlashcardDeckType::class, $deck);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($deck);
            $em->flush();
            $this->addFlash('success', 'Deck créé avec succès.');
            return $this->redirectToRoute('bo_decks_index');
        }

        return $this->render('bo/training/deck/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(FlashcardDeck $deck): Response
    {
        return $this->render('bo/training/deck/show.html.twig', [
            'deck' => $deck,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, FlashcardDeck $deck, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FlashcardDeckType::class, $deck);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Deck modifié avec succès.');
            return $this->redirectToRoute('bo_decks_index');
        }

        return $this->render('bo/training/deck/edit.html.twig', [
            'deck' => $deck,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, FlashcardDeck $deck, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $deck->getId(), $request->request->get('_token'))) {
            $em->remove($deck);
            $em->flush();
            $this->addFlash('success', 'Deck supprimé avec succès.');
        }

        return $this->redirectToRoute('bo_decks_index');
    }
}
