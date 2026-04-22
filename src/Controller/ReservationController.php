<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reservation')]
final class ReservationController extends AbstractController
{
    // ── Old admin "mes-reservations" route (kept to avoid broken links) ──
    #[Route('/mes-reservations', name: 'app_reservation_mes_reservations', methods: ['GET'])]
    public function mesReservations(ReservationRepository $reservationRepository): Response
    {
        return $this->render('reservation/mes_reservations.html.twig', [
            'reservations' => $reservationRepository->findAll(),
        ]);
    }

    // ── Admin index with search, sort, pagination & calendar ──
    #[Route(name: 'app_reservation_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservationRepository, PaginatorInterface $paginator): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'id');
        $direction = strtoupper((string) $request->query->get('direction', 'DESC'));

        $allowedSorts = [
            'id'          => 'r.id',
            'date'        => 'r.dateReservation',
            'statut'      => 'r.statut',
            'paiement'    => 'r.modalitesPaiement',
            'client'      => 'r.clientId',
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

        $calendarReservations = $reservationRepository->findBy([], ['dateReservation' => 'ASC', 'id' => 'DESC']);

        $reservations = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            5,
            [
                PaginatorInterface::SORT_FIELD_PARAMETER_NAME => 'knp_sort',
                PaginatorInterface::SORT_DIRECTION_PARAMETER_NAME => 'knp_dir',
            ]
        );

        return $this->render('reservation/index.html.twig', [
            'reservations'         => $reservations,
            'calendarReservations' => $calendarReservations,
            'search'               => $search,
            'sort'                 => $sort,
            'direction'            => $direction,
        ]);
    }

    // ── Excel export ──
    #[Route('/export/excel', name: 'app_reservation_export_excel', methods: ['GET'])]
    public function exportExcel(ReservationRepository $reservationRepository): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reservations');

        $headers = ['Date réservation', 'Statut', 'Paiement', 'Client', 'Destination'];
        foreach ($headers as $index => $header) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue($column . '1', $header);
        }

        $row = 2;
        foreach ($reservationRepository->findBy([], ['id' => 'DESC']) as $reservation) {
            $sheet->setCellValue("A{$row}", $reservation->getDateReservation()?->format('Y-m-d') ?? '');
            $sheet->setCellValue("B{$row}", $reservation->getStatut() ?? '');
            $sheet->setCellValue("C{$row}", $reservation->getModalitesPaiement() ?? '');
            $sheet->setCellValue("D{$row}", $reservation->getClientId() ?? '');
            $sheet->setCellValue("E{$row}", $reservation->getPaysDestination() ?? '');
            ++$row;
        }

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'reservations-' . (new \DateTime())->format('Y-m-d-His') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    // ── PDF export (uses wkhtmltopdf via KnpSnappy) ──
    #[Route('/export/pdf', name: 'app_reservation_export_pdf', methods: ['GET'])]
    public function exportPdf(ReservationRepository $reservationRepository, Pdf $pdf): Response
    {
        $reservations = $reservationRepository->findBy([], ['id' => 'DESC']);

        $html = $this->renderView('reservation/pdf.html.twig', [
            'reservations' => $reservations,
            'generatedAt'  => new \DateTime(),
        ]);

        $output = $pdf->getOutputFromHtml($html);
        $filename = 'reservations-' . (new \DateTime())->format('Y-m-d-His') . '.pdf';

        return new Response($output, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ── Quick book (AJAX: creates reservation + billet from home page) ──
    #[Route('/quick-book', name: 'app_reservation_quick', methods: ['POST', 'GET'])]
    public function quickBook(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dest    = $request->query->get('dest', 'Destination Inconnue');
        $price   = (float) str_replace(['€', ','], '', $request->query->get('price', '0'));
        $dateStr = $request->query->get('date', date('Y-m-d'));

        $existingRid = $request->query->get('rid');
        if ($existingRid) {
            $reservation = $entityManager->getRepository(Reservation::class)->find($existingRid);
            if (!$reservation) {
                return $this->json(['success' => false, 'message' => 'Réservation parente introuvable.']);
            }
        } else {
            $reservation = new Reservation();
            $reservation->setDateReservation(new \DateTime());
            $reservation->setPaysDestination($dest);
            $reservation->setStatut('En attente');
            $reservation->setModalitesPaiement('Carte');
            $clientId = $this->getUser() ? $this->getUser()->getId() : 1;
            $reservation->setClientId($clientId);
            $entityManager->persist($reservation);
        }

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

        if ($request->isXmlHttpRequest() || $request->query->get('ajax')) {
            return $this->json([
                'success'       => true,
                'reservationId' => $reservation->getId(),
                'billetId'      => $billet->getId(),
                'dest'          => $dest,
                'price'         => $price,
                'ref'           => $billet->getNumeroBillet(),
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

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if (!$reservation->getModalitesPaiement()) {
                    $reservation->setModalitesPaiement('carte');
                }
                if (!$reservation->getClientId()) {
                    $reservation->setClientId(51);
                }
                if (!$reservation->getPaysDestination()) {
                    $reservation->setPaysDestination('Non défini');
                }

                $entityManager->persist($reservation);
                $entityManager->flush();
                $this->addFlash('success', 'Réservation ajoutée avec succès.');

                return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
            }
            $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form'        => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Request $request, Reservation $reservation): Response
    {
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

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if (!$reservation->getModalitesPaiement()) {
                    $reservation->setModalitesPaiement('carte');
                }
                if (!$reservation->getClientId()) {
                    $reservation->setClientId(51);
                }
                if (!$reservation->getPaysDestination()) {
                    $reservation->setPaysDestination('Non défini');
                }
                $entityManager->flush();
                $this->addFlash('success', 'Réservation modifiée avec succès.');

                return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
            }
            $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('reservation/_modal_form.html.twig', [
                'reservation'  => $reservation,
                'form'         => $form,
                'button_label' => 'Mettre à jour',
            ]);
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form'        => $form->createView(),
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

    #[Route('/{id}/quick-update', name: 'app_reservation_quick_update', methods: ['POST'])]
    public function quickUpdate(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $dest    = $request->request->get('dest');
        $dateStr = $request->request->get('date');

        if ($dest) $reservation->setPaysDestination($dest);
        if ($dateStr) $reservation->setDateReservation(new \DateTime($dateStr));

        $entityManager->flush();

        return $this->json(['success' => true]);
    }
}
