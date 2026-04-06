<?php

namespace App\Controller;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Intl\Countries;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(ClientRepository $clientRepository): Response
    {
        // Statistics for the dashboard
        $rawNationalityStats = $clientRepository->getNationalityStats();
        
        // Unify nationality names and merge counts (e.g. TN and Tunisie -> Tunisie)
        $nationalityStats = [];
        foreach ($rawNationalityStats as $stat) {
            $name = $stat['nationalite'];
            // If it's an ISO code, translate it
            if (strlen($name) === 2 && ($fullName = Countries::getName(strtoupper($name), 'fr'))) {
                $name = $fullName;
            }
            
            if (!isset($nationalityStats[$name])) {
                $nationalityStats[$name] = 0;
            }
            $nationalityStats[$name] += $stat['count'];
        }
        
        // Sort and limit again after merge
        arsort($nationalityStats);
        $nationalityStats = array_slice($nationalityStats, 0, 5, true);

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
        $sortBy = $request->query->get('sort', 'date_creation');
        $direction = $request->query->get('direction', 'DESC');

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
            $client->setNom($request->request->get('nom', $client->getNom()));
            $client->setPrenom($request->request->get('prenom', $client->getPrenom()));
            $client->setEmail($request->request->get('email', $client->getEmail()));
            $client->setTelephone($request->request->get('telephone', $client->getTelephone() ?? ""));
            
            // Handle nationality conversion if possible
            $nat = $request->request->get('nationalite', $client->getNationalite());
            if ($nat && strlen($nat) === 2 && ($fName = Countries::getName(strtoupper($nat), 'fr'))) {
                $nat = $fName;
            }
            $client->setNationalite($nat);
            
            $entityManager->flush();

            $this->addFlash('success', 'Le profil du client a été mis à jour.');
            return $this->redirectToRoute('app_admin_travelers');
        }

        return $this->render('admin/edit_client.html.twig', [
            'client' => $client,
        ]);
    }
}
