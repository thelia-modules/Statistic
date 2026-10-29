<?php

namespace Statistic\Hook;

use Statistic\Statistic;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class ProductModuleHook extends BaseHook
{
    public static function getSubscribedHooks(): array
    {
        return [
            'product.tab' => [
                ['type' => 'back', 'method' => 'onProductTab'],
            ],
            'product.edit-js' => [
                ['type' => 'back', 'method' => 'insertJS'],
            ],
        ];
    }

    public function onProductTab(HookRenderBlockEvent $event): void
    {
        $event->add([
            'id' => 'statistic',
            'title' => $this->trans('Statistics', [], Statistic::MESSAGE_DOMAIN),
            'content' => $this->render('Statistic/hook/best-sale-product.html.twig', [
                'product_id' => $event->getArgument('id'),
            ]),
        ]);
    }

    public function insertJS(HookRenderEvent $event): void
    {
        $event->add($this->addCSS('assets/css/stats.css'));
        $event->add($this->addJS('assets/js/product-best-sales.js'));
    }
}
