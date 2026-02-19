<?php

namespace App\Controller\Bo;

use App\Entity\Subject;
use App\Form\Bo\SubjectType;
use App\Repository\SubjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo/subjects', name: 'bo_subjects_')]
class SubjectController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SubjectRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir = $request->query->get('dir', 'desc');
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;

        $allowedSort = ['id', 'name', 'code', 'createdAt'];
        if (!in_array($sort, $allowedSort)) $sort = 'id';
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $qb = $repo->createQueryBuilder('s');
        if ($q) {
            $qb->where('s.name LIKE :q OR s.code LIKE :q')->setParameter('q', "%$q%");
        }
        $qb->orderBy("s.$sort", $dir);

        $total = (int) (clone $qb)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('bo/subjects/index.html.twig', [
            'items' => $items, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'page' => $page, 'totalPages' => (int) ceil($total / $perPage), 'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new Subject();
        $form = $this->createForm(SubjectType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Matière créée.');
            return $this->redirectToRoute('bo_subjects_index');
        }

        return $this->render('bo/subjects/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Subject $item): Response
    {
        return $this->render('bo/subjects/show.html.twig', ['item' => $item]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Subject $item, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SubjectType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Matière modifiée.');
            return $this->redirectToRoute('bo_subjects_index');
        }

        return $this->render('bo/subjects/edit.html.twig', ['form' => $form, 'item' => $item]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Subject $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Matière supprimée.');
        }
        return $this->redirectToRoute('bo_subjects_index');
    }
}
