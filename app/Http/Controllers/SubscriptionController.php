<?php

namespace App\Http\Controllers;

use App\Services\Shopify\ShopifyService;
use Exception;
use GuzzleHttp\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * SubscriptionController class
 *
 * @package App\Http\Controllers
 */
class SubscriptionController extends Controller
{
    public function getToken(Request $request, ShopifyService $shopifyService): JsonResponse
    {
        $entityBody = file_get_contents('php://input');
        $data = Utils::jsonDecode($entityBody, true);
        $onetimes = $data['onetime'] ?? [];
        $subscriptions = $data['subscription'] ?? [];

        try {
            $subscriptionProducts = $shopifyService->getSubscriptionProducts($subscriptions);
            $defaultProductVariants = $shopifyService->getDefaultProductVariants($subscriptionProducts);

            $items = array_map(function (array $item) {
                $newItem = [
                    'id' => $item['id'],
                    'quantity' => $item['qty'],
                ];
                if (isset($item['properties'])) {
                    $newItem['properties'] = $item['properties'];
                }

                return $newItem;
            }, array_merge($onetimes, $defaultProductVariants));

            if (!$items) {
                throw new Exception('List products is empty!');
            }

            return response()->json([
                'success' => true,
                'token' => $shopifyService->getCartToken($items),
            ]);
        } catch (Throwable $throwable) {
            return response()->json([
                'error' => true,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
