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
use Thelia\Core\Hook\BaseHook;
use Thelia\Tools\URL;

/**
 * Class AdminToolHook
 * @package Statistic\Hook
 * @author David Gros <dgros@openstudio.fr>
 */
class AdminToolHook extends BaseHook
{
    public function onMainTopMenuTools(HookRenderBlockEvent $event)
    {
        $event->add(array(
            "url" => URL::getInstance()->absoluteUrl("/admin/module/statistic/tool"),
            "title" => $this->trans("tool.title", [], Statistic::BO_MESSAGE_DOMAIN)
        ));
    }
}
