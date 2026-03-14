<?php

namespace App\Benchmark\Traits;

use App\Benchmark\Enums\OrderStatus;

trait HasStateTransitions
{
    /** @var list<OrderStatus> */
    protected array $history = [];

    protected OrderStatus $status = OrderStatus::Draft;

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function transitionTo(OrderStatus $status): bool
    {
        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $this->history[] = $this->status;
        $this->status = $status;

        return true;
    }

    /**
     * @return list<OrderStatus>
     */
    public function statusHistory(): array
    {
        return $this->history;
    }
}
