<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/client')]
class ClientController extends AbstractController
{
    #[Route('/dashboard', name: 'app_client_dashboard')]
    public function index(): Response
    {
        // Mock data for display as requested
        $reservations = [
            ['id' => 1, 'dest' => 'Maldives — Overwater suite', 'date' => '2026-05-12', 'status' => 'Confirmé'],
            ['id' => 2, 'dest' => 'Paris — Luxe Hotel', 'date' => '2026-06-20', 'status' => 'En attente'],
        ];

        $hebergements = [
            ['id' => 1, 'nom' => 'Elysium Villa Stay', 'lieu' => 'Maldives'],
            ['id' => 2, 'nom' => 'Le Bristol Paris', 'lieu' => 'France'],
        ];

        $activites = [
            ['id' => 1, 'nom' => 'Safari Expedition', 'lieu' => 'Tanzanie'],
            ['id' => 2, 'nom' => 'Diving with Manta Rays', 'lieu' => 'Maldives'],
        ];

        return $this->render('client/index.html.twig', [
            'reservations' => $reservations,
            'hebergements' => $hebergements,
            'activites' => $activites,
        ]);
    }
}
