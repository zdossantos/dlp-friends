<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VisitIndexRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['month' => ['sometimes', 'date_format:Y-m'], 'day' => ['sometimes', 'date_format:Y-m-d'], 'view' => ['sometimes', 'in:agenda,calendar'], 'audience' => ['sometimes', 'in:members,matches'], 'search' => ['nullable', 'string', 'max:100'], 'member' => ['nullable', 'integer', 'min:1'], 'page' => ['sometimes', 'integer', 'min:1']];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->filled('day') && substr($this->string('day')->toString(), 0, 7) !== $this->string('month', CarbonImmutable::today('Europe/Paris')->format('Y-m'))->toString()) {
                $validator->errors()->add('day', __('visits.validation.month'));
            }
        }];
    }

    /** @return array{month:string,day:string,view:string,audience:string,search:string,member:int|null} */
    public function filters(): array
    {
        $today = CarbonImmutable::today('Europe/Paris');
        $month = $this->string('month', $today->format('Y-m'))->toString();

        return ['month' => $month, 'day' => $this->string('day', str_starts_with($today->toDateString(), $month) ? $today->toDateString() : $month.'-01')->toString(), 'view' => $this->string('view', 'agenda')->toString(), 'audience' => $this->string('audience', 'members')->toString(), 'search' => trim($this->string('search')->toString()), 'member' => $this->filled('member') ? $this->integer('member') : null];
    }
}
