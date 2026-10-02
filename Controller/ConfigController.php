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
use Symfony\Component\HttpFoundation\Response;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;

class ConfigController extends BaseAdminController
{
    public function setAction(): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, Statistic::MESSAGE_DOMAIN, AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(Configuration::getName());

        try {
            $configForm = $this->validateForm($form);
        } catch (FormValidationException) {
            $this->flash('error', $this->getTranslator()->trans(
                'tool.config.order_types.invalid',
                [],
                Statistic::MESSAGE_DOMAIN
            ));

            return $this->redirectToConfiguration();
        }

        Statistic::setConfigValue('order_types', $configForm->get('order')->getData());
        $this->flash('success', $this->getTranslator()->trans('tool.config.order_types.saved', [], Statistic::MESSAGE_DOMAIN));

        return $this->redirectToConfiguration();
    }

    public function setIncludeShipping(): Response|RedirectResponse
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, Statistic::MESSAGE_DOMAIN, AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(IncludeShipping::getName());

        $configForm = $this->validateForm($form);

        Statistic::setConfigValue(Statistic::INCLUDE_SHIPPING, $configForm->get('include_shipping')->getData());

        return new RedirectResponse(
            URL::getInstance()->absoluteUrl('/admin/module/Statistic')
        );
    }

    private function redirectToConfiguration(): RedirectResponse
    {
        return new RedirectResponse(
            URL::getInstance()->absoluteUrl('/admin/module/Statistic')
        );
    }

    private function flash(string $type, string $message): void
    {
        $session = $this->getRequest()->getSession();

        if (method_exists($session, 'getFlashBag')) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
