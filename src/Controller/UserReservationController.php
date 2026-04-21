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

final class UserReservationController extends AbstractController
{
    #[Route('/mes-reservations', name: 'app_user_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findBy(['clientId' => 51], ['id' => 'DESC']);

        return $this->render('front/user/reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/mes-reservations/new', name: 'app_user_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $reservation = new Reservation();

        $form = $this->createForm(ReservationType::class, $reservation, [
            'action' => $this->generateUrl('app_user_reservation_new'),
            'method' => 'POST',
            'attr' => [
                'id' => 'newReservationForm'
            ]
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$reservation->getStatut()) {
                $reservation->setStatut('en_attente');
            }

            if (!$reservation->getModalitesPaiement()) {
                $reservation->setModalitesPaiement('carte');
            }

            if (!$reservation->getClientId()) {
                $reservation->setClientId(51);
            }

            if (!$reservation->getPaysDestination()) {
                $reservation->setPaysDestination('Non défini');
            }

            if ($form->isValid()) {
                $em->persist($reservation);
                $em->flush();

                return $this->redirectToRoute('app_flights_search');
            }
        }

        return $this->render('reservation/_form.html.twig', [
            'form' => $form->createView(),
            'button_label' => 'Créer',
        ]);
    }

    #[Route('/mes-reservations/{id}/edit', name: 'app_user_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation, [
            'action' => $this->generateUrl('app_user_reservation_edit', [
                'id' => $reservation->getId()
            ]),
            'method' => 'POST',
            'attr' => [
                'id' => 'editReservationForm'
            ]
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$reservation->getStatut()) {
                $reservation->setStatut('en_attente');
            }

            if (!$reservation->getModalitesPaiement()) {
                $reservation->setModalitesPaiement('carte');
            }

            if (!$reservation->getClientId()) {
                $reservation->setClientId(51);
            }

            if (!$reservation->getPaysDestination()) {
                $reservation->setPaysDestination('Non défini');
            }

            if ($form->isValid()) {
                return $this->redirectToRoute('app_flights_search', [
                    'reservation_id' => $reservation->getId(),
                    'destination' => $reservation->getPaysDestination() ?? '',
                    'date_depart' => $reservation->getDateReservation()?->format('Y-m-d'),
                    'passagers' => 1,
                ]);
            }
        }

        return $this->render('front/user/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
            'button_label' => 'Voir les vols disponibles',
        ]);
    }

    #[Route('/mes-reservations/{id}', name: 'app_user_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_reservation_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $em->remove($reservation);
            $em->flush();
        }

        return $this->redirectToRoute('app_user_reservations');
    }
}
