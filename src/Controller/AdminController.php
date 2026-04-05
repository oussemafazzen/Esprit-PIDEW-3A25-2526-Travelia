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

        $labels = [];
        $daysMap = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = new \DateTimeImmutable("-{$i} days");
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('D');
            $daysMap[$key] = 0;
        }

        foreach ($reservations as $reservation) {
            $date = $reservation->getDateReservation();

            if ($date instanceof \DateTimeInterface) {
                $key = $date->format('Y-m-d');

                if (array_key_exists($key, $daysMap)) {
                    $daysMap[$key]++;
                }
            }
        }

        $chartData = array_values($daysMap);

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

        return $this->render('admin/dashboard.html.twig', [
            'totalRevenue' => $totalRevenue,
            'totalReservations' => $totalReservations,
            'pendingReservations' => $pendingReservations,
            'confirmedReservations' => $confirmedReservations,
            'confirmationRate' => $confirmationRate,
            'labels' => $labels,
            'chartData' => $chartData,
            'recentReservations' => $recentReservations,
        ]);
    }
}