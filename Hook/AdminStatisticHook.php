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

namespace Statistic\Hook;

use Statistic\Statistic;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;

/**
 * Class AdminStatisticHook
 * @package Statistic\Hook
 * @author David Gros <dgros@openstudio.fr>
 */
class AdminStatisticHook extends BaseHook
{
    public static function getSubscribedHooks(): array
    {
        return [
            'statistic.tab' => [
                ['type' => 'back', 'method' => 'onStatisticTab'],
            ],
            'statistic.footer.js' => [
                ['type' => 'back', 'method' => 'insertionJS'],
            ],
        ];
    }

    public function onStatisticTab(HookRenderBlockEvent $event): void
    {
        $request = $this->getRequest();
        $locale = $request->hasSession()
            ? $request->getSession()->getLang()?->getLocale() ?? 'en_US'
            : (\Thelia\Model\LangQuery::create()->findOneByByDefault(true)?->getLocale() ?? 'en_US');

        $brands = $this->getBrands($locale);
        $categories = $this->getCategoryTree($locale);

        $event
            ->add([
                'tab_id' => 'general-statistic',
                'tab_nav_title' => $this->trans('tool.panel.general.title', [], Statistic::MESSAGE_DOMAIN),
                'content' => $this->render('Statistic/hook/statistic-general.html.twig', ['brands' => $brands]),
            ])
            ->add([
                'tab_id' => 'product-statistic',
                'tab_nav_title' => $this->trans('tool.panel.product.title', [], Statistic::MESSAGE_DOMAIN),
                'content' => $this->render('Statistic/hook/statistic-product.html.twig', ['categories' => $categories]),
            ])
            ->add([
                'tab_id' => 'category-statistic',
                'tab_nav_title' => $this->trans('tool.panel.category.title', [], Statistic::MESSAGE_DOMAIN),
                'content' => $this->render('Statistic/hook/statistic-category.html.twig', ['categories' => $categories]),
            ])
            ->add([
                'tab_id' => 'brand-statistic',
                'tab_nav_title' => $this->trans('tool.panel.brand.title', [], Statistic::MESSAGE_DOMAIN),
                'content' => $this->render('Statistic/hook/statistic-brand.html.twig', ['brands' => $brands]),
            ])
            ->add([
                'tab_id' => 'anual-statistic',
                'tab_nav_title' => $this->trans('tool.panel.annual.title', [], Statistic::MESSAGE_DOMAIN),
                'content' => $this->render('Statistic/hook/statistic-annual.html.twig'),
            ])
        ;
    }

    /**
     * @return array<int, array{id: int, title: string}>
     */
    private function getBrands(string $locale): array
    {
        $brands = [];

        foreach (BrandQuery::create()->orderById()->find() as $brand) {
            $brand->setLocale($locale);
            $brands[] = ['id' => $brand->getId(), 'title' => $brand->getTitle()];
        }

        return $brands;
    }

    /**
     * @return array<int, array{id: int, title: string, level: int, label: string}>
     */
    private function getCategoryTree(string $locale): array
    {
        return $this->collectCategories(0, 0, $locale);
    }

    /**
     * @return array<int, array{id: int, title: string, level: int, label: string}>
     */
    private function collectCategories(int $parentId, int $level, string $locale): array
    {
        $result = [];

        $categories = CategoryQuery::create()
            ->filterByParent($parentId)
            ->orderByPosition()
            ->find();

        foreach ($categories as $category) {
            $category->setLocale($locale);
            $result[] = [
                'id' => $category->getId(),
                'title' => $category->getTitle(),
                'level' => $level,
                'label' => str_repeat("\u{00A0}\u{00A0}\u{00A0}", $level).$category->getTitle(),
            ];
            $result = array_merge($result, $this->collectCategories($category->getId(), $level + 1, $locale));
        }

        return $result;
    }

    public function insertionJS(HookRenderEvent $event): void
    {
        $event->add($this->render('Statistic/hook/statistic-assets.html.twig'));

        $event->add($this->addCSS('assets/css/stats.css'));

        foreach ([
            'blocksit.min.js',
            'modalSearch.js',
            'statistic.js',
            'statistic-product.js',
            'statistic-category.js',
            'statistic-brand.js',
            'statistic-annual.js',
        ] as $script) {
            $event->add($this->addJS('assets/js/'.$script));
        }
    }
}
