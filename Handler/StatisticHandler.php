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

namespace Statistic\Handler;

use DateInterval;
use DateTime;
use Exception;
use PDO;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Exception\PropelException;
use Statistic\Query\OrderByHoursQuery;
use Statistic\Query\StatsOrderQuery;
use Statistic\Statistic;
use Thelia\Model\Base\AttributeAvQuery;
use Thelia\Model\Base\OrderProduct;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Map\CouponTableMap;
use Thelia\Model\Map\ModuleI18nTableMap;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Map\OrderCouponTableMap;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductQuery;

/**
 * Class StatisticHandler
 * @package Statistic\Handler
 * @author David Gros <dgros@openstudio.fr>
 */
class StatisticHandler
{
    const START_DAY_FORMAT = 'Y-m-d 00:00:00';
    const END_DAY_FORMAT = 'Y-m-d 23:59:59';

    /**
     * @throws Exception
     */
    public function averageCart(DateTime $startDate, DateTime $endDate): array
    {
        $po = $this->getMonthlySaleStats($startDate, $endDate);
        $order = StatsOrderQuery::getOrderNumber($startDate,  $endDate);

        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        for ($day = 0, $date = clone($startDate); $date <= $endDate; $date->add(new DateInterval('P1D')), $day++) {
            $currentStat = 0;
            if (isset($order[$date->format('Y-n-j')]) && isset($po[$date->format('Y-n-j')])){
                $currentStat = round($po[$date->format('Y-n-j')] / $order[$date->format('Y-n-j')], 2);
            }
            $result['stats'][] = [$day, $currentStat];
            $result['label'][] = [$day, $date->format('d/m')];
        }

        return $result;
    }

    /**
     * @throws PropelException
     */
    public function bestSales(DateTime $startDate, DateTime $endDate, string $locale, ?string $productRef = null): array
    {
        $queryResult = $this->bestSalesQuery($startDate, $endDate, $productRef)->find()->toArray();
        $result = [];

        foreach ($queryResult as &$pse) {
            $product = ProductQuery::create()
                ->useProductSaleElementsQuery()
                ->filterById($pse['product_sale_elements_id'])
                ->endUse()
                ->findOne();

            if (null === $product) {
                $product = ProductQuery::create()
                    ->filterByRef($pse['product_ref'])
                    ->findOne();
            }

            if (null !== $product) {
                $pse['brand_id'] = $product->getBrandId();
                $pse['brand_title'] = '';
                if ($brand = $product->getBrand()) {
                    $pse['brand_title'] = $brand->setLocale($locale)->getTitle();
                }
                $pse['product_id'] = $product->getId();
            }
            $totalHt = $pse['total_ht'] ?: 0;
            $pse['total_ttc'] = $totalHt + $pse['tax'] - $pse['discount'];

            $result[$pse['product_ref']] = $pse;
        }

        return $result;
    }

    /**
     * @throws PropelException
     */
    public function productDetails(DateTime $startDate, DateTime $endDate, int $productId, string $locale): array
    {
        $product = ProductQuery::create()->filterById($productId)->findOne();
        $productRef = $product->getRef();
        $query = OrderProductQuery::create()
            ->useOrderQuery()
            ->useOrderStatusQuery()
            ->filterById(explode(',', Statistic::getConfigValue('order_types')))
            ->endUse()
            ->endUse();
        $queryResult = $query
            ->condition('start', OrderTableMap::INVOICE_DATE . ' >= ?', $startDate->setTime(0, 0))
            ->condition('end', OrderTableMap::INVOICE_DATE . ' <= ?', $endDate->setTime(23, 59, 59))
            ->condition('product_ref', OrderProductTableMap::COL_PRODUCT_REF . '= ?', $productRef)
            ->where(array('start', 'end', 'product_ref'), Criteria::LOGICAL_AND)
            ->find();
        $result = [];
        /** @var OrderProduct $orderProduct */
        foreach ($queryResult as $orderProduct) {
            $pse = null;
            $title = null;
            $quantity = $orderProduct->getQuantity() > 1 ? ' x' . $orderProduct->getQuantity() : null;
            if ($pse = ProductSaleElementsQuery::create()->filterById($orderProduct->getProductSaleElementsId())->findOne()) {
                $combination = $pse->getAttributeCombinations()->toArray() ? $pse->getAttributeCombinations()->toArray()[0] : null;
                $attributeAv = $combination ? AttributeAvQuery::create()->filterById($combination['AttributeAvId'])->findOne()->setLocale($locale)->getTitle() . ' :' : null;
                $title = $attributeAv;
            }

            $result[$title][] = $orderProduct->getOrder()->getCreatedAt()->format('d/m/Y') . $quantity;
        }
        return $result;
    }

