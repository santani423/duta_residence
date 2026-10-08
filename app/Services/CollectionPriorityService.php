<?php

namespace App\Services;

use App\Models\CollectionAccountState;

/**
 * Skor prioritas penagihan 0–100. Bobot, batas, dan ambang level ada di config('collector.priority')
 * - tidak ada angka bisnis di UI, sehingga web dan Android selalu menampilkan prioritas yang sama.
 */
class CollectionPriorityService
{
    /**
     * @param  array{aging_days?: int, outstanding_total?: float, broken_ptp_count?: int, failed_contact_count?: int, failed_visit_count?: int, is_disputed?: bool}  $metrics
     * @return array{score: int, level: string}
     */
    public function evaluate(array $metrics): array
    {
        if ((float) ($metrics['outstanding_total'] ?? 0) <= 0) {
            return ['score' => 0, 'level' => CollectionAccountState::PRIORITY_NORMAL];
        }

        $weights = config('collector.priority.weights');
        $caps = config('collector.priority.caps');
        $largeOutstanding = max(1.0, (float) config('collector.large_outstanding_threshold'));

        $score = $this->component($metrics['aging_days'] ?? 0, $caps['aging_days']) * $weights['aging']
            + $this->component($metrics['outstanding_total'] ?? 0, $largeOutstanding) * $weights['outstanding']
            + $this->component($metrics['broken_ptp_count'] ?? 0, $caps['broken_ptp']) * $weights['broken_ptp']
            + $this->component($metrics['failed_contact_count'] ?? 0, $caps['failed_contact']) * $weights['failed_contact']
            + $this->component($metrics['failed_visit_count'] ?? 0, $caps['failed_visit']) * $weights['failed_visit'];

        $score = (int) round(min(100, max(0, $score)));
        $level = $this->levelForScore($score);

        if (! empty($metrics['is_disputed'])) {
            $level = $this->capLevel($level, config('collector.priority.disputed_max_level', CollectionAccountState::PRIORITY_MEDIUM));
        }

        return ['score' => $score, 'level' => $level];
    }

    public function levelForScore(int $score): string
    {
        $levels = config('collector.priority.levels');

        return match (true) {
            $score >= $levels['critical'] => CollectionAccountState::PRIORITY_CRITICAL,
            $score >= $levels['high'] => CollectionAccountState::PRIORITY_HIGH,
            $score >= $levels['medium'] => CollectionAccountState::PRIORITY_MEDIUM,
            default => CollectionAccountState::PRIORITY_NORMAL,
        };
    }

    private function component(float|int $value, float|int $cap): float
    {
        return $cap > 0 ? min(1.0, max(0.0, (float) $value / (float) $cap)) : 0.0;
    }

    /** PRIORITY_LEVELS berurutan dari tertinggi ke terendah. */
    private function capLevel(string $level, string $max): string
    {
        $order = CollectionAccountState::PRIORITY_LEVELS;

        return array_search($level, $order, true) < array_search($max, $order, true) ? $max : $level;
    }
}
