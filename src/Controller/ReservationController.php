<?php

namespace App\Controller;

use App\Entity\Billet;
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
    #[Route('/mes-reservations', name: 'app_user_reservations', methods: ['GET'])]
    public function mesReservations(ReservationRepository $reservationRepository): Response
    {
        return $this->render('reservation/mes_reservations.html.twig', [
            'reservations' => $reservationRepository->findAll(),
        ]);
    }

    #[Route(name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservationRepository->findAll(),
        ]);
    }

    #[Route('/quick-book', name: 'app_reservation_quick', methods: ['POST', 'GET'])]
    public function quickBook(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dest = $request->query->get('dest', 'Destination Inconnue');
        $price = (float) str_replace(['€', ','], '', $request->query->get('price', '0'));
        $dateStr = $request->query->get('date', date('Y-m-d'));
        
        // Handle existing reservation (rid) if provided
        $existingRid = $request->query->get('rid');
        if ($existingRid) {
            $reservation = $entityManager->getRepository(Reservation::class)->find($existingRid);
            if (!$reservation) {
                return $this->json(['success' => false, 'message' => 'Réservation parente introuvable.']);
            }
        } else {
            // 1. Create NEW Reservation
            $reservation = new Reservation();
            $reservation->setDateReservation(new \DateTime());
            $reservation->setPaysDestination($dest);
            $reservation->setStatut('En attente');
            $reservation->setModalitesPaiement('Carte');
            $reservation->setClientId(1); // Simulation of logged user
            $entityManager->persist($reservation);
        }
        
        // 2. Create Billet (linked to existing or new reservation)
        $billet = new Billet();
        $billet->setReservation($reservation);
        $billet->setTypeTransport('Avion');
        $billet->setNumeroBillet('TR-' . strtoupper(substr($dest, 0, 3)) . '-' . rand(100, 999));
        $billet->setDateDepart(new \DateTime($dateStr));
        $billet->setDateArrivee((new \DateTime($dateStr))->modify('+1 day'));
        $billet->setPrix($price ?: 880);
        $billet->setStatut('Confirmé');
        
        $entityManager->persist($billet);
        $entityManager->flush();

        // If AJAX (from Home Page), return JSON so we can open the Payment Modal
        if ($request->isXmlHttpRequest() || $request->query->get('ajax')) {
            return $this->json([
                'success' => true,
                'reservationId' => $reservation->getId(),
                'billetId' => $billet->getId(),
                'dest' => $dest,
                'price' => $price,
                'ref' => $billet->getNumeroBillet()
            ]);
        }

        $this->addFlash('success', 'Votre réservation a été créée avec succès !');
        return $this->redirectToRoute('app_user_reservations');
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

            return $this->redirectToRoute('app_reservation_front', [], Response::HTTP_SEE_OTHER);
        }

        // AJAX: return just the form fragment for the modal
        if ($request->isXmlHttpRequest()) {
            return $this->render('reservation/_modal_form.html.twig', [
                'reservation' => $reservation,
                'form'        => $form,
                'button_label' => 'Enregistrer',
            ]);
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form'        => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Request $request, Reservation $reservation): Response
    {
        // AJAX: return just the detail fragment for the modal
        if ($request->isXmlHttpRequest()) {
            return $this->render('reservation/_modal_show.html.twig', [
                'reservation' => $reservation,
            ]);
        }

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

        // AJAX: return just the form fragment for the modal
        if ($request->isXmlHttpRequest()) {
            return $this->render('reservation/_modal_form.html.twig', [
                'reservation'  => $reservation,
                'form'         => $form,
                'button_label' => 'Mettre à jour',
            ]);
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form'        => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($reservation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_reservations', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/quick-update', name: 'app_reservation_quick_update', methods: ['POST'])]
    public function quickUpdate(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $dest = $request->request->get('dest');
        $dateStr = $request->request->get('date');

        if ($dest) $reservation->setPaysDestination($dest);
        if ($dateStr) $reservation->setDateReservation(new \DateTime($dateStr));

        $entityManager->flush();

        return $this->json(['success' => true]);
    }
}
