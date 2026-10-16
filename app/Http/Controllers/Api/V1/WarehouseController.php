<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Warehouses\DeleteWarehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\ListWarehousesRequest;
use App\Http\Requests\Warehouses\StoreWarehouseRequest;
use App\Http\Requests\Warehouses\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class WarehouseController extends Controller
{
    /**
     * List warehouses.
     */
    #[Authorize('viewAny', Warehouse::class)]
    public function index(ListWarehousesRequest $request): AnonymousResourceCollection
    {
        $warehouses = Warehouse::query()
            // Case-insensitive exact match; LIKE wildcards in the input are escaped.
            ->when($request->filled('city'), fn (Builder $query) => $query->whereLike('city', addcslashes($request->string('city')->toString(), '\\%_')))
            ->orderBy('code')
            ->paginate($request->perPage())
            ->withQueryString();

        return WarehouseResource::collection($warehouses);
    }

    /**
     * Create a warehouse (admin only).
     */
    #[Authorize('create', Warehouse::class)]
    public function store(StoreWarehouseRequest $request): WarehouseResource
    {
        return new WarehouseResource(Warehouse::query()->create($request->validated()));
    }

    /**
     * Show a warehouse.
     */
    #[Authorize('view', 'warehouse')]
    public function show(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse);
    }

    /**
     * Update a warehouse (admin only).
     */
    #[Authorize('update', 'warehouse')]
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $warehouse->update($request->validated());

        return new WarehouseResource($warehouse);
    }

    /**
     * Delete a warehouse (admin only).
     *
     * Responds 409 `warehouse_in_use` once it has stock history or orders.
     */
    #[Authorize('delete', 'warehouse')]
    public function destroy(Warehouse $warehouse, DeleteWarehouse $deleteWarehouse): Response
    {
        $deleteWarehouse->handle($warehouse);

        return response()->noContent();
    }
}
