<?php

namespace App\Http\Requests;

use App\Enums\SwipeDecision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreSwipeRequest extends DiscoveryRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'decision' => ['required', Rule::enum(SwipeDecision::class)],
        ];
    }
}
