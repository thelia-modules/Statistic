<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Statistic\Hook;

use Statistic\Statistic;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class AdminStatisticHook
 * @package Statistic\Hook
 * @author David Gros <dgros@openstudio.fr>
 */
class AdminStatisticHook extends BaseHook
{
    public function onStatisticTab(HookRenderBlockEvent $event)
    {
        $event
            ->add(array(
                'tab_id' => 'general-statistic',
                'tab_nav_title' => $this->trans('tool.panel.general.title', [], Statistic::BO_MESSAGE_DOMAIN),
                'content' => $this->render('hook/statistic-general.html')
            ))
            ->add(array(
                'tab_id' => 'product-statistic',
                'tab_nav_title' => $this->trans('tool.panel.product.title', [], Statistic::BO_MESSAGE_DOMAIN),
                'content' => $this->render('hook/statistic-product.html')
            ))
        ;

    }

    public function insertionJS(HookRenderEvent $event)
    {
        $css = $this->addCSS('assets/css/bootstrap-datepicker3.css');
        $event->add($css);

        $blocksitJs = $this->addJS('assets/js/blocksit.min.js');
        $event->add($blocksitJs);

        $datePickerJS = $this->addJS('assets/js/bootstrap-datepicker.js');
        $event->add($datePickerJS);

        $statisticJs = $this->addJS('assets/js/statistic.js');
        $event->add($statisticJs);

        $productJs = $this->addJS('assets/js/statistic-product.js');
        $event->add($productJs);
    }
}
