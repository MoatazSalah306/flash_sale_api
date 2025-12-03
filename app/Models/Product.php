<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'price', 'total_stock', 'reserved_stock', 'sold_stock'];

    public function holds()
    {
        return $this->hasMany(Hold::class);
    }
    
    public function getAvailableStockAttribute()
    {
        return $this->total_stock - $this->reserved_stock - $this->sold_stock;
    }
}
