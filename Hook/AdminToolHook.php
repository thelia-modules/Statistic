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

use Statistic\Security\StatisticAccess;
use Statistic\Statistic;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Tools\URL;

/**
 * Class AdminToolHook
 * @package Statistic\Hook
 * @author David Gros <dgros@openstudio.fr>
 */
class AdminToolHook extends BaseHook
{
    public function __construct(
        private readonly StatisticAccess $statisticAccess,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'main.top-menu-tools' => [
                ['type' => 'back', 'method' => 'onMainTopMenuTools'],
            ],
        ];
    }

    public function onMainTopMenuTools(HookRenderBlockEvent $event): void
    {
        if (!$this->statisticAccess->canView()) {
            return;
        }

        $event->add(array(
            "url" => URL::getInstance()->absoluteUrl("/admin/module/statistic/tool"),
            "title" => $this->trans("tool.title", [], Statistic::MESSAGE_DOMAIN)
        ));
    }
}
