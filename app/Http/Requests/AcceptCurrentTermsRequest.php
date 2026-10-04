<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

final class AcceptCurrentTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === UserStatus::Active;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['accepted' => ['required', 'accepted']];
    }
}
