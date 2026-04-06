<?php

namespace App\Controller;

use App\Entity\Paiement;
use App\Form\PaiementType;
use App\Entity\Reservationhebergement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/paiement')]
class PaiementController extends AbstractController
{
    #[Route('/new/{reservation_id}', name: 'app_paiement_new', methods: ['GET', 'POST'])]
    public function new(int $reservation_id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $paiement = new Paiement();
        $paiement->setIdReservation($reservation_id);
        $paiement->setDatePaiement(new \DateTime());
        
        // Find the reservation to get details if needed (like total price)
        $reservation = $entityManager->getRepository(Reservationhebergement::class)->find($reservation_id);
        
        if ($reservation) {
            // Pre-fill amount from hebergement price
            $hebergement = $reservation->getIdHebergement();
            if ($hebergement) {
                $paiement->setMontant($hebergement->getTarifParNuit());
            }
        }

        $form = $this->createForm(PaiementType::class, $paiement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Logic to confirm the reservation status automatically upon payment
            if ($reservation) {
                $reservation->setStatut('Confirmé');
            }

            $entityManager->persist($paiement);
            $entityManager->flush();

            return $this->redirectToRoute('app_reservationhebergement_index');
        }

        return $this->render('paiement/new.html.twig', [
            'paiement' => $paiement,
            'form' => $form,
            'reservation' => $reservation
        ]);
    }
}
