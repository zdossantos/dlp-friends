<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Visit::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['starts_on' => ['bail', 'required', 'date_format:Y-m-d'], 'ends_on' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:starts_on']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $start = CarbonImmutable::parse($this->string('starts_on')->toString(), 'Europe/Paris');
            $end = CarbonImmutable::parse($this->string('ends_on')->toString(), 'Europe/Paris');
            $today = CarbonImmutable::today('Europe/Paris');
            $visit = $this->route('visit');
            $retained = $visit instanceof Visit && $visit->starts_on->toDateString() === $start->toDateString() && $visit->ends_on->toDateString() >= $today->toDateString();
            if ($start->lt($today) && ! $retained) {
                $validator->errors()->add('starts_on', __('visits.validation.future'));
            }
            if ($end->lt($today) || $end->gt($today->addMonthsNoOverflow(12))) {
                $validator->errors()->add('ends_on', __('visits.validation.horizon'));
            }
            if ($start->diffInDays($end) > 29) {
                $validator->errors()->add('ends_on', __('visits.validation.duration'));
            }
        }];
    }
}
