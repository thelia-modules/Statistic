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

namespace Statistic;

use Thelia\Core\Template\TemplateDefinition;
use Thelia\Module\BaseModule;

class Statistic extends BaseModule
{
    const BO_MESSAGE_DOMAIN = "statistic.bo.default";

    public function getHooks()
    {
        return array(
            array(
                'type' => TemplateDefinition::BACK_OFFICE,
                'code' => 'statistic.tab',
                'title' => array(
                    'fr_FR' => 'Module de statistique, onglets.',
                    'en_US' => 'Statistic module, tabs.'
                ),
                'active' => true,
                'block' => true,
                'module' => false
            ),
            array(
                'type' => TemplateDefinition::BACK_OFFICE,
                'code' => 'statistic.footer.js',
                'title' => array(
                    'fr_FR' => 'Module de statistic, insertion des JS.',
                    'en_US' => 'Statistic module, JS insertion'
                ),
                'active' => true,
                'block' => false,
                'module' => false
            )
        );
    }
}
