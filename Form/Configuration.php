<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 17/07/2019
 * Time: 15:27
 */

namespace Statistic\Form;


use Statistic\Statistic;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Thelia\Form\BaseForm;

class Configuration extends BaseForm
{
    protected function buildForm(): void
    {
        $form = $this->formBuilder;

        $form->add('order', TextType::class, [
            'data' => Statistic::getConfigValue('order_types'),
            'constraints' => [
                new NotBlank(),
                new Regex('/^\d+(,\d+)*$/'),
            ],
        ]);
    }

    public static function getName(): string
    {
        return 'statistic_configuration';
    }
}