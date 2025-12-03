<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = ['idempotency_key', 'order_id', 'status', 'processed_at'];
}
