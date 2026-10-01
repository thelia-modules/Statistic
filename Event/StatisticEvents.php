<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Statistic\Event;

/**
 * Events a module listens to in order to extend the statistics.
 */
final class StatisticEvents
{
    /**
     * The best sales table of the general tab, before it is sent: a listener adds its own columns
     * and fills them for each row (BestSalesTableEvent).
     */
    public const BEST_SALES_TABLE = 'statistic.best_sales.table';

    /**
     * The sales of one product, opened under its row of the best sales table: a listener changes
     * the lines shown for each attribute value (ProductDetailsEvent).
     */
    public const PRODUCT_DETAILS = 'statistic.product.details';
}
