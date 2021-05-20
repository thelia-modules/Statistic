<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 17/07/2019
 * Time: 16:12
 */

namespace Statistic\Controller;


use Statistic\Form\Configuration;
use Statistic\Statistic;
use Thelia\Controller\Admin\BaseAdminController;

class ConfigController extends BaseAdminController
{
    public function setAction()
    {
        $form = $this->createForm(Configuration::getName());
        $response = null;

        $configForm = $this->validateForm($form);
        Statistic::setConfigValue('order_types', $configForm->get('order')->getData(), true, true);

        $response = $this->render(
            'module-configure',
            ['module_code' => 'Statistic']
        );
        return $response;
    }
}