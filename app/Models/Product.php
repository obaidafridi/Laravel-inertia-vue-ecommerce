<?php

namespace App\Models;

use App\Traits\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use DB;

class Product extends Model
{
    use HasFactory, Sluggable;

    protected $casts = [
        'price' => 'decimal:2'
    ];

    protected $with = ['images'];

    public function scopeWithCategories($query, $categorySlugs)
    {
        $query->when($categorySlugs, function ($query) use ($categorySlugs) {
            $query->whereHas('category', function ($query) use ($categorySlugs) {
                if (Str::contains($categorySlugs, ',')) {
                    $query->whereIn('slug', explode(',', $categorySlugs));
                } else {
                    $query->where('slug', $categorySlugs);
                }
            });
        });
    }

    public function scopeWithMinPrice($query, $min_price)
    {
        $query->when($min_price, function ($query) use ($min_price) {
            $query->where('price', '>=', $min_price);
        });
    }

    public function scopeWithMaxPrice($query, $max_price)
    {
        $query->when($max_price, function ($query) use ($max_price) {
            $query->where('price', '<=', $max_price);
        });
    }

    public function scopeWithSearch($query, $search)
    {
        $query->when($search, function ($query) use ($search) {
            $query->where('name', 'LIKE', "%$search%");
        });
    }

    public function scopeWithSortBy($query, $sortBy)
    {
        $query->when($sortBy, function ($query) use ($sortBy) {
            switch ($sortBy) {
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'best_selling':
                    $subquery = DB::table('order_product')
                        ->selectRaw('product_id, SUM(quantity) as total_quantity')
                        ->groupBy('product_id');

                    $query
                        ->selectRaw('products.*, COALESCE(sub.total_quantity, 0) as best_selling')
                        ->leftJoinSub($subquery, 'sub', 'products.id', '=', 'sub.product_id')
                        ->orderBy('best_selling', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
                    break;
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_product')
            ->withPivot(['quantity', 'unit_price'])
            ->withTimestamps();
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}
