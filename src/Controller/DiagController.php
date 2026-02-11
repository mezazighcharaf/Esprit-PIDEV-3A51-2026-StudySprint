<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DiagController extends AbstractController
{
    #[Route('/diag/users', name: 'diag_users')]
    public function index(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $users = $entityManager->getRepository(User::class)->findAll();
        $output = "<h1>Diagnostic Users</h1>";
        $output .= "<table border='1'><tr><th>ID</th><th>Email</th><th>Role</th><th>Status</th><th>Password Hash</th></tr>";
        
        $emails = [];
        $duplicates = [];

        foreach ($users as $user) {
            $email = strtolower($user->getEmail());
            $output .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>", 
                $user->getId(), 
                $user->getEmail(), 
                $user->getRole(),
                $user->getStatut(),
                substr($user->getPassword(), 0, 20) . '...'
            );

            if (isset($emails[$email])) {
                $duplicates[] = $email;
            }
            $emails[$email] = true;
        }
        $output .= "</table>";

        if (!empty($duplicates)) {
            $output .= "<h2>DUPLICATES FOUND: " . implode(', ', $duplicates) . "</h2>";
        } else {
            $output .= "<h2>No duplicate emails found.</h2>";
        }

        // Test Hashing for a specific user
        $targetEmail = 'cherniranym@gmail.com'; 
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $targetEmail]);
        
        if ($user) {
            $output .= "<h2>Hashing Test for $targetEmail</h2>";
            $testPass = 'Azerty123!'; 
            
            $output .= "<p>Test Password: $testPass</p>";
            
            $valid = $hasher->isPasswordValid($user, $testPass); 
            $output .= "<p>Is '$testPass' valid for CURRENT DB hash? " . ($valid ? 'YES' : 'NO') . "</p>";
            
            // Force Reset Test
            if ($request->query->get('force_reset')) {
                $output .= "<h2>FORCING RESET TO '$testPass'</h2>";
                $newHash = $hasher->hashPassword($user, $testPass);
                $user->setMotDePasse($newHash);
                $entityManager->flush();
                $output .= "<p> flushed. </p>";
                
                // Re-check (reload from DB to be sure)
                $entityManager->refresh($user);
                $validAfter = $hasher->isPasswordValid($user, $testPass);
                $output .= "<p>Is '$testPass' valid AFTER FORCED RESET? " . ($validAfter ? 'YES' : 'NO') . "</p>";
            }

            // Force Activate Test
            if ($request->query->get('force_activate')) {
                $output .= "<h2>FORCING ACTIVATION for $targetEmail</h2>";
                $user->setStatut('actif');
                $entityManager->flush();
                $output .= "<p>Status updated to 'actif' and flushed.</p>";
            }
        } else {
            $output .= "<p>User $targetEmail not found.</p>";
        }

        return new Response($output);
    }
}
