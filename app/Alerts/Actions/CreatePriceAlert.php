<?php

declare(strict_types=1);

namespace App\Alerts\Actions;

use App\Alerts\AlertStatus;
use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Alerts\DirectionResolver;
use App\Alerts\Exceptions\AlertRejected;
use App\Models\PriceAlert;
use App\Models\User;
use App\Pricing\Price;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreatePriceAlert
{
    public function __construct(
        private readonly DirectionResolver $resolver,
        private readonly AlertIndex $index,
        private readonly Config $config,
    ) {}

    /**
     * @throws AlertRejected
     */
    public function create(User $user, Price $target, ?Direction $requested): PriceAlert
    {
        $resolved = $this->resolver->resolve($target, $requested);

        $limit = (int) $this->config->get('gold.max_alerts_per_user');

        if ($user->priceAlerts()->count() >= $limit) {
            throw AlertRejected::limitReached($limit);
        }

        return DB::transaction(function () use ($user, $target, $resolved): PriceAlert {
            $this->replaceFailedDuplicate($user, $resolved->direction, $target);

            try {
                $alert = $user->priceAlerts()->create([
                    'direction' => $resolved->direction,
                    'target_price' => $target,
                    'reference_price' => $resolved->reference,
                    'status' => AlertStatus::Active,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw AlertRejected::duplicate();
            }

            // Index only once the row is committed, and never let an index
            // outage turn a stored alert into an error: the reconcile pass
            // repairs the drift within a minute.
            DB::afterCommit(function () use ($alert): void {
                try {
                    retry(3, fn () => $this->index->add($alert->id, $alert->direction, $alert->target_price), 50);
                } catch (Throwable $e) {
                    report($e);
                }
            });

            return $alert;
        });
    }

    /**
     * A previous alert at the same level that could not be delivered gives way
     * to a fresh one; a live duplicate is refused.
     */
    private function replaceFailedDuplicate(User $user, Direction $direction, Price $target): void
    {
        $existing = $user->priceAlerts()
            ->where('direction', $direction)
            ->where('target_price', $target->minor)
            ->first();

        if ($existing === null) {
            return;
        }

        if ($existing->status !== AlertStatus::Failed) {
            throw AlertRejected::duplicate();
        }

        $existing->delete();
        $this->index->remove($existing->id);
    }
}
