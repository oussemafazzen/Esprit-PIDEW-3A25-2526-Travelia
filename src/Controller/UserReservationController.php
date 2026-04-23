<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ClientRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserReservationController extends AbstractController
{
    #[Route('/mes-reservations', name: 'app_user_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository, ClientRepository $clientRepository): Response
    {
        $currentClient = $this->resolveAuthenticatedClient($clientRepository);

        if (!$currentClient instanceof Client || $currentClient->getId() === null) {
            $this->addFlash('error', 'Vous devez être connecté pour consulter vos réservations.');
            return $this->redirectToRoute('app_login');
        }

        $reservations = $reservationRepository->createQueryBuilder('r')
            ->leftJoin('r.billets', 'b')
            ->addSelect('b')
            ->andWhere('r.clientId = :clientId')
            ->setParameter('clientId', $currentClient->getId())
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('b.dateDepart', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('front/user/reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/mes-reservations/new', name: 'app_user_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ClientRepository $clientRepository): Response
    {
        $currentClient = $this->resolveAuthenticatedClient($clientRepository);

        if (!$currentClient instanceof Client || $currentClient->getId() === null) {
            $this->addFlash('error', 'Vous devez être connecté pour créer une réservation.');
            return $this->redirectToRoute('app_login');
        }

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
                $reservation->setClientId($currentClient->getId());
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
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $em, ClientRepository $clientRepository): Response
    {
        $currentClient = $this->resolveAuthenticatedClient($clientRepository);

        if (!$currentClient instanceof Client || $currentClient->getId() === null) {
            $this->addFlash('error', 'Vous devez être connecté pour modifier une réservation.');
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getClientId() !== $currentClient->getId()) {
            throw $this->createAccessDeniedException('Cette réservation ne vous appartient pas.');
        }

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
                $reservation->setClientId($currentClient->getId());
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

    private function resolveAuthenticatedClient(ClientRepository $clientRepository): ?Client
    {
        $user = $this->getUser();

        if ($user instanceof Client) {
            return $user;
        }

        $identifier = null;

        if ($user instanceof UserInterface) {
            $identifier = $user->getUserIdentifier();
        } elseif (is_object($user) && method_exists($user, 'getEmail')) {
            $identifier = (string) $user->getEmail();
        }

        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return null;
        }

        return $clientRepository->findOneBy(['email' => $identifier]);
    }
}
