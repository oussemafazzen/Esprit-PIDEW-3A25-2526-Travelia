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

class UserReservationController extends AbstractController
{
    #[Route('/mes-reservations', name: 'app_user_reservations')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findBy([], ['id' => 'DESC']);

        return $this->render('front/user/reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/mes-reservations/new', name: 'app_user_reservation_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $reservation = new Reservation();

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (method_exists($reservation, 'setDateReservation') && !$reservation->getDateReservation()) {
                $reservation->setDateReservation(new \DateTime());
            }

            if (method_exists($reservation, 'setStatut') && !$reservation->getStatut()) {
                $reservation->setStatut('confirmé');
            }

            $em->persist($reservation);
            $em->flush();

            return new Response('success');
        }

        return $this->render('front/user/_new_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/mes-reservations/{id}/edit', name: 'app_user_reservation_edit')]
    public function edit(Reservation $reservation, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return new Response('success');
        }

        return $this->render('front/user/_edit_form.html.twig', [
            'form' => $form->createView(),
            'reservation' => $reservation,
        ]);
    }

    #[Route('/mes-reservations/{id}/delete', name: 'app_user_reservation_delete', methods: ['POST'])]
    public function delete(Reservation $reservation, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            $em->remove($reservation);
            $em->flush();
        }

        return $this->redirectToRoute('app_user_reservations');
    }
}