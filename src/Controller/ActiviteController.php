<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Entity\InscriptionActivite;
use App\Form\ActiviteType;
use App\Form\InscriptionActiviteType;
use App\Repository\ActiviteRepository;
use App\Repository\InscriptionActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/activites')]
final class ActiviteController extends AbstractController
{
    // ══════════════════════════════════════════════════════════
    //  ACTIVITÉ CRUD
    // ══════════════════════════════════════════════════════════

    #[Route('', name: 'app_activite_index', methods: ['GET'])]
    public function index(
        Request $request,
        ActiviteRepository $activiteRepo,
        InscriptionActiviteRepository $inscriptionRepo
    ): Response {
        $search    = trim((string) $request->query->get('search', ''));
        $sortBy    = (string) $request->query->get('sort_by', 'nom');
        $direction = strtoupper((string) $request->query->get('direction', 'ASC'));
        $view      = $request->query->get('view', 'activites'); // 'activites' | 'inscriptions'

        // Activités table
        $activites = $activiteRepo->searchAndSort(
            $search !== '' ? $search : null,
            $sortBy,
            $direction
        );

        // Inscriptions table
        $inscSearch    = trim((string) $request->query->get('insc_search', ''));
        $inscSort      = (string) $request->query->get('insc_sort', 'date');
        $inscDirection = strtoupper((string) $request->query->get('insc_direction', 'DESC'));
        $inscriptions  = $inscriptionRepo->searchAndSort(
            $inscSearch !== '' ? $inscSearch : null,
            $inscSort,
            $inscDirection
        );

        // Stats
        $totalActivites       = count($activites);
        $avgPrix              = $activiteRepo->avgPrix();
        $categorieStats       = $activiteRepo->countByCategorie();
        $totalInscriptions    = count($inscriptions);
        $totalParticipants    = $inscriptionRepo->totalParticipants();
        $statutStats          = $inscriptionRepo->countByStatut();

        return $this->render('activite/index.html.twig', [
            'activites'        => $activites,
            'inscriptions'     => $inscriptions,
            'view'             => $view,

            // Activité search/sort state
            'search'           => $search,
            'sortBy'           => $sortBy,
            'direction'        => $direction,

            // Inscription search/sort state
            'inscSearch'       => $inscSearch,
            'inscSort'         => $inscSort,
            'inscDirection'    => $inscDirection,

            // Stats
            'totalActivites'    => $totalActivites,
            'avgPrix'           => $avgPrix,
            'categorieStats'    => $categorieStats,
            'totalInscriptions' => $totalInscriptions,
            'totalParticipants' => $totalParticipants,
            'statutStats'       => $statutStats,
        ]);
    }

    // ── Activité New ──────────────────────────────────────────
    #[Route('/new', name: 'app_activite_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $activite = new Activite();
        $form     = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($activite);
            $em->flush();

            if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $this->addFlash('success', 'Activité créée avec succès.');
            return $this->redirectToRoute('app_activite_index');
        }

        return $this->render('activite/_form_activite.html.twig', [
            'form'     => $form,
            'activite' => $activite,
            'isNew'    => true,
        ]);
    }

    // ── Activité Edit ─────────────────────────────────────────
    #[Route('/{id}/edit', name: 'app_activite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Activite $activite, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $this->addFlash('success', 'Activité modifiée avec succès.');
            return $this->redirectToRoute('app_activite_index');
        }

        return $this->render('activite/_form_activite.html.twig', [
            'form'     => $form,
            'activite' => $activite,
            'isNew'    => false,
        ]);
    }

    // ── Activité Delete ───────────────────────────────────────
    #[Route('/{id}/delete', name: 'app_activite_delete', methods: ['POST'])]
    public function delete(Request $request, Activite $activite, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_activite_' . $activite->getIdActivite(), (string) $request->request->get('_token'))) {
            $em->remove($activite);
            $em->flush();
            $this->addFlash('success', 'Activité supprimée.');
        }
        return $this->redirectToRoute('app_activite_index');
    }

    // ── Activités PDF export ──────────────────────────────────
    #[Route('/pdf', name: 'app_activite_pdf', methods: ['GET'])]
    public function pdfActivites(ActiviteRepository $activiteRepo, Pdf $pdf): Response
    {
        $activites = $activiteRepo->findAll();
        $html = $this->renderView('activite/pdf_activites.html.twig', [
            'activites' => $activites,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $content = $pdf->getOutputFromHtml($html);
        return new Response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="activites_' . date('Y-m-d') . '.pdf"',
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  INSCRIPTION CRUD
    // ══════════════════════════════════════════════════════════

    // ── Inscription New ───────────────────────────────────────
    #[Route('/inscriptions/new', name: 'app_inscription_new', methods: ['GET', 'POST'])]
    public function newInscription(Request $request, EntityManagerInterface $em): Response
    {
        $inscription = new InscriptionActivite();
        $form        = $this->createForm(InscriptionActiviteType::class, $inscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($inscription);
            $em->flush();

            if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $this->addFlash('success', 'Inscription créée avec succès.');
            return $this->redirectToRoute('app_activite_index', ['view' => 'inscriptions']);
        }

        return $this->render('activite/_form_inscription.html.twig', [
            'form'        => $form,
            'inscription' => $inscription,
            'isNew'       => true,
        ]);
    }

    // ── Inscription Edit ──────────────────────────────────────
    #[Route('/inscriptions/{id}/edit', name: 'app_inscription_edit', methods: ['GET', 'POST'])]
    public function editInscription(
        Request $request,
        InscriptionActivite $inscription,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(InscriptionActiviteType::class, $inscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $this->addFlash('success', 'Inscription modifiée.');
            return $this->redirectToRoute('app_activite_index', ['view' => 'inscriptions']);
        }

        return $this->render('activite/_form_inscription.html.twig', [
            'form'        => $form,
            'inscription' => $inscription,
            'isNew'       => false,
        ]);
    }

    // ── Inscription Delete ────────────────────────────────────
    #[Route('/inscriptions/{id}/delete', name: 'app_inscription_delete', methods: ['POST'])]
    public function deleteInscription(
        Request $request,
        InscriptionActivite $inscription,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete_inscription_' . $inscription->getIdInscription(), (string) $request->request->get('_token'))) {
            $em->remove($inscription);
            $em->flush();
            $this->addFlash('success', 'Inscription supprimée.');
        }
        return $this->redirectToRoute('app_activite_index', ['view' => 'inscriptions']);
    }

    // ── Inscriptions PDF export ───────────────────────────────
    #[Route('/inscriptions/pdf', name: 'app_inscription_pdf', methods: ['GET'])]
    public function pdfInscriptions(InscriptionActiviteRepository $inscriptionRepo, Pdf $pdf): Response
    {
        $inscriptions = $inscriptionRepo->findAll();
        $html = $this->renderView('activite/pdf_inscriptions.html.twig', [
            'inscriptions' => $inscriptions,
            'generatedAt'  => new \DateTimeImmutable(),
        ]);

        $content = $pdf->getOutputFromHtml($html);
        return new Response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="inscriptions_activites_' . date('Y-m-d') . '.pdf"',
        ]);
    }
}
