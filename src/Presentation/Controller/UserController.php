<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Application\Command\CreateUserCommand;
use App\UserManagement\Application\Handler\CreateUserHandler;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users')]
class UserController extends AbstractController
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private CreateUserHandler $createUserHandler
    ) {
    }

    #[Route('/', name: 'app_user_list', methods: ['GET'])]
    public function list(): Response
    {
        $users = $this->userRepository->findAll();

        return $this->render('user/list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/create', name: 'app_user_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $command = new CreateUserCommand(
                Uuid::generate()->value(),
                $request->request->get('name'),
                $request->request->get('email'),
                $request->request->get('password'),
                $request->request->get('role', 'ROLE_USER')
            );

            ($this->createUserHandler)($command);

            return $this->redirectToRoute('app_user_list');
        }

        return $this->render('user/create.html.twig');
    }
}
