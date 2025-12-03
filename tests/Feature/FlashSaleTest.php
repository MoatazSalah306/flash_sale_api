<?php

namespace Tests\Feature;

use App\Enums\HoldingStatus;
use App\Enums\OrderStatus;
use App\Jobs\ExpireHolds;
use App\Models\Hold;
use App\Models\Order;
use App\Models\Product;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;
use Carbon\Carbon;
use Database\Seeders\ProductSeeder;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
        
    }

    public function test_no_oversell_on_parallel_holds()
    {

        $product = Product::first();
        $product->update(['total_stock' => 3]);

        $successCount = 0;

        // MS- Try to create 5 holds concurrently
        for ($i = 0; $i < 5; $i++) {
            try {
                $response = $this->postJson('/api/holds', [
                    'product_id' => $product->id,
                    'qty' => 1,
                ]);

                if ($response->status() === 201) {
                    $successCount++;
                }
            } catch (\Exception $e) {
                // MS- Some might fail due to locks
            }
        }

        $this->assertEquals(3, $successCount);
    }

    public function test_hold_expiry_releases_stock()
    {
        $response = $this->postJson('/api/holds', ['product_id' => 1, 'qty' => 1]);
        $holdId = $response->json()['data']['holdID'];
        $product = Product::first();
        $this->assertEquals(1, $product->reserved_stock);

        Carbon::setTestNow(now()->addMinutes(3));
        (new ExpireHolds)->handle();

        $hold = Hold::find($holdId);
        $this->assertEquals(HoldingStatus::EXPIRED->value, $hold->status);
        $this->assertEquals(0, Product::first()->reserved_stock);
    }

    public function test_webhook_idempotent()
    {
        $hold = $this->postJson('/api/holds', ['product_id' => 1, 'qty' => 1])->json()['data']['holdID'];
        $order = $this->postJson('/api/orders', ['hold_id' => $hold])->json()['data']['orderID'];
        $payload = ['order_id' => $order, 'status' => 'success', 'idempotency_key' => Str::uuid()];

        $this->postJson('/api/payments/webhook', $payload);  // First
        $this->postJson('/api/payments/webhook', $payload);  // Duplicate

        Artisan::call('queue:work', ['--once' => true]);  // Process first
        $this->assertEquals(OrderStatus::PAID->value, Order::find($order)->status);
        $this->assertEquals(1, Order::find($order)->hold->product->sold_stock);

        Artisan::call('queue:work', ['--once' => true]);  // Duplicate ignored
        $this->assertEquals(OrderStatus::PAID->value, Order::find($order)->status);  // Unchanged
    }

    public function test_webhook_before_order()
    {

         
        $orderId = 9999;
        $idempotencyKey = Str::uuid()->toString();

        $response = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'success',
            'idempotency_key' => $idempotencyKey,
        ]);

        $response->assertStatus(202);

        $webhookLog = WebhookLog::where('idempotency_key', $idempotencyKey)->first();

        $this->assertNotNull($webhookLog);
        $this->assertNull($webhookLog->processed_at);


    }
}
