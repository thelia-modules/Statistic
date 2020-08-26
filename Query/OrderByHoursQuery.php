<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 16/07/2019
 * Time: 10:36
 */

namespace Statistic\Query;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Statistic\Statistic;
use Thelia\Model\Base\OrderQuery as BaseOrderQuery;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;

class OrderByHoursQuery extends BaseOrderQuery
{

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param $includeShipping
     * @return float|int
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getSaleStats(\DateTime $startDate, \DateTime $endDate, $includeShipping)
    {
        $orderTaxJoin = new Join();
        $orderTaxJoin->addExplicitCondition(OrderProductTableMap::TABLE_NAME, 'ID', null, OrderProductTaxTableMap::TABLE_NAME, 'ORDER_PRODUCT_ID', null);
        $orderTaxJoin->setJoinType(Criteria::LEFT_JOIN);
        $query = self::baseSaleStats($startDate, $endDate, 'o')
            ->innerJoinOrderProduct()
            ->addJoinObject($orderTaxJoin)
            ->withColumn("SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product`.PROMO_PRICE,`order_product`.PRICE)))", 'TOTAL')
            ->withColumn("SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product_tax`.PROMO_AMOUNT,`order_product_tax`.AMOUNT)))", 'TAX')
            ->select(['TOTAL', 'TAX']);
        $arrayAmount = $query->findOne();

        $amount = $arrayAmount['TOTAL'] + $arrayAmount['TAX'];

        if (null === $amount) {
            $amount = 0;
        }

        $discountQuery = self::baseSaleStats($startDate, $endDate)
            ->withColumn("SUM(`order`.discount)", 'DISCOUNT')
            ->select('DISCOUNT');

        $discount = $discountQuery->findOne();

        if (null === $discount) {
            $discount = 0;
        }

        $amount -= $discount;

        if ($includeShipping) {
            $query = self::baseSaleStats($startDate, $endDate)
                ->withColumn("SUM(`order`.postage)", 'POSTAGE')
                ->select('POSTAGE');

            $amount += $query->findOne();
        }

        return null === $amount ? 0 : round($amount, 2);
    }

    protected static function baseSaleStats(\DateTime $startDate, \DateTime $endDate, $modelAlias = null)
    {
        return self::create($modelAlias)
            ->filterByInvoiceDate($startDate->format('Y-m-d H:i:s'), Criteria::GREATER_EQUAL)
            ->filterByInvoiceDate($endDate->format('Y-m-d H:i:s'), Criteria::LESS_EQUAL)
            ->filterByStatusId(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN);
    }

    public static function getOrdersStats(\DateTime $startDate, \DateTime $endDate, $status = array(1, 2, 3, 4))
    {
        return self::create()
            ->filterByStatusId($status, Criteria::IN)
            ->filterByInvoiceDate($startDate->format('Y-m-d H:i:s'), Criteria::GREATER_EQUAL)
            ->filterByInvoiceDate($endDate->format('Y-m-d H:i:s'), Criteria::LESS_EQUAL)
            ->count();
    }

}