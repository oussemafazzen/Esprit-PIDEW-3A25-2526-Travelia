<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\BilletController;
use App\Entity\Billet;
use App\Repository\BilletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Snappy\Pdf;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

#[AllowMockObjectsWithoutExpectations]
final class BilletControllerTest extends TestCase
{
    public function testIndexReturnsValidResponseEvenWithOrphanBillet(): void
    {
        $controller = new BilletController();
        $controller->setContainer($this->createContainer());

        $orphanBillet = (new Billet())
            ->setTypeTransport('avion')
            ->setNumeroBillet('TEST-001')
            ->setStatut('confirmé');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$orphanBillet]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('addSelect')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createMock(BilletRepository::class);
        $repository->expects(self::once())
            ->method('createQueryBuilder')
            ->with('b')
            ->willReturn($queryBuilder);

        $pagination = $this->createMock(PaginationInterface::class);
        $paginator = $this->createMock(PaginatorInterface::class);
        $paginator->expects(self::once())
            ->method('paginate')
            ->with($queryBuilder, 1, 4)
            ->willReturn($pagination);

        $response = $controller->index(new Request(), $repository, $paginator);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('rendered:billet/index.html.twig', $response->getContent());
    }

    public function testExportPdfReturnsPdfResponse(): void
    {
        $controller = new BilletController();
        $controller->setContainer($this->createContainer());

        $billet = (new Billet())
            ->setTypeTransport('avion')
            ->setNumeroBillet('TEST-002')
            ->setStatut('confirmé');

        $repository = $this->createMock(BilletRepository::class);
        $repository->expects(self::once())
            ->method('findBy')
            ->with([], ['id' => 'DESC'])
            ->willReturn([$billet]);

        $pdf = $this->createMock(Pdf::class);
        $pdf->expects(self::once())
            ->method('getOutputFromHtml')
            ->with('rendered:billet/pdf.html.twig')
            ->willReturn('%PDF-mock');

        $response = $controller->exportPdf($repository, $pdf);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringContainsString('%PDF-mock', $response->getContent());
    }

    public function testNewWithInvalidDataDoesNotSave(): void
    {
        $form = $this->createFormMock(true, false);
        $controller = new BilletController();
        $controller->setContainer($this->createContainer($form));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $response = $controller->new(new Request(), $entityManager);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('rendered:billet/new.html.twig', $response->getContent());
    }

    public function testEditWithInvalidDataDoesNotSave(): void
    {
        $form = $this->createFormMock(true, false);
        $controller = new BilletController();
        $controller->setContainer($this->createContainer($form));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $billet = new Billet();
        $response = $controller->edit(new Request(), $billet, $entityManager);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('rendered:billet/edit.html.twig', $response->getContent());
    }

    private function createFormMock(bool $submitted, bool $valid): FormInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($valid);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }

    private function createContainer(?FormInterface $form = null): ContainerInterface
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn(string $view): string => 'rendered:' . $view
        );

        $formFactory = $this->createMock(FormFactoryInterface::class);
        if ($form !== null) {
            $formFactory->method('create')->willReturn($form);
        }

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new class($twig, $formFactory, $requestStack) implements ContainerInterface {
            public function __construct(
                private readonly Environment $twig,
                private readonly FormFactoryInterface $formFactory,
                private readonly RequestStack $requestStack,
            ) {
            }

            public function get(string $id)
            {
                return match ($id) {
                    'twig' => $this->twig,
                    'form.factory' => $this->formFactory,
                    'request_stack' => $this->requestStack,
                    default => throw new \RuntimeException('Unexpected service: ' . $id),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, ['twig', 'form.factory', 'request_stack'], true);
            }
        };
    }
}
