<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Notifications are always read through the authenticated user's own
 * relation: another user's notification id is simply not found (404), so
 * ownership is enforced by the query itself and existence is not leaked.
 */
final class NotificationController extends Controller
{
    /**
     * List the authenticated user's notifications, newest first.
     */
    public function index(ListNotificationsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->paginate($request->perPage())
            ->withQueryString();

        return NotificationResource::collection($notifications);
    }

    /**
     * Mark one of the authenticated user's notifications as read.
     */
    public function markAsRead(Request $request, string $notification): NotificationResource
    {
        /** @var User $user */
        $user = $request->user();

        $model = $user->notifications()->whereKey($notification)->firstOrFail();
        $model->markAsRead();

        return new NotificationResource($model);
    }
}
