<?php

namespace App\Components\Shopify;

use App\Components\Shopify\DTO\ShopifyCostDTO;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Shopify\Clients\Graphql;
use Shopify\Clients\HttpResponse;

/**
 * Class ShopifyGraphqlClient
 * @package App\Components\Shopify
 */
class ShopifyGraphqlClient extends Graphql
{
    public const TIMEOUT_KEY = 'shopify_graphql_timeout';
    public const MAX_DELAY = 1000;

    /**
     * Sends a GraphQL query to this client's domain.
     *
     * @param string|array   $data         Query to be posted to endpoint
     * @param array          $query        Parameters on a query to be added to the URL
     * @param array          $extraHeaders Any extra headers to send along with the request
     * @param int|null       $tries        How many times to attempt the request
     *
     * @return HttpResponse
     * @throws \Shopify\Exception\HttpRequestException
     * @throws \Shopify\Exception\MissingArgumentException
     */
    public function query(
        $data,
        array $query = [],
        array $extraHeaders = [],
        ?int $tries = null
    ): HttpResponse {
        while (Redis::get(self::TIMEOUT_KEY) > 0) {
            usleep(1000 * 100);
        }
        $request = parent::query($data, $query, $extraHeaders, $tries);
        $body = $request->getDecodedBody();
        $errors = $body['errors'] ?? null;
        $cost = new ShopifyCostDTO($body['extensions']['cost'] ?? []);

        $msDelay = $this->calculateDelayCost($cost);
        if ($msDelay === null) {
            $message = $errors[0]['message'] ?? null;
            throw new Exception($message ?? ('Error in query: ' . print_r($data, 1)));
        }

        if ($msDelay || $errors) {
            Redis::set(self::TIMEOUT_KEY, '1', 'PX', $msDelay ?: self::MAX_DELAY);
        }

        if ($errors && $tries) {
            Log::error('Query: ' . print_r($data, 1) . 'Trie: ' . $tries . PHP_EOL .
                'Errors: ' . PHP_EOL . print_r($errors, 1));
            $tries--;

            return $this->query($data, $query, $extraHeaders, $tries);
        }

        return $request;
    }

    /**
     * @param  ShopifyCostDTO $cost
     *
     * @return bool
     */
    private function exceedsMaximumCost(ShopifyCostDTO $cost): bool
    {
        $requested = $cost->actualQueryCost ?? $cost->requestedQueryCost;

        return $requested > $cost->throttleStatus->maximumAvailable;
    }

    /**
     * @param  ShopifyCostDTO $cost
     *
     * @return int|null
     */
    private function calculateDelayCost(ShopifyCostDTO $cost): ?int
    {
        $requested = $cost->actualQueryCost ?? $cost->requestedQueryCost;
        $restoreAmount = max(0, $requested - $cost->throttleStatus->currentlyAvailable);

        return $cost->throttleStatus->restoreRate ?
            (ceil($restoreAmount / $cost->throttleStatus->restoreRate) * 1000) :
            null;
    }
}
