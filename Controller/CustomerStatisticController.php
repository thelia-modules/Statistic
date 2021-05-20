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

use Statistic\Handler\CustomerStatHandler;
use Statistic\Statistic;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
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
    public function statisticAction(Request $request, CustomerStatHandler $customerStatHandler)
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) {
            return $response;
        }

        $ghost = $request->query->get('ghost');

        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

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

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);

        if ($startDate->diff($endDate)->format('%a') === '0') {
            $result = $customerStatHandler->getNewCustomersStatsByHours($startDate);
        } else {
            $result = $customerStatHandler->getNewCustomersStats($startDate, $endDate);
        }

        $newCustomerSeries = new \stdClass();
        $newCustomerSeries->color = $request->query->get('customers_color', '#f39922');
        $newCustomerSeries->graphLabel = $result['label'];
        $newCustomerSeries->graph = $result['stats'];

        $data->series = array(
            $newCustomerSeries,
        );

        if ((int)$ghost === 1) {
            if ($startDate->diff($endDate)->format('%a') === '0') {
                $ghostGraph = $customerStatHandler->getNewCustomersStatsByHours($startDate->sub(new \DateInterval('P1Y')));
            } else {
                $ghostGraph = $customerStatHandler->getNewCustomersStats(
                    $startDate->sub(new \DateInterval('P1Y')),
                    $endDate->sub(new \DateInterval('P1Y'))
                );
            }
            $ghostCurve = new \stdClass();
            $ghostCurve->color = "#38acfc";
            $ghostCurve->graph = $ghostGraph['stats'];

            $data->series[] = $ghostCurve;
        }

        $json = json_encode($data);

        return $this->jsonResponse($json);
    }
}