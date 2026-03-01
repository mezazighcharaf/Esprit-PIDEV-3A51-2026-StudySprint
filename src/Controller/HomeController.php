<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
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
