<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DiagLoginController extends AbstractController
{
    #[Route('/diag/login', name: 'diag_login')]
    public function index(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $output = '<h1>Login Diagnostic</h1>';
        $output .= '<form method="post">';
        $output .= 'Email: <input type="email" name="email" value="'.htmlspecialchars($request->request->get('email') ?? '').'"><br>';
        $output .= 'Password: <input type="text" name="password" value="'.htmlspecialchars($request->request->get('password') ?? '').'"><br>';
        $output .= '<button type="submit">Test Login</button>';
        $output .= '</form>';

        if ($request->isMethod('POST')) {
            $email = strtolower(trim($request->request->get('email')));
            $password = $request->request->get('password');

            $output .= "<h2>Results for: $email</h2>";

            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user) {
                $output .= "<p style='color:red'>User NOT FOUND in database.</p>";
            } else {
                $output .= "<p style='color:green'>User FOUND.</p>";
                $output .= "<p>ID: " . $user->getId() . "</p>";
                $output .= "<p>Roles: " . implode(', ', $user->getRoles()) . "</p>";
                $output .= "<p>Status: " . $user->getStatut() . "</p>";
                $output .= "<p>Stored Hash: " . $user->getPassword() . "</p>";

                $valid = $hasher->isPasswordValid($user, $password);
                
                if ($valid) {
                    $output .= "<p style='color:green; font-weight:bold; font-size:1.5em'>PASSWORD MATCHES!</p>";
                } else {
                    $output .= "<p style='color:red; font-weight:bold; font-size:1.5em'>PASSWORD DOES NOT MATCH!</p>";
                    
                    // Debug info
                    $newHash = $hasher->hashPassword($user, $password);
                    $output .= "<p>If you hashed '$password' right now, it would be: $newHash</p>";
                    $output .= "<p>Compare this to Stored Hash above.</p>";
                }
            }
        }

        return new Response($output);
    }
}
