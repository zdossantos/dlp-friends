<?php

namespace App\Http\Requests;

use App\Models\Interest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $interest = $this->route('interest');

        return $interest instanceof Interest
            && ($this->user()?->can('update', $interest) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['direction' => ['required', Rule::in(['up', 'down'])]];
    }
}
