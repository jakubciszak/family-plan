<?php

declare(strict_types=1);

namespace App\Presentation\Api\EventListener;

use App\TaskManagement\Domain\Exception\UnauthorizedTaskActionException;
use App\TeamManagement\Domain\Exception\InvitationNotFoundException;
use App\TeamManagement\Domain\Exception\TeamNotFoundException;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[AsEventListener(event: ExceptionEvent::class)]
final readonly class DomainExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $this->unwrap($event->getThrowable());

        if ($exception instanceof HttpExceptionInterface || !$exception instanceof \DomainException) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['error' => $exception->getMessage()],
            $this->statusFor($exception)
        ));
    }

    private function unwrap(\Throwable $throwable): \Throwable
    {
        while ($throwable instanceof HandlerFailedException) {
            $previous = $throwable->getPrevious();

            if ($previous === null) {
                break;
            }

            $throwable = $previous;
        }

        return $throwable;
    }

    private function statusFor(\DomainException $exception): int
    {
        return match (true) {
            $exception instanceof TeamNotFoundException,
            $exception instanceof InvitationNotFoundException => Response::HTTP_NOT_FOUND,
            $exception instanceof UnauthorizedTeamActionException,
            $exception instanceof UnauthorizedTaskActionException => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_BAD_REQUEST,
        };
    }
}
