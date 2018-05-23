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

namespace Statistic\Controller;

use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\CustomerQuery;
use Thelia\Model\OrderQuery;

/**
 * Class CustomerStatisticController
 * @package Statistic\Controller
 * @author David Gros <dgros@openstudio.fr>
 */
class CustomerStatisticController extends BaseAdminController
{

    const RESOURCE_CODE = "admin.home";

    public function statisticAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) {
            return $response;
        }

        $data = new \stdClass();

        $data->title = $this->getTranslator()->trans("Stats on %month/%year", array('%month' => $this->getRequest()->query->get('month', date('m')), '%year' => $this->getRequest()->query->get('year', date('Y'))));


        /* new customers */
        $newCustomerSeries = new \stdClass();
        $newCustomerSeries->color = $this->getRequest()->query->get('customers_color', '#f39922');
        $newCustomerSeries->graph = CustomerQuery::getMonthlyNewCustomersStats(
            $this->getRequest()->query->get('month', date('m')),
            $this->getRequest()->query->get('year', date('Y'))
        );

//        /* first order */
//        $firstOrderSeries = new \stdClass();
//        $firstOrderSeries->color = $this->getRequest()->query->get('first_orders_color', '#5bc0de');
//        $firstOrderSeries->data = OrderQuery::getFirstOrdersStats(
//            $this->getRequest()->query->get('month', date('m')),
//            $this->getRequest()->query->get('year', date('Y'))
//        );

        $data->series = array(
            $newCustomerSeries,
            //$firstOrderSeries,
        );

        $json = json_encode($data);

        return $this->jsonResponse($json);
    }
}
