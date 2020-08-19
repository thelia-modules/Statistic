<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 17/07/2019
 * Time: 15:27
 */

namespace Statistic\Form;


use Statistic\Statistic;
use Thelia\Form\BaseForm;

class Configuration extends BaseForm
{
    protected function buildForm()
    {
        $form = $this->formBuilder;

        $form->add('order', 'text', [
            'data' => Statistic::getConfigValue('order_types')
        ]);
    }

    public function getName()
    {
        return 'statistic_configuration';
    }
}