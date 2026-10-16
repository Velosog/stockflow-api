<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ListStockLevelsRequest;
use App\Http\Resources\StockLevelResource;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class StockLevelController extends Controller
{
    /**
     * List current stock per product and warehouse.
     *
     * `low_stock=true` keeps only levels whose available quantity is below the
     * product's low_stock_threshold.
     */
    #[Authorize('viewAny', StockLevel::class)]
    public function index(ListStockLevelsRequest $request): AnonymousResourceCollection
    {
        $levels = StockLevel::query()
            ->with(['product', 'warehouse'])
            ->when($request->filled('product_id'), fn (Builder $query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn (Builder $query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->boolean('low_stock'), fn (Builder $query) => $query->whereHas(
                'product',
                fn (Builder $product) => $product->whereColumn('products.low_stock_threshold', '>', 'stock_levels.available'),
            ))
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->paginate($request->perPage())
            ->withQueryString();

        return StockLevelResource::collection($levels);
    }
}
