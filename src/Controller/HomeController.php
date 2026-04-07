<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        $queryString = $request->getQueryString();
        $target = '/travelia.html' . ($queryString ? '?' . $queryString : '');
        
        return $this->redirect($target);
    }
}
