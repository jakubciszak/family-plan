<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiDocController extends AbstractController
{
    #[Route('/api/doc', name: 'api_doc', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('api/doc.html.twig');
    }

    #[Route('/api/openapi.yaml', name: 'api_openapi_spec', methods: ['GET'])]
    public function openapiSpec(): Response
    {
        $specPath = $this->getParameter('kernel.project_dir') . '/public/openapi.yaml';
        
        if (!file_exists($specPath)) {
            throw $this->createNotFoundException('OpenAPI specification not found');
        }

        $content = @file_get_contents($specPath);
        
        if ($content === false) {
            throw $this->createNotFoundException('Unable to read OpenAPI specification');
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'application/x-yaml',
        ]);
    }
}
