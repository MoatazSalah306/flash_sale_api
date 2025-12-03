<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentWebhookRequest;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function webhook(PaymentWebhookRequest $request) {
        $key = $request->idempotency_key;
        $orderId = $request->order_id;
        $status = $request->status;
        
        $log = WebhookLog::firstOrNew(['idempotency_key' => $key]);
        if ($log->processed_at) {
            Log::info("Duplicate webhook ignored: key {$key}");
            return response('OK', 200);
        }
        
        $log->order_id = $orderId;
        $log->status = $status;
        $log->save();
        
        ProcessPaymentWebhook::dispatch($log->id);
        Log::info("Webhook queued: key {$key}, order {$orderId}, status {$status}");
        return response('Accepted', 202);
    }
}
