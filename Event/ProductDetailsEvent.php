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
 * The sales of one product over the period, grouped by attribute value: for each group, the lines
 * shown (order date, quantity) and the product sale elements its first line was sold as.
 */
class ProductDetailsEvent extends Event
{
    /**
     * @param array<string, list<string>> $lines                  group label => lines
     * @param array<string, int|null>     $productSaleElementsIds group label => product sale elements of its first line
     */
    public function __construct(
        private readonly int $productId,
        private array $lines,
        private readonly array $productSaleElementsIds,
    ) {
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * @param array<string, list<string>> $lines
     */
    public function setLines(array $lines): static
    {
        $this->lines = $lines;

        return $this;
    }

    public function getProductSaleElementsId(string $label): ?int
    {
        return $this->productSaleElementsIds[$label] ?? null;
    }
}
