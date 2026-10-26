<?php

use Database\Factories\PartnerAnnouncementFactory;
use Tests\TestCase;

pest()->extend(TestCase::class);

it('generates announcement titles within the database limit even for long words', function () {
    fake()->seed(79340);

    try {
        expect(mb_strlen((new PartnerAnnouncementFactory)->definition()['title']))->toBeLessThanOrEqual(80);
    } finally {
        fake()->seed();
    }
});
