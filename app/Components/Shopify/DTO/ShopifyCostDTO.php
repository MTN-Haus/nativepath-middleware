<?php

namespace App\Components\Shopify\DTO;

/**
 * ShopifyCostDTO class
 *
 * @package App\Components\Shopify\DTO
 */
class ShopifyCostDTO
{
    public int $requestedQueryCost = 0;
    public ?int $actualQueryCost = null;
    public ?ShopifyCostThrottleStatusDTO $throttleStatus = null;

    /**
     * @param  array $cost
     */
    public function __construct(array $cost)
    {
        $this->requestedQueryCost = $cost['requestedQueryCost'] ?? 0;
        $this->actualQueryCost = $cost['actualQueryCost'] ?? null;
        $this->throttleStatus = new ShopifyCostThrottleStatusDTO($cost['throttleStatus'] ?? []);
    }
}
