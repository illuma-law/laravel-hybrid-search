<?php

declare(strict_types=1);

use IllumaLaw\HybridSearch\Concerns\EnsuresScoutKeyIsString;
use Illuminate\Database\Eloquent\Model;

it('returns the primary key cast to string', function (): void {
    $model = new class extends Model
    {
        use EnsuresScoutKeyIsString;

        protected $primaryKey = 'id';

        public function getKey(): mixed
        {
            return 42;
        }
    };

    expect($model->getScoutKey())->toBe('42');
});

it('returns the primary key name', function (): void {
    $model = new class extends Model
    {
        use EnsuresScoutKeyIsString;

        protected $primaryKey = 'uuid';
    };

    expect($model->getScoutKeyName())->toBe('uuid');
});
