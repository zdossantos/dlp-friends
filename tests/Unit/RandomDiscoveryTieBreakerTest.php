<?php

use App\Services\RandomDiscoveryTieBreaker;

test('random discovery ranks stay stable throughout one streamed selection', function () {
    $tieBreaker = new RandomDiscoveryTieBreaker;
    $first = $tieBreaker->rank(42);
    foreach (range(1, 205) as $id) {
        $tieBreaker->rank($id);
    }
    expect($tieBreaker->rank(42))->toBe($first);
});
