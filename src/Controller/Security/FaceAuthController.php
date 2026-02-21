<?php

namespace App\Controller\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/security/face')]
class FaceAuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/enroll', name: 'api_face_enroll', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enroll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['descriptor']) || !is_array($data['descriptor'])) {
            return $this->json(['error' => 'Données biométriques invalides.'], 400);
        }

        $user->setFaceDescriptor($data['descriptor']);
        $this->entityManager->flush();

        return $this->json(['success' => 'Votre visage a été enregistré avec succès.']);
    }

    #[Route('/verify', name: 'api_face_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['success' => false, 'message' => 'Email manquant.'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || !$user->getFaceDescriptor()) {
            return $this->json(['success' => false, 'message' => 'Référence faciale non trouvée.'], 404);
        }

        // Note: Real verification happens on client-side for speed, 
        // but we could implement extra security checks here if needed.
        // For this MVP, we return the stored descriptor so the client can compare,
        // OR we can just return success if the client already verified it (less secure but faster).
        
        return $this->json([
            'success' => true,
            'storedDescriptor' => $user->getFaceDescriptor()
        ]);
    }

    #[Route('/finalize-login', name: 'api_face_login_finalize', methods: ['POST'])]
    public function finalizeLogin(Request $request, Security $security): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['success' => false, 'message' => 'Email manquant.'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Utilisateur non trouvé.'], 404);
        }

        // Manually authenticate the user
        $security->login($user, 'form_login', 'main');

        return $this->json([
            'success' => true,
            'targetUrl' => $this->generateUrl('app_post_login')
        ]);
    }
}
