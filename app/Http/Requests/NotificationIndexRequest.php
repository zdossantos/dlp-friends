<?php

namespace App\Http\Requests;

use App\Enums\NotificationCategory;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class NotificationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->input('category') !== NotificationCategory::Administration->value) {
            return true;
        }

        $user = $this->user();

        return $user instanceof User && $user->hasRole(RoleName::Admin);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::enum(NotificationCategory::class)],
            'unread' => ['nullable', 'boolean'],
        ];
    }
}
