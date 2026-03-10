<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UIShowcaseController extends AbstractController
{
    #[Route('/ui-showcase', name: 'app_ui_showcase')]
    public function index(): Response
    {
        return $this->render('examples/ui_showcase.html.twig');
    }
}