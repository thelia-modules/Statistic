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

use PDO;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Statistic\Statistic;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;


/**
 * Class ProductStatisticHandler
 * @package Statistic\Handler
 * @author David Gros <dgros@openstudio.fr>
 */
class ProductStatisticHandler
{
    public function __construct(protected StatisticHandler $statisticHandler)
    {
    }

    /**
     * @param $productId
     * @param $year
     * @return array
     * @throws PropelException
     */
    public function turnover($productId, $year): array
    {
        return $this->turnoverQuery($productId, $year)->find()->toArray('date');
    }

    /**
     * @param $productRef
     * @param $year
     * @return array
     * @throws PropelException
     */
    public function sale($productRef, $year): array
    {
        return $this->saleQuery($productRef, $year)->find()->toArray('date');
    }

    // -------------
    // Query methods
    // -------------

    /**
     * @throws PropelException
     */
    public function turnoverQuery(string $productRef, string $year): OrderQuery
    {
        $query = $this->statisticHandler->turnoverQuery($year);
        $query->where('order_product.product_ref = ?', $productRef, PDO::PARAM_STR);

        return $query;
    }

    /**
     * @param $productRef
     * @param $year
     * @return OrderProductQuery
     * @throws PropelException
     */
    public function saleQuery($productRef, $year): OrderProductQuery
    {
        $query = OrderProductQuery::create();

        // filter with status
        $query
            ->useOrderQuery()
            ->useOrderStatusQuery()
            ->filterById(explode(',', Statistic::getConfigValue('order_types')), Criteria::IN)
            ->endUse()
            ->endUse();

        // filtrage par Ref
        $query->filterByProductRef($productRef);

        // filtrage par date
        $query->where('YEAR(order_product.created_at) = ?', $year, PDO::PARAM_STR);

        // ajout des colonnes AS
        $query
            ->addAsColumn('date', "CONCAT(YEAR(order_product.created_at), '-', MONTH(order_product.created_at))")
            ->addAsColumn('total', 'SUM(order_product.quantity)');

        // group by par mois
        $query->addGroupByColumn('YEAR(order_product.created_at)');
        $query->addGroupByColumn('MONTH(order_product.created_at)');

        $query->select(array(
            'date',
            'total'
        ));

        return $query;
    }
}
