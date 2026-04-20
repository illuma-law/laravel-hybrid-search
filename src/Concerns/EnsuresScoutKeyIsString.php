<?php

declare(strict_types=1);

namespace IllumaLaw\HybridSearch\Concerns;

trait EnsuresScoutKeyIsString
{
    public function getScoutKey(): string
    {
        $key = $this->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    public function getScoutKeyName(): string
    {
        return $this->getKeyName();
    }
}
