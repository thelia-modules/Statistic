<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 18/07/2019
 * Time: 10:13
 */

namespace Statistic\Query;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Statistic\Statistic;
use Thelia\Model\OrderQuery;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Map\OrderTableMap;


class StatsOrderQuery extends OrderQuery
{
    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param $includeShipping
     * @param bool $withTaxes
     * @return float|int
     */
    public static function getSaleStats(\DateTime $startDate, \DateTime $endDate, $includeShipping, $withTaxes = true)
    {
        $orderTaxJoin = new Join();
        $orderTaxJoin->addExplicitCondition(OrderProductTableMap::TABLE_NAME, 'ID', null, OrderProductTaxTableMap::TABLE_NAME, 'ORDER_PRODUCT_ID', null);
        $orderTaxJoin->setJoinType(Criteria::LEFT_JOIN);
        $query = self::baseSaleStats($startDate, $endDate, 'o')
            ->innerJoinOrderProduct()
            ->addJoinObject($orderTaxJoin)
            ->withColumn("SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product`.PROMO_PRICE,`order_product`.PRICE)))", 'TOTAL')
            ->withColumn("SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product_tax`.PROMO_AMOUNT,`order_product_tax`.AMOUNT)))", 'TAX')
            ->select(['TOTAL', 'TAX'])
        ;
        $arrayAmount = $query->findOne();

        $amount = $arrayAmount['TOTAL'] + $arrayAmount['TAX'];

        if (null === $amount) {
            $amount = 0;
        }

        $discountQuery = self::baseSaleStats($startDate, $endDate)
            ->withColumn("SUM(`order`.discount)", 'DISCOUNT')
            ->select('DISCOUNT')
        ;

        $discount = $discountQuery->findOne();

        if (null === $discount) {
            $discount = 0;
        }

        $amount = $amount - $discount;

        if ($includeShipping) {
            $query = self::baseSaleStats($startDate, $endDate)
                ->withColumn("SUM(`order`.postage)", 'POSTAGE')
                ->select('POSTAGE')
            ;

            $amount += $query->findOne();
        }

        return null === $amount ? 0 : round($amount, 2);
    }

    protected static function baseSaleStats(\DateTime $startDate, \DateTime $endDate, $modelAlias = null)
    {
        $status = explode(',',Statistic::getConfigValue('order_types'));
        return self::create($modelAlias)
            ->filterByCreatedAt(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByCreatedAt(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->filterByStatusId($status, Criteria::IN);
    }
}