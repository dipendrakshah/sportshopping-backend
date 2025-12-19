<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/products",
     *      operationId="getProducts",
     *      tags={"Products"},
     *      summary="List Products",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="List of products")
     * )
     */
    public function index(Request $request)
    {
        $query = Product::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        return response()->json($query->get());
    }

    /**
     * @OA\Get(
     *      path="/api/products/{id}",
     *      operationId="getProduct",
     *      tags={"Products"},
     *      summary="Get Product Details",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="Product details"),
     *      @OA\Response(response=404, description="Product not found")
     * )
     */
    public function show($id)
    {
        $product = Product::with('category')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json($product);
    }

    /**
     * @OA\Get(
     *      path="/api/products/search",
     *      operationId="searchProducts",
     *      tags={"Products"},
     *      summary="Search Products",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *      @OA\Response(response=200, description="Search results")
     * )
     */
    public function search(Request $request)
    {
        $keyword = $request->query('q');

        if (!$keyword) {
             return response()->json([]);
        }

        // Search by Product Name OR Category Name
        $products = Product::with('category')
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhereHas('category', function($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    })
                    ->get();

        return response()->json($products);
    }

    /**
     * @OA\Post(
     *      path="/api/products",
     *      operationId="storeProduct",
     *      tags={"Products"},
     *      summary="Create New Product",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name","price","stock"},
     *              @OA\Property(property="name", type="string"),
     *              @OA\Property(property="description", type="string"),
     *              @OA\Property(property="price", type="number", format="float"),
     *              @OA\Property(property="stock", type="integer"),
     *              @OA\Property(property="image_url", type="string", format="url"),
     *              @OA\Property(property="category_id", type="integer")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Product created"
     *      )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image_url' => 'nullable|url',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $product = Product::create($request->all());

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }
}
