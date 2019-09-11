<?php

namespace Statistic\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class AdminHomeHook extends BaseHook
{
    public function onMainHomeAdmin(HookRenderEvent $event)
    {
        $content = $this->render("statistic-content-tool.html");
        $event->add($content);
    }
}