<?php

namespace App\Controller;

use App\Entity\Hebergement;
use App\Form\HebergementType;
use App\Repository\HebergementRepository;
use App\Service\HolidayService;
use App\Service\UnsplashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/hebergement')]
final class HebergementController extends AbstractController
{
    public function __construct(
        private readonly HolidayService $holidayService,
        private readonly UnsplashService $unsplashService
    ) {
    }

    #[Route(name: 'app_hebergement_index', methods: ['GET'])]
    public function index(Request $request, HebergementRepository $hebergementRepository): Response
    {
        $search  = $request->query->get('search');
        $sortBy  = $request->query->get('sort_by');
        $hebergements = $hebergementRepository->searchAndSort($search, $sortBy);

        // ── Holiday detection ──────────────────────────────────────────────
        // We build a map: hebergementId => holiday message (or null)
        // We deduplicate API calls by country (one call per unique country).
        $today           = new \DateTimeImmutable();
        $countryHolidays = []; // ISO code → holiday data (cache within the request)
        $holidayMessages = []; // hebergement id → message string (or null)

        foreach ($hebergements as $h) {
            $pays    = (string) $h->getPays();
            $isoCode = $this->holidayService->resolveIsoCode($pays);

            if ($isoCode === null) {
                $holidayMessages[$h->getIdHebergement()] = null;
                continue;
            }

            // Reuse result if we already queried this country in this request
            if (!array_key_exists($isoCode, $countryHolidays)) {
                $countryHolidays[$isoCode] = $this->holidayService->getHoliday($pays, $today);
            }

            $holiday = $countryHolidays[$isoCode];
            $holidayMessages[$h->getIdHebergement()] = $holiday
                ? $this->holidayService->buildMessage($holiday)
                : null;
        }
        // ──────────────────────────────────────────────────────────────────

        // ── Unsplash photo enrichment ──────────────────────────────────────
        $unsplashPhotos = [];
        foreach ($hebergements as $h) {
            // Only fetch from Unsplash if no local image is uploaded
            if (!$h->getImageUrl()) {
                $unsplashPhotos[$h->getIdHebergement()] = $this->unsplashService->getPhotoForHebergement(
                    (string) $h->getNom(),
                    (string) $h->getVille(),
                    (string) $h->getPays(),
                    (string) $h->getType()
                );
            } else {
                $unsplashPhotos[$h->getIdHebergement()] = null;
            }
        }
        // ──────────────────────────────────────────────────────────────────

        return $this->render('hebergement/index.html.twig', [
            'hebergements'    => $hebergements,
            'currentSearch'   => $search,
            'currentSort'     => $sortBy,
            'holidayMessages' => $holidayMessages,
            'today'           => $today,
            'unsplashPhotos'  => $unsplashPhotos,
        ]);
    }

