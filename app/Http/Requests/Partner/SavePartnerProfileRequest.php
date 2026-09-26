<?php

namespace App\Http\Requests\Partner;

use App\Models\PartnerProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class SavePartnerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $profile = $user->partnerProfile;

        return $profile instanceof PartnerProfile
            ? $user->can('update', $profile)
            : $user->can('create', PartnerProfile::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name_fr' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:100'],
            'description_fr' => ['required', 'string', 'max:500'],
            'description_en' => ['required', 'string', 'max:500'],
            'image' => [
                'nullable',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024)
                    ->dimensions(Rule::dimensions()
                        ->minWidth(640)
                        ->minHeight(360)
                        ->maxWidth(6000)
                        ->maxHeight(6000)),
            ],
        ];
    }

    /** @return array{name_fr: string, name_en: string, description_fr: string, description_en: string} */
    public function profileData(): array
    {
        return [
            'name_fr' => $this->string('name_fr')->toString(),
            'name_en' => $this->string('name_en')->toString(),
            'description_fr' => $this->string('description_fr')->toString(),
            'description_en' => $this->string('description_en')->toString(),
        ];
    }
}
