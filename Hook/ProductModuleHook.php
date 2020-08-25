<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 18/08/2020
 * Time: 11:46
 */

namespace Statistic\Hook;


use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class ProductModuleHook extends BaseHook
{
    public function onProductTabContent(HookRenderEvent $event)
    {
        $event->add($this->render('hook/best-sale-product.html'));
    }

    public function insertJS(HookRenderEvent $event)
    {
        $css = $this->addCSS('assets/css/bootstrap-datepicker3.css');
        $event->add($css);

        $statsCss = $this->addCSS('assets/css/stats.css');
        $event->add($statsCss);

        $dataTable = $this->addJS('assets/js/jquery.dataTables.min.js');
        $event->add($dataTable);

        $bootstrapDataTable = $this->addJS('assets/js/datatables.bootstrap.min.js');
        $event->add($bootstrapDataTable);

        $datePickerJS = $this->addJS('assets/js/bootstrap-datepicker.js');
        $event->add($datePickerJS);

        $annualJs = $this->addJS('assets/js/product-best-sales.js');
        $event->add($annualJs);
    }
}