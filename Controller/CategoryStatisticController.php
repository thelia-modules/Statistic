<?php

namespace Statistic\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Propel;
use Statistic\Statistic;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Log\Tlog;
use Thelia\Model\Base\CategoryQuery;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\ProductCategoryTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductCategory;

class CategoryStatisticController extends BaseAdminController
{
    public function categorySalesAction()
    {
        $this->getDataFromRequest($categories, $startDate, $endDate, $ghost);
        $plot = new \stdClass();
        $data = new \stdClass();

        if ($startDate->diff($endDate)->format('%a') === '0') {

            $result = $this->getCategorySalesByHours($categories, $startDate, true);
            $data->title = $this->getTranslator()->trans("Stats for %startDay/%startMonth/%startYear", array(
                '%startDay' => $startDate->format('d'),
                '%startMonth' => $startDate->format('m'),
                '%startYear' => $startDate->format('Y'),
            ), Statistic::MESSAGE_DOMAIN);

        } else {
            /** @var OrderQuery $query */
            $result = $this->getCategorySales($categories, $startDate, $endDate, true);
            $data->title = $this->getTranslator()->trans("Stats between %startDay/%startMonth/%startYear and %endDay/%endMonth/%endYear", array(
                '%startDay' => $startDate->format('d'),
                '%startMonth' => $startDate->format('m'),
                '%startYear' => $startDate->format('Y'),
                '%endDay' => $endDate->format('d'),
                '%endMonth' => $endDate->format('m'),
                '%endYear' => $endDate->format('Y')
            ), Statistic::MESSAGE_DOMAIN);
        }

        $plot->color = '#5cb85c';
        $plot->graph = $result['stats'];
        $plot->graphLabel = $result['label'];

        $data->series = [$plot];


        if ((int)$ghost === 1) {

            $ghostGraph = $this->getCategorySales(
                $categories,
                $startDate->sub(new \DateInterval('P1Y')),
                $endDate->sub(new \DateInterval('P1Y')),
                true
            );
            $ghostCurve = new \stdClass();
            $ghostCurve->color = "#38acfc";
            $ghostCurve->graph = $ghostGraph['stats'];

            $data->series[] = $ghostCurve;
        }


        return $this->jsonResponse(json_encode($data));
    }

    public function categoryTurnoverAction()
    {
        $this->getDataFromRequest($categories, $startDate, $endDate, $ghost);
        $plot = new \stdClass();
        $data = new \stdClass();

        if ($startDate->diff($endDate)->format('%a') === '0') {

            $result = $this->getCategorySalesByHours($categories, $startDate);
            $data->title = $this->getTranslator()->trans("Stats for %startDay/%startMonth/%startYear", array(
                '%startDay' => $startDate->format('d'),
                '%startMonth' => $startDate->format('m'),
                '%startYear' => $startDate->format('Y'),
            ), Statistic::MESSAGE_DOMAIN);

        } else {
            /** @var OrderQuery $query */
            $result = $this->getCategorySales($categories, $startDate, $endDate);
            $data->title = $this->getTranslator()->trans("Stats between %startDay/%startMonth/%startYear and %endDay/%endMonth/%endYear", array(
                '%startDay' => $startDate->format('d'),
                '%startMonth' => $startDate->format('m'),
                '%startYear' => $startDate->format('Y'),
                '%endDay' => $endDate->format('d'),
                '%endMonth' => $endDate->format('m'),
                '%endYear' => $endDate->format('Y')
            ), Statistic::MESSAGE_DOMAIN);
        }

        $plot->color = '#f39922';
        $plot->graph = $result['stats'];
        $plot->graphLabel = $result['label'];

        $data->series = [$plot];

        if ((int)$ghost === 1) {

            $ghostGraph = $this->getCategorySales(
                $categories,
                $startDate->sub(new \DateInterval('P1Y')),
                $endDate->sub(new \DateInterval('P1Y'))
            );
            $ghostCurve = new \stdClass();
            $ghostCurve->color = "#38acfc";
            $ghostCurve->graph = $ghostGraph['stats'];

            $data->series[] = $ghostCurve;
        }


        return $this->jsonResponse(json_encode($data));
    }

