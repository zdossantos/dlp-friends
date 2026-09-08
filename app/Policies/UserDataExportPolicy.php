<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDataExport;

final class UserDataExportPolicy
{
    public function download(User $user, UserDataExport $export): bool
    {
        return $export->user_id === $user->id;
    }
}
