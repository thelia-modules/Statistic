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

use Statistic\Handler\ProductStatisticHandler;
use Statistic\Statistic;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Loop\Product;

/**
 * Class ProductController
 * @package Statistic\Controller
 * @author David Gros <dgros@openstudio.fr>
 */
class ProductStatisticController extends BaseAdminController
{

    public function listProductAction(Request $request, RequestStack $requestStack, EventDispatcherInterface $eventDispatcher, SecurityContext $securityContext, TranslatorInterface $translator, $theliaParserLoops, $kernelEnvironment)
    {
        $category = $request->get('category');

        $loop = new Product($this->container, $requestStack, $eventDispatcher, $securityContext, $translator, $theliaParserLoops, $kernelEnvironment);
        $loop->initializeArgs([
            "category" => $category,
            "depth" => "10"
        ]);

        $query = $loop->buildModelCriteria();
        $result = $query->find()->toArray();

        return new JsonResponse($result);
    }

    public function statTurnoverAction(ProductStatisticHandler $productStatisticHandler, Request $request)
    {
        // récupération des paramètres
        $productRef = $request->query->get('ref', '141_4_91359672');

        $year = $request->query->get('year', date('Y'));
        $year2 = $request->query->get('year2', date('Y'));

        $results = array();
        $results[0] = $productStatisticHandler->turnover($productRef, $year);

        if ($year !== $year2) {
            $results[1] = $productStatisticHandler->turnover($productRef, $year2);
        }

        $data = $this->prepareData($year, $year2, $results, "TOTAL");

        return $this->jsonResponse(json_encode($data));
    }

    public function statSaleAction(ProductStatisticHandler $productStatisticHandler, Request $request)
    {
        // récupération des paramètres
        $productId = $request->query->get('ref', '141_4_91359672');

        $year = $request->query->get('year', date('Y'));
        $year2 = $request->query->get('year2', date('Y'));

        $results = array();
        $results[0] = $productStatisticHandler->sale($productId, $year);

        if ($year !== $year2) {
            $results[1] = $productStatisticHandler->sale($productId, $year2);
        }

        $data = $this->prepareData($year, $year2, $results, "total");

        return $this->jsonResponse(json_encode($data));

    }

    public function prepareData($year, $year2, $results, $type)
    {
        $graph = array();
        $graphLabel = array();
        $turnover = new \stdClass();
        $turnover2 = new \stdClass();
        $productYear = $year;

        foreach ($results as $index => $result) {
            for ($i = 1; $i <= 12; ++$i) {
                $date = new \DateTime($productYear . '-' . $i);
                if (!isset($result[$date->format('Y-n')])) {
                    $graph[$index][] = [$i - 1, 0];
                } else {
                    $graph[$index][] = [$i - 1, (float)($result[$date->format('Y-n')][$type])];
                }
                $graphLabel[$index][] = $date->format('M');
            }
            $productYear = $year2;
        }

        $turnover->color = '#ff0000';
        $turnover->graph = $graph[0];
        $turnover->graphLabel = $graphLabel[0];

        $data = new \stdClass();

        $data->series = array(
            $turnover
        );

        // There are two graphs
        if (count($graph) > 1) {
            $data->title = $this->getTranslator()->trans("Stats on %startYear and %endYear", array('%startYear' => $year, '%endYear' => $year2), Statistic::MESSAGE_DOMAIN);
            $turnover2->color = '#f39922';
            $turnover2->graph = $graph[1];
            $turnover2->graphLabel = $graphLabel[1];
            $data->series[] = $turnover2;
        } else {
            $data->title = $this->getTranslator()->trans("Stats on %startYear", array('%startYear' => $year), Statistic::MESSAGE_DOMAIN);
        }

        return $data;
    }
}
