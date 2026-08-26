<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

abstract class BrowserTestCase extends TestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($user instanceof User && $user->locale === null) {
            $user->update(['locale' => 'fr']);
        }

        return parent::actingAs($user, $guard);
    }

    protected function usesViteAssets(): bool
    {
        return true;
    }
}
