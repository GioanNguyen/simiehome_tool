<?php

namespace App\Domain\Pricing\Calculators;

/**
 * Shared helper for reading the user-entered custom size out of the
 * calculation context. Only LengthRatioCalculator actually uses this value
 * in its formula, but every calculator still needs to echo it back onto the
 * LineCalculationResult it returns — otherwise a size the sales person
 * typed into "Kích thước tuỳ chỉnh (cm)" silently disappears from the
 * saved quote/PDF (and from the confirm→back-to-edit prefill) for the
 * large majority of products, which price via the default catalog_price
 * formula and don't need length_cm for the math at all.
 */
trait ReadsContextLength
{
    /**
     * @param  array{length_cm?: int|string|null}  $context
     */
    private function contextLengthCm(array $context): ?int
    {
        $lengthCm = (int) ($context['length_cm'] ?? 0);

        return $lengthCm > 0 ? $lengthCm : null;
    }
}
