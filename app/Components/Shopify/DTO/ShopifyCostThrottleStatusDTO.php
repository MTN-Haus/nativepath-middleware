<?php

namespace App\Components\Shopify\DTO;

/**
 * ShopifyCostThrottleStatusDTO class
 *
 * @package App\Components\Shopify\DTO
 */
class ShopifyCostThrottleStatusDTO
{
    public int $maximumAvailable = 0;
    public int $currentlyAvailable = 0;
    public int $restoreRate = 0;

    /**
     * @param  array $throttleStatus
     */
    public function __construct(array $throttleStatus)
    {
        $this->maximumAvailable = $throttleStatus['maximumAvailable'] ?? 0;
        $this->currentlyAvailable = $throttleStatus['currentlyAvailable'] ?? 0;
        $this->restoreRate = $throttleStatus['restoreRate'] ?? 0;
    }
}
