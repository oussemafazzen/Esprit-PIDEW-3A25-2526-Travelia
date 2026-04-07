<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Form\BilletType;
use App\Repository\BilletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/billet')]
final class BilletController extends AbstractController
{
    #[Route(name: 'app_billet_index', methods: ['GET'])]
    public function index(Request $request, BilletRepository $billetRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'id');
        $direction = strtoupper((string) $request->query->get('direction', 'DESC'));

        $allowedSorts = [
            'id' => 'b.id',
            'transport' => 'b.typeTransport',
            'numero' => 'b.numeroBillet',
            'depart' => 'b.dateDepart',
            'arrivee' => 'b.dateArrivee',
            'prix' => 'b.prix',
            'statut' => 'b.statut',
            'reservation' => 'r.id',
        ];

        if (!isset($allowedSorts[$sort])) {
            $sort = 'id';
        }

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }

        $qb = $billetRepository->createQueryBuilder('b')
            ->leftJoin('b.reservation', 'r')
            ->addSelect('r');

        if ($search !== '') {
            $expr = $qb->expr()->orX(
                $qb->expr()->like('LOWER(b.typeTransport)', ':q'),
                $qb->expr()->like('LOWER(b.numeroBillet)', ':q'),
                $qb->expr()->like('LOWER(b.statut)', ':q')
            );

            if (ctype_digit($search)) {
                $expr->add($qb->expr()->eq('b.id', ':idSearch'));
                $expr->add($qb->expr()->eq('r.id', ':reservationSearch'));
                $qb->setParameter('idSearch', (int) $search);
                $qb->setParameter('reservationSearch', (int) $search);
            }

            $qb->andWhere($expr)
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        $qb->orderBy($allowedSorts[$sort], $direction);

        return $this->render('billet/index.html.twig', [
            'billets' => $qb->getQuery()->getResult(),
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/new', name: 'app_billet_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $billet = new Billet();
        $form = $this->createForm(BilletType::class, $billet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($billet);
            $entityManager->flush();

            return $this->redirectToRoute('app_billet_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('billet/new.html.twig', [
            'billet' => $billet,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_billet_show', methods: ['GET'])]
    public function show(Billet $billet): Response
    {
        return $this->render('billet/show.html.twig', [
            'billet' => $billet,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_billet_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Billet $billet, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BilletType::class, $billet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_billet_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('billet/edit.html.twig', [
            'billet' => $billet,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_billet_delete', methods: ['POST'])]
    public function delete(Request $request, Billet $billet, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $billet->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($billet);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_billet_index', [], Response::HTTP_SEE_OTHER);
    }
}