<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 17/07/2019
 * Time: 15:27
 */

namespace Statistic\Form;


use Statistic\Statistic;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints\Count;
use Thelia\Form\BaseForm;
use Thelia\Model\LangQuery;
use Thelia\Model\OrderStatusQuery;

class Configuration extends BaseForm
{
    protected function buildForm(): void
    {
        $form = $this->formBuilder;

        $form->add('order', ChoiceType::class, [
            'choices' => $this->getOrderStatusChoices(),
            'multiple' => true,
            'data' => array_map('intval', array_filter(explode(',', (string) Statistic::getConfigValue('order_types')))),
            'constraints' => [
                new Count(min: 1),
            ],
        ]);
    }

    /**
     * @return array<string, int> order status title => id
     */
    private function getOrderStatusChoices(): array
    {
        $session = $this->request->hasSession() ? $this->request->getSession() : null;
        $locale = $session?->getLang()?->getLocale()
            ?? LangQuery::create()->findOneByByDefault(true)?->getLocale()
            ?? 'en_US';

        $choices = [];

        foreach (OrderStatusQuery::create()->orderByPosition()->find() as $status) {
            $status->setLocale($locale);
            $choices[$status->getTitle() ?: $status->getCode()] = $status->getId();
        }

        return $choices;
    }

    public static function getName(): string
    {
        return 'statistic_configuration';
    }
}