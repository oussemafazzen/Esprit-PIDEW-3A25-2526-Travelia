<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ReservationController;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
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
final class ReservationControllerTest extends TestCase
{
    public function testIndexReturnsValidResponse(): void
    {
        $controller = new ReservationController();
        $controller->setContainer($this->createContainer());

        $listBuilder = $this->createMock(QueryBuilder::class);
        $listBuilder->method('orderBy')->willReturnSelf();

        $calendarQuery = $this->createMock(Query::class);
        $calendarQuery->method('getResult')->willReturn([new Reservation()]);

        $calendarBuilder = $this->createMock(QueryBuilder::class);
        $calendarBuilder->method('leftJoin')->willReturnSelf();
        $calendarBuilder->method('addSelect')->willReturnSelf();
        $calendarBuilder->method('orderBy')->willReturnSelf();
        $calendarBuilder->method('addOrderBy')->willReturnSelf();
        $calendarBuilder->method('getQuery')->willReturn($calendarQuery);

        $repository = $this->createMock(ReservationRepository::class);
        $repository->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($listBuilder, $calendarBuilder);

        $pagination = $this->createMock(PaginationInterface::class);
        $paginator = $this->createMock(PaginatorInterface::class);
        $paginator->expects(self::once())
            ->method('paginate')
            ->with($listBuilder, 1, 3)
            ->willReturn($pagination);

        $response = $controller->index(new Request(), $repository, $paginator);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('rendered:reservation/index.html.twig', $response->getContent());
    }

    public function testNewWithInvalidDataDoesNotSave(): void
    {
        $form = $this->createFormMock(true, false);
        $controller = new ReservationController();
        $controller->setContainer($this->createContainer($form));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $response = $controller->new(new Request(), $entityManager);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('rendered:reservation/new.html.twig', $response->getContent());
    }

    public function testEditWithInvalidDataDoesNotSave(): void
    {
        $form = $this->createFormMock(true, false);
        $controller = new ReservationController();
        $controller->setContainer($this->createContainer($form));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $reservation = new Reservation();
        $response = $controller->edit(new Request(), $reservation, $entityManager);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('rendered:reservation/edit.html.twig', $response->getContent());
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
