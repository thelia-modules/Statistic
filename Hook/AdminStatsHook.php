<?php


namespace Statistic\Hook;


use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;


class AdminStatsHook extends BaseHook
{
    public function onMainHomeAdmin(HookRenderEvent $event)
    {
        $content = $this->render("pages-home-tool.html");
        $event->add($content);
    }
}
