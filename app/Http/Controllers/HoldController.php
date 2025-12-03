<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateHoldRequest;
use App\Models\Hold;
use App\Models\Product;
use App\Services\ApiResponseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HoldController extends Controller
{
    public function create(CreateHoldRequest $request)
    {
        $productId = $request->product_id;
        $qty = $request->qty;

        // MS- Used DB Transaction To ensure Atomicity
        try {
            $hold = DB::transaction(function () use ($productId, $qty) {
                $product = Product::lockForUpdate()->findOrFail($productId); // MS- Locking the row for update to PREVENT another reading on it while in transaction.
                if ($product->available_stock < $qty) {
                    Log::warning("Out of stock for product {$productId}, qty {$qty}");
                    abort(400, 'Out of stock');
                }

                $product->reserved_stock += $qty;
                $product->save();

                $hold = Hold::create([
                    'product_id' => $productId,
                    'qty' => $qty,
                    'expires_at' => now()->addMinutes(2),
                ]);

                Cache::forget("product.{$productId}");
                Log::info("Hold created: {$hold->id} for product {$productId}, qty {$qty}");
                return $hold;
            });

            $userTz = $request->input('user_timezone', 'UTC');
            $expiresAt = $hold->expires_at->setTimezone($userTz);

            return ApiResponseService::success(["holdID" => $hold->id, "expiresAt" => $expiresAt->toIso8601String()], 'Hold created successfully', 201);
        } catch (\Exception $e) {
            Log::error("Hold creation failed: " . $e->getMessage());
            throw $e; // MS- May retry using Queues Later
        }
    }
}
