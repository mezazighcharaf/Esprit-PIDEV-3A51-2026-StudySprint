<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'root')]
    public function root(): Response
    {
        // Redirect root to login page
        return $this->redirectToRoute('app_login');
    }

    #[Route('/profil', name: 'app_profile_redirect')]
    public function profilRedirect(): Response
    {
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/start', name: 'app_home')]
    public function index(): Response
    {
        // If user is already logged in, redirect to their dashboard (Removed to allow viewing landing page)
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('app_post_login');
        // }

        return $this->render('home.html.twig');
    }
}
