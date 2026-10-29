<?php

namespace Statistic\Hook;

use Statistic\Security\StatisticAccess;
use Statistic\Statistic;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

class ProductModuleHook extends BaseHook
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
        if (!$this->statisticAccess->canView()) {
            return;
        }

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
        if (!$this->statisticAccess->canView()) {
            return;
        }

        $event->add($this->addCSS('assets/css/stats.css'));
        $event->add($this->addJS('assets/js/product-best-sales.js'));
    }
}
