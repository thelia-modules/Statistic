<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 17/07/2019
 * Time: 16:12
 */

namespace Statistic\Controller;


use Statistic\Form\Configuration;
use Statistic\Form\IncludeShipping;
use Statistic\Statistic;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Response;

class ConfigController extends BaseAdminController
{
    public function setAction()
    {
        $form = $this->createForm(Configuration::getName());

        $configForm = $this->validateForm($form);
        Statistic::setConfigValue('order_types', $configForm->get('order')->getData(), true, true);

        return $this->render(
            'module-configure',
            ['module_code' => 'Statistic']
        );
    }

    public function setIncludeShipping(): Response|RedirectResponse
    {
        $form = $this->createForm(IncludeShipping::getName());

        $configForm = $this->validateForm($form);

        Statistic::setConfigValue(Statistic::INCLUDE_SHIPPING, $configForm->get('include_shipping')->getData());

        return $this->render(
            'module-configure',
            ['module_code' => 'Statistic']
        );
    }
}
