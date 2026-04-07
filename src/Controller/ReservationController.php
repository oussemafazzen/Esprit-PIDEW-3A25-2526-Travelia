<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reservation')]
final class ReservationController extends AbstractController
{
    #[Route(name: 'app_reservation_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservationRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'id');
        $direction = strtoupper((string) $request->query->get('direction', 'DESC'));

        $allowedSorts = [
            'id' => 'r.id',
            'date' => 'r.dateReservation',
            'statut' => 'r.statut',
            'paiement' => 'r.modalitesPaiement',
            'client' => 'r.clientId',
            'destination' => 'r.paysDestination',
        ];

        if (!isset($allowedSorts[$sort])) {
            $sort = 'id';
        }

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }

        $qb = $reservationRepository->createQueryBuilder('r');

        if ($search !== '') {
            $expr = $qb->expr()->orX(
                $qb->expr()->like('LOWER(r.statut)', ':q'),
                $qb->expr()->like('LOWER(r.modalitesPaiement)', ':q'),
                $qb->expr()->like('LOWER(r.paysDestination)', ':q')
            );

            if (ctype_digit($search)) {
                $expr->add($qb->expr()->eq('r.id', ':idSearch'));
                $expr->add($qb->expr()->eq('r.clientId', ':clientSearch'));
                $qb->setParameter('idSearch', (int) $search);
                $qb->setParameter('clientSearch', (int) $search);
            }

            $qb->andWhere($expr)
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        $qb->orderBy($allowedSorts[$sort], $direction);

        return $this->render('reservation/index.html.twig', [
            'reservations' => $qb->getQuery()->getResult(),
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reservation);
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reservation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($reservation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
    }
}