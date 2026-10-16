<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ListUsersRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class UserController extends Controller
{
    /**
     * List users (admin only).
     */
    #[Authorize('viewAny', User::class)]
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->when($request->filled('role'), fn (Builder $query) => $query->where('role', $request->string('role')->toString()))
            ->orderBy('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Create a user (admin only).
     */
    #[Authorize('create', User::class)]
    public function store(StoreUserRequest $request): UserResource
    {
        /** @status 201 */
        return new UserResource(User::query()->create($request->validated()));
    }
}
