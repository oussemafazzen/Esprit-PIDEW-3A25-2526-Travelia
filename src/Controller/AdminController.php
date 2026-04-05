<?php

namespace App\Controller;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(ClientRepository $clientRepository): Response
    {
        // Statistics for the dashboard
        $nationalityStats = $clientRepository->getNationalityStats();
        $ageGroupStats = $clientRepository->getAgeGroupStats();
        $totalClients = $clientRepository->count([]);

        return $this->render('admin/dashboard.html.twig', [
            'nationalityStats' => $nationalityStats,
            'ageGroupStats' => $ageGroupStats,
            'totalClients' => $totalClients,
        ]);
    }

    #[Route('/travelers', name: 'app_admin_travelers')]
    public function travelers(Request $request, ClientRepository $clientRepository): Response
    {
        $search = $request->query->get('q');
        $sortBy = $request->query->get('sort', 'nom');
        $direction = $request->query->get('direction', 'ASC');

        $clients = $clientRepository->findBySearchAndSort($search, $sortBy, $direction);

        return $this->render('admin/travelers.html.twig', [
            'clients' => $clients,
            'search' => $search,
            'sortBy' => $sortBy,
            'direction' => $direction,
        ]);
    }

    #[Route('/client/{id}/delete', name: 'app_admin_client_delete', methods: ['POST'])]
    public function deleteClient(Client $client, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($client);
        $entityManager->flush();

        $this->addFlash('success', 'Le client a été supprimé.');
        return $this->redirectToRoute('app_admin_travelers');
    }

    #[Route('/client/{id}/toggle-status', name: 'app_admin_client_toggle_status', methods: ['POST'])]
    public function toggleClientStatus(Client $client, EntityManagerInterface $entityManager): Response
    {
        $newStatus = ($client->getStatut() === 'ACTIF') ? 'BLOQUE' : 'ACTIF';
        $client->setStatut($newStatus);
        $entityManager->flush();

        $statusMsg = ($newStatus === 'BLOQUE') ? 'bloqué' : 'débloqué';
        $this->addFlash('success', "Le client a été $statusMsg.");
        
        return $this->redirectToRoute('app_admin_travelers');
    }

    #[Route('/client/{id}/edit', name: 'app_admin_client_edit')]
    public function editClient(Client $client, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $client->setNom($request->request->get('nom'));
            $client->setPrenom($request->request->get('prenom'));
            $client->setEmail($request->request->get('email'));
            $client->setTelephone($request->request->get('telephone'));
            $client->setNationalite($request->request->get('nationalite'));
            
            $entityManager->flush();

            $this->addFlash('success', 'Le profil du client a été mis à jour.');
            return $this->redirectToRoute('app_admin_travelers');
        }

        return $this->render('admin/edit_client.html.twig', [
            'client' => $client,
        ]);
    }
}
