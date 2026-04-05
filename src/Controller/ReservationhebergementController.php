<?php

namespace App\Controller;

use App\Entity\Reservationhebergement;
use App\Form\ReservationhebergementType;
use App\Repository\ReservationhebergementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reservationhebergement')]
final class ReservationhebergementController extends AbstractController
{
    #[Route(name: 'app_reservationhebergement_index', methods: ['GET'])]
    public function index(ReservationhebergementRepository $reservationhebergementRepository): Response
    {
        return $this->render('reservationhebergement/index.html.twig', [
            'reservationhebergements' => $reservationhebergementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_reservationhebergement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reservationhebergement = new Reservationhebergement();
        $form = $this->createForm(ReservationhebergementType::class, $reservationhebergement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reservationhebergement);
            $entityManager->flush();

            return $this->redirectToRoute('app_reservationhebergement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservationhebergement/new.html.twig', [
            'reservationhebergement' => $reservationhebergement,
            'form' => $form,
        ]);
    }

    #[Route('/{idReservationHebergement}', name: 'app_reservationhebergement_show', methods: ['GET'])]
    public function show(Reservationhebergement $reservationhebergement): Response
    {
        return $this->render('reservationhebergement/show.html.twig', [
            'reservationhebergement' => $reservationhebergement,
        ]);
    }

    #[Route('/{idReservationHebergement}/edit', name: 'app_reservationhebergement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservationhebergement $reservationhebergement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReservationhebergementType::class, $reservationhebergement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_reservationhebergement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservationhebergement/edit.html.twig', [
            'reservationhebergement' => $reservationhebergement,
            'form' => $form,
        ]);
    }

    #[Route('/{idReservationHebergement}', name: 'app_reservationhebergement_delete', methods: ['POST'])]
    public function delete(Request $request, Reservationhebergement $reservationhebergement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reservationhebergement->getIdReservationHebergement(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($reservationhebergement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_reservationhebergement_index', [], Response::HTTP_SEE_OTHER);
    }
}
