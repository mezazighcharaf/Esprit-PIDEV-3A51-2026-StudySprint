<?php

namespace App\Controller\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/security/face')]
class FaceAuthController extends AbstractController
{
    private const MATCH_THRESHOLD = 0.6;

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

    #[Route('/verify-and-login', name: 'api_face_verify_and_login', methods: ['POST'])]
    public function verifyAndLogin(Request $request, Security $security): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $capturedDescriptor = $data['descriptor'] ?? null;

        if (!$email || !$capturedDescriptor || !is_array($capturedDescriptor)) {
            return $this->json(['success' => false, 'message' => 'Email et descripteur facial requis.'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || !$user->getFaceDescriptor()) {
            return $this->json(['success' => false, 'message' => 'Référence faciale non trouvée.'], 404);
        }

        $storedDescriptor = $user->getFaceDescriptor();

        // Compute Euclidean distance server-side
        $distance = $this->euclideanDistance($capturedDescriptor, $storedDescriptor);

        if ($distance >= self::MATCH_THRESHOLD) {
            return $this->json([
                'success' => false,
                'message' => 'Visage non reconnu (distance: ' . round($distance, 4) . ', seuil: ' . self::MATCH_THRESHOLD . ').',
                'debug' => [
                    'distance' => round($distance, 4),
                    'threshold' => self::MATCH_THRESHOLD,
                    'stored_length' => count($storedDescriptor),
                    'captured_length' => count($capturedDescriptor),
                ]
            ], 401);
        }

        $security->login($user, 'form_login', 'main');

        return $this->json([
            'success' => true,
            'targetUrl' => $this->generateUrl('app_groups')
        ]);
    }

    #[Route('/enroll/page', name: 'face_enroll_page', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function enrollPage(): Response
    {
        return $this->render('fo/profile/face_enroll.html.twig');
    }

    private function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return PHP_FLOAT_MAX;
        }

        $sum = 0.0;
        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $diff = (float)$a[$i] - (float)$b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }
}
