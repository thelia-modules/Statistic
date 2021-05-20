<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 22/07/2020
 * Time: 16:08
 */

namespace Statistic\Controller;


use Statistic\Handler\BrandStatisticHandler;
use Statistic\Statistic;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Base\OrderQuery;

class BrandStatisticController extends BaseAdminController
{
    /**
     * @throws \Exception
     */
    public function brandTurnoverAction(Request $request, BrandStatisticHandler $brandStatisticHandler)
    {
        $brandId = $request->get('brandId');

        $ghost = $request->query->get('ghost');

        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);


        if ($startDate->diff($endDate)->format('%a') === '0') {
            $result = $brandStatisticHandler->getBrandTurnoverByHours($brandId, $startDate);
        } else {
            /** @var OrderQuery $query */
            $result = $brandStatisticHandler->getBrandTurnover($brandId, $startDate, $endDate);
        }


        $plot = new \stdClass();
        $data = new \stdClass();

        $plot->color = '#f39922';
        $plot->graph = $result['stats'];
        $plot->graphLabel = $result['label'];

        $data->series = [$plot];

        if ($startDay === $endDay && $startMonth === $endMonth && $startYear === $endYear) {
            $data->title = $this->getTranslator()->trans("Stats for %startDay/%startMonth/%startYear", array(
                '%startDay' => $startDay,
                '%startMonth' => $startMonth,
                '%startYear' => $startYear,
            ), Statistic::MESSAGE_DOMAIN);
        } else {
            $data->title = $this->getTranslator()->trans("Stats between %startDay/%startMonth/%startYear and %endDay/%endMonth/%endYear", array(
                '%startDay' => $startDay,
                '%startMonth' => $startMonth,
                '%startYear' => $startYear,
                '%endDay' => $endDay,
                '%endMonth' => $endMonth,
                '%endYear' => $endYear
            ), Statistic::MESSAGE_DOMAIN);
        }

        if ((int)$ghost === 1) {

            $ghostGraph = $brandStatisticHandler->getBrandTurnover(
                $brandId,
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

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function brandSalesAction(Request $request, BrandStatisticHandler $brandStatisticHandler)
    {
        $brandId = $request->get('brandId');

        $ghost = $request->query->get('ghost');

        $startDay = $request->query->get('startDay', date('d'));
        $startMonth = $request->query->get('startMonth', date('m'));
        $startYear = $request->query->get('startYear', date('Y'));

        $endDay = $request->query->get('endDay', date('d'));
        $endMonth = $request->query->get('endMonth', date('m'));
        $endYear = $request->query->get('endYear', date('Y'));

        $startDate = new \DateTime($startYear . '-' . $startMonth . '-' . $startDay);
        $endDate = new \DateTime($endYear . '-' . $endMonth . '-' . $endDay);

        if ($startDate->diff($endDate)->format('%a') === '0') {
            $result = $brandStatisticHandler->getBrandTurnoverByHours($brandId, $startDate, true);
        } else {
            /** @var OrderQuery $query */
            $result = $brandStatisticHandler->getBrandTurnover($brandId, $startDate, $endDate, true);
        }

        $plot = new \stdClass();
        $data = new \stdClass();

        $plot->color = '#5cb85c';
        $plot->graph = $result['stats'];
        $plot->graphLabel = $result['label'];

        $data->series = [$plot];

        if ($startDay === $endDay && $startMonth === $endMonth && $startYear === $endYear) {
            $data->title = $this->getTranslator()->trans("Stats for %startDay/%startMonth/%startYear", array(
                '%startDay' => $startDay,
                '%startMonth' => $startMonth,
                '%startYear' => $startYear,
            ), Statistic::MESSAGE_DOMAIN);
        } else {
            $data->title = $this->getTranslator()->trans("Stats between %startDay/%startMonth/%startYear and %endDay/%endMonth/%endYear", array(
                '%startDay' => $startDay,
                '%startMonth' => $startMonth,
                '%startYear' => $startYear,
                '%endDay' => $endDay,
                '%endMonth' => $endMonth,
                '%endYear' => $endYear
            ), Statistic::MESSAGE_DOMAIN);
        }

        if ((int)$ghost === 1) {

            $ghostGraph = $brandStatisticHandler->getBrandTurnover(
                $brandId,
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

}