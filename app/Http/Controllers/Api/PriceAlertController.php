<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Alerts\Actions\CancelPriceAlert;
use App\Alerts\Actions\CreatePriceAlert;
use App\Alerts\AlertStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePriceAlertRequest;
use App\Http\Resources\PriceAlertResource;
use App\Models\PriceAlert;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class PriceAlertController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::enum(AlertStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $alerts = $this->user($request)->priceAlerts()
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20));

        return PriceAlertResource::collection($alerts);
    }

    public function store(StorePriceAlertRequest $request, CreatePriceAlert $action): PriceAlertResource
    {
        $alert = $action->create($this->user($request), $request->target(), $request->direction());

        return PriceAlertResource::make($alert);
    }

    public function show(PriceAlert $alert): PriceAlertResource
    {
        Gate::authorize('view', $alert);

        return PriceAlertResource::make($alert);
    }

    public function destroy(PriceAlert $alert, CancelPriceAlert $action): Response
    {
        Gate::authorize('delete', $alert);

        $action->cancel($alert);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
