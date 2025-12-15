<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReactAppController extends AbstractController
{
    #[Route('/app', name: 'react_app')]
    public function index(): Response
    {
        return $this->render('react/index.html.twig');
    }
}
