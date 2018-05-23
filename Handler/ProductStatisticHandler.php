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
use Thelia\Model\OrderProductQuery;


/**
 * Class ProductStatisticHandler
 * @package Statistic\Handler
 * @author David Gros <dgros@openstudio.fr>
 */
class ProductStatisticHandler
{
    protected $statisticHandler;

    public function __construct(
        StatisticHandler $statisticHandler
    ){
        $this->statisticHandler = $statisticHandler;
    }

    public function turnover($productId, $year)
    {
        $turnoverQuery = $this->statisticHandler->turnoverQuery($year);
        $turnoverProductQuery = $this->turnoverQuery($productId, $year);

        $turnover = $turnoverQuery->find()->toArray('date');
        $turnoverProduct = $turnoverProductQuery->find()->toArray('date');

        foreach( $turnover as $date => $val){
            $turnoverProduct[$date]['percent'] = isset( $turnoverProduct[$date] )
                ? $turnoverProduct[$date]['TOTAL'] * 100 / $val['TOTAL']
                : 0
            ;
        }

        return $turnoverProduct;
    }

    public function sale($productRef, $year){
        $query = $this->saleQuery($productRef, $year);
        $q = $query->toString();
        $result = $query->find()->toArray('date');
        return $result;
    }

    // -------------
    // Query methods
    // -------------

    public function turnoverQuery($productRef, $year)
    {
        $query = $this->statisticHandler->turnoverQuery($year);
        $query->where('order_product.product_ref = ?',$productRef, \PDO::PARAM_STR);
        $q = $query->toString();

        return $query;
    }

    public function saleQuery($productRef, $year)
    {
        $query = OrderProductQuery::create();

        // filter with status
        $query
            ->useOrderQuery()
                ->useOrderStatusQuery()
                    ->filterByCode(explode(",",StatisticHandler::ALLOWED_STATUS), Criteria::IN)
                ->endUse()
            ->endUse();

        // filtrage par Ref
        $query->filterByProductRef($productRef);

        // filtrage par date
        $query->where('YEAR(order_product.created_at) = ?', $year, \PDO::PARAM_STR);

        // ajout des colonnes AS
        $query
            ->addAsColumn('date', "CONCAT(YEAR(order_product.created_at), '-', MONTH(order_product.created_at))")
            ->addAsColumn('total', 'SUM(order_product.quantity)')
        ;

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
