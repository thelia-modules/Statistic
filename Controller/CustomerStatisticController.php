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

use DateInterval;
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

        // récupération des paramètres
        $startMonth = $this->getRequest()->query->get('monthStart', date('m'));
        $startYear = $this->getRequest()->query->get('yearStart', date('m'));
        $endMonth = $this->getRequest()->query->get('monthEnd', date('m'));
        $endYear = $this->getRequest()->query->get('yearEnd', date('m'));
        $ghostCurve = $this->getRequest()->query->get('ghostCurve');

        // Vérification des paramètres, renvoie un message d'erreur si le mois de fin est incorrect
        if($startYear === $endYear && $endMonth < $startMonth)
        {
            $error = $this->getTranslator()->trans( "Error : End month is incorrect." );
            return $this->jsonResponse(json_encode($error));
        }

        // Création date de début et date de fin
        $startDate = new \DateTime($startYear . '-' . $startMonth . '-01');
        /** @var \DateTime $endDate */
        $endDate = clone($startDate);
        $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $endMonth, $endYear)-1) . 'D'));

        $data = new \stdClass();

        // Change le titre en fonction de la période analysée
        if ($startMonth === $endMonth)
        {
            $data->title = $this->getTranslator()->trans("Stats on %monthStart/%yearStart", array('%monthStart' => $this->getRequest()->query->get('monthStart', date('m')), '%yearStart' => $this->getRequest()->query->get('yearStart', date('Y'))));
        }
        else
        {
            $data->title = $this->getTranslator()->trans("Stats for beginning of %monthStart/%yearStart to end of %monthEnd/%yearEnd", array('%monthStart' => $this->getRequest()->query->get('monthStart', date('m')), '%yearStart' => $this->getRequest()->query->get('yearStart', date('Y')), '%monthEnd' => $this->getRequest()->query->get('monthEnd', date('m')), '%yearEnd' => $this->getRequest()->query->get('yearEnd', date('Y'))));
        }

        /* new customers */
        $newCustomerSeries = new \stdClass();
        $newCustomerSeries->color = $this->getRequest()->query->get('customers_color', '#f39922');
        /*
        $newCustomerSeries->graph = CustomerQuery::getMonthlyNewCustomersStats(
            $this->getRequest()->query->get('month', date('m')),
            $this->getRequest()->query->get('year', date('Y'))
        );
        */

        // Récupére les données pour chaques jours et les injecte dans un tableau
        $dayCount = 0;
        $stats = array();

        if ($startYear !== $endYear)
        {
            for ($year=$startYear; $year<=$endYear; $year++)
            {
                if ($year < $endYear)
                {
                    for ($month=$startMonth; $month<=12; $month++)
                    {
                        $newCustomerSeries->graph = CustomerQuery::getMonthlyNewCustomersStats($month,$year);
                    }
                }
                else
                {
                    for ($month=1; $month<=$endMonth; $month++)
                    {
                        $newCustomerSeries->graph = CustomerQuery::getMonthlyNewCustomersStats($month,$year);
                    }
                }
            }
        }
        else
        {
            for ($month=$startMonth; $month<=$endMonth; $month++)
            {
                $newCustomerSeries->graph = CustomerQuery::getMonthlyNewCustomersStats($month,$endYear);
            }
        }


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
