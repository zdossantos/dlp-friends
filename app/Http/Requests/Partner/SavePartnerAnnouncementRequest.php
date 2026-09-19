<?php

namespace App\Http\Requests\Partner;

use App\Models\PartnerAnnouncement;
use App\Rules\SafeHttpsUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class SavePartnerAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $announcement = $this->route('announcement');

        return $announcement instanceof PartnerAnnouncement
            ? $this->user()?->can('update', $announcement) === true
            : $this->user()?->can('create', PartnerAnnouncement::class) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'content' => ['required', 'string', 'max:500'],
            'destination_url' => ['required', 'string', 'max:2048', new SafeHttpsUrl],
        ];
    }

    /** @return array{title: string, content: string, destination_url: string} */
    public function announcementData(): array
    {
        return [
            'title' => $this->string('title')->toString(),
            'content' => $this->string('content')->toString(),
            'destination_url' => $this->string('destination_url')->toString(),
        ];
    }
}
