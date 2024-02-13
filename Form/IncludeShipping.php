<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 17/07/2019
 * Time: 15:27
 */

namespace Statistic\Form;


use Statistic\Statistic;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class IncludeShipping extends BaseForm
{
    protected function buildForm(): void
    {
        $form = $this->formBuilder;

        $form->add(
            'include_shipping',
            CheckboxType::class,
            [
                'data' => (bool) Statistic::getConfigValue(Statistic::INCLUDE_SHIPPING),
                'label' => Translator::getInstance()->trans("Include delivery fee in the statistics dashboard ?", [], Statistic::MESSAGE_DOMAIN),
                'required' => false
            ]
        );
    }
}