<?php

namespace App\Controller\Bo;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ComponentsController extends AbstractController
{
    #[Route('/admin/components', name: 'admin_components')]
    public function index(): Response
    {
        return $this->render('bo/components.html.twig');
    }
}
