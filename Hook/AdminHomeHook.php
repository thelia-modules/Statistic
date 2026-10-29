<?php

namespace Statistic\Hook;

use Statistic\Security\StatisticAccess;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

class AdminHomeHook extends BaseHook
{
    public function __construct(
        private readonly StatisticAccess $statisticAccess,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'hook_home_stats' => [
                ['type' => 'back', 'method' => 'onMainHomeAdmin'],
            ],
        ];
    }

    public function onMainHomeAdmin(HookRenderEvent $event): void
    {
        if (!$this->statisticAccess->canView()) {
            return;
        }

        $content = $this->render('Statistic/statistic-content-tool.html.twig');
        $event->add($content);
    }
}
