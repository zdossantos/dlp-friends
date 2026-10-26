<?php

namespace App\Http\Requests\Admin;

use App\Models\ApplicationAnnouncement;
use App\Models\User;
use App\Rules\SafeHttpsUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PrepareApplicationAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ApplicationAnnouncement::class) === true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'title_fr' => ['required', 'string', 'max:80'],
            'title_en' => ['required', 'string', 'max:80'],
            'content_fr' => ['required', 'string', 'max:5000'],
            'content_en' => ['required', 'string', 'max:5000'],
            'destination_url' => ['exclude_if:is_release,true', 'nullable', 'string', 'max:2048', new SafeHttpsUrl],
            'is_release' => ['required', 'boolean'],
            'send_push' => ['sometimes', 'boolean'],
            'audience' => ['required', Rule::in(['all', 'selected'])],
            'recipient_ids' => ['exclude_unless:audience,selected', 'required_if:audience,selected', 'array', 'min:1', 'max:500'],
            'recipient_ids.*' => ['integer', 'distinct', Rule::exists(User::class, 'id')->where(fn ($query) => $query->whereIn('id', User::query()->eligibleForApplicationAnnouncements()->select('id')))],
        ];
    }
}