    /**
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return array
     * @throws PropelException
     */
    public function discountCode(DateTime $startDate, DateTime $endDate): array
    {
        return $this->discountCodeQuery($startDate, $endDate)->find()->toArray();
    }

    /**
     * @throws PropelException
     */
    public function meansTransport(DateTime $startDate, DateTime $endDate, string $local): array
    {
        return $this->meansTransportQuery($startDate, $endDate, $local)->find()->toArray();
    }

    /**
     * @throws PropelException
     */
    public function meansPayment(DateTime $startDate, DateTime $endDate, string $local)
    {
        return $this->meansPaymentQuery($startDate, $endDate, $local)->find()->toArray();

    }

    /**
     * @throws PropelException
     */
    public function turnover($year): array
    {
        return $this->turnoverQuery($year)->find()->toArray('date');
    }

    // -----------------
    // Query methods

    /**
     * @throws Exception
     */
    public function getMonthlySaleStats(DateTime $startDate, DateTime $endDate): array
    {
        /** @var DateTime $date */
        $result = StatsOrderQuery::getStatisticSaleStats(
            clone($startDate)->setTime(0, 0),
            clone($endDate)->setTime(23, 59, 59),
            (bool) Statistic::getConfigValue(Statistic::INCLUDE_SHIPPING)
        );

        return $result;
    }

    /**
     * @throws Exception
     * @throws PropelException
     */
    public static function getRevenueStats(DateTime $startDate, DateTime $endDate): array
    {
        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        $queryResult = StatsOrderQuery::getStatisticSaleStats(
            clone($startDate)->setTime(0, 0),
            clone($endDate)->setTime(23, 59, 59),
            (bool) Statistic::getConfigValue(Statistic::INCLUDE_SHIPPING)
        );

        for($day = 0, $date = clone($startDate); $date <= $endDate; $date->add(new DateInterval('P1D')), $day++) {
            $currentStat = 0;
            if (isset($queryResult[$date->format('Y-n-j')])){
                $currentStat = $queryResult[$date->format('Y-n-j')];
            }
            $result['stats'][] = [$day, $currentStat];
            $result['label'][] = [$day, $date->format('d/m')];
        }

        return $result;
    }

    /**
     * @throws PropelException
     */
    public static function getRevenueStatsByHours(DateTime $startDate): array
    {
        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        for ($hour = 0; $hour < 24; $hour++) {
            $dayAmount = OrderByHoursQuery::getStatisticSaleStats(
                clone ($startDate->setTime($hour, 0, 0)),
                clone($startDate->setTime($hour, 59, 59)),
                false
            );
            $result['stats'][] = array($hour, $dayAmount);
            $result['label'][] = array($hour, ($hour + 1) . 'h');
        }
        return $result;
    }

    /**
     * @throws Exception
     */
    public static function getOrdersStats(DateTime $startDate, DateTime $endDate): array
    {
        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        $status = explode(',', Statistic::getConfigValue('order_types'));

        for ($day = 0, $date = clone($startDate); $date <= $endDate; $date->add(new DateInterval('P1D')), $day++) {
            $dayAmount = OrderQuery::getOrderStats(
                $date->setTime(0, 0, 0),
                $date->setTime(23, 59, 59),
                $status
            );
            $key = explode('-', $date->format('Y-m-d'));
            $result['stats'][] = array($day, $dayAmount);
            $result['label'][] = array($day, $key[2] . '/' . $key[1]);
        }

        return $result;
    }

    public static function getOrdersStatsByHours(DateTime $startDate): array
    {
        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        $status = explode(',', Statistic::getConfigValue('order_types'));

        for ($hour = 0; $hour < 24; $hour++) {
            $dayAmount = OrderByHoursQuery::getOrdersStats(
                clone ($startDate->setTime($hour, 0, 0)),
                clone($startDate->setTime($hour, 59, 59)),
                $status
            );
            $result['stats'][] = array($hour, $dayAmount);
            $result['label'][] = array($hour, ($hour + 1) . 'h');
        }

        return $result;
    }

