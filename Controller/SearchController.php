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
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CategoryI18nQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;

class SearchController extends BaseAdminController
{
    public function searchProductAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, Statistic::BO_MESSAGE_DOMAIN, AccessManager::VIEW)) {
            return $response;
        }

        $search = '%'.$this->getRequest()->query->get('q').'%';

        $resultArray = array();

        $productsI18nQuery = ProductI18nQuery::create()
            ->join('Product')
            ->filterByTitle($search)
            ->select(array('Id','title','Product.ref'));


        $category_id = $this->getRequest()->query->get('category_id');
        if($category_id != null){
            $productsI18nQuery
                ->useProductQuery()
                ->useProductCategoryQuery()
                ->filterByCategoryId($category_id)
                ->endUse()
                ->endUse();
        }
        $products = $productsI18nQuery->limit(100);

        foreach ($products as $product) {
            $resultArray[$product['Product.ref']] = $product['title'];
        }

        return $this->jsonResponse(json_encode($resultArray));
    }


    public function searchCategoryAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, Statistic::BO_MESSAGE_DOMAIN, AccessManager::VIEW)) {
            return $response;
        }

        $search = '%'.$this->getRequest()->query->get('q').'%';

        $resultArray = array();

        $categoriesI18n = CategoryI18nQuery::create()->filterByTitle($search)->limit(100);

        /** @var \Thelia\Model\CategoryI18n $categoryI18n */
        foreach ($categoriesI18n as $categoryI18n) {
            $category = $categoryI18n->getCategory();
            $resultArray[$category->getId()] = $categoryI18n->getTitle();
        }

        return $this->jsonResponse(json_encode($resultArray));
    }
}