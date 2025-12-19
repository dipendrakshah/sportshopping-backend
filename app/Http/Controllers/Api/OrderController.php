<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmation;

class OrderController extends Controller
{
    public function placeOrder(Request $request)
    {
        $user = $request->user();
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        return DB::transaction(function () use ($user, $cart) {
            $totalAmount = 0;
            foreach ($cart->items as $item) {
                // Determine price (current product price)
                $totalAmount += $item->product->price * $item->quantity;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                ]);
                
                // Decrement stock
                $item->product->decrement('stock', $item->quantity);
            }

            // Clear cart
            $cart->items()->delete();
            // Optional: delete cart itself or keep it empty for next time.
            // keeping it usually fine, or delete items only.

            // Send Email
            Mail::to($user->email)->send(new OrderConfirmation($order));
            
            return response()->json([
                'message' => 'Order placed successfully',
                'order_id' => $order->id
            ], 201);
        });
    }
}
