<?php

namespace App\Controller\Bo;

use App\Entity\TeacherCertificationRequest;
use App\Repository\TeacherCertificationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\MailerService;
use App\Service\NotificationService;

#[Route('/bo/certifications', name: 'bo_certifications_')]
/**
 * @method \App\Entity\User|null getUser()
 */
class CertificationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, TeacherCertificationRequestRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $statusFilter = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'requestedAt');
        $dir = $request->query->get('dir', 'desc');
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;

        $allowedSort = ['id', 'requestedAt', 'status', 'reviewedAt'];
        if (!in_array($sort, $allowedSort)) $sort = 'requestedAt';
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $qb = $repo->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->addSelect('u');

        if ($q) {
            $qb->andWhere('u.email LIKE :q OR u.fullName LIKE :q')
               ->setParameter('q', "%$q%");
        }

        if ($statusFilter && in_array($statusFilter, ['PENDING', 'APPROVED', 'REJECTED'])) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $statusFilter);
        }

        $qb->orderBy("r.$sort", $dir);

        $total = (int) (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $requests = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('bo/certifications/index.html.twig', [
            'requests' => $requests,
            'q' => $q,
            'statusFilter' => $statusFilter,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'totalPages' => (int) ceil($total / $perPage),
            'total' => $total,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(TeacherCertificationRequest $certRequest): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('bo/certifications/show.html.twig', [
            'certRequest' => $certRequest,
        ]);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(Request $request, TeacherCertificationRequest $certRequest, EntityManagerInterface $em, MailerService $mailer, NotificationService $notificationService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('approve' . $certRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('bo_certifications_show', ['id' => $certRequest->getId()]);
        }

        if (!$certRequest->isPending()) {
            $this->addFlash('error', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('bo_certifications_show', ['id' => $certRequest->getId()]);
        }

        $certRequest->setStatus(TeacherCertificationRequest::STATUS_APPROVED);
        $certRequest->setReviewedAt(new \DateTimeImmutable());
        /** @var \App\Entity\User $reviewer */
        $reviewer = $this->getUser();
        $certRequest->setReviewedBy($reviewer);

        // Promote user to TEACHER
        $certRequest->getUser()->setRole('TEACHER');

        $em->flush();

        try {
            $mailer->sendCertificationNotification($certRequest->getUser(), 'APPROVED');
        } catch (\Exception $e) {}

        $notificationService->create(
            $certRequest->getUser(),
            'Certification approuvée !',
            'Votre demande de certification enseignant a été approuvée.',
            'success'
        );

        $this->addFlash('success', 'Demande approuvée. L\'utilisateur est maintenant certifié professeur.');
        return $this->redirectToRoute('bo_certifications_show', ['id' => $certRequest->getId()]);
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(Request $request, TeacherCertificationRequest $certRequest, EntityManagerInterface $em, MailerService $mailer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('reject' . $certRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('bo_certifications_show', ['id' => $certRequest->getId()]);
        }

        if (!$certRequest->isPending()) {
            $this->addFlash('error', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('bo_certifications_show', ['id' => $certRequest->getId()]);
        }

        $reason = trim($request->request->get('reason', ''));

        $certRequest->setStatus(TeacherCertificationRequest::STATUS_REJECTED);
        $certRequest->setReviewedAt(new \DateTimeImmutable());
        /** @var \App\Entity\User $reviewer */
        $reviewer = $this->getUser();
        $certRequest->setReviewedBy($reviewer);
        $certRequest->setReason($reason ?: null);

        $em->flush();

        try {
            $mailer->sendCertificationNotification($certRequest->getUser(), 'REJECTED', $reason);
        } catch (\Exception $e) {}

        $this->addFlash('success', 'Demande refusée.');
        return $this->redirectToRoute('bo_certifications_show', ['id' => $certRequest->getId()]);
    }
}
