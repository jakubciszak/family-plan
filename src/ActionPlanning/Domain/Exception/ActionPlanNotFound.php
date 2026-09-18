<?php

declare(strict_types=1);

namespace App\ActionPlanning\Domain\Exception;

final class ActionPlanNotFound extends \DomainException
{
    public function __construct()
    {
        parent::__construct('actionPlans.notFound');
    }
}
