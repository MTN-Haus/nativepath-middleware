<?php

namespace App\Services\Shopify;

use App\Components\Shopify\Query\DefaultProductVariantsQuery;
use App\Components\Shopify\Query\SubscriptionProductsQuery;
use App\Components\Shopify\ShopifyGraphqlClient;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ShopifyService class
 *
 * @package App\Services\Shopify
 */
class ShopifyService
{
    public const REQUEST_TRIES = 2;

    private ShopifyGraphqlClient $graphql;
    private Client $client;

    public function __construct()
    {
        $this->graphql = App::make(ShopifyGraphqlClient::class, ['domain' => env('SHOPIFY_SHOP')]);
        $this->client = App::make(Client::class, [
            'config' => [
                'base_uri' => 'https://' . env('SHOPIFY_SHOP'),
            ],
        ]);
    }

    /**
     *
     * @return ShopifyGraphqlClient
     */
    public function getGraphqlClient(): ShopifyGraphqlClient
    {
        return $this->graphql;
    }

    /**
     * @param  string|array $query
     * @param  string       $field
     * @param  array        $data
     *
     * @return array
     */
    public function graphqlQuery(string|array $query, string $field, array $data = []): array
    {
        try {
            $response = $this->graphql->query($query, [], [], self::REQUEST_TRIES);
            $result = $response->getDecodedBody() ?? [];
            if ($result['errors'] ?? []) {
                throw new Exception(ucfirst($field) . ': ' . $result['errors'][0]['message'] .
                    ($data ? PHP_EOL . print_r($data, 1) : ''));
            }
            $data = $result['data'][$field] ?? [];
            if ($data['userErrors'] ?? []) {
                throw new Exception(ucfirst($field) . ': ' . $data['userErrors'][0]['message'] .
                    ($data ? PHP_EOL . print_r($data, 1) : ''));
            }
        } catch (Throwable $throwable) {
            Log::error('Graphql query: ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString());
            throw new Exception($throwable->getMessage());
        }

        return $data;
    }

    /**
     * @param  array $subscriptions
     *
     * @return array
     */
    public function getSubscriptionProducts(array $subscriptions): array
    {
        $variantIds = array_map(function (array $item) {
            $variantId = $item['id'] ?? '';
            if (str_starts_with($variantId, 'gid://shopify/ProductVariant/')) {
                return $variantId;
            }

            return preg_match('#\d+#', $variantId) ? "gid://shopify/ProductVariant/{$item['id']}" : null;
        }, $subscriptions);

        $subscriptionVariants = $this->graphqlQuery(
            SubscriptionProductsQuery::build(array_filter($variantIds)),
            'nodes'
        ) ?? [];
        $items = [];
        foreach ($subscriptions as $subscription) {
            foreach ($subscriptionVariants as $subscriptionVariant) {
                $variantId = filter_var(($subscriptionVariant['id'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
                $productId = $subscriptionVariant['product']['metafields']['nodes'][0]['value'] ?? null;
                if ($variantId && $productId && $variantId === strval($subscription['id'] ?? null)) {
                    $items[] = [
                        'id' => $variantId,
                        'qty' => $subscription['qty'] ?? 0,
                        'product_id' => $productId,
                        'properties' => $subscription['properties'] ?? [],
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * @param  array $subscriptionProducts
     *
     * @return array
     */
    public function getDefaultProductVariants(array $subscriptionProducts): array
    {
        $query = implode(" OR ", array_map(function ($item) {
            return "id:{$item['product_id']}";
        }, $subscriptionProducts));

        $defaultProductVariants = $this->graphqlQuery(
            DefaultProductVariantsQuery::build(200, null, $query),
            'products'
        )['nodes'] ?? [];
        $items = [];
        foreach ($subscriptionProducts as $subscriptionProduct) {
            foreach ($defaultProductVariants as $subscriptionVariant) {
                $productId = filter_var(($subscriptionVariant['id'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
                $variantId =  filter_var(
                    ($subscriptionVariant['variants']['nodes'][0]['id'] ?? ''),
                    FILTER_SANITIZE_NUMBER_INT
                );
                if ($productId && $variantId && $productId === strval($subscriptionProduct['product_id'] ?? null)) {
                    $properties = $subscriptionProduct['properties'] ?? [];
                    $properties['shipping_interval_frequency'] ??= 30;
                    $items[] = [
                        'id' => $variantId,
                        'qty' => $subscriptionProduct['qty'] ?? 0,
                        'properties' => $properties,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * @param  array       $items
     *
     * @return string|null
     */
    public function getCartToken(array $items): ?string
    {
        $response = $this->client->post('cart/add.js', [
            'json' => [
                'items' => $items,
            ],
        ]);

        $cookies = $response->getHeaderLine('Set-Cookie');
        preg_match('#(^|\s)cart=([^;]+);#i', $cookies, $matches);

        return $matches[2] ?? null;
    }
}
