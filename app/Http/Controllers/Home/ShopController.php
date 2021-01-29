<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index()
    {
        $brands = Brand::all('name', 'id');
        $list = [
            'id',
            'title',
            'price',
            'old_price',
            'discount',
            'slug',
            'thumbnail',
        ];
        if (request()->has('sort')) {
            $products = Product::orderBy('title', request('sort'))
                ->paginate(12, $list)
                ->withQueryString();
        } elseif (request()->has('price')) {
            $products = Product::orderBy('price', request('price'))
                ->paginate(12, $list)
                ->withQueryString();
        } else {
            $products = Product::latest()
                ->paginate(12, $list)
                ->withQueryString();
        }
        return view('home.shop.index', compact('brands', 'products'));
    }
}
