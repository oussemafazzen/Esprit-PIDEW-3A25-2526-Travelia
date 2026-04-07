<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/travelia.html', name: 'app_home_alt')]
    public function index(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        return $this->render('home.html.twig', [
            'rid' => $request->query->get('rid')
        ]);
    }
}
