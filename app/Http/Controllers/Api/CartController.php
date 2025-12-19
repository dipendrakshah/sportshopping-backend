<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/cart",
     *      operationId="viewCart",
     *      tags={"Cart"},
     *      summary="View User Cart",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(response=200, description="Cart details")
     * )
     */
    public function viewCart(Request $request)
    {
        $cart = Cart::with('items.product')->where('user_id', $request->user()->id)->first();
        
        if (!$cart) {
             return response()->json(['items' => []]);
        }

        return response()->json($cart);
    }

    /**
     * @OA\Post(
     *      path="/api/cart",
     *      operationId="addToCart",
     *      tags={"Cart"},
     *      summary="Add Item to Cart",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"product_id","quantity"},
     *              @OA\Property(property="product_id", type="integer"),
     *              @OA\Property(property="quantity", type="integer")
     *          )
     *      ),
     *      @OA\Response(response=200, description="Item added")
     * )
     */
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $product = Product::find($request->product_id);

        // Check stock (simplified)
        if ($product->stock < $request->quantity) {
             return response()->json(['message' => 'Not enough stock'], 400);
        }

        $item = CartItem::where('cart_id', $cart->id)
                        ->where('product_id', $product->id)
                        ->first();

        if ($item) {
            $item->quantity += $request->quantity;
            $item->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $request->quantity,
            ]);
        }

        return response()->json(['message' => 'Item added to cart']);
    }

    /**
     * @OA\Delete(
     *      path="/api/cart/{itemId}",
     *      operationId="removeFromCart",
     *      tags={"Cart"},
     *      summary="Remove Item from Cart",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="itemId", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="Item removed"),
     *      @OA\Response(response=404, description="Item not found")
     * )
     */
    public function removeFromCart(Request $request, $itemId)
    {
        // Ensure user owns the cart item via cart
        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->first();

        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $item = CartItem::where('cart_id', $cart->id)->where('id', $itemId)->first();

        if ($item) {
            $item->delete();
            return response()->json(['message' => 'Item removed']);
        }

        return response()->json(['message' => 'Item not found in cart'], 404);
    }
}
