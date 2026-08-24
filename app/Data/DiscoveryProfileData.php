<?php

namespace App\Data;

final readonly class DiscoveryProfileData
{
    /**
     * @param  list<string>  $commonInterests
     */
    public function __construct(
        public int $userId,
        public int $profileId,
        public string $displayName,
        public int $age,
        public ?string $bio,
        public ?string $visitFrequency,
        public int $commonInterestCount,
        public array $commonInterests,
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
            'age' => $this->age,
            'bio' => $this->bio,
            'visitFrequency' => $this->visitFrequency,
            'commonInterestCount' => $this->commonInterestCount,
            'commonInterests' => $this->commonInterests,
            'frequencyBonus' => $this->frequencyBonus,
            'score' => $this->score,
        ];
    }
}
