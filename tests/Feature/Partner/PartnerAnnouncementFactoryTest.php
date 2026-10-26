<?php

use Database\Factories\PartnerAnnouncementFactory;

test('partner announcement factory titles fit the schema even for long sentences', function () {
    fake()->seed(79340);

    try {
        expect(mb_strlen((new PartnerAnnouncementFactory)->definition()['title']))->toBeLessThanOrEqual(80);
    } finally {
        fake()->seed();
    }
});
