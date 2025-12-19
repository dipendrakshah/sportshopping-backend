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
    /**
     * @OA\Post(
     *      path="/api/orders",
     *      operationId="placeOrder",
     *      tags={"Orders"},
     *      summary="Place Order from Cart",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(
     *          response=201,
     *          description="Order placed successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="order_id", type="integer")
     *          )
     *      ),
     *      @OA\Response(response=400, description="Cart is empty")
     * )
     */
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
                // Check stock
                if ($item->product->stock < $item->quantity) {
                    throw new \Exception("Product {$item->product->name} does not have enough stock");
                }
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

            // Send Email
            Mail::to($user->email)->send(new OrderConfirmation($order));
            
            return response()->json([
                'message' => 'Order placed successfully',
                'order_id' => $order->id
            ], 201);
        });
    }

    /**
     * @OA\Post(
     *      path="/api/orders/{id}/cancel",
     *      operationId="cancelOrder",
     *      tags={"Orders"},
     *      summary="Cancel Order",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="Order cancelled")
     * )
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('items.product')->where('id', $id)->where('user_id', $user->id)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Cannot cancel order that is not pending'], 400);
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                // Restore stock
                $item->product->increment('stock', $item->quantity);
            }

            $order->status = 'cancelled';
            $order->save();

            return response()->json(['message' => 'Order cancelled and stock restored']);
        });
    }

    /**
     * @OA\Post(
     *      path="/api/admin/orders/{id}/refund",
     *      operationId="refundOrder",
     *      tags={"Admin"},
     *      summary="Refund Order (Admin)",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="Order refunded")
     * )
     */
    public function refund(Request $request, $id)
    {
        $order = Order::with('items.product')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->status === 'refunded') {
            return response()->json(['message' => 'Order already refunded'], 400);
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                // Restore stock
                $item->product->increment('stock', $item->quantity);
            }

            $order->status = 'refunded';
            $order->save();

            // Here we would trigger the actual payment refund via gateway (Stripe/Paypal)
            // But for this project we just update the status

            return response()->json(['message' => 'Order refunded and stock restored']);
        });
    }
}
