<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Stock\RecordStockMovement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ListStockMovementsRequest;
use App\Http\Requests\Stock\RecordStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Gate;

final class StockMovementController extends Controller
{
    /**
     * List ledger entries, newest first.
     */
    #[Authorize('viewAny', StockMovement::class)]
    public function index(ListStockMovementsRequest $request): AnonymousResourceCollection
    {
        $movements = StockMovement::query()
            ->when($request->filled('product_id'), fn (Builder $query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn (Builder $query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('order_id'), fn (Builder $query) => $query->where('order_id', $request->integer('order_id')))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')->toString()))
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return StockMovementResource::collection($movements);
    }

    /**
     * Record goods received (`inbound`, any role) or a stock count correction
     * (`adjustment`, managers and admins; requires a note).
     */
    public function store(RecordStockMovementRequest $request, RecordStockMovement $recordStockMovement): StockMovementResource
    {
        $type = $request->movementType();
        // The allowed roles depend on the movement type in the body, so this
        // check runs after validation instead of in an #[Authorize] attribute.
        Gate::authorize('record', [StockMovement::class, $type]);

        /** @var User $user */
        $user = $request->user();

        $movement = $recordStockMovement->handle(
            $user,
            $request->integer('product_id'),
            $request->integer('warehouse_id'),
            $type,
            $request->integer('quantity'),
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        /** @status 201 */
        return new StockMovementResource($movement);
    }
}
