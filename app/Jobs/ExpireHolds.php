<?php

namespace App\Jobs;

use App\Enums\HoldingStatus;
use App\Models\Hold;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireHolds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Hold::where('status', HoldingStatus::ACTIVE->value)
            ->where('expires_at', '<', now()) // MS- Get expired holds
            ->chunk(100, function ($holds) { // MS- Process in chunks = 100 to avoid memory issues
                foreach ($holds as $hold) {
                    DB::transaction(function () use ($hold) {
                        $product = Product::lockForUpdate()->find($hold->product_id);
                        $product->reserved_stock -= $hold->qty;
                        $product->save();

                        $hold->status = HoldingStatus::EXPIRED->value;
                        $hold->save();

                        Cache::forget("product.{$hold->product_id}");
                        Log::info("Hold {$hold->id} expired, stock released");
                    });
                }
            });
    }
}
