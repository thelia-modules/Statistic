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


class StatsOrderQuery extends OrderQuery
{
    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param bool $includeShipping
     * @param bool $withTaxes
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getSaleStats(\DateTime $startDate, \DateTime $endDate, $includeShipping, $withTaxes = true)
    {
        $orderTaxJoin = new Join();
        $orderTaxJoin->addExplicitCondition(OrderProductTableMap::TABLE_NAME, 'ID', null, OrderProductTaxTableMap::TABLE_NAME, 'ORDER_PRODUCT_ID', null);
        $orderTaxJoin->setJoinType(Criteria::LEFT_JOIN);
        $query = self::baseSaleStats($startDate, $endDate, 'o')
            ->innerJoinOrderProduct()
            ->addJoinObject($orderTaxJoin)
            ->withColumn( 'CONCAT(YEAR(`order`.`invoice_date`),"-",MONTH(`order`.`invoice_date`),"-",DAY(`order`.`invoice_date`))', 'DATE')
            ->withColumn("SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product`.PROMO_PRICE,`order_product`.PRICE)))", 'TOTAL')
            ->withColumn("SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product_tax`.PROMO_AMOUNT,`order_product_tax`.AMOUNT)))", 'TAX')
            ->withColumn("SUM(`order`.discount)", 'DISCOUNT')
            ->withColumn("SUM(`order`.postage)", 'POSTAGE')
            ->groupBy('DATE')
            ->select(['TOTAL', 'TAX', 'DATE', 'DISCOUNT', 'POSTAGE']);
        $arrayResults = $query->find();

        $results = [];
        foreach ($arrayResults as $arrayAmount){
            $amount = $arrayAmount['TOTAL'] + $arrayAmount['TAX'] - $arrayAmount['DISCOUNT'] ? : 0;

            if (null === $amount) {
                $amount = 0;
            }

            if ($includeShipping) {
                $amount += $arrayAmount['POSTAGE'];
            }

            $results[$arrayAmount['DATE']] = round($amount, 2);
        }

        return $results;
    }

    public static function getOrderNumber(\DateTime $startDate, \DateTime $endDate)
    {
        $query = self::baseSaleStats($startDate, $endDate, 'o')
            ->withColumn('COUNT(DISTINCT id)', 'TOTAL')
            ->withColumn( 'CONCAT(YEAR(`order`.`invoice_date`),"-",MONTH(`order`.`invoice_date`),"-",DAY(`order`.`invoice_date`))', 'DATE')
            ->groupBy('DATE')
            ->select(['TOTAL', 'DATE']);
        $arrayResults = $query->find();

        $results = [];
        foreach ($arrayResults as $arrayStat){
            $results[$arrayStat['DATE']] = $arrayStat['TOTAL'];
        }

        return $results;
    }

    protected static function baseSaleStats(\DateTime $startDate, \DateTime $endDate, $modelAlias = null)
    {
        $status = explode(',', Statistic::getConfigValue('order_types'));
        return self::create($modelAlias)
            ->filterByInvoiceDate(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByInvoiceDate(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->filterByStatusId($status, Criteria::IN);
    }
}