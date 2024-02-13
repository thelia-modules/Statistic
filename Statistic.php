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

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Module\BaseModule;

class Statistic extends BaseModule
{
    const MESSAGE_DOMAIN = "statistic";

    public const INCLUDE_SHIPPING = "statistic_include_shipping";

    public function getHooks()
    {
        return array(
            array(
                'type' => TemplateDefinition::BACK_OFFICE,
                'code' => 'statistic.tab',
                'title' => array(
                    'fr_FR' => 'Module de statistiques, onglets.',
                    'en_US' => 'Statistic module, tabs.'
                ),
                'active' => true,
                'block' => true,
                'module' => false
            ),
            array(
                'type' => TemplateDefinition::BACK_OFFICE,
                'code' => 'hook_home_stats',
                'title' => array(
                    'fr_FR' => 'Accueil des statistiques',
                    'en_US' => 'Home Statistics'
                ),
                'active' => true,
                'block' => false,
                'module' => false
            ),
            array(
                'type' => TemplateDefinition::BACK_OFFICE,
                'code' => 'statistic.footer.js',
                'title' => array(
                    'fr_FR' => 'Module de statistiques, insertion des JS.',
                    'en_US' => 'Statistic module, JS insertion'
                ),
                'active' => true,
                'block' => false,
                'module' => false
            )
        );
    }

    public function postActivation(ConnectionInterface $con = null): void
    {
        self::setConfigValue('order_types', '2,3,4');
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }

}
