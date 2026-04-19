<?php

declare(strict_types=1);

namespace IllumaLaw\HybridSearch;

use Illuminate\Support\Collection;

final class ReciprocalRankFusion
{
    private const DEFAULT_K = 60;

    /**
     * @param  array<string, Collection<int, string|int>>  $rankings  Map of source name to collection of item IDs in ranked order.
     * @param  int  $k  The RRF constant (usually 60).
     * @return Collection<string|int, float> Map of item ID to RRF score, sorted descending.
     */
    public static function combine(array $rankings, int $k = self::DEFAULT_K): Collection
    {
        $scores = [];

        foreach ($rankings as $items) {
            foreach ($items->values() as $index => $id) {
                $rank = $index + 1;
                $scores[$id] = ($scores[$id] ?? 0.0) + (1.0 / ($k + $rank));
            }
        }

        return collect($scores)->sortDesc();
    }
}