    #[Route('/api/all', name: 'api_hebergements_all', methods: ['GET'])]
    public function apiAll(HebergementRepository $hebergementRepository): JsonResponse
    {
        $hebergements = $hebergementRepository->findAll();

        $data = [];
        $fallbacks = [
            'https://images.unsplash.com/photo-1540541338287-41700207dee6?w=600&q=75',
            'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=600&q=75',
            'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=600&q=75',
            'https://images.unsplash.com/photo-1596436889106-be35e843f974?w=600&q=75',
            'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?w=600&q=75',
            'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=600&q=75',
        ];

        // ── RATE-LIMIT FIX: deduplicate API calls by country ────────────────
        // Build a map of ISO code → holiday ONCE (one API call per unique country,
        // not one per hebergement). This prevents hitting the 1 req/sec limit.
        $countryHolidays = []; // isoCode => holiday|null
        foreach ($hebergements as $h) {
            $isoCode = $this->holidayService->resolveIsoCode((string) $h->getPays());
            if ($isoCode !== null && !array_key_exists($isoCode, $countryHolidays)) {
                try {
                    $countryHolidays[$isoCode] = $this->holidayService->getHoliday((string) $h->getPays());
                } catch (\Throwable) {
                    // Never let a holiday API failure break the hotel listing
                    $countryHolidays[$isoCode] = null;
                }
            }
        }
        // ────────────────────────────────────────────────────────────────────

        foreach ($hebergements as $index => $h) {
            $img = $h->getImageUrl();
            if (!$img) {
                $img = $fallbacks[$index % count($fallbacks)];
            }

            $isoCode        = $this->holidayService->resolveIsoCode((string) $h->getPays());
            $holiday        = $isoCode ? ($countryHolidays[$isoCode] ?? null) : null;
            $holidayMessage = $holiday ? $this->holidayService->buildMessage($holiday) : null;

            $data[] = [
                'id'             => $h->getIdHebergement(),
                'name'           => $h->getNom(),
                'loc'            => $h->getVille() . ', ' . $h->getPays(),
                'type'           => strtoupper($h->getType() ?? 'HOTEL'),
                'price'          => '€' . $h->getTarifParNuit(),
                'per'            => '/night',
                'img'            => $img,
                'emoji'          => '🏨',
                'holidayMessage' => $holidayMessage,
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/{idHebergement}', name: 'api_hebergement_show', methods: ['GET'])]
    public function apiShow(Hebergement $hebergement): JsonResponse
    {
        $fallbacks = [
            'https://images.unsplash.com/photo-1540541338287-41700207dee6?w=1200&q=85',
            'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=1200&q=85',
            'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=1200&q=85',
            'https://images.unsplash.com/photo-1596436889106-be35e843f974?w=1200&q=85',
        ];

        $img = $hebergement->getImageUrl();
        if (!$img) {
            $img = $fallbacks[$hebergement->getIdHebergement() % count($fallbacks)];
        }

        $holiday        = $this->holidayService->getHoliday((string) $hebergement->getPays());
        $holidayMessage = $holiday ? $this->holidayService->buildMessage($holiday) : null;

        return $this->json([
            'id'             => $hebergement->getIdHebergement(),
            'name'           => $hebergement->getNom(),
            'loc'            => $hebergement->getVille() . ', ' . $hebergement->getPays(),
            'type'           => strtoupper($hebergement->getType() ?? 'HOTEL'),
            'price'          => '€' . $hebergement->getTarifParNuit(),
            'per'            => '/night',
            'img'            => $img,
            'capacite'       => $hebergement->getCapacite(),
            'equipements'    => $hebergement->getEquipements(),
            'holidayMessage' => $holidayMessage,
        ]);
    }

    /**
     * Global holiday status endpoint — reads ALL hebergements from the DB automatically.
     * No ID needed. Visit: /hebergement/holidays/status
     * Returns today's holiday info for every unique country in your database.
     */
    #[Route('/holidays/status', name: 'app_hebergement_holidays_status', methods: ['GET'])]
    public function holidaysStatus(HebergementRepository $hebergementRepository): JsonResponse
    {
        $today        = new \DateTimeImmutable();
        $hebergements = $hebergementRepository->findAll();

        // Group hebergements by unique country
        $countrySeen  = []; // isoCode => true (to avoid duplicate API calls)
        $results      = [];
        $summary      = ['total_hebergements' => count($hebergements), 'date' => $today->format('Y-m-d'), 'holidays_found' => 0];

        foreach ($hebergements as $h) {
            $pays    = (string) $h->getPays();
            $isoCode = $this->holidayService->resolveIsoCode($pays);

            if ($isoCode === null) {
                $results[] = [
                    'id'         => $h->getIdHebergement(),
                    'nom'        => $h->getNom(),
                    'pays'       => $pays,
                    'iso'        => null,
                    'status'     => 'unmapped',
                    'message'    => "⚠️ Pays \"{$pays}\" absent du mapping ISO — ajoutez-le dans HolidayService::COUNTRY_MAP",
                    'holiday'    => null,
                ];
                continue;
            }

            // Only call the API once per unique country
            if (!isset($countrySeen[$isoCode])) {
                $countrySeen[$isoCode] = $this->holidayService->getHoliday($pays, $today);
            }

            $holiday = $countrySeen[$isoCode];

            if ($holiday) {
                $summary['holidays_found']++;
            }

            $results[] = [
                'id'      => $h->getIdHebergement(),
                'nom'     => $h->getNom(),
                'pays'    => $pays,
                'iso'     => $isoCode,
                'status'  => $holiday ? 'holiday' : 'no_holiday',
                'message' => $holiday
                    ? $this->holidayService->buildMessage($holiday)
                    : "✅ Aucun jour férié aujourd'hui en {$pays} ({$isoCode})",
                'holiday' => $holiday,
            ];
        }

        return $this->json([
            'summary' => $summary,
            'results' => $results,
        ]);
    }

    /**
     * Per-hebergement JSON endpoint — finds the hebergement by its real DB ID automatically.
     * Usage: /hebergement/{real_id}/holiday  (use an ID that exists in your DB)
     */
    #[Route('/{idHebergement}/holiday', name: 'app_hebergement_holiday', methods: ['GET'])]
    public function holidayInfo(Hebergement $hebergement): JsonResponse
    {
        $pays    = (string) $hebergement->getPays();
        $isoCode = $this->holidayService->resolveIsoCode($pays);

        if ($isoCode === null) {
            return $this->json([
                'status'   => 'unmapped',
                'message'  => "⚠️ Pays \"{$pays}\" absent du mapping ISO.",
                'holiday'  => null,
            ]);
        }

        $holiday = $this->holidayService->getHoliday($pays);

        if ($holiday === null) {
            return $this->json([
                'status'  => 'none',
                'country' => $isoCode,
                'date'    => (new \DateTimeImmutable())->format('Y-m-d'),
                'holiday' => null,
                'message' => "Aucun jour férié aujourd'hui en {$pays}.",
            ]);
        }

        return $this->json([
            'status'   => 'holiday',
            'country'  => $isoCode,
            'date'     => (new \DateTimeImmutable())->format('Y-m-d'),
            'holiday'  => $holiday,
            'message'  => $this->holidayService->buildMessage($holiday),
        ]);
    }

    #[Route('/new', name: 'app_hebergement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $hebergement = new Hebergement();
        $form = $this->createForm(HebergementType::class, $hebergement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/hotels',
                        $newFilename
                    );
                } catch (FileException $e) {
                    // handle exception if something happens during file upload
                }

                $hebergement->setImageUrl('/uploads/hotels/'.$newFilename);
            }

            $entityManager->persist($hebergement);
            $entityManager->flush();

            return $this->redirectToRoute('app_hebergement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('hebergement/new.html.twig', [
            'hebergement' => $hebergement,
            'form'        => $form,
        ]);
    }

    #[Route('/{idHebergement}', name: 'app_hebergement_show', methods: ['GET'])]
    public function show(Hebergement $hebergement): Response
    {
        $holiday        = $this->holidayService->getHoliday((string) $hebergement->getPays());
        $holidayMessage = $holiday ? $this->holidayService->buildMessage($holiday) : null;

        return $this->render('hebergement/show.html.twig', [
            'hebergement'    => $hebergement,
            'holiday'        => $holiday,
            'holidayMessage' => $holidayMessage,
        ]);
    }

    #[Route('/{idHebergement}/edit', name: 'app_hebergement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Hebergement $hebergement, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(HebergementType::class, $hebergement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/hotels',
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $hebergement->setImageUrl('/uploads/hotels/'.$newFilename);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_hebergement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('hebergement/edit.html.twig', [
            'hebergement' => $hebergement,
            'form'        => $form,
        ]);
    }

    #[Route('/{idHebergement}', name: 'app_hebergement_delete', methods: ['POST'])]
    public function delete(Request $request, Hebergement $hebergement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$hebergement->getIdHebergement(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($hebergement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_hebergement_index', [], Response::HTTP_SEE_OTHER);
    }
}
