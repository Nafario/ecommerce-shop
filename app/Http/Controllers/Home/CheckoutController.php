<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Customer\BillingAddress;
use App\Models\Customer\Country;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function index()
    {
        // dd($cartsProduct);
        return view('home.checkout');
    }
    public function apiIndex(Request $request)
    {
        $billingAddress = DB::table('billing_addresses')
            ->join('countries', 'billing_addresses.country_id', 'countries.id')
            ->select('countries.*', 'billing_addresses.*')
            ->where('billing_addresses.user_id', auth()->id())
            ->get()
            ->first();
        $countries = Country::all(['id', 'country_name']);
        return response()->json([$billingAddress, $countries]);
    }
    public function purchase(User $user)
    {
        $billingAddress = DB::table('billing_addresses')
            ->join('countries', 'billing_addresses.country_id', 'countries.id')
            ->select('countries.*', 'billing_addresses.*')
            ->where('billing_addresses.user_id', auth()->id())
            ->get()
            ->first();
        if ($billingAddress) {
            $user = User::findOrFail(auth()->id());
            try {
                $user->createOrGetStripeCustomer();
                $payment = $user->charge(
                    floor(request('amount')),
                    request('payment_method_id')
                );
                $payment = $payment->asStripePaymentIntent();
                $order = $user->orders()->create([
                    'transaction_id' => $payment->charges->data[0]->id,
                    'total' => $payment->charges->data[0]->amount,
                    'currency' => "USD"
                ]);
                $cartsProduct = Cart::join(
                    'products',
                    'carts.product_id',
                    'products.id'
                )
                    ->select(
                        'products.id',
                        'carts.quantity',
                        'carts.id as cart_id'
                    )
                    ->where('carts.user_id', auth()->id())
                    ->get();
                foreach ($cartsProduct as $product) {
                    $order->products()->attach($product['id'], [
                        'quantity' => $product['quantity'],
                    ]);
                    Cart::where('id', $product->cart_id)->delete();
                }
                return $order;
            } catch (\Throwable $th) {
                //throw $th;
                return response()->json(['message' => $th->getMessage()], 500);
            }
        } else {
            $address = request()->validate([
                'first_name' => ['string', 'required', 'min:3', 'max:50'],
                'last_name' => ['string', 'required', 'min:3', 'max:50'],
                'company_name' => ['string', 'min:3', 'max:50'],
                'country_id' => [''],
                'street_address' => ['string', 'required', 'min:3', 'max:50'],
                'city' => ['string', 'required', 'min:3', 'max:50'],
                'post_code' => ['string', 'required', 'min:3', 'max:50'],
                'phone' => ['string', 'required', 'min:3', 'max:50'],
                'email' => ['string', 'required', 'min:3', 'max:50', 'email'],
            ]);

            BillingAddress::create(
                array_merge($address, [
                    'user_id' => auth()->id(),
                ])
            );
            $user = User::findOrFail(auth()->id());
            try {
                $user->createOrGetStripeCustomer();
                $payment = $user->charge(
                    floor(request('amount')),
                    request('payment_method_id')
                );

                $payment = $payment->asStripePaymentIntent();

                $order = $user->orders()->create([
                    'transaction_id' => $payment->charges->data[0]->id,
                    'total' => $payment->charges->data[0]->amount,
                    'currency' => "USD"
                ]);
                $cartsProduct = Cart::join(
                    'products',
                    'carts.product_id',
                    'products.id'
                )
                    ->select('products.id', 'carts.quantity')
                    ->where('carts.user_id', auth()->id())
                    ->get();
                foreach (json_decode($cartsProduct, true) as $product) {
                    $order->products()->attach($product->id, [
                        'quantity' => $product->quantity,
                    ]);
                    Cart::where('id', $product->cart_id)->delete();
                }

                $order->load('products');
                return $order;
            } catch (\Throwable $th) {
                //throw $th;
                return response()->json(['message' => $th->getMessage()], 500);
            }
        }
    }
}
