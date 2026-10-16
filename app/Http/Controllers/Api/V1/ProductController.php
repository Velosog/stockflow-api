<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ListProductsRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class ProductController extends Controller
{
    /**
     * List products.
     */
    #[Authorize('viewAny', Product::class)]
    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->when($request->filled('search'), fn (Builder $query) => $query->search($request->string('search')->toString()))
            ->when($request->has('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * Create a product (admin, manager).
     */
    #[Authorize('create', Product::class)]
    public function store(StoreProductRequest $request): ProductResource
    {
        /** @status 201 */
        return new ProductResource(Product::query()->create($request->validated()));
    }

    /**
     * Show a product.
     */
    #[Authorize('view', 'product')]
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    /**
     * Update a product (admin, manager).
     */
    #[Authorize('update', 'product')]
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    /**
     * Soft-delete a product (admin, manager).
     *
     * Deleted products can no longer be ordered; their history is kept.
     */
    #[Authorize('delete', 'product')]
    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted product (admin, manager).
     */
    #[Authorize('restore', 'product')]
    public function restore(Product $product): ProductResource
    {
        $product->restore();

        return new ProductResource($product);
    }
}
