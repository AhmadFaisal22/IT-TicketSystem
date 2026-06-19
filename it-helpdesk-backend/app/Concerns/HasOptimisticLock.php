<?php

namespace App\Concerns;

/**
 * Optimistic concurrency control via a `version` column.
 *
 * Callers pass the version they originally read. The update only applies if the
 * row still holds that version (compare-and-set in a single atomic statement),
 * bumping it by one. If another writer already moved the version on, zero rows
 * match and we abort 409 instead of overwriting their changes (lost update).
 */
trait HasOptimisticLock
{
    public function optimisticUpdate(array $attributes, int $expectedVersion): static
    {
        $affected = static::query()
            ->whereKey($this->getKey())
            ->where('version', $expectedVersion)
            ->update($attributes + [
                'version'    => $expectedVersion + 1,
                'updated_at' => now(),
            ]);

        abort_if(
            $affected === 0,
            409,
            'This record was changed by someone else. Please reload and try again.'
        );

        return $this->refresh();
    }
}
