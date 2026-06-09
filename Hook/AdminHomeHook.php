<?php

namespace Statistic\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class AdminHomeHook extends BaseHook
{
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
        $content = $this->render('Statistic/statistic-content-tool.html.twig');
        $event->add($content);
    }
}