    /**
     * @throws PropelException
     */
    public function bestSalesQuery(DateTime $startDate, DateTime $endDate, ?string $productRef = null): OrderQuery
    {
        $query = OrderQuery::create()
            ->filterByInvoiceDate(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByInvoiceDate(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->filterByStatusId(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN)
            ->innerJoinOrderProduct()
            ->withColumn("SUM(" . OrderProductTableMap::COL_QUANTITY . ")", "total_sold")
            ->withColumn(
                "SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product`.PROMO_PRICE,`order_product`.PRICE)))",
                "total_ht"
            )
            ->useOrderProductQuery()
            ->useOrderProductTaxQuery()
            ->withColumn("SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product_tax`.PROMO_AMOUNT,`order_product_tax`.AMOUNT)))", 'tax')
            ->endUse()
            ->endUse()
            ->withColumn("SUM(`order`.discount)", 'discount');

        $query->groupBy(OrderProductTableMap::COL_PRODUCT_REF);

        if ($productRef) {
            $query
                ->useOrderProductQuery()
                ->filterByProductRef($productRef)
                ->endUse();
        }

        // selection des données
        $query
            ->addAsColumn('title', OrderProductTableMap::COL_TITLE)
            ->addAsColumn('product_ref', OrderProductTableMap::COL_PRODUCT_REF)
            ->addAsColumn('pse_ref', OrderProductTableMap::COL_PRODUCT_SALE_ELEMENTS_REF)
            ->addAsColumn('product_sale_elements_id', OrderProductTableMap::COL_PRODUCT_SALE_ELEMENTS_ID);
        $query->select(array(
            'title',
            'product_ref',
            'pse_ref',
            'total_sold',
            'total_ht',
            'tax',
            'discount',
            'postage',
            'product_sale_elements_id',
            OrderTableMap::COL_INVOICE_DATE,
            OrderTableMap::COL_ID,
        ));

        return $query;
    }

    /**
     * @throws PropelException
     */
    public function discountCodeQuery(DateTime $startDate, DateTime $endDate): CouponQuery
    {
        $query = CouponQuery::create();

        // Jointure sur order_coupon pour la date et le comptage
        $sql = "code
            AND
            order_coupon.created_at >= '%start'
            AND
            order_coupon.created_at <= '%end'";
        $sql = str_replace(
            array('%start', '%end'),
            array($startDate->format(self::START_DAY_FORMAT), $endDate->format(self::END_DAY_FORMAT)),
            $sql
        );
        $join = new Join();
        $join->addExplicitCondition('coupon', 'code', null, 'order_coupon', $sql);
        $join->setJoinType(Criteria::LEFT_JOIN);
        $query->addJoinObject($join);

        // Ajout du select
        $query
            ->addAsColumn('code', CouponTableMap::CODE)
            ->addAsColumn('type', CouponTableMap::TYPE)
            ->addAsColumn('rule', CouponTableMap::SERIALIZED_EFFECTS)
            ->addAsColumn('total', "COUNT(" . OrderCouponTableMap::CODE . ")");
        $query->groupBy(CouponTableMap::CODE)->orderBy('total', Criteria::DESC);
        $query->select(array(
            'code',
            'type',
            'rule',
            'total'
        ));

        return $query;
    }

    /**
     * @throws PropelException
     */
    public function meansTransportQuery(DateTime $startDate, DateTime $endDate, string $local): OrderQuery
    {
        $query = OrderQuery::create();

        // filter with status
        $query->useOrderStatusQuery()
            ->filterById(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN)
            ->endUse();

        // filtrage sur la date
        $query
            ->condition('start', OrderTableMap::CREATED_AT . ' >= ?', $startDate->setTime(0, 0))
            ->condition('end', OrderTableMap::CREATED_AT . ' <= ?', $endDate->setTime(23, 59, 59))
            ->where(array('start', 'end'), Criteria::LOGICAL_AND);

        // Jointure sur les modules de transport
        $query->useModuleRelatedByDeliveryModuleIdQuery()
            ->useI18nQuery($local)
            ->endUse()
            ->endUse();

        // select
        $query
            ->addAsColumn('code', ModuleTableMap::CODE)
            ->addAsColumn('title', ModuleI18nTableMap::TITLE)
            ->addAsColumn('total', 'COUNT(' . ModuleTableMap::CODE . ')');

        $query->groupBy('code');
        $query->select(array(
            'code',
            'title',
            'total'
        ));

        return $query;
    }

    /**
     * @throws PropelException
     */
    public function meansPaymentQuery(DateTime $startDate, DateTime $endDate, string $local): OrderQuery
    {
        $query = OrderQuery::create();

        // filter with status
        $query->useOrderStatusQuery()
            ->filterById(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN)
            ->endUse();

        // filtrage sur la date
        $query
            ->condition('start', OrderTableMap::CREATED_AT . ' >= ?', $startDate->setTime(0, 0))
            ->condition('end', OrderTableMap::CREATED_AT . ' <= ?', $endDate->setTime(23, 59, 59))
            ->where(array('start', 'end'), Criteria::LOGICAL_AND);

        // Jointure sur le module de payement
        $query
            ->useModuleRelatedByPaymentModuleIdQuery()
            ->useI18nQuery($local)
            ->endUse()
            ->endUse();

        // select
        $query
            ->addAsColumn('code', ModuleTableMap::CODE)
            ->addAsColumn('title', ModuleI18nTableMap::TITLE)
            ->addAsColumn('total', 'COUNT(' . ModuleTableMap::CODE . ')');

        $query->groupBy('code');
        $query->select(array(
            'code',
            'title',
            'total'
        ));

        return $query;
    }

    /**
     * @throws PropelException
     */
    public function turnoverQuery(string $year): OrderQuery
    {
        $query = OrderQuery::create();

        // filtrage sur la date
        $query
            ->filterByStatusId(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN)
            ->where('YEAR(order.invoice_date) = ?', $year, PDO::PARAM_STR);

        // jointure sur l'order product
        $orderTaxJoin = new Join();
        $orderTaxJoin->addExplicitCondition(
            OrderProductTableMap::TABLE_NAME,
            'ID',
            null,
            OrderProductTaxTableMap::TABLE_NAME,
            'ORDER_PRODUCT_ID',
            null
        );
        $orderTaxJoin->setJoinType(Criteria::LEFT_JOIN);
        $query
            ->innerJoinOrderProduct()
            ->addJoinObject($orderTaxJoin);


        // group by par mois
        $query->addGroupByColumn('YEAR(order.invoice_date)');
        $query->addGroupByColumn('MONTH(order.invoice_date)');


        // ajout des colonnes de compte
        $query
            ->withColumn(
                "SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product`.PROMO_PRICE,`order_product`.PRICE)))",
                'TOTAL'
            )
            ->withColumn(
                "SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product_tax`.PROMO_AMOUNT,`order_product_tax`.AMOUNT)))",
                'TAX'
            )
            ->addAsColumn('date', "CONCAT(YEAR(order.invoice_date),'-',MONTH(order.invoice_date))");


        $query->select(array(
            'date',
            'TOTAL',
            'TAX',
        ));

        return $query;
    }

    /**
     * @throws Exception
     * @throws PropelException
     */
    public function getTurnoverYear(string $year): array
    {
        $result = $this->turnover($year);

        $table = array();
        $graph = array();
        $month = array();
        for ($i = 1; $i <= 12; ++$i) {
            $date = new DateTime($year . '-' . $i);
            if (!isset($result[$date->format('Y-n')])) {
                $table[$i] = array(
                    'TTCWithShippping' => 0,
                    'TTCWithoutShippping' => 0
                );
                $graph[] = array(
                    $i - 1,
                    0
                );
            } else {
                $tmp = $result[$date->format('Y-n')];

                //Get first day of month
                $startDate = new DateTime($year . '-' . $i . '-01');
                /** @var DateTime $endDate */

                //Get last day of month (first + total of month day -1)
                $endDate = clone($startDate);
                $endDate->add(new DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $i, $year) - 1) . 'D'));

                $discount = OrderQuery::create()
                    ->filterByInvoiceDate(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
                    ->filterByInvoiceDate(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
                    ->filterByStatusId(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN)
                    ->withColumn("SUM(`order`.discount)", 'DISCOUNT')
                    ->select('DISCOUNT')->findOne();

                $postage = OrderQuery::create()
                    ->filterByInvoiceDate(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
                    ->filterByInvoiceDate(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
                    ->filterByStatusId(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN)
                    ->withColumn("SUM(`order`.postage)", 'POSTAGE')
                    ->select('POSTAGE')->findOne();

                if (null === $discount) {
                    $discount = 0;
                }

                // We want the HT turnover instead of TTC
                $table[$i] = array(
                    'TTCWithShippping' => round($tmp['TOTAL'] + $postage - $discount, 2), //round($tmp['TOTAL'] + $tmp['TAX'] + $postage - $discount, 2),
                    'TTCWithoutShippping' => round($tmp['TOTAL'] - $discount, 2) //round($tmp['TOTAL'] + $tmp['TAX'] - $discount, 2)
                );
                $graph[] = array(
                    $i - 1,
                    // We just want the HT turnover here
                    (int)($tmp['TOTAL'] - $discount) //intval($tmp['TOTAL']+$tmp['TAX'] - $discount)
                );
            }
            $month[] = array($i - 1, $date->format('M'));
            $table[$i]['month'] = $date->format('M');
        }
        $result['graph'] = $graph;
        $result['month'] = $month;
        $result['table'] = $table;
        return $result;
    }
}
