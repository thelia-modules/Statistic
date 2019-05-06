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
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\CustomerQuery;

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

        /* first order */
        /*
        $firstOrderSeries = new \stdClass();
        $firstOrderSeries->color = $this->getRequest()->query->get('first_orders_color', '#5bc0de');
        $firstOrderSeries->data = OrderQuery::getFirstOrdersStats(
            $this->getRequest()->query->get('month', date('m')),
            $this->getRequest()->query->get('year', date('Y'))
            );
        */

        // récupération des paramètres
        $startMonth = $this->getRequest()->query->get('monthStart', date('m'));
        $startYear = $this->getRequest()->query->get('yearStart', date('m'));
        $endMonth = $this->getRequest()->query->get('monthEnd', date('m'));
        $endYear = $this->getRequest()->query->get('yearEnd', date('m'));
        $ghostCurve = $this->getRequest()->query->get('ghostCurve');

        // Vérification des paramètres, renvoie un message d'erreur si le mois de fin est incorrect
        if($startYear === $endYear && $endMonth < $startMonth || $startYear > $endYear)
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
                        $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

                        for ($day=1; $day<=$numberOfDay; $day++) {

                            $dayCount++;

                            $dailyCustomers = CustomerQuery::create()
                                ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $year, $month, $day), Criteria::GREATER_EQUAL)
                                ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $year, $month, $day), Criteria::LESS_EQUAL)
                                ->count();

                            $stats[] = array($dayCount - 1, $dailyCustomers);
                        }
                    }
                }
                else
                {
                    for ($month=1; $month<=$endMonth; $month++)
                    {
                        $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

                        for ($day=1; $day<=$numberOfDay; $day++) {

                            $dayCount++;

                            $dailyCustomers = CustomerQuery::create()
                                ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $year, $month, $day), Criteria::GREATER_EQUAL)
                                ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $year, $month, $day), Criteria::LESS_EQUAL)
                                ->count();

                            $stats[] = array($dayCount - 1, $dailyCustomers);
                        }
                    }
                }
            }
        }
        else
        {
            for ($month=$startMonth; $month<=$endMonth; $month++)
            {
                $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $endYear);

                for ($day=1; $day<=$numberOfDay; $day++) {

                    $dayCount++;

                    $dailyCustomers = CustomerQuery::create()
                        ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $endYear, $month, $day), Criteria::GREATER_EQUAL)
                        ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $endYear, $month, $day), Criteria::LESS_EQUAL)
                        ->count();

                    $stats[] = array($dayCount - 1, $dailyCustomers);
                }
            }
        }

        // En fonction du nombre de jours a analyser, definit si l'affichage se fait par jours ou par semaines
        if(count($stats) > 91)
        {
            $data->label = $this->getTranslator()->trans("Weeks");
            $dayCount = 1;
            $weeklyCustomers = 0;
            $weekCount = 0;
            $statsByWeek = array();

            foreach ($stats as $stat)
            {
                $dayCount ++;
                $dailyCustomers = $stat[1];
                $weeklyCustomers = $weeklyCustomers +$dailyCustomers;

                if ($dayCount == 7)
                {
                    $weekCount ++;
                    $statsByWeek[] = array($weekCount-1, $weeklyCustomers);
                    $dayCount = 0;
                    $weeklyCustomers = 0;
                }
            }

            $newCustomerSeries->graph = $statsByWeek;
        }
        else
        {
            $newCustomerSeries->graph = $stats;
            $data->label = $this->getTranslator()->trans("Days");
        }

        $data->series[] = $newCustomerSeries;

        // Récupére les données pour l'année precedente en comparaison et les injecte dans un tableau
        if($ghostCurve === "true")
        {
            // Création d'une classe pour stocker les données du graph
            $newCustomerSeries = new \stdClass();
            $newCustomerSeries->color = '#b2b2b2';

            $dayCount = 0;
            $stats = array();

            $startYear = $startYear - 1;
            $endYear = $endYear - 1;

            if ($startYear !== $endYear)
            {
                for ($year=$startYear; $year<=$endYear; $year++)
                {
                    if ($year < $endYear)
                    {
                        for ($month=$startMonth; $month<=12; $month++)
                        {
                            $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

                            for ($day=1; $day<=$numberOfDay; $day++) {

                                $dayCount++;

                                $dailyCustomers = CustomerQuery::create()
                                    ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $year, $month, $day), Criteria::GREATER_EQUAL)
                                    ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $year, $month, $day), Criteria::LESS_EQUAL)
                                    ->count();

                                $stats[] = array($dayCount - 1, $dailyCustomers);
                            }
                        }
                    }
                    else
                    {
                        for ($month=1; $month<=$endMonth; $month++)
                        {
                            $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

                            for ($day=1; $day<=$numberOfDay; $day++) {

                                $dayCount++;

                                $dailyCustomers = CustomerQuery::create()
                                    ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $year, $month, $day), Criteria::GREATER_EQUAL)
                                    ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $year, $month, $day), Criteria::LESS_EQUAL)
                                    ->count();

                                $stats[] = array($dayCount - 1, $dailyCustomers);
                            }
                        }
                    }
                }
            }
            else
            {
                for ($month=$startMonth; $month<=$endMonth; $month++)
                {
                    $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $endYear);

                    for ($day=1; $day<=$numberOfDay; $day++) {

                        $dayCount++;

                        $dailyCustomers = CustomerQuery::create()
                            ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $endYear, $month, $day), Criteria::GREATER_EQUAL)
                            ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $endYear, $month, $day), Criteria::LESS_EQUAL)
                            ->count();

                        $stats[] = array($dayCount - 1, $dailyCustomers);
                    }
                }
            }

            // En fonction du nombre de jours a analyser, definit si l'affichage se fait par jours ou par semaines
            if(count($stats) > 91)
            {
                $data->label = $this->getTranslator()->trans("Weeks");
                $dayCount = 1;
                $weeklyCustomers = 0;
                $weekCount = 0;
                $statsByWeek = array();

                foreach ($stats as $stat)
                {
                    $dayCount ++;
                    $dailyCustomers = $stat[1];
                    $weeklyCustomers = $weeklyCustomers +$dailyCustomers;

                    if ($dayCount == 7)
                    {
                        $weekCount ++;
                        $statsByWeek[] = array($weekCount-1, $weeklyCustomers);
                        $dayCount = 0;
                        $weeklyCustomers = 0;
                    }
                }

                $newCustomerSeries->graph = $statsByWeek;
            }
            else
            {
                $newCustomerSeries->graph = $stats;
                $data->label = $this->getTranslator()->trans("Days");
            }

            $data->series[] = $newCustomerSeries;

        }

        $json = json_encode($data);

        return $this->jsonResponse($json);
    }
}
