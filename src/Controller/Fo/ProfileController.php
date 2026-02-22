<?php

namespace App\Controller\Fo;

use App\Entity\TeacherCertificationRequest;
use App\Entity\UserProfile;
use App\Form\Fo\ProfileType;
use App\Repository\TeacherCertificationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AiGatewayService;
use App\Repository\UserBadgeRepository;

#[Route('/fo/profile', name: 'fo_profile_')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(TeacherCertificationRequestRepository $certRepo, UserBadgeRepository $userBadgeRepo): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $latestCertRequest = $certRepo->findLatestByUser($user);
        $userBadges = $userBadgeRepo->findByUser($user);

        return $this->render('fo/profile/show.html.twig', [
            'user' => $user,
            'certRequest' => $latestCertRequest,
            'userBadges' => $userBadges,
        ]);
    }

    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $profile = $user->getProfile();
        if (!$profile) {
            $profile = new UserProfile();
            $profile->setUser($user);
            $user->setProfile($profile);
            $em->persist($profile);
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $form->get('avatarFile')->getData();

            if ($avatarFile) {
                $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0775, true);
                }

                $extension = $avatarFile->guessExtension() ?: 'bin';
                $newFilename = 'avatar_' . bin2hex(random_bytes(8)) . '.' . $extension;

                $avatarFile->move($targetDir, $newFilename);

                $profile->setAvatarUrl('/uploads/avatars/' . $newFilename);
            }

            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('fo_profile_show');
        }

        return $this->render('fo/profile/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/ai-enhance', name: 'ai_enhance', methods: ['POST'])]
    public function aiEnhance(
        Request $request,
        AiGatewayService $aiGateway,
        EntityManagerInterface $em
    ): Response {
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
        $user = $this->getUser();
        if (!$user) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Non authentifié'], 401);
            }
            return $this->redirectToRoute('app_login');
        }

        $token = $isAjax
            ? (json_decode($request->getContent(), true)['_token'] ?? '')
            : $request->request->get('_token');

        if (!$this->isCsrfTokenValid('ai_enhance_profile', $token)) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
            }
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_profile_show');
        }

        $profile = $user->getProfile();

        // Call FastAPI AI Gateway
        try {
            $data = $aiGateway->enhanceProfile(
                $user->getId(),
                $profile?->getBio(),
                $profile?->getLevel(),
                $profile?->getSpecialty()
            );

            // Create profile if not exists
            if (!$profile) {
                $profile = new UserProfile();
                $profile->setUser($user);
                $em->persist($profile);
            }

            // Store AI suggestions (don't overwrite manual fields)
            $profile->setAiSuggestedBio($data['suggested_bio'] ?? null);
            $profile->setAiSuggestedGoals($data['suggested_goals'] ?? null);
            $profile->setAiSuggestedRoutine($data['suggested_routine'] ?? null);

            $em->flush();

            if ($isAjax) {
                return new JsonResponse([
                    'success' => true,
                    'suggested_bio' => $data['suggested_bio'] ?? '',
                    'suggested_goals' => $data['suggested_goals'] ?? '',
                    'suggested_routine' => $data['suggested_routine'] ?? '',
                    'ai_log_id' => $data['ai_log_id'] ?? null,
                ]);
            }

            $this->addFlash('success', 'Suggestions IA générées avec succès ! Consultez-les ci-dessous.');
        } catch (\Exception $e) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Service IA indisponible: ' . $e->getMessage()], 503);
            }
            $this->addFlash('error', 'Impossible de contacter le service IA: ' . $e->getMessage());
        }

        return $this->redirectToRoute('fo_profile_show');
    }

    #[Route('/certification', name: 'certification_request', methods: ['POST'])]
    public function certificationRequest(
        Request $request,
        EntityManagerInterface $em,
        TeacherCertificationRequestRepository $certRepo
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('certification_request', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_profile_show');
        }

        // Already a certified teacher
        if ($user->isCertifiedTeacher()) {
            $this->addFlash('info', 'Vous êtes déjà certifié(e) en tant que professeur.');
            return $this->redirectToRoute('fo_profile_show');
        }

        // Already has a pending request (idempotence)
        if ($certRepo->hasPendingRequest($user)) {
            $this->addFlash('info', 'Vous avez déjà une demande en cours de traitement.');
            return $this->redirectToRoute('fo_profile_show');
        }

        $motivation = trim($request->request->get('motivation', ''));

        $certRequest = new TeacherCertificationRequest();
        $certRequest->setUser($user);
        $certRequest->setMotivation($motivation ?: null);

        $em->persist($certRequest);
        $em->flush();

        $this->addFlash('success', 'Votre demande de certification professeur a été soumise avec succès.');
        return $this->redirectToRoute('fo_profile_show');
    }
}
