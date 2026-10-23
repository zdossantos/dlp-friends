<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Must be called inside the transition transaction, before locking trips and registrations. */
final class LockTripAccounts
{
    /** @param list<int|null> $ids
     * @return Collection<int, User>
     */
    public function handle(array $ids): Collection
    {
        return User::query()->whereIn('id', array_filter($ids))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }
}
