<?php

namespace App\Components\Shopify\Query;

/**
 * SubscriptionProductsQuery class
 *
 * @package App\Components\Shopify\Query
 */
class SubscriptionProductsQuery
{
    /**
     * @param  array $variantIds
     *
     * @return array
     */
    public static function build(array $variantIds): array
    {
        $query = '
            query SubscriptionProducts($variantIds: [ID!]!) {
                nodes(ids: $variantIds) {
                    ... on ProductVariant {
                        id
                        product {
                            id
                            metafields(first: 1, keys: "subscriptions.discount_product_id") {
                                nodes {
                                    value
                                }
                            }
                        }
                    }
                }
            }
        ';

        return [
            'query' => $query,
            'variables' => [
                'variantIds' => $variantIds,
            ],
        ];
    }
}
