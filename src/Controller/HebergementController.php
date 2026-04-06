<?php

namespace App\Controller;

use App\Entity\Hebergement;
use App\Form\HebergementType;
use App\Repository\HebergementRepository;
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
    #[Route(name: 'app_hebergement_index', methods: ['GET'])]
    public function index(Request $request, HebergementRepository $hebergementRepository): Response
    {
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sort_by');

        return $this->render('hebergement/index.html.twig', [
            'hebergements' => $hebergementRepository->searchAndSort($search, $sortBy),
            'currentSearch' => $search,
            'currentSort' => $sortBy,
        ]);
    }

    #[Route('/api/all', name: 'api_hebergements_all', methods: ['GET'])]
    public function apiAll(HebergementRepository $hebergementRepository): JsonResponse
    {
        $hebergements = $hebergementRepository->findAll();
        
        $data = [];
        $fallbacks = [
            'https://images.unsplash.com/photo-1540541338287-41700207dee6?w=600&q=75', // Amanjiwo Resort
            'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=600&q=75', // Soneva Fushi
            'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=600&q=75', // Burj Al Arab
            'https://images.unsplash.com/photo-1596436889106-be35e843f974?w=600&q=75', // Amangiri
            'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?w=600&q=75', // Beach Haus
            'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=600&q=75', // Luxury Room
        ];
        
        foreach ($hebergements as $index => $h) {
            $img = $h->getImageUrl();
            if (!$img) {
                $img = $fallbacks[$index % count($fallbacks)];
            }
            
            $data[] = [
                'id' => $h->getIdHebergement(),
                'name' => $h->getNom(),
                'loc' => $h->getVille() . ', ' . $h->getPays(),
                'type' => strtoupper($h->getType() ?? 'HOTEL'),
                'price' => '€' . $h->getTarifParNuit(),
                'per' => '/night',
                'img' => $img,
                'emoji' => '🏨'
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

        return $this->json([
            'id' => $hebergement->getIdHebergement(),
            'name' => $hebergement->getNom(),
            'loc' => $hebergement->getVille() . ', ' . $hebergement->getPays(),
            'type' => strtoupper($hebergement->getType() ?? 'HOTEL'),
            'price' => '€' . $hebergement->getTarifParNuit(),
            'per' => '/night',
            'img' => $img,
            'capacite' => $hebergement->getCapacite(),
            'equipements' => $hebergement->getEquipements()
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
            'form' => $form,
        ]);
    }

    #[Route('/{idHebergement}', name: 'app_hebergement_show', methods: ['GET'])]
    public function show(Hebergement $hebergement): Response
    {
        return $this->render('hebergement/show.html.twig', [
            'hebergement' => $hebergement,
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
            'form' => $form,
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
