<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\WebhookLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $logId;
    public function __construct($logId)
    {
        $this->logId = $logId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = WebhookLog::findOrFail($this->logId);
        if ($log->processed_at) return;
        
        $order = Order::with('hold.product')->find($log->order_id);
        if (!$order) {
            Log::info("Order {$log->order_id} not found, retrying webhook {$log->id}");
            $this->release(5); 
            return;
        }
        
        DB::transaction(function () use ($order, $log) {
            $order->refresh()->lockForUpdate(); 
            $product = Product::lockForUpdate()->findOrFail($order->hold->product_id);
            if ($order->status !== OrderStatus::PENDING->value) return; 
            
            $qty = $order->hold->qty;
            if ($log->status === 'success') {
                $order->status = OrderStatus::PAID->value;
                $product->reserved_stock -= $qty;
                $product->sold_stock += $qty;
                Log::info("Order {$order->id} paid, stock updated");
            } else {
                $order->status = OrderStatus::CANCELLED->value;
                $product->reserved_stock -= $qty;
                Log::info("Order {$order->id} cancelled, stock released");
            }
            $order->save();
            $product->save();
            Cache::forget("product.{$product->id}");
        });
        
        $log->processed_at = now();
        $log->save();
    }
}
