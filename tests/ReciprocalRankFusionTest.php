<?php

use IllumaLaw\HybridSearch\ReciprocalRankFusion;

it('combines multiple rankings using RRF', function () {
    $ranking1 = collect([1, 2, 3]);
    $ranking2 = collect([2, 1, 4]);

    $rankings = [
        'source1' => $ranking1,
        'source2' => $ranking2,
    ];

    $results = ReciprocalRankFusion::combine($rankings, 60);

    expect($results->get(1))->toBeGreaterThan($results->get(3));
    expect($results->get(2))->toBeGreaterThan($results->get(4));
    expect($results->keys()->first())->toBeIn([1, 2]);
});
