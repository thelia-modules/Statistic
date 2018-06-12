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
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\CountryQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Map\CouponTableMap;
use Thelia\Model\Map\ModuleI18nTableMap;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\Map\OrderCouponTableMap;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;

/**
 * Class StatisticHandler
 * @package Statistic\Handler
 * @author David Gros <dgros@openstudio.fr>
 */
class StatisticHandler
{
    const START_DAY_FORMAT = 'Y-m-d 00:00:00';
    const END_DAY_FORMAT = 'Y-m-d 23:59:59';
    const ALLOWED_STATUS = "not_paid,paid,processing,sent";

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     * @throws \Exception
     */
    public function averageCart(\DateTime $startDate, \DateTime $endDate)
    {
        $po = $this->getMonthlySaleStats($startDate, $endDate);
        $order = $this->getMonthlyOrdersStats($startDate, $endDate);

        $result = array();

        foreach ($po as $date => $gold) {
            $key = explode('-', $date);
            $result[$key[2] - 1] = array(
                $key[2] - 1,
                $gold && isset($order[$date]) ? $gold / $order[$date] : 0
            );
        }

        return $result;
    }

    /**
     * @param Request $request
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function bestSales(Request $request, \DateTime $startDate, \DateTime $endDate)
    {
        $query = $this->bestSalesQuery($startDate, $endDate);
        $result = $query->find()->toArray();

        $calc = new Calculator();
        $countries = array();

        foreach ($result as &$pse) {
            $country = isset($countries[$pse['country']])
                ? $countries[$pse['country']]
                : $countries[$pse['country']] = CountryQuery::create()->findOneById($pse['country']);

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
                $calc->load($product, $country);
                $totalHt = $pse['total_ht'];

                $pse['total_ht'] = MoneyFormat::getInstance($request)->formatByCurrency($totalHt);
                $pse['total_ttc'] = MoneyFormat::getInstance($request)->formatByCurrency($calc->getTaxedPrice($totalHt));
            }
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
        $query = $this->discountCodeQuery($startDate, $endDate);

        $result = $query->find()->toArray();

        return $result;

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
        $query = $this->meansTransportQuery($startDate, $endDate, $local);

        $result = $query->find()->toArray();

        return $result;
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
        $query = $this->meansPaymentQuery($startDate, $endDate, $local);

        $result = $query->find()->toArray();

        return $result;
    }

    /**
     * @param $year
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function turnover($year)
    {
        $query = $this->turnoverQuery($year);

        $result = $query->find()->toArray('date');

        return $result;
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
     * @param int $limit
     * @return \Thelia\Model\OrderProductQuery
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function bestSalesQuery(\DateTime $startDate, \DateTime $endDate, $limit = 20)
    {
        /** @var \Thelia\Model\OrderProductQuery $query */
        $query = OrderProductQuery::create()
            ->limit($limit)
            ->withColumn("SUM(" . OrderProductTableMap::QUANTITY . ")", "total_sold")
            ->withColumn(
                "SUM( IF(" . OrderProductTableMap::WAS_IN_PROMO . ',' . OrderProductTableMap::PROMO_PRICE . ',' . OrderProductTableMap::PRICE . ") * " . OrderProductTableMap::QUANTITY . ")",
                "total_ht"
            )
            ->addDescendingOrderByColumn("total_sold");

        $query->groupBy(OrderProductTableMap::PRODUCT_SALE_ELEMENTS_REF);

        // jointure de l'address de livraison pour le pays
        $query
            ->useOrderQuery()
            ->useOrderAddressRelatedByDeliveryOrderAddressIdQuery()
            ->endUse()
            ->endUse()
        ;

        // filter with status
        $query
            ->useOrderQuery()
            ->useOrderStatusQuery()
            ->filterByCode(explode(",", self::ALLOWED_STATUS), Criteria::IN)
            ->endUse()
            ->endUse();

        // filtrage sur la date
        $query
            ->condition('start', OrderProductTableMap::CREATED_AT . ' >= ?', $startDate->setTime(0, 0))
            ->condition('end', OrderProductTableMap::CREATED_AT . ' <= ?', $endDate->setTime(23, 59, 59))
            ->where(array('start', 'end'), Criteria::LOGICAL_AND);

        // selection des données
        $query
            ->addAsColumn('title', OrderProductTableMap::TITLE)
            ->addAsColumn('product_ref', OrderProductTableMap::PRODUCT_REF)
            ->addAsColumn('pse_ref', OrderProductTableMap::PRODUCT_SALE_ELEMENTS_REF)
            ->addAsColumn('country', OrderAddressTableMap::COUNTRY_ID);
        $query->select(array(
            'title',
            'product_sale_elements_id',
            'product_ref',
            'pse_ref',
            'total_sold',
            'total_ht',
            'country'
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
            ->addAsColumn('total', "COUNT(".OrderCouponTableMap::CODE.")");
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
            ->filterByCode(explode(",", self::ALLOWED_STATUS), Criteria::IN)
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
            ->filterByCode(explode(",", self::ALLOWED_STATUS), Criteria::IN)
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
            ->filterByStatusId([2,3,4], Criteria::IN)
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
}
