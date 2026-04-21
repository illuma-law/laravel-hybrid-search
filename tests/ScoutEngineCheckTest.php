<?php

declare(strict_types=1);

use IllumaLaw\HybridSearch\HealthChecks\ScoutEngineCheck;
use Spatie\Health\Enums\Status;

it('skips when scout driver is database', function (): void {
    config(['scout.driver' => 'database']);

    $result = ScoutEngineCheck::new()->run();

    expect($result->status)->toEqual(Status::skipped());
});

it('skips when scout driver is collection', function (): void {
    config(['scout.driver' => 'collection']);

    $result = ScoutEngineCheck::new()->run();

    expect($result->status)->toEqual(Status::skipped());
});

it('skips when scout driver is null', function (): void {
    config(['scout.driver' => 'null']);

    $result = ScoutEngineCheck::new()->run();

    expect($result->status)->toEqual(Status::skipped());
});
