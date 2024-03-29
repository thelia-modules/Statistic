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
use Statistic\Handler\StatisticHandler;
use Statistic\Statistic;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Base\ProductQuery;
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

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function statAverageCartAction(Request $request, Session $session, StatisticHandler $statisticHandler)
    {
        // récupération des paramètres
        if ($session) {
            $session->save();
        }

        $ghost = $request->query->get('ghost');

        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);

        $result = $statisticHandler->averageCart($startDate, $endDate);
        $average = new \stdClass();
        $average->color = '#5cb85c';
        $average->graph = $result['stats'];
        $average->graphLabel = $result['label'];

        $data = new \stdClass();

        if ($startDay === $endDay && $startMonth === $endMonth && $startYear === $endYear) {
            $data->title = $this->getTranslator()->trans("Stats between %startDay/%startMonth/%startYear", array(
                '%startDay' => $startDay,
                '%startMonth' => $startMonth,
                '%startYear' => $startYear,
            ), Statistic::MESSAGE_DOMAIN);
        } else {
            $data->title = $this->getTranslator()->trans("Stats between %startDay/%startMonth/%startYear and %endDay/%endMonth/%endYear", array(
                '%startDay' => $startDay,
                '%startMonth' => $startMonth,
                '%startYear' => $startYear,
                '%endDay' => $endDay,
                '%endMonth' => $endMonth,
                '%endYear' => $endYear
            ), Statistic::MESSAGE_DOMAIN);
        }

        $data->series = array(
            $average,
        );

        if ((int)$ghost === 1) {

            $ghostGraph = $statisticHandler->averageCart(
                $startDate->sub(new DateInterval('P1Y')),
                $endDate->sub(new DateInterval('P1Y'))
            );
            $ghostCurve = new \stdClass();
            $ghostCurve->color = "#38acfc";
            $ghostCurve->graph = $ghostGraph['stats'];

            $data->series[] = $ghostCurve;
        }
        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function statBestSalesAction(Request $request, StatisticHandler $statisticHandler)
    {
        // récupération des paramètres
        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);

        $productRef = null;
        if ($productId = $request->query->get('productId')) {
            $productRef = ProductQuery::create()->findOneById($productId)->getRef();
        }

        $dateDiff = date_diff($startDate, (new \DateTime($endDate->format("Y-m-d"))));
        $table = [];
        $locale = $request->getSession()->getLang()->getLocale();
        $results = $statisticHandler->bestSales($startDate, $endDate, $locale, $productRef);
        $results2 = $statisticHandler->bestSales(
            (clone($startDate))->sub($dateDiff),
            (clone($endDate))->sub($dateDiff),
            $locale,
            $productRef
        );
        $results3 = $statisticHandler->bestSales(
            (clone($startDate))->sub(new DateInterval('P1Y')),
            (clone($endDate))->sub(new DateInterval('P1Y')),
            $locale,
            $productRef
        );

        /** @var 'N (Période)' $result */
        foreach ($results as $result) {
            $row = $result;
            $row['total_sold2'] = 0;
            $row['total_sold3'] = 0;
            $row['total_ttc2'] = 0;
            $row['total_ttc3'] = 0;
            $row['total_ttc'] = MoneyFormat::getInstance($request)->formatByCurrency($row['total_ttc']);

            if (array_key_exists($result['product_ref'], $results2)) {
                $row['total_sold2'] = $results2[$result['product_ref']]['total_sold'];
                $row['total_ttc2'] = MoneyFormat::getInstance($request)->formatByCurrency($results2[$result['product_ref']]['total_ttc']);
                unset($results2[$result['product_ref']]);
            }

            if (array_key_exists($result['product_ref'], $results3)) {
                $row['total_sold3'] = $results3[$result['product_ref']]['total_sold'];
                $row['total_ttc3'] =  MoneyFormat::getInstance($request)->formatByCurrency($results3[$result['product_ref']]['total_ttc']);
                unset($results3[$result['product_ref']]);
            }

            if ($row) {
                $table[] = $row;
            }
        }

        /** @var '(Période-1)' $result */
        foreach ($results2 as $result) {
            $row = $result;
            $row['total_sold'] = 0;
            $row['total_sold2'] = $result['total_sold'];
            $row['total_sold3'] = 0;
            $row['total_ttc'] = 0;
            $row['total_ttc2'] = MoneyFormat::getInstance($request)->formatByCurrency($result['total_ttc']);
            $row['total_ttc3'] = 0;

            if (array_key_exists($result['product_ref'], $results3)) {
                $row['total_sold3'] = $results3[$result['product_ref']]['total_sold'];
                $row['total_ttc3'] =  MoneyFormat::getInstance($request)->formatByCurrency($results3[$result['product_ref']]['total_ttc']);
                unset($results3[$result['product_ref']]);
            }

            if ($row) {
                $table[] = $row;
            }
        }

        /** @var 'N-1 (Période N-1)' $result */
        foreach ($results3 as $result) {
            $row = $result;
            $row['total_sold'] = 0;
            $row['total_sold2'] = 0;
            $row['total_sold3'] = $result['total_sold'];
            $row['total_ttc'] = 0;
            $row['total_ttc2'] = 0;
            $row['total_ttc3'] = MoneyFormat::getInstance($request)->formatByCurrency($result['total_ttc']);

            if ($row) {
                $table[] = $row;
            }
        }

        $bestSales = new \stdClass();
        $bestSales->color = '#5cb85c';
        $bestSales->mhead = [
            $this->getTranslator()->trans('tool.panel.general.bestSales.sales', [], Statistic::MESSAGE_DOMAIN),
            $this->getTranslator()->trans('tool.panel.general.bestSales.totalTTC', [], Statistic::MESSAGE_DOMAIN),
        ];

        $bestSales->thead = array(
            'title' => $this->getTranslator()->trans('tool.panel.general.bestSales.name', [], Statistic::MESSAGE_DOMAIN),
            'product_ref' => $this->getTranslator()->trans('tool.panel.general.bestSales.reference', [], Statistic::MESSAGE_DOMAIN),
            'brand_title' => $this->getTranslator()->trans('tool.panel.general.bestSales.brand', [], Statistic::MESSAGE_DOMAIN),
            'total_sold' => $this->getTranslator()->trans('tool.panel.general.bestSales.periode', [], Statistic::MESSAGE_DOMAIN),
            'total_sold2' => $this->getTranslator()->trans('tool.panel.general.bestSales.periode-1', [], Statistic::MESSAGE_DOMAIN),
            'total_sold3' => $this->getTranslator()->trans('tool.panel.general.bestSales.periodeN-1', [], Statistic::MESSAGE_DOMAIN),
            'total_ttc' => $this->getTranslator()->trans('tool.panel.general.bestSales.periode', [], Statistic::MESSAGE_DOMAIN),
            'total_ttc2' => $this->getTranslator()->trans('tool.panel.general.bestSales.periode-1', [], Statistic::MESSAGE_DOMAIN),
            'total_ttc3' => $this->getTranslator()->trans('tool.panel.general.bestSales.periodeN-1', [], Statistic::MESSAGE_DOMAIN),
        );
        $bestSales->table = $table;

        $bestSales->totals = [
            $this->getTranslator()->trans('TOTALS', [], Statistic::MESSAGE_DOMAIN),
            '', '',
            'total_sold',
            'total_sold2',
            'total_sold3',
            'total_ttc',
            'total_ttc2',
            'total_ttc3',
            ''
        ];

        $data = new \stdClass();
        $data->series = array(
            $bestSales,
        );

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getProductDetails(Request $request, StatisticHandler $statisticHandler)
    {
        $productId = $request->query->get('productId');

        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);

        $locale = $request->getSession()->getLang()->getLocale();

        $result = $statisticHandler->productDetails($startDate, $endDate, $productId, $locale);

        return $this->jsonResponse(json_encode($result));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function statDiscountCodeAction(Request $request, StatisticHandler $statisticHandler)
    {
        // Get Parameters
        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);

        $discount = new \stdClass();
        $result = $statisticHandler->discountCode($startDate, $endDate);
        foreach ($result as &$coupon) {
            /** @var \Thelia\Coupon\Type\CouponInterface $couponService */
            $couponService = $this->getSpecificCouponService($coupon['type']);
            $coupon['rule'] = $couponService->getName();
        }
        unset($coupon);
        $discount->table = $result;
        $discount->thead = array(
            'code' => $this->getTranslator()->trans('tool.panel.general.discountCode.code', [], Statistic::MESSAGE_DOMAIN),
            'rule' => $this->getTranslator()->trans('tool.panel.general.discountCode.rule', [], Statistic::MESSAGE_DOMAIN),
            'total' => $this->getTranslator()->trans('tool.panel.general.discountCode.nbUse', [], Statistic::MESSAGE_DOMAIN),
        );

        $data = new \stdClass();
        $data->series = array(
            $discount,
        );

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function statMeansTransportAction(Request $request, StatisticHandler $statisticHandler)
    {
        // récupération des paramètres
        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);

        $local = $request->getSession()->getLang()->getLocale();

        $transport = new \stdClass();
        $transport->table = $statisticHandler->meansTransport($startDate, $endDate, $local);
        $transport->thead = array(
            'code' => $this->getTranslator()->trans('tool.panel.general.meansTransport.means', [], Statistic::MESSAGE_DOMAIN),
            'title' => $this->getTranslator()->trans('tool.panel.general.meansTransport.description', [], Statistic::MESSAGE_DOMAIN),
            'total' => $this->getTranslator()->trans('tool.panel.general.meansTransport.nbUse', [], Statistic::MESSAGE_DOMAIN),
        );

        $data = new \stdClass();
        $data->series = array(
            $transport,
        );

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function statMeansPaymentAction(Request $request, StatisticHandler $statisticHandler)
    {
        // récupération des paramètres
        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);

        $local = $request->getSession()->getLang()->getLocale();

        $payment = new \stdClass();
        $payment->table = $statisticHandler->meansPayment($startDate, $endDate, $local);
        $payment->thead = array(
            'code' => $this->getTranslator()->trans('tool.panel.general.meansPayment.means', [], Statistic::MESSAGE_DOMAIN),
            'title' => $this->getTranslator()->trans('tool.panel.general.meansPayment.description', [], Statistic::MESSAGE_DOMAIN),
            'total' => $this->getTranslator()->trans('tool.panel.general.meansPayment.nbUse', [], Statistic::MESSAGE_DOMAIN),
        );

        $data = new \stdClass();
        $data->series = array(
            $payment,
        );

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function statTurnoverAction(Request $request, Session $session, StatisticHandler $statisticHandler)
    {
        if ($session) {
            $session->save();
        }
        setlocale(LC_TIME, 'fr_FR.utf8', 'fra');

        // récupération des paramètres

        $startYear = $request->query->get('startYear', date('Y'));
        $endYear = $request->query->get('endYear', date('Y'));

        $result[$startYear] = $statisticHandler->getTurnoverYear($startYear);

        $turnoverStart = new \stdClass();

        $turnoverStart->color = '#adadad';
        $turnoverStart->graph = $result[$startYear]['graph'];
        $turnoverStart->graphLabel = $result[$startYear]['month'];
        $turnoverStart->table = $result[$startYear]['table'];
        $turnoverStart->thead = array(
            'month' => $this->getTranslator()->trans('tool.panel.general.turnover.month', [], Statistic::MESSAGE_DOMAIN),
            'TTCWithShippping' => $this->getTranslator()->trans('tool.panel.general.turnover.TTCWithShippping', [], Statistic::MESSAGE_DOMAIN),
            'TTCWithoutShippping' => $this->getTranslator()->trans('tool.panel.general.turnover.TTCWithoutShippping', [], Statistic::MESSAGE_DOMAIN),
        );

        $data = new \stdClass();

        $data->series = array(
            $turnoverStart,
        );

        if ($startYear !== $endYear) {
            $result[$endYear] = $statisticHandler->getTurnoverYear($endYear);

            $turnoverEnd = new \stdClass();

            $turnoverEnd->color = '#F00';
            $turnoverEnd->graph = $result[$endYear]['graph'];
            $turnoverEnd->graphLabel = $result[$endYear]['month'];
            $turnoverEnd->table = $result[$endYear]['table'];
            $turnoverEnd->thead = array(
                'month' => $this->getTranslator()->trans('tool.panel.general.turnover.month', [], Statistic::MESSAGE_DOMAIN),
                'TTCWithShippping' => $this->getTranslator()->trans('tool.panel.general.turnover.TTCWithShippping', [], Statistic::MESSAGE_DOMAIN),
                'TTCWithoutShippping' => $this->getTranslator()->trans('tool.panel.general.turnover.TTCWithoutShippping', [], Statistic::MESSAGE_DOMAIN),
            );
            $data->series[] = $turnoverEnd;
            $data->title = $this->getTranslator()->trans("Stats on %startYear and %endYear", array('%startYear' => $startYear, '%endYear' => $endYear), Statistic::MESSAGE_DOMAIN);
        } else {
            $data->title = $this->getTranslator()->trans("Stats on %startYear", array('%startYear' => $startYear), Statistic::MESSAGE_DOMAIN);
        }

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function statRevenueAction(Request $request, Session $session, StatisticHandler $statisticHandler)
    {
        if ($session) {
            $session->save();
        }
        $ghost = $request->query->get('ghost');

        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);

        $saleSeries = new \stdClass();

        if ($startDate->diff($endDate)->format('%a') === '0') {
            $result = $statisticHandler->getRevenueStatsByHours($startDate);
        } else {
            $endDate->add(new DateInterval('P1D'));
            $result = $statisticHandler->getRevenueStats($startDate, $endDate);
        }
        $saleSeries->color = '#adadad';
        $saleSeries->graph = $result['stats'];
        $saleSeries->graphLabel = $result['label'];

        $data = new \stdClass();

        if ($startDay === $endDay && $startMonth === $endMonth && $startYear === $endYear) {
            $data->title = $this->getTranslator()->trans("Stats between %startDay/%startMonth/%startYear", array(
                '%startDay' => $startDay,
                '%startMonth' => $startMonth,
                '%startYear' => $startYear,
            ), Statistic::MESSAGE_DOMAIN);
        } else {
            $data->title = $this->getTranslator()->trans("Stats between %startDay/%startMonth/%startYear and %endDay/%endMonth/%endYear", array(
                '%startDay' => $startDay,
                '%startMonth' => $startMonth,
                '%startYear' => $startYear,
                '%endDay' => $endDay,
                '%endMonth' => $endMonth,
                '%endYear' => $endYear
            ), Statistic::MESSAGE_DOMAIN);
        }

        $data->series = array(
            $saleSeries,
        );

        if ((int)$ghost === 1) {
            if ($startDate->diff($endDate)->format('%a') === '0') {
                $ghostGraph = $statisticHandler->getRevenueStatsByHours($startDate->sub(new DateInterval('P1Y')));
            } else {
                $ghostGraph = $statisticHandler->getRevenueStats(
                    $startDate->sub(new DateInterval('P1Y')),
                    $endDate->sub(new DateInterval('P1Y'))
                );
            }
            $ghostCurve = new \stdClass();
            $ghostCurve->color = "#38acfc";
            $ghostCurve->graph = $ghostGraph['stats'];

            $data->series[] = $ghostCurve;
        }

        return $this->jsonResponse(json_encode($data));
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function statOrdersAction(Request $request, StatisticHandler $statisticHandler)
    {
        if ($session = $request->getSession()) {
            $session->save();
        }
        $ghost = $request->query->get('ghost');

        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);

        $saleSeries = new \stdClass();


        if ($startDate->diff($endDate)->format('%a') === '0') {
            $result = $statisticHandler->getOrdersStatsByHours($startDate);
        } else {
            $endDate->add(new DateInterval('P1D'));
            $result = $statisticHandler->getOrdersStats($startDate, $endDate);
        }
        $saleSeries->color = '#d10d0d';
        $saleSeries->graph = $result['stats'];
        $saleSeries->graphLabel = $result['label'];

        $data = new \stdClass();

        if ($startDay === $endDay && $startMonth === $endMonth && $startYear === $endYear) {
            $data->title = $this->getTranslator()->trans("Stats between %startDay/%startMonth/%startYear", array(
                '%startDay' => $startDay,
                '%startMonth' => $startMonth,
                '%startYear' => $startYear,
            ), Statistic::MESSAGE_DOMAIN);
        } else {
            $data->title = $this->getTranslator()->trans("Stats between %startDay/%startMonth/%startYear and %endDay/%endMonth/%endYear", array(
                '%startDay' => $startDay,
                '%startMonth' => $startMonth,
                '%startYear' => $startYear,
                '%endDay' => $endDay,
                '%endMonth' => $endMonth,
                '%endYear' => $endYear
            ), Statistic::MESSAGE_DOMAIN);
        }

        $data->series = array(
            $saleSeries,
        );

        if ((int)$ghost === 1) {
            if ($startDate->diff($endDate)->format('%a') === '0') {
                $ghostGraph = $statisticHandler->getOrdersStatsByHours($startDate->sub(new DateInterval('P1Y')));
            } else {
                $ghostGraph = $statisticHandler->getOrdersStats(
                    $startDate->sub(new DateInterval('P1Y')),
                    $endDate->sub(new DateInterval('P1Y'))
                );
            }
            $ghostCurve = new \stdClass();
            $ghostCurve->color = "#38acfc";
            $ghostCurve->graph = $ghostGraph['stats'];

            $data->series[] = $ghostCurve;
        }

        return $this->jsonResponse(json_encode($data));
    }

    /** @var  \Thelia\Coupon\Type\CouponInterface */
    protected $couponsServices = array();

    protected function getSpecificCouponService($serviceId)
    {
        if (!isset($this->couponsServices[$serviceId])) {
            $this->couponsServices[$serviceId] = $this->getContainer()->get($serviceId);
        }
        return $this->couponsServices[$serviceId];
    }

}
