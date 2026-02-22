<?php

namespace App\Controller\Bo;

use App\Entity\Chapter;
use App\Form\Bo\ChapterType;
use App\Repository\ChapterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\AiGatewayService;

#[Route('/bo/chapters', name: 'bo_chapters_')]
class ChapterController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ChapterRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir = strtolower($request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;
        $allowedSort = ['id', 'title', 'orderNo', 'createdAt'];
        if (!in_array($sort, $allowedSort)) $sort = 'id';

        $qb = $repo->createQueryBuilder('c')->leftJoin('c.subject', 's');
        if ($q) $qb->where('c.title LIKE :q')->setParameter('q', "%$q%");
        $qb->orderBy("c.$sort", $dir);

        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('bo/chapters/index.html.twig', [
            'items' => $items, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'page' => $page, 'totalPages' => (int) ceil($total / $perPage), 'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new Chapter();
        $form = $this->createForm(ChapterType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Chapitre créé.');
            return $this->redirectToRoute('bo_chapters_index');
        }
        return $this->render('bo/chapters/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Chapter $item): Response
    {
        return $this->render('bo/chapters/show.html.twig', ['item' => $item]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Chapter $item, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ChapterType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Chapitre modifié.');
            return $this->redirectToRoute('bo_chapters_index');
        }
        return $this->render('bo/chapters/edit.html.twig', ['form' => $form, 'item' => $item]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Chapter $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Chapitre supprimé.');
        }
        return $this->redirectToRoute('bo_chapters_index');
    }

    #[Route('/{id}/up', name: 'up', methods: ['POST'])]
    public function moveUp(Request $request, Chapter $item, ChapterRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('move'.$item->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('bo_chapters_index');
        }

        $currentOrder = $item->getOrderNo();
        if ($currentOrder <= 1) {
            $this->addFlash('warning', 'Ce chapitre est déjà en première position.');
            return $this->redirectToRoute('bo_chapters_index');
        }

        // Find the chapter with orderNo = currentOrder - 1 in the same subject
        $previousChapter = $repo->findOneBy([
            'subject' => $item->getSubject(),
            'orderNo' => $currentOrder - 1
        ]);

        $em->wrapInTransaction(function() use ($item, $previousChapter, $currentOrder) {
            if ($previousChapter) {
                $previousChapter->setOrderNo($currentOrder);
            }
            $item->setOrderNo($currentOrder - 1);
        });

        $this->addFlash('success', 'Chapitre déplacé vers le haut.');
        return $this->redirectToRoute('bo_chapters_index');
    }

    #[Route('/{id}/down', name: 'down', methods: ['POST'])]
    public function moveDown(Request $request, Chapter $item, ChapterRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('move'.$item->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('bo_chapters_index');
        }

        $currentOrder = $item->getOrderNo();

        // Find the chapter with orderNo = currentOrder + 1 in the same subject
        $nextChapter = $repo->findOneBy([
            'subject' => $item->getSubject(),
            'orderNo' => $currentOrder + 1
        ]);

        if (!$nextChapter) {
            $this->addFlash('warning', 'Ce chapitre est déjà en dernière position.');
            return $this->redirectToRoute('bo_chapters_index');
        }

        $em->wrapInTransaction(function() use ($item, $nextChapter, $currentOrder) {
            $nextChapter->setOrderNo($currentOrder);
            $item->setOrderNo($currentOrder + 1);
        });

        $this->addFlash('success', 'Chapitre déplacé.');
        return $this->redirectToRoute('bo_chapters_index');
    }

    #[Route('/{id}/ai-summarize', name: 'ai_summarize', methods: ['POST'])]
    public function aiSummarize(
        Request $request,
        Chapter $item,
        AiGatewayService $aiGateway,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('ai_summarize'.$item->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('bo_chapters_show', ['id' => $item->getId()]);
        }

        // Call FastAPI AI Gateway
        try {
            $data = $aiGateway->summarizeChapter($this->getUser()->getId(), $item->getId());

            // Update chapter with AI data
            $item->setAiSummary($data['summary'] ?? null);
            $item->setAiKeyPoints($data['key_points'] ?? []);
            $item->setAiTags($data['tags'] ?? []);

            $em->flush();

            $this->addFlash('success', 'Résumé IA généré avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de contacter le service IA: ' . $e->getMessage());
        }

        return $this->redirectToRoute('bo_chapters_show', ['id' => $item->getId()]);
    }
}
