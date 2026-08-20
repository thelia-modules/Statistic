<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 16/07/2019
 * Time: 10:36
 */

namespace Statistic\Query;

use Propel\Runtime\ActiveQuery\Criteria;
use Statistic\Statistic;
use Thelia\Model\Base\OrderQuery as BaseOrderQuery;

class OrderByHoursQuery extends BaseOrderQuery
{

    /**
     * Revenue over one slice of a day.
     *
     * Each order is totalled on its own, by OrderAmountSql, so the slice reads the
     * amounts the customers were charged. Summing the joined order lines instead, as
     * this used to do, applied one rounding rule to every order and counted a line
     * once per tax row attached to it.
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param $includeShipping
     * @return float|int
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getStatisticSaleStats(\DateTime $startDate, \DateTime $endDate, $includeShipping)
    {
        $amount = self::baseSaleStats($startDate, $endDate, 'o')
            ->withColumn('SUM('.OrderAmountSql::orderTotal((bool) $includeShipping).')', 'AMOUNT')
            ->select('AMOUNT')
            ->findOne();

        return round((float) $amount, 2);
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