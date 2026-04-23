<?php

namespace App\Controller;

use App\Entity\Client;
use App\Repository\BilletRepository;
use App\Repository\ClientRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_dashboard')]
    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(
        Request $request,
        ReservationRepository $reservationRepository,
        BilletRepository $billetRepository
    ): Response {
        $reservations = $reservationRepository->findAll();
        $billets = $billetRepository->findAll();

       

        $totalRevenue = 0.0;
        foreach ($billets as $billet) {
            $prix = $billet->getPrix();
            $totalRevenue += $prix !== null ? (float) $prix : 0;
        }

        $totalReservations = count($reservations);
        $pendingReservations = 0;
        $confirmedReservations = 0;

        foreach ($reservations as $reservation) {
            $statut = mb_strtolower(trim((string) $reservation->getStatut()));

            if (str_contains($statut, 'attente')) {
                $pendingReservations++;
            }

            if (str_contains($statut, 'confirm')) {
                $confirmedReservations++;
            }
        }

        $confirmationRate = $totalReservations > 0
            ? round(($confirmedReservations / $totalReservations) * 100, 1)
            : 0;

        $totalBillets = count($billets);
        $pendingBillets = 0;
        $confirmedBillets = 0;

        foreach ($billets as $billet) {
            $statut = mb_strtolower(trim((string) $billet->getStatut()));

            if (str_contains($statut, 'attente')) {
                $pendingBillets++;
            }

            if (str_contains($statut, 'confirm')) {
                $confirmedBillets++;
            }
        }

        $billetConfirmationRate = $totalBillets > 0
            ? round(($confirmedBillets / $totalBillets) * 100, 1)
            : 0;

        

        $labels = ['JAN', 'FÉV', 'MAR', 'AVR', 'MAI', 'JUI', 'JUL', 'AOÛ', 'SEP', 'OCT', 'NOV', 'DÉC'];
        $chartData = array_fill(0, 12, 0);

        foreach ($reservations as $reservation) {
            $date = $reservation->getDateReservation();

            if ($date instanceof \DateTimeInterface) {
                $monthIndex = (int) $date->format('n') - 1;
                $chartData[$monthIndex]++;
            }
        }

        
        $reservationSearch = trim((string) $request->query->get('reservation_search', ''));
        $reservationSort = (string) $request->query->get('reservation_sort', 'date');
        $reservationDirection = strtoupper((string) $request->query->get('reservation_direction', 'DESC'));

        $allowedReservationSorts = ['id', 'date', 'statut', 'paiement', 'client', 'destination'];

        if (!in_array($reservationSort, $allowedReservationSorts, true)) {
            $reservationSort = 'date';
        }

        if (!in_array($reservationDirection, ['ASC', 'DESC'], true)) {
            $reservationDirection = 'DESC';
        }

        $filteredReservations = array_filter($reservations, function ($reservation) use ($reservationSearch) {
            if ($reservationSearch === '') {
                return true;
            }

            $search = mb_strtolower($reservationSearch);

            $id = (string) ($reservation->getId() ?? '');
            $date = $reservation->getDateReservation()?->format('Y-m-d') ?? '';
            $statut = mb_strtolower((string) ($reservation->getStatut() ?? ''));
            $paiement = mb_strtolower((string) ($reservation->getModalitesPaiement() ?? ''));
            $client = (string) ($reservation->getClientId() ?? '');
            $destination = mb_strtolower((string) ($reservation->getPaysDestination() ?? ''));

            return str_contains($id, $search)
                || str_contains($date, $search)
                || str_contains($statut, $search)
                || str_contains($paiement, $search)
                || str_contains($client, $search)
                || str_contains($destination, $search);
        });

        usort($filteredReservations, function ($a, $b) use ($reservationSort, $reservationDirection) {
            $valueA = match ($reservationSort) {
                'id' => $a->getId(),
                'date' => $a->getDateReservation()?->getTimestamp() ?? 0,
                'statut' => mb_strtolower((string) $a->getStatut()),
                'paiement' => mb_strtolower((string) $a->getModalitesPaiement()),
                'client' => $a->getClientId(),
                'destination' => mb_strtolower((string) $a->getPaysDestination()),
                default => $a->getDateReservation()?->getTimestamp() ?? 0,
            };

            $valueB = match ($reservationSort) {
                'id' => $b->getId(),
                'date' => $b->getDateReservation()?->getTimestamp() ?? 0,
                'statut' => mb_strtolower((string) $b->getStatut()),
                'paiement' => mb_strtolower((string) $b->getModalitesPaiement()),
                'client' => $b->getClientId(),
                'destination' => mb_strtolower((string) $b->getPaysDestination()),
                default => $b->getDateReservation()?->getTimestamp() ?? 0,
            };

            $result = $valueA <=> $valueB;

            return $reservationDirection === 'ASC' ? $result : -$result;
        });

        $recentReservations = array_slice($filteredReservations, 0, 10);

       
        $billetSearch = trim((string) $request->query->get('billet_search', ''));
        $billetSort = (string) $request->query->get('billet_sort', 'depart');
        $billetDirection = strtoupper((string) $request->query->get('billet_direction', 'DESC'));

        $allowedBilletSorts = ['id', 'transport', 'numero', 'depart', 'arrivee', 'prix', 'statut'];

        if (!in_array($billetSort, $allowedBilletSorts, true)) {
            $billetSort = 'depart';
        }

        if (!in_array($billetDirection, ['ASC', 'DESC'], true)) {
            $billetDirection = 'DESC';
        }

        $filteredBillets = array_filter($billets, function ($billet) use ($billetSearch) {
            if ($billetSearch === '') {
                return true;
            }

            $search = mb_strtolower($billetSearch);

            $id = (string) ($billet->getId() ?? '');
            $transport = mb_strtolower((string) ($billet->getTypeTransport() ?? ''));
            $numero = mb_strtolower((string) ($billet->getNumeroBillet() ?? ''));
            $depart = $billet->getDateDepart()?->format('Y-m-d') ?? '';
            $arrivee = $billet->getDateArrivee()?->format('Y-m-d') ?? '';
            $prix = (string) ($billet->getPrix() ?? '');
            $statut = mb_strtolower((string) ($billet->getStatut() ?? ''));

            return str_contains($id, $search)
                || str_contains($transport, $search)
                || str_contains($numero, $search)
                || str_contains($depart, $search)
                || str_contains($arrivee, $search)
                || str_contains($prix, $search)
                || str_contains($statut, $search);
        });

        usort($filteredBillets, function ($a, $b) use ($billetSort, $billetDirection) {
            $valueA = match ($billetSort) {
                'id' => $a->getId(),
                'transport' => mb_strtolower((string) $a->getTypeTransport()),
                'numero' => mb_strtolower((string) $a->getNumeroBillet()),
                'depart' => $a->getDateDepart()?->getTimestamp() ?? 0,
                'arrivee' => $a->getDateArrivee()?->getTimestamp() ?? 0,
                'prix' => (float) ($a->getPrix() ?? 0),
                'statut' => mb_strtolower((string) $a->getStatut()),
                default => $a->getDateDepart()?->getTimestamp() ?? 0,
            };

            $valueB = match ($billetSort) {
                'id' => $b->getId(),
                'transport' => mb_strtolower((string) $b->getTypeTransport()),
                'numero' => mb_strtolower((string) $b->getNumeroBillet()),
                'depart' => $b->getDateDepart()?->getTimestamp() ?? 0,
                'arrivee' => $b->getDateArrivee()?->getTimestamp() ?? 0,
                'prix' => (float) ($b->getPrix() ?? 0),
                'statut' => mb_strtolower((string) $b->getStatut()),
                default => $b->getDateDepart()?->getTimestamp() ?? 0,
            };

            $result = $valueA <=> $valueB;

            return $billetDirection === 'ASC' ? $result : -$result;
        });

        $recentBillets = array_slice($filteredBillets, 0, 10);

        return $this->render('admin/dashboard.html.twig', [
            'totalRevenue' => $totalRevenue,

            'totalReservations' => $totalReservations,
            'pendingReservations' => $pendingReservations,
            'confirmedReservations' => $confirmedReservations,
            'confirmationRate' => $confirmationRate,

            'totalBillets' => $totalBillets,
            'pendingBillets' => $pendingBillets,
            'confirmedBillets' => $confirmedBillets,
            'billetConfirmationRate' => $billetConfirmationRate,

            'labels' => $labels,
            'chartData' => $chartData,

            'recentReservations' => $recentReservations,
            'recentBillets' => $recentBillets,

            'reservationSearch' => $reservationSearch,
            'reservationSort' => $reservationSort,
            'reservationDirection' => $reservationDirection,

            'billetSearch' => $billetSearch,
            'billetSort' => $billetSort,
            'billetDirection' => $billetDirection,
        ]);
    }

    #[Route('/admin/travelers', name: 'app_admin_travelers', methods: ['GET'])]
    public function travelers(Request $request, ClientRepository $clientRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sortBy = (string) $request->query->get('sort', 'nom');
        $direction = strtoupper((string) $request->query->get('direction', 'ASC'));

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $clients = $clientRepository->findBySearchAndSort($search, $sortBy, $direction);

        return $this->render('admin/travelers.html.twig', [
            'clients' => $clients,
            'search' => $search,
            'sortBy' => $sortBy,
            'direction' => $direction,
        ]);
    }

    #[Route('/admin/clients/{id}/toggle-status', name: 'app_admin_client_toggle_status', methods: ['POST'])]
    public function toggleClientStatus(Client $client, EntityManagerInterface $entityManager): Response
    {
        $client->setStatut($client->getStatut() === 'ACTIF' ? 'BLOQUE' : 'ACTIF');
        $entityManager->flush();

        $this->addFlash('success', 'Statut client mis a jour.');

        return $this->redirectToRoute('app_admin_travelers');
    }

    #[Route('/admin/clients/{id}/delete', name: 'app_admin_client_delete', methods: ['POST'])]
    public function deleteClient(Client $client, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($client);
        $entityManager->flush();

        $this->addFlash('success', 'Client supprime.');

        return $this->redirectToRoute('app_admin_travelers');
    }
}
