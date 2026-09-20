<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query\Period;

final readonly class StatisticsPeriodResolver
{
    public function __construct(private string $appTimezone = 'UTC')
    {
    }

    public function resolve(?string $preset, ?string $fromValue, ?string $toValue): StatisticsPeriod
    {
        $timezone = new \DateTimeZone($this->appTimezone);
        $today = new \DateTimeImmutable('today', $timezone);
        $preset = trim((string) $preset);
        if ($preset === '') {
            $preset = 'custom';
        }

        if ($preset !== 'custom') {
            [$from, $toExclusive] = match ($preset) {
                'today' => [$today, $today->modify('+1 day')],
                'yesterday' => [$today->modify('-1 day'), $today],
                'week' => [$today->modify('-'.((int) $today->format('N') - 1).' days'), $today->modify('+1 day')],
                'month' => [$today->modify('first day of this month'), $today->modify('first day of next month')],
                default => throw new \InvalidArgumentException('Invalid statistics preset.'),
            };

            return new StatisticsPeriod(
                $from,
                $toExclusive,
                $from->format('Y-m-d'),
                $toExclusive->modify('-1 day')->format('Y-m-d'),
                $preset,
            );
        }

        $fromText = trim((string) ($fromValue ?? $today->format('Y-m-d')));
        $toText = trim((string) ($toValue ?? $today->format('Y-m-d')));
        $from = $this->parseDate($fromText, $timezone);
        $to = $this->parseDate($toText, $timezone);

        return new StatisticsPeriod(
            $from,
            $to->modify('+1 day'),
            $fromText,
            $toText,
            'custom',
        );
    }

    private function parseDate(string $value, \DateTimeZone $timezone): \DateTimeImmutable
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Statistics date is required.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new \InvalidArgumentException('Invalid statistics date.');
        }

        return $date;
    }
}
