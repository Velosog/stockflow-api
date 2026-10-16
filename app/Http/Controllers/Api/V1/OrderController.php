<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Actions\Orders\CreateOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CancelOrderRequest;
use App\Http\Requests\Orders\ListOrdersRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class OrderController extends Controller
{
    /**
     * List orders, newest first.
     */
    #[Authorize('viewAny', Order::class)]
    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with('items.product')
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('warehouse_id'), fn (Builder $query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    /**
     * Create an order and reserve its stock.
     *
     * Send an `Idempotency-Key` header to make retries safe: repeating the
     * same key and body returns the original response without a new order.
     * Responds 409 `insufficient_stock` (with per-item shortages) when any
     * line cannot be reserved; in that case nothing is reserved.
     */
    #[Authorize('create', Order::class)]
    public function store(StoreOrderRequest $request, CreateOrder $createOrder): OrderResource
    {
        /** @var User $user */
        $user = $request->user();

        $order = $createOrder->handle(
            $user,
            $request->integer('warehouse_id'),
            $request->lines(),
            $request->filled('customer_reference') ? $request->string('customer_reference')->toString() : null,
        );

        /** @status 201 */
        return new OrderResource($order->load('items.product'));
    }

    /**
     * Show an order with its items.
     */
    #[Authorize('view', 'order')]
    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load('items.product'));
    }

    /**
     * Confirm a pending order (managers, admins): reserved stock ships.
     *
     * Responds 409 `invalid_status_transition` if the order is not pending.
     */
    #[Authorize('confirm', 'order')]
    public function confirm(Request $request, Order $order, ConfirmOrder $confirmOrder): OrderResource
    {
        /** @var User $user */
        $user = $request->user();

        return new OrderResource($confirmOrder->handle($order, $user)->load('items.product'));
    }

    /**
     * Cancel a pending order and release its reserved stock.
     *
     * Managers and admins may cancel any order; operators only their own.
     */
    #[Authorize('cancel', 'order')]
    public function cancel(CancelOrderRequest $request, Order $order, CancelOrder $cancelOrder): OrderResource
    {
        /** @var User $user */
        $user = $request->user();
        $reason = $request->filled('reason') ? $request->string('reason')->toString() : null;

        return new OrderResource($cancelOrder->handle($order, $user, $reason)->load('items.product'));
    }
}
