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

namespace Statistic\Controller;

use Statistic\Statistic;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Template\Loop\Product;

/**
 * Class ProductController
 * @package Statistic\Controller
 * @author David Gros <dgros@openstudio.fr>
 */
class ProductStatisticController extends BaseAdminController
{

    public function listProductAction()
    {
        $category = $this->getRequest()->get('category');

        $loop = new Product($this->container);
        $loop->initializeArgs([
            "category" => $category,
            "depth"=>"10"
        ]);

        $query = $loop->buildModelCriteria();
        $result= $query->find()->toArray();

        return new JsonResponse($result);
    }

    public function statTurnoverAction()
    {
        // récupération des paramètres
        $productRef = $this->getRequest()->query->get('ref', '141_4_91359672');
        $year = $this->getRequest()->query->get('year', date('m'));
        $year2 = $this->getRequest()->query->get('year2', date('m'));

        $turnover = new \stdClass();
        $turnover2 = new \stdClass();
        $results = array();
        $results[0] = $this->getProductStatisticHandler()->turnover($productRef, $year);
        $results[1] = $this->getProductStatisticHandler()->turnover($productRef, $year2);
        $graph = array();
        $graphLabel = array();
        $productYear = $year;
        foreach ($results as $index => $result){
            for ($i = 1; $i <= 12; ++$i) {
                $date = new \DateTime($productYear.'-'.$i);
                if( !isset($result[$date->format('Y-n')])) {
                    $graph[$index][] = [$i-1, 0];
                }else{
                    $graph[$index][] = [$i-1, floatval($result[$date->format('Y-n')]['percent'])];
                }
                $graphLabel[$index][] = $date->format('M');
            }
            $productYear = $year2;
        }

        $turnover->color ='#ff0000';
        $turnover->graph = $graph[0];
        $turnover->graphLabel = $graphLabel[0];

        $turnover2->color ='#f39922';
        $turnover2->graph = $graph[1];
        $turnover2->graphLabel = $graphLabel[1];

        $data = new \stdClass();
        $data->title = $this->getTranslator()->trans("Stats on %startYear and %endYear", array('%startYear' => $year, '%endYear' => $year2), "statistic");

        $data->series = array(
            $turnover,
            $turnover2
        );

        return $this->jsonResponse(json_encode($data));
    }

    public function statSaleAction()
    {
        // récupération des paramètres
        $productId = $this->getRequest()->query->get('ref', '141_4_91359672');
        $year = $this->getRequest()->query->get('year', date('m'));
        $year2 = $this->getRequest()->query->get('year2', date('m'));

        $sale = new \stdClass();
        $sale2 = new \stdClass();
        $results[0] = $this->getProductStatisticHandler()->sale($productId, $year);
        $results[1] = $this->getProductStatisticHandler()->sale($productId, $year2);
        $graph = array();
        $graphLabel = array();
        $productYear = $year;
        foreach ($results as $index => $result) {
            for ($i = 1; $i <= 12; ++$i) {
                $date = new \DateTime($productYear . '-' . $i);
                if (!isset($result[$date->format('Y-n')])) {
                    $graph[$index][] = [$i - 1, 0];
                } else {
                    $graph[$index][] = [$i - 1, intval($result[$date->format('Y-n')]['total'])];
                }
                $graphLabel[$index][] = $date->format('M');
            }
            $productYear = $year2;
        }

        $sale->color = '#38ADFE';
        $sale->graph = $graph[0];
        $sale->graphLabel = $graphLabel[0];

        $sale2->color = '#5cb85c';
        $sale2->graph = $graph[1];
        $sale2->graphLabel = $graphLabel[1];

        $data = new \stdClass();
        $data->title = $this->getTranslator()->trans("Stats on %startYear and %endYear", array('%startYear' => $year, '%endYear' => $year2), "statistic");

        $data->series = array(
            $sale,
            $sale2
        );

        return $this->jsonResponse(json_encode($data));

    }

    // Protected methods
    // -----------------

    /** @var  \Statistic\Handler\StatisticHandler */
    protected $statisticHandler;

    protected function getStatisticHandler()
    {
        if (!isset($this->statisticHandler)) {
            $this->statisticHandler = $this->getContainer()->get('statistic.handler.statistic');
        }

        return $this->statisticHandler;
    }

    /** @var  \Statistic\Handler\ProductStatisticHandler */
    protected $productHandler;

    protected function getProductStatisticHandler()
    {
        if( !isset($this->productHandler)){
            $this->productHandler = $this->getContainer()->get('statistic.handler.product');
        }
        return $this->productHandler;
    }

}
