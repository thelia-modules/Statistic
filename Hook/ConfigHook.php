<?php

namespace Statistic\Hook;

use Statistic\Form\Configuration;
use Statistic\Form\IncludeShipping;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;

class ConfigHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $configurationForm = $this->formFactory->createForm(Configuration::getName());
        $includeShippingForm = $this->formFactory->createForm(IncludeShipping::getName());

        $event->add($this->render('Statistic/config/module-config.html.twig', [
            'configuration_form' => $configurationForm->createView()->getView(),
            'include_shipping_form' => $includeShippingForm->createView()->getView(),
            'order_statuses' => $this->getOrderStatuses(),
        ]));
    }

    /**
     * @return array<int, array{id: int, code: string, title: string, color: string, position: int, order_count: int}>
     */
    private function getOrderStatuses(): array
    {
        $locale = $this->getRequest()->getSession()?->getLang()?->getLocale() ?? 'en_US';

        $statuses = [];

        foreach (OrderStatusQuery::create()->orderByPosition()->find() as $status) {
            $status->setLocale($locale);

            $statuses[] = [
                'id' => $status->getId(),
                'code' => $status->getCode(),
                'title' => $status->getTitle(),
                'color' => $status->getColor() ?? '',
                'position' => $status->getPosition(),
                'order_count' => OrderQuery::create()->filterByStatusId($status->getId())->count(),
            ];
        }

        return $statuses;
    }
}
