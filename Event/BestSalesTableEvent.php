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

use Symfony\Contracts\EventDispatcher\Event;

/**
 * The best sales table, one row per product reference. Each row holds what the statistic handler
 * read (product_ref, product_sale_elements_id; product_id and brand_id, null for a product deleted
 * since the order) and the figures of the three periods.
 *
 * A column added here is shown before the product title, in the order the columns were added,
 * and a row without a value for it shows an empty cell.
 */
class BestSalesTableEvent extends Event
{
    /**
     * The columns of the module itself, which a listener cannot replace.
     */
    public const NATIVE_COLUMNS = [
        'title', 'product_ref', 'brand_title',
        'total_sold', 'total_sold2', 'total_sold3',
        'total_ttc', 'total_ttc2', 'total_ttc3',
    ];

    /** @var array<string, string> column key => column title */
    private array $columns = [];

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        private array $rows,
        private readonly \DateTime $startDate,
        private readonly \DateTime $endDate,
        private readonly string $locale,
    ) {
    }

    /**
     * Adds a column shown before the product title. The title and the cell values are written
     * into the page as HTML (statistic.js sets innerHTML): escape any text that is not markup.
     *
     * @throws \InvalidArgumentException when the key is one of the module's own columns
     */
    public function addColumn(string $key, string $title): static
    {
        if (\in_array($key, self::NATIVE_COLUMNS, true)) {
            throw new \InvalidArgumentException(sprintf('The best sales table already has a column "%s"', $key));
        }

        $this->columns[$key] = $title;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function setRows(array $rows): static
    {
        $this->rows = array_values($rows);

        return $this;
    }

    public function getStartDate(): \DateTime
    {
        return clone $this->startDate;
    }

    public function getEndDate(): \DateTime
    {
        return clone $this->endDate;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
