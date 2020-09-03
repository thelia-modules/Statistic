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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Propel;
use Statistic\Query\OrderByHoursQuery;
use Statistic\Statistic;
use Thelia\Core\HttpFoundation\Request;
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
use Thelia\Model\ModuleConfigI18nQuery;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
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
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     * @throws \Exception
     */
    public function averageCart(\DateTime $startDate, \DateTime $endDate)
    {
        $po = $this->getMonthlySaleStats($startDate, $endDate);
        $order = self::getMonthlyOrdersStats($startDate, $endDate);

        $result = array();
        $result['stats'] = array();
        $result['label'] = array();
        $i = 0;
        foreach ($po as $date => $gold) {
            $key = explode('-', $date);
            $result['stats'][] = array($i, $gold && isset($order[$date]) ? $gold / $order[$date] : 0);
            $result['label'][] = array($i, $key[2] . '/' . $key[1]);
            $i++;
        }

        return $result;
    }

    /**
     * @param Request $request
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param $locale
     * @param null $productRef
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function bestSales(Request $request, \DateTime $startDate, \DateTime $endDate, $locale, $productRef = null)
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
                    ->findOneByRef($pse['product_ref']);
            }

            if (null !== $product) {
                $totalHt = $pse['total_ht'] ?: 0;

                $pse['brand_id'] = $product->getBrandId();
                $pse['brand_title'] = null;
                if ($brand = $product->getBrand()) {
                    $pse['brand_title'] = $brand->setLocale($locale)->getTitle();
                }
                $pse['product_id'] = $product->getId();
                $pse['total_ttc'] = $totalHt + $pse['tax'] - $pse['discount'];
            }

            $result[$pse['product_ref']] = $pse;
        }

        return $result;
    }

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param $productId
     * @param $locale
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function productDetails(\DateTime $startDate, \DateTime $endDate, $productId, $locale)
    {
        $product = ProductQuery::create()->findOneById($productId);
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
            if ($pseId = $orderProduct->getProductSaleElementsId()) {
                $pse = ProductSaleElementsQuery::create()->findOneById($pseId);
                $combination = $pse->getAttributeCombinations()->toArray() ? $pse->getAttributeCombinations()->toArray()[0] : null;
                $attributeAv = $combination ? AttributeAvQuery::create()->findOneById($combination['AttributeAvId'])->setLocale($locale)->getTitle() . ' :' : null;
                $title = $attributeAv;
            }

            $result[$title][] = $orderProduct->getOrder()->getCreatedAt()->format('d/m/Y') . $quantity;
        }
        return $result;
    }

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function discountCode(\DateTime $startDate, \DateTime $endDate)
    {
        return $this->discountCodeQuery($startDate, $endDate)->find()->toArray();
    }

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param $local
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function meansTransport(\DateTime $startDate, \DateTime $endDate, $local)
    {
        return $this->meansTransportQuery($startDate, $endDate, $local)->find()->toArray();
    }

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param $local
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function meansPayment(\DateTime $startDate, \DateTime $endDate, $local)
    {
        return $this->meansPaymentQuery($startDate, $endDate, $local)->find()->toArray();

    }

    /**
     * @param $year
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function turnover($year)
    {
        return $this->turnoverQuery($year)->find()->toArray('date');
    }

    // -----------------
    // Query methods

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     * @throws \Exception
     */
    public function getMonthlySaleStats(\DateTime $startDate, \DateTime $endDate)
    {
        $result = array();
        /** @var \DateTime $date */
        for ($date = clone($startDate); $date <= $endDate; $date->add(new \DateInterval('P1D'))) {
            $result[$date->format('Y-m-d')] = OrderQuery::getSaleStats(
                $date->setTime(0, 0),
                $date->setTime(23, 59, 59),
                false
            );
        }

        return $result;
    }

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getRevenueStats(\DateTime $startDate, \DateTime $endDate)
    {

        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        for ($day = 0, $date = clone($startDate); $date <= $endDate; $date->add(new \DateInterval('P1D')), $day++) {
            $dayAmount = OrderQuery::getSaleStats(
                $date->setTime(0, 0, 0),
                $date->setTime(23, 59, 59),
                false
            );
            $key = explode('-', $date->format('Y-m-d'));
            $result['stats'][] = array($day, $dayAmount);
            $result['label'][] = array($day, $key[2] . '/' . $key[1]);
        }

        return $result;
    }

    /**
     * @param \DateTime $startDate
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getRevenueStatsByHours(\DateTime $startDate)
    {
        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        for ($hour = 0; $hour < 24; $hour++) {
            $dayAmount = OrderByHoursQuery::getSaleStats(
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
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     * @throws \Exception
     */
    public static function getOrdersStats(\DateTime $startDate, \DateTime $endDate)
    {

        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        $statModuleId = ModuleQuery::create()->findOneByCode('Statistic')->getId();
        $statConfig = ModuleConfigQuery::create()->filterByModuleId($statModuleId)->findOneByName('order_types')->getId();
        $status = explode(',', ModuleConfigI18nQuery::create()->findOneById($statConfig));

        for ($day = 0, $date = clone($startDate); $date <= $endDate; $date->add(new \DateInterval('P1D')), $day++) {
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

    /**
     * @param \DateTime $startDate
     * @return array
     */
    public static function getOrdersStatsByHours(\DateTime $startDate)
    {
        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        $statModuleId = ModuleQuery::create()->findOneByCode('Statistic')->getId();
        $statConfig = ModuleConfigQuery::create()->filterByModuleId($statModuleId)->findOneByName('order_types')->getId();
        $status = explode(',', ModuleConfigI18nQuery::create()->findOneById($statConfig));


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
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     */
    public static function getMonthlyOrdersStats(\DateTime $startDate, \DateTime $endDate)
    {
        $sql = "
            SELECT
            DATE(created_at) `date`,
            COUNT(DISTINCT id) total
            FROM `order`
            WHERE created_at >= '%startDate'
            AND
            created_at <= '%endDate'
            GROUP BY Date(created_at)
        ";

        $sql = str_replace(
            array('%startDate', '%endDate'),
            array($startDate->format(self::START_DAY_FORMAT), $endDate->format(self::END_DAY_FORMAT)),
            $sql
        );

        /** @var \Propel\Runtime\Connection\ConnectionWrapper $con */
        $con = Propel::getConnection(OrderTableMap::DATABASE_NAME);
        /** @var \Propel\Runtime\Connection\StatementWrapper $query */
        $query = $con->prepare($sql);
        $query->execute();

        return $query->fetchAll(\PDO::FETCH_COLUMN | \PDO::FETCH_UNIQUE);
    }

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param null $productRef
     * @return OrderQuery
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function bestSalesQuery(\DateTime $startDate, \DateTime $endDate, $productRef = null)
    {
        /** @var \Thelia\Model\OrderQuery $query */
        $query = OrderQuery::create()
            ->filterByInvoiceDate(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByInvoiceDate(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->filterByStatusId(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN)
            ->innerJoinOrderProduct()
            ->withColumn("SUM(" . OrderProductTableMap::QUANTITY . ")", "total_sold")
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

        $query->groupBy(OrderProductTableMap::PRODUCT_REF);

        if ($productRef) {
            $query
                ->useOrderProductQuery()
                ->filterByProductRef($productRef)
                ->endUse();
        }

        // selection des données
        $query
            ->addAsColumn('title', OrderProductTableMap::TITLE)
            ->addAsColumn('product_ref', OrderProductTableMap::PRODUCT_REF)
            ->addAsColumn('pse_ref', OrderProductTableMap::PRODUCT_SALE_ELEMENTS_REF)
            ->addAsColumn('product_sale_elements_id', OrderProductTableMap::PRODUCT_SALE_ELEMENTS_ID);
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
        ));

        return $query;
    }

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return CouponQuery
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function discountCodeQuery(\DateTime $startDate, \DateTime $endDate)
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
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param $local
     * @return OrderQuery
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function meansTransportQuery(\DateTime $startDate, \DateTime $endDate, $local)
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
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param $local
     * @return OrderQuery
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function meansPaymentQuery(\DateTime $startDate, \DateTime $endDate, $local)
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
     * @param $year
     * @return OrderQuery
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function turnoverQuery($year)
    {
        $query = OrderQuery::create();

        // filtrage sur la date
        $query
            ->filterByStatusId(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN)
            ->where('YEAR(order.invoice_date) = ?', $year, \PDO::PARAM_STR);

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
     * @param $year
     * @return array
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTurnoverYear($year)
    {

        $result = $this->turnover($year);

        $table = array();
        $graph = array();
        $month = array();
        for ($i = 1; $i <= 12; ++$i) {
            $date = new \DateTime($year . '-' . $i);
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
                $startDate = new \DateTime($year . '-' . $i . '-01');
                /** @var \DateTime $endDate */

                //Get last day of month (first + total of month day -1)
                $endDate = clone($startDate);
                $endDate->add(new \DateInterval('P' . (cal_days_in_month(CAL_GREGORIAN, $i, $year) - 1) . 'D'));

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
