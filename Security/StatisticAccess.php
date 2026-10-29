<?php

namespace Statistic\Security;

use Statistic\Statistic;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;

/**
 * Whether the current administrator may view the statistics: the hooks that show them (product tab, home, tools menu)
 * ask the same permission as the controllers that serve them.
 */
final readonly class StatisticAccess
{
    public function __construct(
        private SecurityContext $securityContext,
    ) {
    }

    public function canView(): bool
    {
        return $this->securityContext->isGranted(['ADMIN'], [AdminResources::MODULE], [Statistic::MESSAGE_DOMAIN], [AccessManager::VIEW]);
    }
}
