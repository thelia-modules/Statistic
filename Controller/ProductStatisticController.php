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
        $productRef = $this->getRequest()->query->get('ref', '');
        $month = $this->getRequest()->query->get('month', date('m'));
        $year = $this->getRequest()->query->get('year', date('m'));

        $turnover = new \stdClass();
        $result = $this->getProductStatisticHandler()->turnover($productRef, $year);
        $graph = array();
        $graphLabel = array();
        for ($i = 1; $i <= 12; ++$i) {
            $date = new \DateTime($year.'-'.$i);
            if( !isset($result[$date->format('Y-n')])) {
                $graph[] = [$i-1, 0];
            }else{
                $graph[] = [$i-1, floatval($result[$date->format('Y-n')]['percent'])];
            }
            $graphLabel[] = $date->format('M');
        }
        $turnover->color ='#f39922';
        $turnover->graph = $graph;
        $turnover->graphLabel = $graphLabel;

        $data = new \stdClass();
        $data->title = $this->getTranslator()->trans("Stats on %year", array('%year' => $this->getRequest()->query->get('year', date('Y'))), "statistic");

        $data->series = array(
            $turnover
        );

        return $this->jsonResponse(json_encode($data));
    }

    public function statSaleAction()
    {
        // récupération des paramètres
        $productId = $this->getRequest()->query->get('ref', '');
        $month = $this->getRequest()->query->get('month', date('m'));
        $year = $this->getRequest()->query->get('year', date('m'));

        $sale = new \stdClass();
        $result = $this->getProductStatisticHandler()->sale($productId, $year);
        $graph = array();
        $graphLabel = array();
        for ($i = 1; $i <= 12; ++$i) {
            $date = new \DateTime($year.'-'.$i);
            if( !isset($result[$date->format('Y-n')])) {
                $graph[] = [$i-1, 0];
            }else{
                $graph[] = [$i-1, intval($result[$date->format('Y-n')]['total'])];
            }
            $graphLabel[] = $date->format('M');
        }
        $sale->color = '#5cb85c';
        $sale->graph = $graph;
        $sale->graphLabel = $graphLabel;

        $data = new \stdClass();
        $data->title = $this->getTranslator()->trans("Stats on %year", array('%year' => $this->getRequest()->query->get('year', date('Y'))), "statistic");

        $data->series = array(
            $sale
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
