<?php

namespace App\Components\Shopify\Query;

/**
 * DefaultProductVariantsQuery class
 *
 * @package App\Components\Shopify\Query
 */
class DefaultProductVariantsQuery
{
    /**
     * @param  int         $limit
     * @param  string|null $after
     * @param  string|null $filter
     * @param  string      $sortKey
     *
     * @return array
     */
    public static function build(
        int $limit,
        ?string $after = null,
        ?string $filter = null,
        string $sortKey = "POSITION"
    ): array {
        $query = '
            query DefaultProductVariants($first: Int!, $after: String, $query: String, $sortKey: ProductVariantSortKeys) {
                products(first:$first after:$after query:$query) {
                    nodes {
                        id
                        variants(first: 1, sortKey: $sortKey) {
                            nodes {
                                id
                            }
                        }
                    }
                }
            }
        ';

        return [
            'query' => $query,
            'variables' => [
                'first' => $limit,
                'query' => $filter,
                'sortKey' => $sortKey,
                'after' => $after,
            ],
        ];
    }
}
