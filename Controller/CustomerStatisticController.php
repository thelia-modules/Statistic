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

use Statistic\Statistic;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;



/**
 * Class CustomerStatisticController
 * @package Statistic\Controller
 * @author David Gros <dgros@openstudio.fr>
 */
class CustomerStatisticController extends BaseAdminController
{

    const RESOURCE_CODE = "admin.home";

    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function statisticAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) {
            return $response;
        }

        $ghost = $this->getRequest()->query->get('ghost');

        $startDay = $this->getRequest()->query->get('startDay', date('d'));
        $startMonth = $this->getRequest()->query->get('startMonth', date('m'));
        $startYear = $this->getRequest()->query->get('startYear', date('Y'));

        $endDay = $this->getRequest()->query->get('endDay', date('d'));
        $endMonth = $this->getRequest()->query->get('endMonth', date('m'));
        $endYear = $this->getRequest()->query->get('endYear', date('Y'));

        $data = new \stdClass();

        $data->title = $this->getTranslator()->trans(
            "Stats between %startDay/%startMonth/%startYear and %endDay/%endMonth/%endYear", array(
            '%startDay' => $startDay,
            '%startMonth' => $startMonth,
            '%startYear' => $startYear,
            '%endDay' => $endDay,
            '%endMonth' => $endMonth,
            '%endYear' => $endYear
        ), Statistic::MESSAGE_DOMAIN
        );

        $startDate = new \DateTime($startYear.'-'.$startMonth.'-'.$startDay);
        $endDate = new \DateTime($endYear.'-'.$endMonth.'-'.$endDay);

        if ($startDate->diff($endDate)->format('%a') === '0'){
            $result = $this->getCustomerStatHandler()->getNewCustomersStatsByHours($startDate);
        }
        else{
            $result = $this->getCustomerStatHandler()->getNewCustomersStats($startDate, $endDate);
        }

        $newCustomerSeries = new \stdClass();
        $newCustomerSeries->color = $this->getRequest()->query->get('customers_color', '#f39922');
        $newCustomerSeries->graphLabel = $result['label'];
        $newCustomerSeries->graph = $result['stats'];

        $data->series = array(
            $newCustomerSeries,
            //$firstOrderSeries,
        );

        if ($ghost == 1){
            if ($startDate->diff($endDate)->format('%a') === '0') {
                $ghostGraph = $this->getCustomerStatHandler()->getNewCustomersStatsByHours($startDate->sub(new \DateInterval('P1Y')));
            }
            else{
                $ghostGraph = $this->getCustomerStatHandler()->getNewCustomersStats(
                    $startDate->sub(new \DateInterval('P1Y')),
                    $endDate->sub(new \DateInterval('P1Y'))
                );
            }
            $ghostCurve = new \stdClass();
            $ghostCurve->color = "#38acfc";
            $ghostCurve->graph = $ghostGraph['stats'];

            array_push($data->series, $ghostCurve);
        }

        $json = json_encode($data);

        return $this->jsonResponse($json);
    }

    /** @var Statistic/Handler/CustomerStatHandler */
    protected $customerStatHandler;

    protected function getCustomerStatHandler()
    {
        if (!isset($this->customerStatHandler)) {
            $this->customerStatHandler = $this->getContainer()->get('statistic.handler.customer');
        }

        return $this->customerStatHandler;
    }
}