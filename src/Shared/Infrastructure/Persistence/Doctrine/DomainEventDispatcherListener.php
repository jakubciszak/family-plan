<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Doctrine listener that dispatches domain events after flush
 *
 * This listener collects all domain events from entities after they've been
 * persisted and dispatches them to the Symfony event dispatcher.
 */
#[AsDoctrineListener(event: Events::postFlush)]
class DomainEventDispatcherListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $entities = array_merge(
            $unitOfWork->getIdentityMap(),
        );

        foreach ($entities as $entityClass => $entityInstances) {
            foreach ($entityInstances as $entity) {
                if (method_exists($entity, 'pullDomainEvents')) {
                    $events = $entity->pullDomainEvents();

                    foreach ($events as $event) {
                        $this->eventDispatcher->dispatch($event);
                    }
                }
            }
        }
    }
}
