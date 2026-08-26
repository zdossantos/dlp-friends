<?php

namespace App\Data;

final readonly class DiscoveryProfileData
{
    /**
     * @param  list<string>  $commonInterests
     * @param  list<array{name: string, isCommon: bool}>  $interests
     * @param  array{id: int, name: string, image_url: string, primary_color: string, secondary_color: string}  $avatar
     */
    public function __construct(
        public int $userId,
        public int $profileId,
        public string $displayName,
        public array $avatar,
        public int $age,
        public ?string $bio,
        public ?string $visitFrequency,
        public int $commonInterestCount,
        public array $commonInterests,
        public array $interests,
        public bool $frequencyBonus,
        public float $score,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'profileId' => $this->profileId,
            'displayName' => $this->displayName,
            'avatar' => $this->avatar,
            'age' => $this->age,
            'bio' => $this->bio,
            'visitFrequency' => $this->visitFrequency,
            'commonInterestCount' => $this->commonInterestCount,
            'commonInterests' => $this->commonInterests,
            'interests' => $this->interests,
            'frequencyBonus' => $this->frequencyBonus,
            'score' => $this->score,
        ];
    }
}
