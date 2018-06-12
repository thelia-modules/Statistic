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
use Statistic\Statistic;
use Thelia\Controller\Admin\BaseAdminController;
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

    public function statAverageCartAction()
    {
        // récupération des paramètres
        $month = $this->getRequest()->query->get('month', date('m'));
        $year = $this->getRequest()->query->get('year', date('m'));

        $startDate = new \DateTime($year . '-' . $month . '-01');
        /** @var \DateTime $endDate */
        $endDate = clone($startDate);
        $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

        $average = new \stdClass();
        $average->color = '#5cb85c';
        $average->graph = $this->getStatisticHandler()->averageCart($startDate, $endDate);

        $data = new \stdClass();

        $data->title = $this->getTranslator()->trans("Stats on %month/%year", array('%month' => $this->getRequest()->query->get('month', date('m')), '%year' => $this->getRequest()->query->get('year', date('Y'))));

        $data->series = array(
            $average,
        );

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function statBestSalesAction()
    {
        // récupération des paramètres
        $month = $this->getRequest()->query->get('month', date('m'));
        $year = $this->getRequest()->query->get('year', date('m'));

        $startDate = new \DateTime($year . '-' . $month . '-01');
        /** @var \DateTime $endDate */
        $endDate = clone($startDate);
        $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

        $bestSales = new \stdClass();
        $bestSales->color = '#5cb85c';
        $bestSales->thead = array(
            'title' => $this->getTranslator()->trans('tool.panel.general.bestSales.name', [], Statistic::BO_MESSAGE_DOMAIN),
            'pse_ref' => $this->getTranslator()->trans('tool.panel.general.bestSales.reference', [], Statistic::BO_MESSAGE_DOMAIN),
            'total_sold' => $this->getTranslator()->trans('tool.panel.general.bestSales.totalSold', [], Statistic::BO_MESSAGE_DOMAIN),
            'total_ht' => $this->getTranslator()->trans('tool.panel.general.bestSales.totalHT', [], Statistic::BO_MESSAGE_DOMAIN),
            'total_ttc' => $this->getTranslator()->trans('tool.panel.general.bestSales.totalTTC', [], Statistic::BO_MESSAGE_DOMAIN),
        );
        $bestSales->table = $this->getStatisticHandler()->bestSales($this->getRequest(), $startDate, $endDate);

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
        $month = $this->getRequest()->query->get('month', date('m'));
        $year = $this->getRequest()->query->get('year', date('m'));

        $startDate = new \DateTime($year . '-' . $month . '-01');
        /** @var \DateTime $endDate */
        $endDate = clone($startDate);
        $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $month, $year)-1) . 'D'));

        $discount = new \stdClass();
        $result = $this->getStatisticHandler()->discountCode($startDate, $endDate);
        foreach( $result as &$coupon ){
            /** @var \Thelia\Coupon\Type\CouponInterface $couponService */
            $couponService = $this->getSpecificCouponService($coupon['type']);
            $coupon['rule'] = $couponService->getName();
        }
        $discount->table = $result;
        $discount->thead = array(
            'code' => $this->getTranslator()->trans('tool.panel.general.discountCode.code',[], Statistic::BO_MESSAGE_DOMAIN),
            'rule' => $this->getTranslator()->trans('tool.panel.general.discountCode.rule',[], Statistic::BO_MESSAGE_DOMAIN),
            'total' => $this->getTranslator()->trans('tool.panel.general.discountCode.nbUse',[], Statistic::BO_MESSAGE_DOMAIN),
        );

        $data = new \stdClass();
        $data->series = array(
            $discount,
        );

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
        $month = $this->getRequest()->query->get('month', date('m'));
        $year = $this->getRequest()->query->get('year', date('m'));

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
                    ->filterByInvoiceDate(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
                    ->filterByInvoiceDate(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
                    ->filterByStatusId([2, 3, 4], Criteria::IN)
                    ->withColumn("SUM(`order`.discount)", 'DISCOUNT')
                    ->select('DISCOUNT')->findOne();

                $postage = OrderQuery::create()
                    ->filterByInvoiceDate(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
                    ->filterByInvoiceDate(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
                    ->filterByStatusId([2, 3, 4], Criteria::IN)
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
        $data->title = $this->getTranslator()->trans("Stats on %year", array('%year' => $this->getRequest()->query->get('year', date('Y'))), "statistic");

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
