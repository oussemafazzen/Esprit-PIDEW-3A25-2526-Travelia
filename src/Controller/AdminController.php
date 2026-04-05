<?php

namespace App\Controller;

use App\Repository\BilletRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(
        ReservationRepository $reservationRepository,
        BilletRepository $billetRepository
    ): Response {
        $reservations = $reservationRepository->findAll();
        $billets = $billetRepository->findAll();

        // Revenus
        $totalRevenue = 0.0;
        foreach ($billets as $billet) {
            $prix = $billet->getPrix();
            $totalRevenue += $prix !== null ? (float) $prix : 0;
        }

        // Réservations
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

        // Billets
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

        // Graphe PAR MOIS (réservations)
        $labels = ['JAN', 'FÉV', 'MAR', 'AVR', 'MAI', 'JUI', 'JUL', 'AOÛ', 'SEP', 'OCT', 'NOV', 'DÉC'];
        $chartData = array_fill(0, 12, 0);

        foreach ($reservations as $reservation) {
            $date = $reservation->getDateReservation();

            if ($date instanceof \DateTimeInterface) {
                $monthIndex = (int) $date->format('n') - 1;
                $chartData[$monthIndex]++;
            }
        }

        // Réservations récentes
        usort($reservations, function ($a, $b) {
            $dateA = $a->getDateReservation();
            $dateB = $b->getDateReservation();

            if (!$dateA && !$dateB) {
                return 0;
            }
            if (!$dateA) {
                return 1;
            }
            if (!$dateB) {
                return -1;
            }

            return $dateB <=> $dateA;
        });

        $recentReservations = array_slice($reservations, 0, 5);

        // Billets récents
        usort($billets, function ($a, $b) {
            $dateA = $a->getDateDepart();
            $dateB = $b->getDateDepart();

            if (!$dateA && !$dateB) {
                return 0;
            }
            if (!$dateA) {
                return 1;
            }
            if (!$dateB) {
                return -1;
            }

            return $dateB <=> $dateA;
        });

        $recentBillets = array_slice($billets, 0, 5);

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
        ]);
    }
}