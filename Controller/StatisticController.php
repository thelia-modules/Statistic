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
use SoColissimo\Form\ImportForm;
use function Sodium\add;
use Statistic\Handler\StatisticHandler;
use Statistic\Statistic;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Model\OrderQuery;
use Thelia\Tools\MoneyFormat;

/**
 * Class StatisticController
 * @package Statistic\Controller
 * @author David Gros <dgros@openstudio.fr>
 */
class StatisticController extends BaseAdminController
{

    /**
     * Display statistic page.
     *
     * fr_FR Affichage de la page de statistique.
     */
    public function toolShow()
    {
        return $this->render('statistic-tool');
    }

    public function statDaySalesAction()
    {
        $this->getRequest()->getSession()->save();

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

        // Création d'une classe pour stocker les données du graph
        $average = new \stdClass();
        $average->color = '#adadad';

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

                            $dayCount ++;

                            $dailyAmount = $this->getStatisticHandler()->getSaleStats(
                                new \DateTime(sprintf('%s-%s-%s', $startYear, $month, $day)),
                                new \DateTime(sprintf('%s-%s-%s', $startYear, $month, $day)),
                                true
                            );
                            $stats[] = array($dayCount-1, $dailyAmount);
                        }
                    }
                }
                else
                {
                    for ($month=1; $month<=$endMonth; $month++)
                    {
                        $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $endYear);

                        for ($day=1; $day<=$numberOfDay; $day++) {

                            $dayCount ++;

                            $dailyAmount = $this->getStatisticHandler()->getSaleStats(
                                new \DateTime(sprintf('%s-%s-%s', $endYear, $month, $day)),
                                new \DateTime(sprintf('%s-%s-%s', $endYear, $month, $day)),
                                true
                            );
                            $stats[] = array($dayCount-1, $dailyAmount);
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

                    $dayCount ++;

                    $dailyAmount = $this->getStatisticHandler()->getSaleStats(
                        new \DateTime(sprintf('%s-%s-%s', $endYear, $month, $day)),
                        new \DateTime(sprintf('%s-%s-%s', $endYear, $month, $day)),
                        true
                    );
                    $stats[] = array($dayCount-1, $dailyAmount);
                }
            }
        }

        $data = new \stdClass();

        // En fonction du nombre de jours a analyser, definit si l'affichage se fait par jours ou par semaines
        if(count($stats) > 91)
        {
            $data->label = $this->getTranslator()->trans("Weeks");
            $dayCount = 1;
            $weeklyAmount = 0;
            $weekCount = 0;
            $statsByWeek = array();

            foreach ($stats as $stat)
            {
                $dayCount ++;
                $dailyAmount = $stat[1];
                $weeklyAmount = $weeklyAmount +$dailyAmount;

                if ($dayCount == 7)
                {
                    $weekCount ++;
                    $statsByWeek[] = array($weekCount-1, $weeklyAmount);
                    $dayCount = 0;
                    $weeklyAmount = 0;
                }
            }

            $average->graph = $statsByWeek;
        }
        else
        {
            $average->graph = $stats;
            $data->label = $this->getTranslator()->trans("Days");
        }

        // Change le titre en fonction de la période analysée
        if ($startMonth === $endMonth)
        {
            $data->title = $this->getTranslator()->trans("Stats on %monthStart/%yearStart", array('%monthStart' => $this->getRequest()->query->get('monthStart', date('m')), '%yearStart' => $this->getRequest()->query->get('yearStart', date('Y'))));
        }
        else
        {
            $data->title = $this->getTranslator()->trans("Stats for beginning of %monthStart/%yearStart to end of %monthEnd/%yearEnd", array('%monthStart' => $this->getRequest()->query->get('monthStart', date('m')), '%yearStart' => $this->getRequest()->query->get('yearStart', date('Y')), '%monthEnd' => $this->getRequest()->query->get('monthEnd', date('m')), '%yearEnd' => $this->getRequest()->query->get('yearEnd', date('Y'))));
        }

        $data->series[] = $average;

        // Récupére les données pour l'année precedente en comparaison et les injecte dans un tableau
        if($ghostCurve === "true")
        {
            // Création d'une classe pour stocker les données du graph
            $average = new \stdClass();
            $average->color = '#b2b2b2';

            $dayCount = 0;
            $stats = array();

            $startYear = $startYear - 1;
            $endYear = $endYear - 1;

            if ($startYear !== $endYear) {
                for ($i = $startYear; $i <= $endYear; $i++) {
                    if ($i < $endYear) {
                        for ($j = $startMonth; $j <= 12; $j++) {
                            $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $j, $startYear);

                            for ($day = 1; $day <= $numberOfDay; $day++) {

                                $dayCount++;

                                $dailyAmount = $this->getStatisticHandler()->getSaleStats(
                                    new \DateTime(sprintf('%s-%s-%s', $startYear, $j, $day)),
                                    new \DateTime(sprintf('%s-%s-%s', $startYear, $j, $day)),
                                    true
                                );
                                $stats[] = array($dayCount - 1, $dailyAmount);
                            }
                        }
                    } else {
                        for ($k = 1; $k <= $endMonth; $k++) {
                            $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $k, $endYear);

                            for ($day = 1; $day <= $numberOfDay; $day++) {

                                $dayCount++;

                                $dailyAmount = $this->getStatisticHandler()->getSaleStats(
                                    new \DateTime(sprintf('%s-%s-%s', $endYear, $k, $day)),
                                    new \DateTime(sprintf('%s-%s-%s', $endYear, $k, $day)),
                                    true
                                );
                                $stats[] = array($dayCount - 1, $dailyAmount);
                            }
                        }
                    }
                }
            } else {
                for ($i = $startMonth; $i <= $endMonth; $i++) {
                    $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $i, $endYear);

                    for ($day = 1; $day <= $numberOfDay; $day++) {

                        $dayCount++;

                        $dailyAmount = $this->getStatisticHandler()->getSaleStats(
                            new \DateTime(sprintf('%s-%s-%s', $endYear, $i, $day)),
                            new \DateTime(sprintf('%s-%s-%s', $endYear, $i, $day)),
                            true
                        );
                        $stats[] = array($dayCount - 1, $dailyAmount);
                    }
                }
            }

            // En fonction du nombre de jours a analyser, definit si l'affichage se fait par jours ou par semaines
            if (count($stats) > 91) {
                $dayCount = 0;
                $weeklyAmount = 0;
                $weekCount = 0;
                $statsByWeek = array();

                foreach ($stats as $stat) {
                    $dayCount++;
                    $dailyAmount = $stat[1];
                    $weeklyAmount = $weeklyAmount + $dailyAmount;

                    if ($dayCount == 7) {
                        $weekCount++;
                        $statsByWeek[] = array($weekCount - 1, $weeklyAmount);
                        $dayCount = 0;
                        $weeklyAmount = 0;
                    }
                }

                $average->graph = $statsByWeek;
            } else {
                $average->graph = $stats;
            }

            $data->series[] = $average;
        }

        return $this->jsonResponse(json_encode($data));
    }

    public function statAverageCartAction()
    {
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

        $average = new \stdClass();
        $average->color = '#5cb85c';

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
                        // Création date de début et date de fin
                        $monthStartDate = new \DateTime($year . '-' . $month . '-01');
                        /** @var \DateTime $endDate */
                        $monthEndDate = clone($monthStartDate);
                        $monthEndDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

                        $values = $this->getStatisticHandler()->averageCart($monthStartDate, $monthEndDate);

                        foreach ($values as $value)
                        {
                            $dayCount ++;

                            $stats[] = array($dayCount-1, $value[1]);
                        }
                    }
                }
                else
                {
                    for ($month=1; $month<=$endMonth; $month++)
                    {
                        // Création date de début et date de fin
                        $monthStartDate = new \DateTime($year . '-' . $month . '-01');
                        /** @var \DateTime $endDate */
                        $monthEndDate = clone($monthStartDate);
                        $monthEndDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

                        $values = $this->getStatisticHandler()->averageCart($monthStartDate, $monthEndDate);

                        foreach ($values as $value)
                        {
                            $dayCount ++;

                            $stats[] = array($dayCount-1, $value[1]);
                        }
                    }
                }
            }
        }
        else
        {
            for ($month=$startMonth; $month<=$endMonth; $month++)
            {
                // Création date de début et date de fin
                $monthStartDate = new \DateTime($endYear . '-' . $month . '-01');
                /** @var \DateTime $endDate */
                $monthEndDate = clone($monthStartDate);
                $monthEndDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $endYear)-1) . 'D'));

                $values = $this->getStatisticHandler()->averageCart($monthStartDate, $monthEndDate);

                foreach ($values as $value)
                {
                    $dayCount ++;

                    $stats[] = array($dayCount-1, $value[1]);
                }
            }
        }

        $average->graph = $stats;

        $data = new \stdClass();

        // En fonction du nombre de jours a analyser, definit si l'affichage se fait par jours ou par semaines
        if(count($stats) > 91)
        {
            $data->label = $this->getTranslator()->trans("Weeks");
            $dayCount = 1;
            $weeklyAmount = 0;
            $weekCount = 0;
            $statsByWeek = array();

            foreach ($stats as $stat)
            {
                $dayCount ++;
                $dailyAmount = $stat[1];
                $weeklyAmount = $weeklyAmount +$dailyAmount;

                if ($dayCount == 7)
                {
                    $weekCount ++;
                    $statsByWeek[] = array($weekCount-1, $weeklyAmount);
                    $dayCount = 0;
                    $weeklyAmount = 0;
                }
            }

            $average->graph = $statsByWeek;
        }
        else
        {
            $average->graph = $stats;
            $data->label = $this->getTranslator()->trans("Days");
        }

        // Change le titre en fonction de la période analysée
        if ($startMonth === $endMonth)
        {
            $data->title = $this->getTranslator()->trans("Stats on %monthStart/%yearStart", array('%monthStart' => $this->getRequest()->query->get('monthStart', date('m')), '%yearStart' => $this->getRequest()->query->get('yearStart', date('Y'))));
        }
        else
        {
            $data->title = $this->getTranslator()->trans("Stats for beginning of %monthStart/%yearStart to end of %monthEnd/%yearEnd", array('%monthStart' => $this->getRequest()->query->get('monthStart', date('m')), '%yearStart' => $this->getRequest()->query->get('yearStart', date('Y')), '%monthEnd' => $this->getRequest()->query->get('monthEnd', date('m')), '%yearEnd' => $this->getRequest()->query->get('yearEnd', date('Y'))));
        }

        $data->series[] = $average;

        // Récupére les données pour l'année precedente en comparaison et les injecte dans un tableau
        if($ghostCurve === "true")
        {
            // Création d'une classe pour stocker les données du graph
            $average = new \stdClass();
            $average->color = '#b2b2b2';

            $dayCount = 0;
            $stats = array();

            $startYear = $startYear - 1;
            $endYear = $endYear - 1;

            if ($startYear !== $endYear) {
                for ($i = $startYear; $i <= $endYear; $i++) {
                    if ($i < $endYear) {
                        for ($j = $startMonth; $j <= 12; $j++) {
                            $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $j, $startYear);

                            for ($day = 1; $day <= $numberOfDay; $day++) {

                                $dayCount++;

                                $dailyAmount = $this->getStatisticHandler()->getSaleStats(
                                    new \DateTime(sprintf('%s-%s-%s', $startYear, $j, $day)),
                                    new \DateTime(sprintf('%s-%s-%s', $startYear, $j, $day)),
                                    true
                                );
                                $stats[] = array($dayCount - 1, $dailyAmount);
                            }
                        }
                    } else {
                        for ($k = 1; $k <= $endMonth; $k++) {
                            $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $k, $endYear);

                            for ($day = 1; $day <= $numberOfDay; $day++) {

                                $dayCount++;

                                $dailyAmount = $this->getStatisticHandler()->getSaleStats(
                                    new \DateTime(sprintf('%s-%s-%s', $endYear, $k, $day)),
                                    new \DateTime(sprintf('%s-%s-%s', $endYear, $k, $day)),
                                    true
                                );
                                $stats[] = array($dayCount - 1, $dailyAmount);
                            }
                        }
                    }
                }
            } else {
                for ($i = $startMonth; $i <= $endMonth; $i++) {
                    $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $i, $endYear);

                    for ($day = 1; $day <= $numberOfDay; $day++) {

                        $dayCount++;

                        $dailyAmount = $this->getStatisticHandler()->getSaleStats(
                            new \DateTime(sprintf('%s-%s-%s', $endYear, $i, $day)),
                            new \DateTime(sprintf('%s-%s-%s', $endYear, $i, $day)),
                            true
                        );
                        $stats[] = array($dayCount - 1, $dailyAmount);
                    }
                }
            }

            // En fonction du nombre de jours a analyser, definit si l'affichage se fait par jours ou par semaines
            if (count($stats) > 91) {
                $dayCount = 0;
                $weeklyAmount = 0;
                $weekCount = 0;
                $statsByWeek = array();

                foreach ($stats as $stat) {
                    $dayCount++;
                    $dailyAmount = $stat[1];
                    $weeklyAmount = $weeklyAmount + $dailyAmount;

                    if ($dayCount == 7) {
                        $weekCount++;
                        $statsByWeek[] = array($weekCount - 1, $weeklyAmount);
                        $dayCount = 0;
                        $weeklyAmount = 0;
                    }
                }

                $average->graph = $statsByWeek;
            } else {
                $average->graph = $stats;
            }

            $data->series[] = $average;
        }

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function statBestSalesAction()
    {
        // récupération des paramètres
        $startMonth = $this->getRequest()->query->get('monthStart', date('m'));
        $startYear = $this->getRequest()->query->get('yearStart', date('m'));
        $endMonth = $this->getRequest()->query->get('monthEnd', date('m'));
        $endYear = $this->getRequest()->query->get('yearEnd', date('m'));

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

        $bestSales = new \stdClass();
        $bestSales->color = '#5cb85c';
        $bestSales->thead = array(
            'title' => $this->getTranslator()->trans('tool.panel.general.bestSales.name', [], Statistic::BO_MESSAGE_DOMAIN),
            'pse_ref' => $this->getTranslator()->trans('tool.panel.general.bestSales.reference', [], Statistic::BO_MESSAGE_DOMAIN),
            'total_sold' => $this->getTranslator()->trans('tool.panel.general.bestSales.totalSold', [], Statistic::BO_MESSAGE_DOMAIN),
            'total_ht' => $this->getTranslator()->trans('tool.panel.general.bestSales.totalHT', [], Statistic::BO_MESSAGE_DOMAIN),
            'total_ttc' => $this->getTranslator()->trans('tool.panel.general.bestSales.totalTTC', [], Statistic::BO_MESSAGE_DOMAIN),
        );

        if ($startYear !== $endYear)
        {
            for ($year=$startYear; $year<=$endYear; $year++)
            {
                if ($year < $endYear)
                {
                    for ($month=$startMonth; $month<=12; $month++)
                    {
                        // Création date de début et date de fin
                        $monthStartDate = new \DateTime($year . '-' . $month . '-01');
                        /** @var \DateTime $endDate */
                        $monthEndDate = clone($monthStartDate);
                        $monthEndDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

                        $result = $this->getStatisticHandler()->bestSales($this->getRequest(), $monthStartDate, $monthEndDate);
                        $results[] = array($result);
                    }
                }
                else
                {
                    for ($month=1; $month<=$endMonth; $month++)
                    {
                        // Création date de début et date de fin
                        $monthStartDate = new \DateTime($year . '-' . $month . '-01');
                        /** @var \DateTime $endDate */
                        $monthEndDate = clone($monthStartDate);
                        $monthEndDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

                        $result = $this->getStatisticHandler()->bestSales($this->getRequest(), $monthStartDate, $monthEndDate);
                        $results[] = array($result);
                    }
                }
            }
        }
        else
        {
            for ($month=$startMonth; $month<=$endMonth; $month++)
            {
                // Création date de début et date de fin
                $monthStartDate = new \DateTime($endYear . '-' . $month . '-01');
                /** @var \DateTime $endDate */
                $monthEndDate = clone($monthStartDate);
                $monthEndDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $endYear)-1) . 'D'));

                $result = $this->getStatisticHandler()->bestSales($this->getRequest(), $monthStartDate, $monthEndDate);
                $results[] = array($result);
            }
        }

        $finalResult = [];

        foreach ($results as $result)
        {
            foreach ($result as $value)
            {
                foreach ($value as $item)
                {
                    if (array_key_exists($item['product_ref'], $finalResult))
                    {
                        $totalHT = $item['total_ht'] + $finalResult[$item['product_ref']]['total_ht'];
                        $totalTTC = $item['total_ttc'] + $finalResult[$item['product_ref']]['total_ttc'];
                        $finalResult[$item['product_ref']]['total_sold'] = $item['total_sold'] + $finalResult[$item['product_ref']]['total_sold'];

                        $finalResult[$item['product_ref']]['total_ht'] = ''. $totalHT .' €';

                        $finalResult[$item['product_ref']]['total_ttc'] = ''. $totalTTC .' €';
                    }
                    else
                    {
                        $finalResult[$item['product_ref']] = $item;
                    }
                }
            }
        }

        $bestSales->table = $finalResult;

        $data = new \stdClass();
        $data->series = array(
            $bestSales,
        );

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function statDiscountCodeAction()
    {
        // récupération des paramètres
        $startMonth = $this->getRequest()->query->get('monthStart', date('m'));
        $startYear = $this->getRequest()->query->get('yearStart', date('m'));
        $endMonth = $this->getRequest()->query->get('monthEnd', date('m'));
        $endYear = $this->getRequest()->query->get('yearEnd', date('m'));

        // Vérification des paramètres, renvoie un message d'erreur si le mois de fin est incorrect
        if($startYear === $endYear && $endMonth < $startMonth)
        {
            $error = $this->getTranslator()->trans( "Error : End month is incorrect." );
            return $this->jsonResponse(json_encode($error));
        }

        $discount = new \stdClass();

        if ($startYear !== $endYear)
        {
            for ($year=$startYear; $year<=$endYear; $year++)
            {
                if ($year < $endYear)
                {
                    for ($month=$startMonth; $month<=12; $month++)
                    {
                        // Création date de début et date de fin
                        $startDate = new \DateTime($year . '-' . $month . '-01');
                        /** @var \DateTime $endDate */
                        $endDate = clone($startDate);
                        $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

                        $result = $this->getStatisticHandler()->discountCode($startDate, $endDate);

                        foreach( $result as $coupon ){
                            /** @var \Thelia\Coupon\Type\CouponInterface $couponService */
                            $couponService = $this->getSpecificCouponService($coupon['type']);
                            $coupon['rule'] = $couponService->getName();
                        }

                        $results[] = array($result);
                    }
                }
                else
                {
                    for ($month=1; $month<=$endMonth; $month++)
                    {
                        // Création date de début et date de fin
                        $startDate = new \DateTime($year . '-' . $month . '-01');
                        /** @var \DateTime $endDate */
                        $endDate = clone($startDate);
                        $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

                        $result = $this->getStatisticHandler()->discountCode($startDate, $endDate);

                        foreach( $result as $coupon ){
                            /** @var \Thelia\Coupon\Type\CouponInterface $couponService */
                            $couponService = $this->getSpecificCouponService($coupon['type']);
                            $coupon['rule'] = $couponService->getName();
                        }

                        $results[] = array($result);
                    }
                }
            }
        }
        else
        {
            for ($month=$startMonth; $month<=$endMonth; $month++)
            {
                // Création date de début et date de fin
                $startDate = new \DateTime($endYear . '-' . $month . '-01');
                /** @var \DateTime $endDate */
                $endDate = clone($startDate);
                $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $endYear)-1) . 'D'));

                $result = $this->getStatisticHandler()->discountCode($startDate, $endDate);

                foreach( $result as $coupon ){
                    /** @var \Thelia\Coupon\Type\CouponInterface $couponService */
                    $couponService = $this->getSpecificCouponService($coupon['type']);
                    $coupon['rule'] = $couponService->getName();
                }

                $results[] = array($result);
            }
        }

        $finalResult = [];

        foreach ($results as $result)
        {
            foreach ($result as $value)
            {
                foreach ($value as $item)
                {
                    if (array_key_exists($item['code'], $finalResult))
                    {
                        $finalResult[$item['code']]['total'] = $item['total'] + $finalResult[$item['code']]['total'];
                    }
                    else
                    {
                        if ($item['total'] != 0)
                        {
                            $finalResult[$item['code']] = $item;
                        }
                    }
                }
            }
        }

        $discount->thead = array(
            'code' => $this->getTranslator()->trans('tool.panel.general.discountCode.code',[], Statistic::BO_MESSAGE_DOMAIN),
            'rule' => $this->getTranslator()->trans('tool.panel.general.discountCode.rule',[], Statistic::BO_MESSAGE_DOMAIN),
            'total' => $this->getTranslator()->trans('tool.panel.general.discountCode.nbUse',[], Statistic::BO_MESSAGE_DOMAIN),
        );

        $discount->table = $finalResult;

        $data = new \stdClass();
        $data->series = array($discount);

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function statMeansTransportAction()
    {
        // récupération des paramètres
        $month = $this->getRequest()->query->get('month', date('m'));
        $year = $this->getRequest()->query->get('year', date('m'));

        $startDate = new \DateTime($year . '-' . $month . '-01');
        /** @var \DateTime $endDate */
        $endDate = clone($startDate);
        $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

        $local = $this->getSession()->getLang()->getLocale();

        $transport = new \stdClass();
        $transport->table = $this->getStatisticHandler()->meansTransport($startDate, $endDate, $local);
        $transport->thead = array(
            'code' => $this->getTranslator()->trans('tool.panel.general.meansTransport.means',[], Statistic::BO_MESSAGE_DOMAIN),
            'title' => $this->getTranslator()->trans('tool.panel.general.meansTransport.description',[], Statistic::BO_MESSAGE_DOMAIN),
            'total' => $this->getTranslator()->trans('tool.panel.general.meansTransport.nbUse',[], Statistic::BO_MESSAGE_DOMAIN),
        );

        $data = new \stdClass();
        $data->series = array(
            $transport,
        );

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function statMeansPaymentAction()
    {
        // récupération des paramètres
        $month = $this->getRequest()->query->get('month', date('m'));
        $year = $this->getRequest()->query->get('year', date('m'));

        $startDate = new \DateTime($year . '-' . $month . '-01');
        /** @var \DateTime $endDate */
        $endDate = clone($startDate);
        $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

        $local = $this->getSession()->getLang()->getLocale();

        $payment = new \stdClass();
        $payment->table = $this->getStatisticHandler()->meansPayment($startDate, $endDate, $local);
        $payment->thead = array(
            'code' => $this->getTranslator()->trans('tool.panel.general.meansPayment.means',[], Statistic::BO_MESSAGE_DOMAIN),
            'title' => $this->getTranslator()->trans('tool.panel.general.meansPayment.description',[], Statistic::BO_MESSAGE_DOMAIN),
            'total' => $this->getTranslator()->trans('tool.panel.general.meansPayment.nbUse',[], Statistic::BO_MESSAGE_DOMAIN),
        );

        $data = new \stdClass();
        $data->series = array(
            $payment,
        );

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     * @throws \Exception
     */
    public function statTurnoverAction()
    {
        setlocale (LC_TIME, 'fr_FR.utf8','fra');

        // récupération des paramètres
        $year = $this->getRequest()->query->get('yearEnd', date('m'));

        $turnover = new \stdClass();
        $result =  $this->getStatisticHandler()->turnover($year);
        $table = array();
        $graph = array();
        $month = array();
        $zero = MoneyFormat::getInstance($this->getRequest())->formatByCurrency(0);

        for ($i = 1; $i <= 12; ++$i) {
            $date = new \DateTime($year.'-'.$i);
            if(!isset($result[$date->format('Y-n')])){
                $table[$i] = array(
                    'TTCWithShippping' => $zero,
                    'TTCWithoutShippping' => $zero
                );
                $graph[] = array(
                    $i - 1,
                    0
                );
            }else{
                $tmp = $result[$date->format('Y-n')];

                //Get first day of month
                $startDate = new \DateTime($year . '-' . $i . '-01');
                /** @var \DateTime $endDate */

                //Get last day of month (first + total of month day -1)
                $endDate = clone($startDate);
                $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $i, $year)-1) . 'D'));

                $discount = OrderQuery::create()
                    ->filterByCreatedAt(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
                    ->filterByCreatedAt(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
                    ->filterByStatusId(StatisticHandler::ALLOWED_STATUS_INT, Criteria::IN)
                    ->withColumn("SUM(`order`.discount)", 'DISCOUNT')
                    ->select('DISCOUNT')->findOne();

                $postage = OrderQuery::create()
                    ->filterByCreatedAt(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
                    ->filterByCreatedAt(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
                    ->filterByStatusId(StatisticHandler::ALLOWED_STATUS_INT, Criteria::IN)
                    ->withColumn("SUM(`order`.postage)", 'POSTAGE')
                    ->select('POSTAGE')->findOne();

                if (null === $discount) {
                    $discount = 0;
                }

                $table[$i] = array(
                    'TTCWithShippping' => MoneyFormat::getInstance($this->getRequest())->formatByCurrency($tmp['TOTAL'] + $tmp['TAX'] + $postage - $discount),
                    'TTCWithoutShippping' => MoneyFormat::getInstance($this->getRequest())->formatByCurrency($tmp['TOTAL'] + $tmp['TAX'] - $discount)
                );
                $graph[] = array(
                    $i - 1,
                    intval($tmp['TOTAL']+$tmp['TAX'] - $discount)
                );
            }
            $month[] = $date->format('M');
            $table[$i]['month'] = $date->format('M');
        }
        $turnover->color = '#adadad';
        $turnover->graph = $graph;
        $turnover->graphLabel = $month;
        $turnover->table = $table;
        $turnover->thead = array(
            'month' => $this->getTranslator()->trans('tool.panel.general.turnover.month', [], Statistic::BO_MESSAGE_DOMAIN),
            'TTCWithShippping' => $this->getTranslator()->trans('tool.panel.general.turnover.TTCWithShippping', [], Statistic::BO_MESSAGE_DOMAIN),
            'TTCWithoutShippping' => $this->getTranslator()->trans('tool.panel.general.turnover.TTCWithoutShippping', [], Statistic::BO_MESSAGE_DOMAIN),
        );
        $data = new \stdClass();
        $data->title = $this->getTranslator()->trans("Stats on %year", array('%year' => $this->getRequest()->query->get('yearEnd', date('Y'))), "statistic");

        $data->series = array(
            $turnover
        );

        return $this->jsonResponse(json_encode($data));
    }

    /** @var  \Statistic\Handler\StatisticHandler */
    protected $statisticHandler;

    protected function getStatisticHandler()
    {
        if (!isset($this->statisticHandler)) {
            $this->statisticHandler = $this->getContainer()->get('statistic.handler.statistic');
        }

        return $this->statisticHandler;
    }

    /** @var  \Thelia\Coupon\Type\CouponInterface */
    protected $couponsServices = array();

    protected function getSpecificCouponService( $serviceId)
    {
        if( !isset( $this->couponsServices[$serviceId])){
            $this->couponsServices[$serviceId] = $this->getContainer()->get($serviceId);
        }
        return $this->couponsServices[$serviceId];
    }

}
