<?php

namespace Statistic\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class ProductModuleHook extends BaseHook
{
    public static function getSubscribedHooks(): array
    {
        return [
            'product.tab-content' => [
                ['type' => 'back', 'method' => 'onProductTabContent'],
            ],
            'product.edit-js' => [
                ['type' => 'back', 'method' => 'insertJS'],
            ],
        ];
    }

    public function onProductTabContent(HookRenderEvent $event): void
    {
        $event->add($this->render('Statistic/hook/best-sale-product.html.twig', [
            'product_id' => $event->getArgument('product_id'),
        ]));
    }

    public function insertJS(HookRenderEvent $event): void
    {
        $event->add($this->addCSS('assets/css/stats.css'));
        $event->add($this->addJS('assets/js/product-best-sales.js'));
    }
}
