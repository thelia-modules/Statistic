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

/**
 * Class CustomerStatHandler
 * @package Statistic\Handler
 * @author David Gros <dgros@openstudio.fr>
 */
use Thelia\Model\Base\CustomerQuery as BaseCustomerQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class CustomerStatHandler extends BaseCustomerQuery
{
    public static function getNewCustomersStats(\DateTime $start, \DateTime $end)
    {

        /*$start = date('Y-m-d', strtotime($start));
        $end = date('Y-m-d', strtotime($end));*/

        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        for ($day = 0, $date = clone($start); $date <= $end; $date->add(new \DateInterval('P1D')), $day++) {
            $dayCustomers = self::create()
                ->filterByCreatedAt($date->format('Y-m-d')."00:00:00", Criteria::GREATER_EQUAL)
                ->filterByCreatedAt( $date->format('Y-m-d')."23:59:59", Criteria::LESS_EQUAL)
                ->count();
            $key = explode('-', $date->format('Y-m-d'));
            array_push($result['stats'],array($day, $dayCustomers));
            array_push($result['label'],array($day,$key[2] . '/' . $key[1]));

        }

        return $result;
    }
}
