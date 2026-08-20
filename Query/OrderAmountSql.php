<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Statistic\Query;

use Thelia\Model\ConfigQuery;

/**
 * SQL fragments that total an order the way Order::getTotalAmount() totals it, so
 * that a statistic reads the amount the customer was actually charged.
 *
 * The core rounds a line total in one of three ways, and which one applies is a
 * per-order question: orders placed before Thelia 2.4 were never rounded at all,
 * orders invoiced before the shop changed its rounding mode are frozen on the rule
 * they were invoiced with, and the rest follow order_rounding_mode. Both pivots are
 * therefore part of the expression, as a CASE on the order id.
 *
 * The variables are read raw rather than through ConfigQuery::getOrderRoundingMode(),
 * which answers for one order at a time and so cannot be consulted from SQL. Reading
 * raw also keeps the module working against a core that predates thelia/thelia#3801:
 * order_rounding_mode is simply absent there, no CASE branch is emitted for it, and
 * the historical rule applies.
 */
final class OrderAmountSql
{
    /**
     * Mirrors ConfigQuery::ROUNDING_MODE_* (thelia/thelia#3801).
     */
    private const MODE_SUM_OF_ROUNDINGS = 1;
    private const MODE_ROUNDING_OF_SUMS = 2;

    /**
     * What one order in `order` contributed, discount deducted and clamped at zero
     * the way Order::getTotalAmount() clamps it, postage added afterwards.
     */
    public static function orderTotal(bool $includeShipping): string
    {
        $itemsTotal = self::branch(
            self::legacyItemsTotal(),
            self::itemsTotal(false),
            self::itemsTotal(true)
        );

        $total = 'GREATEST(COALESCE('.$itemsTotal.', 0) - `order`.`discount`, 0)';

        return $includeShipping ? '('.$total.' + `order`.`postage`)' : '('.$total.')';
    }

    /**
     * The untaxed total of one joined `order_product` row. Used by statistics that
     * break sales down per line rather than per order.
     */
    public static function untaxedLineTotal(): string
    {
        $unitPrice = 'IF(`order_product`.`was_in_promo` = 1, `order_product`.`promo_price`, `order_product`.`price`)';

        return self::branch(
            '(`order_product`.`quantity` * '.$unitPrice.')',
            '(`order_product`.`quantity` * ROUND('.$unitPrice.', 2))',
            '(ROUND(`order_product`.`quantity` * '.$unitPrice.', 2))'
        );
    }

    /**
     * Picks between the three rules per order, emitting a branch only for a pivot
     * the shop actually set. A shop that never switched gets a single expression.
     */
    private static function branch(string $legacy, string $sumOfRoundings, string $roundingOfSums): string
    {
        $legacyPivot = (int) ConfigQuery::read('last_legacy_rounding_order_id', 0);
        $sumOfRoundingsPivot = (int) ConfigQuery::read('last_sum_of_roundings_order_id', 0);
        $roundsLineTotals = self::MODE_ROUNDING_OF_SUMS === (int) ConfigQuery::read(
            'order_rounding_mode',
            self::MODE_SUM_OF_ROUNDINGS
        );

        $frozenBranches = [];

        if ($legacyPivot > 0) {
            $frozenBranches[] = 'WHEN `order`.`id` <= '.$legacyPivot.' THEN '.$legacy;
        }

        if ($roundsLineTotals && $sumOfRoundingsPivot > 0) {
            $frozenBranches[] = 'WHEN `order`.`id` <= '.$sumOfRoundingsPivot.' THEN '.$sumOfRoundings;
        }

        $currentRule = $roundsLineTotals ? $roundingOfSums : $sumOfRoundings;

        if ([] === $frozenBranches) {
            return $currentRule;
        }

        return 'CASE '.implode(' ', $frozenBranches).' ELSE '.$currentRule.' END';
    }

    /**
     * Totals the lines of one order the way Order::buildTotalAmountQuery() does:
     * rounding the unit amounts before multiplying by the quantity is the historical
     * rule, rounding the line total instead is what a shop selling by weight needs.
     */
    private static function itemsTotal(bool $roundLineTotals): string
    {
        $unitPrice = 'IF(op.was_in_promo = 1, op.promo_price, op.price)';
        $unitTax = 'IF(op.was_in_promo = 1, opt.promo_amount, opt.amount)';

        if (!$roundLineTotals) {
            $unitPrice = 'ROUND('.$unitPrice.', 2)';
            $unitTax = 'ROUND('.$unitTax.', 2)';
        }

        $lineTotal = 'op.quantity * ('.$unitPrice.' + (
            SELECT COALESCE(SUM('.$unitTax.'), 0)
            FROM order_product_tax opt
            WHERE opt.order_product_id = op.id
        ))';

        if ($roundLineTotals) {
            $lineTotal = 'ROUND('.$lineTotal.', 2)';
        }

        return '(SELECT SUM('.$lineTotal.') FROM order_product op WHERE op.order_id = `order`.`id`)';
    }

    /**
     * Orders placed before Thelia 2.4 keep the shape of getTotalAmountLegacy(): no
     * rounding, and the untaxed total and the tax summed apart then added. The shape
     * matters as much as the absence of ROUND, since summing the two apart and
     * summing them per line land on different sides of a half-cent tie.
     */
    private static function legacyItemsTotal(): string
    {
        return '(
            (
                SELECT SUM(op.quantity * IF(op.was_in_promo = 1, op.promo_price, op.price))
                FROM order_product op
                WHERE op.order_id = `order`.`id`
            )
            + (
                SELECT COALESCE(SUM(op.quantity * IF(op.was_in_promo = 1, opt.promo_amount, opt.amount)), 0)
                FROM order_product op
                INNER JOIN order_product_tax opt ON opt.order_product_id = op.id
                WHERE op.order_id = `order`.`id`
            )
        )';
    }
}
