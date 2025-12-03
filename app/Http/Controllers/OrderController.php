<?php

namespace App\Http\Controllers;

use App\Enums\HoldingStatus;
use App\Http\Requests\CreateOrderRequest;
use App\Models\Hold;
use App\Models\Order;
use App\Services\ApiResponseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class OrderController extends Controller
{
    public function create(CreateOrderRequest $request) {
        $holdId = $request->hold_id;
        
        $order = DB::transaction(function () use ($holdId) {
            $hold = Hold::lockForUpdate()->with('product')->findOrFail($holdId);
            if ($hold->status == HoldingStatus::EXPIRED->value || $hold->expires_at < now()) {
                Log::warning("Invalid hold {$holdId}");
                abort(400, 'Invalid or expired hold');
            }
            if ($hold->order) abort(400, 'Hold already used, each Hold can be used only once to create an Order');
            
            $hold->status = HoldingStatus::USED->value;
            $hold->save();
            
            $order = Order::create(['hold_id' => $holdId]);
            Cache::forget("product.{$hold->product_id}");
            Log::info("Order created: {$order->id} from hold {$holdId}");
            return $order;
        });
        
        return ApiResponseService::success(["orderID" => $order->id], 'Order created successfully', 201);

    }
}
