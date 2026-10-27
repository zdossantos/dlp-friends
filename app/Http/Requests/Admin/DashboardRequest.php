<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Admin) ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'from' => ['bail', 'required_with:to', 'string', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:2000-01-01'],
            'to' => ['bail', 'required_with:from', 'string', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('from')) {
                return;
            }

            if ($this->string('from')->toString() > $this->string('to')->toString()) {
                $validator->errors()->add('to', __('validation.after_or_equal', ['attribute' => __('usage.to'), 'date' => $this->string('from')->toString()]));

                return;
            }

            if (CarbonImmutable::parse($this->string('from')->toString(), 'UTC')->diffInDays(CarbonImmutable::parse($this->string('to')->toString(), 'UTC')) >= 366) {
                $validator->errors()->add('to', __('usage.range_error'));
            }
        }];
    }
}
