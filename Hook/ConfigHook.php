<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 17/07/2019
 * Time: 15:13
 */

namespace Statistic\Hook;


use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class ConfigHook extends BaseHook
{
    public function onModuleConfiguration(HookRenderEvent $event){
        $event->add($this->render("config/module-config.html"));
    }
}