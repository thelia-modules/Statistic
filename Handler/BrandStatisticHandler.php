<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 23/07/2020
 * Time: 09:27
 */

namespace Statistic\Handler;


use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Statistic\Statistic;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\OrderQuery;

class BrandStatisticHandler
{

    /**
     * @param $brandId
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param bool $count
     * @return array
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getBrandTurnover($brandId, \DateTime $startDate, \DateTime $endDate, $count = false)
    {
        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        $query = $this->brandTurnoverQuery($brandId, $startDate, $endDate, $count);
        $queryResult = $query->find()->toArray('date');

        for ($day=0, $date = clone($startDate); $date <= $endDate; $date->add(new \DateInterval('P1D')), $day++) {
            array_push($result['stats'], array($day, isset($queryResult[$date->format('Y-n-j')]) ? floatval($queryResult[$date->format('Y-n-j')]['TOTAL']) : 0));
            array_push($result['label'], array($date->format('d/m')));
        }

        return $result;
    }

    /**
     * @param $brandId
     * @param \DateTime $startDate
     * @param bool $count
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getBrandTurnoverByHours($brandId, \DateTime $startDate, $count = false)
    {
        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        for ($hour = 0; $hour < 24; $hour++) {
            $val = $this->brandTurnoverByHourQuery(
                $brandId,
                clone ($startDate->setTime($hour,0,0)),
                clone($startDate->setTime($hour,59,59)),
                $count
            );
            array_push($result['stats'], array($hour, $val? floatval($val) : 0));
            array_push($result['label'], array(($hour+1) . 'h'));
        }

        return $result;
    }

    /**
     * @param $brandId
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param bool $count
     * @return OrderQuery
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function brandTurnoverQuery($brandId, \DateTime $startDate, \DateTime $endDate, $count = false)
    {
        $query = OrderQuery::create();

        $query
            ->filterByInvoiceDate(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByInvoiceDate(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->filterByStatusId(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN);

        $orderProductJoin = new Join();
        $orderProductJoin->addExplicitCondition(
            OrderProductTableMap::TABLE_NAME,
            'PRODUCT_REF',
            null,
            ProductTableMap::TABLE_NAME,
            'REF',
            null
        );

        $orderProductJoin->setJoinType(Criteria::INNER_JOIN);
        $query
           ->innerJoinOrderProduct()
           ->addJoinObject($orderProductJoin);

        $query->where('product.brand_id = ?', $brandId, \PDO::PARAM_STR);

        $query->addGroupByColumn('DAY(order.invoice_date)');

        if ($count){
            $query
                ->withColumn(
                    "SUM(order_product.quantity)",
                    'TOTAL'
                );
        }else{
            $query->withColumn(
                    "SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product`.PROMO_PRICE,`order_product`.PRICE)))",
                    'TOTAL'
            );
        }

        $query->addAsColumn('date', "CONCAT(YEAR(order.invoice_date),'-',MONTH(order.invoice_date),'-',DAY(order.invoice_date))");

        $query->select([
            'date',
            'TOTAL'
        ]);

        return $query;
    }

    /**
     * @param $brandId
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param bool $count
     * @return \Thelia\Model\Order
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function brandTurnoverByHourQuery ($brandId, \DateTime $startDate, \DateTime $endDate, $count = false)
    {
        $query = OrderQuery::create();

        $query
            ->filterByStatusId(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN)
            ->filterByCreatedAt($startDate->format('Y-m-d H:i:s'), Criteria::GREATER_EQUAL)
            ->filterByCreatedAt($endDate->format('Y-m-d H:i:s'), Criteria::LESS_EQUAL);

        $orderProductJoin = new Join();
        $orderProductJoin->addExplicitCondition(
            OrderProductTableMap::TABLE_NAME,
            'PRODUCT_REF',
            null,
            ProductTableMap::TABLE_NAME,
            'REF',
            null
        );

        $orderProductJoin->setJoinType(Criteria::INNER_JOIN);
        $query
            ->innerJoinOrderProduct()
            ->addJoinObject($orderProductJoin);

        $query->where('product.brand_id = ?', $brandId, \PDO::PARAM_STR);

        if ($count){
            $query
                ->withColumn(
                    "SUM(order_product.quantity)",
                    'TOTAL'
                );
        }else{
            $query->withColumn(
                "SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product`.PROMO_PRICE,`order_product`.PRICE)))",
                'TOTAL'
            );
        }
        $query->select(['TOTAL']);

        return $query->findOne();
    }

    protected function brandSaledQuery($brandId, \DateTime $startDate, \DateTime $endDate)
    {
        $query = OrderQuery::create();

        $query
            ->filterByInvoiceDate(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByInvoiceDate(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->filterByStatusId(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN);

        $orderProductJoin = new Join();
        $orderProductJoin->addExplicitCondition(
            OrderProductTableMap::TABLE_NAME,
            'PRODUCT_REF',
            null,
            ProductTableMap::TABLE_NAME,
            'REF',
            null
        );

        $orderProductJoin->setJoinType(Criteria::INNER_JOIN);
        $query
            ->innerJoinOrderProduct()
            ->addJoinObject($orderProductJoin);

        $query->where('product.brand_id = ?', $brandId, \PDO::PARAM_STR);

        $query->addGroupByColumn('DAY(order.invoice_date)');

        $query
            ->withColumn(
                "SUM(order_product.quantity)",
                'TOTAL'
            )
            ->addAsColumn('date', "CONCAT(YEAR(order.invoice_date),'-',MONTH(order.invoice_date),'-',DAY(order.invoice_date))");

        $query->select([
            'date',
            'TOTAL'
        ]);

        return $query;
    }

}