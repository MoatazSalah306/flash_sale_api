<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function show($id)
    {
        Log::info("Trying to Fetch product with ID : {$id}");
        $product = Cache::remember("product.{$id}", 60, function () use ($id) {
            $product = Product::findOrFail($id);
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'available_stock' => $product->available_stock,
            ];
        });
        
        return ApiResponseService::success($product, 'Product fetched successfully');
    }
}
