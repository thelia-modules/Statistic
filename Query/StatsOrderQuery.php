<?php

namespace Statistic\Query;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Statistic\Statistic;
use Thelia\Model\OrderQuery;


class StatsOrderQuery extends OrderQuery
{
    /**
     * Daily revenue, one entry per day that carries an order.
     *
     * Each order is totalled on its own, by OrderAmountSql, and the day is the sum
     * of its orders. Totalling the joined order lines instead, as this used to do,
     * both applied one rounding rule to every order and counted `order`.`discount`
     * and `order`.`postage` once per joined line rather than once per order.
     *
     * @throws PropelException
     */
    public static function getStatisticSaleStats(\DateTime $startDate, \DateTime $endDate, bool $includeShipping): array
    {
        $arrayResults = self::baseSaleStats($startDate, $endDate, 'o')
            ->withColumn( 'CONCAT(YEAR(`order`.`invoice_date`),"-",MONTH(`order`.`invoice_date`),"-",DAY(`order`.`invoice_date`))', 'DATE')
            ->withColumn('SUM('.OrderAmountSql::orderTotal($includeShipping).')', 'AMOUNT')
            ->groupBy('DATE')
            ->select(['AMOUNT', 'DATE'])
            ->find();

        $results = [];
        foreach ($arrayResults as $arrayAmount){
            $results[$arrayAmount['DATE']] = round((float) $arrayAmount['AMOUNT'], 2);
        }

        return $results;
    }

    public static function getOrderNumber(\DateTime $startDate, \DateTime $endDate): array
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

    protected static function baseSaleStats(\DateTime $startDate, \DateTime $endDate, $modelAlias = null): OrderQuery
    {
        $status = explode(',', Statistic::getConfigValue('order_types'));
        return self::create($modelAlias)
            ->filterByInvoiceDate(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByInvoiceDate(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->filterByStatusId($status, Criteria::IN);
    }
}