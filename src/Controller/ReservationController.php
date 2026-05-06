<?php

namespace App\Controller;

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
    #[Route(name: 'app_reservation_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservationRepository, PaginatorInterface $paginator): Response
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

        $calendarReservations = $reservationRepository->createQueryBuilder('cr')
            ->leftJoin('cr.billets', 'cb')
            ->addSelect('cb')
            ->orderBy('cr.dateReservation', 'ASC')
            ->addOrderBy('cr.id', 'DESC')
            ->addOrderBy('cb.dateDepart', 'ASC')
            ->getQuery()
            ->getResult();

        $reservations = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            3
        );

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
            'calendarReservations' => $calendarReservations,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/export/excel', name: 'app_reservation_export_excel', methods: ['GET'])]
    public function exportExcel(ReservationRepository $reservationRepository): StreamedResponse
    {
        // We build the spreadsheet in memory, then Symfony streams the file
        // directly to the browser so the admin can download it immediately.
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
            $sheet->setCellValue(
                "A{$row}",
                $reservation->getDateReservation()?->format('Y-m-d') ?? ''
            );
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

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    #[Route('/export/pdf', name: 'app_reservation_export_pdf', methods: ['GET'])]
    public function exportPdf(ReservationRepository $reservationRepository, Pdf $pdf): Response
    {
        // We reuse the same data as the Excel export so both admin exports stay
        // consistent and easy to present.
        $reservations = $reservationRepository->findBy([], ['id' => 'DESC']);

        // Symfony renders a print-friendly Twig template into plain HTML first.
        $html = $this->renderView('reservation/pdf.html.twig', [
            'reservations' => $reservations,
            'generatedAt' => new \DateTime(),
        ]);

        // Snappy sends this HTML to wkhtmltopdf and receives a binary PDF file.
        $output = $pdf->getOutputFromHtml($html);

        $filename = 'reservations-' . (new \DateTime())->format('Y-m-d-His') . '.pdf';

        // Symfony then returns the generated PDF as a downloadable response.
        return new Response($output, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
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

            $this->addFlash('success', 'Réservation ajoutée avec succès.');

            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted()) {
            $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
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

            $this->addFlash('success', 'Réservation modifiée avec succès.');

            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted()) {
            $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
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