    private function getCategorySales($brandId, \DateTime $startDate, \DateTime $endDate, $count = false)
    {
        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        $queryResults = $this->categorySalesQuery(
            $brandId,
            clone ($startDate->setTime(0, 0, 0)),
            clone ($endDate->setTime(23, 59, 59)),
            $count
        );

        $queryData = [];
        foreach ($queryResults as $queryResult){
            $queryData[$queryResult['date']] = $queryResult['TOTAL'];
        }

        for ($day = 0, $date = clone($startDate); $date <= $endDate; $date->add(new \DateInterval('P1D')), $day++) {
            $result['stats'][] = array($day, isset($queryData[$date->format('Y-n-j')]) ? (float)($queryData[$date->format('Y-n-j')]) : 0);
            $result['label'][] = array($date->format('d/m'));
        }

        return $result;
    }

    private function getCategorySalesByHours($categories, \DateTime $startDate, $count = false)
    {
        $result = array();
        $result['stats'] = array();
        $result['label'] = array();

        for ($hour = 0; $hour < 24; $hour++) {
            $val = $this->categorySalesQuery(
                $categories,
                clone ($startDate->setTime($hour, 0, 0)),
                clone ($startDate->setTime($hour, 59, 59)),
                $count
            );
            $result['stats'][] = array($hour, $val ? (float)($val[0]['TOTAL']) : 0);
            $result['label'][] = array(($hour + 1) . 'h');
        }

        return $result;
    }

    protected function categorySalesQuery($categories, \DateTime $startDate, \DateTime $endDate, $count = false)
    {
        $con = Propel::getConnection();

        $sql = "SELECT 
	            SUM(ROUND(order_product.quantity * IF(order_product.was_in_promo = 1, order_product.promo_price, order_product.price), 2) ) AS TOTAL,
	            CONCAT(YEAR(`order`.`created_at`),'-',MONTH(`order`.`created_at`),'-',DAY(`order`.`created_at`)) AS date 
                FROM `order` 
                INNER JOIN `order_product` ON (`order`.`id`=`order_product`.`order_id`) 
                INNER JOIN `product` ON (`order_product`.`product_ref`=`product`.`ref`) 
                INNER JOIN `product_category` ON (`product`.`id`=`product_category`.`product_id`) 
                WHERE `order`.`status_id` IN (".Statistic::getConfigValue('order_types').") AND (`order`.`created_at`>= :p1 AND `order`.`created_at`<= :p2) 
                AND `product_category`.`category_id` IN (".implode(",", $categories).") 
                GROUP BY `date`;";
        if ($count){
            $sql = "SELECT 
                    SUM(`order_product`.`quantity`) AS TOTAL, 
                    CONCAT(YEAR(`order`.`created_at`),'-',MONTH(`order`.`created_at`),'-',DAY(`order`.`created_at`)) AS date 
                    FROM `order` 
                    INNER JOIN `order_product` ON (`order`.`id`=`order_product`.`order_id`) 
                    INNER JOIN `product` ON (`order_product`.`product_ref`=`product`.`ref`) 
                    INNER JOIN `product_category` ON (`product`.`id`=`product_category`.`product_id`) 
                    WHERE `order`.`status_id` IN (".Statistic::getConfigValue('order_types').") AND (`order`.`created_at`>= :p1 AND `order`.`created_at`<= :p2) 
                    AND `product_category`.`category_id` IN (".implode(",", $categories).") 
                    GROUP BY `date`;";
        }

            $stmt = $con->prepare($sql);

            $stmt->bindValue(':p1', $startDate->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $stmt->bindValue(':p2', $endDate->format('Y-m-d H:i:s'), \PDO::PARAM_STR);

            $stmt->execute();

            return $stmt->fetchAll();
    }

    protected function getDataFromRequest(&$categories, &$startDate, &$endDate, &$ghost)
    {
        $categoryId = $this->getRequest()->get('categoryId');

        $categories = $this->getCategoryChildren($categoryId, []);

        $ghost = $this->getRequest()->query->get('ghost');

        $startDay = $this->getRequest()->query->get('startDay', date('d'));
        $startMonth = $this->getRequest()->query->get('startMonth', date('m'));
        $startYear = $this->getRequest()->query->get('startYear', date('Y'));

        $endDay = $this->getRequest()->query->get('endDay', date('d'));
        $endMonth = $this->getRequest()->query->get('endMonth', date('m'));
        $endYear = $this->getRequest()->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);
    }

    protected function getCategoryChildren($parentCategory, $categories)
    {
        $children = CategoryQuery::create()
            ->filterByParent($parentCategory)
            ->find();

        foreach ($children as $child){
            $categories = $this->getCategoryChildren($child->getId(), $categories);
        }

        $categories[] = (int)$parentCategory;
        return $categories;
    }
}