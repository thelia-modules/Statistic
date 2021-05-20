<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 23/07/2019
 * Time: 13:44
 */

namespace Statistic\Controller;


use Statistic\Statistic;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Base\BrandI18nQuery;
use Thelia\Model\CategoryI18nQuery;
use Thelia\Model\ProductQuery;

class SearchController extends BaseAdminController
{
    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function searchProductAction(Request $request)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, Statistic::MESSAGE_DOMAIN, AccessManager::VIEW)) {
            return $response;
        }

        $search = '%' . $request->query->get('q') . '%';

        $resultArray = array();

        $productQuery = ProductQuery::create()
            ->join('ProductI18n')
            ->where('Product.ref LIKE ?', $search)
            ->_or()
            ->where('ProductI18n.title LIKE ?', $search)
            ->filterByVisible(1)
            ->select(array('Id', 'ref', 'ProductI18n.title'));

        $category_id = $request->query->get('category_id');
        if ($category_id !== null) {
            $productQuery
                ->useProductCategoryQuery()
                ->filterByCategoryId($category_id)
                ->endUse();
        }
        $products = $productQuery->limit(100);

        foreach ($products as $product) {
            $resultArray[$product['ref']] = $product['ProductI18n.title'];
        }

        return $this->jsonResponse(json_encode($resultArray));
    }


    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function searchCategoryAction(Request $request)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, Statistic::MESSAGE_DOMAIN, AccessManager::VIEW)) {
            return $response;
        }

        $search = '%' . $request->query->get('q') . '%';

        $resultArray = array();

        $categoriesI18n = CategoryI18nQuery::create()
            ->where('CategoryI18n.title LIKE ?', $search)
            ->limit(100)
            ->find();

        /** @var \Thelia\Model\CategoryI18n $categoryI18n */
        foreach ($categoriesI18n as $categoryI18n) {
            $category = $categoryI18n->getCategory();
            $resultArray[$category->getId()] = $categoryI18n->getTitle();
        }

        return $this->jsonResponse(json_encode($resultArray));
    }

    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function searchBrandAction(Request $request)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, Statistic::MESSAGE_DOMAIN, AccessManager::VIEW)) {
            return $response;
        }

        $search = '%' . $request->query->get('q') . '%';

        $resultArray = array();

        $brands = BrandI18nQuery::create()
            ->where('BrandI18n.title LIKE ?', $search)
            ->limit(100)
            ->find();

        foreach ($brands as $brand) {
            $resultArray[$brand->getId()] = $brand->getTitle();
        }

        return $this->jsonResponse(json_encode($resultArray));
    }

}