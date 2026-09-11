<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/task-templates', name: 'api_task_template_')]
#[OA\Tag(name: 'Task templates')]
class TaskTemplateApiController extends AbstractController
{
    public function __construct(
        private readonly TaskTemplateRepositoryInterface $taskTemplateRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/task-templates',
        summary: 'List task templates',
        tags: ['Task templates']
    )]
    #[OA\Response(
        response: 200,
        description: 'List of task templates',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'templates',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'points', type: 'integer'),
                            new OA\Property(property: 'frequency', type: 'string'),
                            new OA\Property(property: 'isActive', type: 'boolean'),
                            new OA\Property(property: 'teamId', type: 'string', format: 'uuid', nullable: true),
                            new OA\Property(property: 'assignedUserId', type: 'string', format: 'uuid', nullable: true)
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(): JsonResponse
    {
        return $this->json([
            'templates' => array_map(
                fn (TaskTemplate $template): array => $this->serializeTemplate($template),
                $this->taskTemplateRepository->findAll()
            ),
        ]);
    }

    private function serializeTemplate(TaskTemplate $template): array
    {
        return [
            'id' => $template->id()->value(),
            'name' => $template->name()->value(),
            'description' => $template->description(),
            'points' => $template->points()->value(),
            'frequency' => $template->frequency()->value,
            'isActive' => $template->isActive(),
            'teamId' => $template->teamId()?->value(),
            'assignedUserId' => $template->assignedUserId()?->value(),
        ];
    }
}
