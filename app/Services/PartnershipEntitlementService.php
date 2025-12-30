<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use App\Settings\PartnershipSettings;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PartnershipEntitlementService
{
    /**
     * Evaluate whether a member can reserve the requested date range.
     *
     * Counts weekend/holiday *days* (not hours). Holidays take precedence over weekends.
     *
     * @return array{
     *   ok: bool,
     *   years: array<int, array{
     *     year: int,
     *     requested_weekend_days: int,
     *     requested_holiday_days: int,
     *     used_weekend_days: int,
     *     used_holiday_days: int,
     *     allowed_weekend_days: int,
     *     allowed_holiday_days: int,
     *     remaining_weekend_days: int,
     *     remaining_holiday_days: int
     *   }>
     * }
     */
    public function evaluate(User $user, Carbon $start, Carbon $stop, ?int $excludeReservationId = null): array
    {
        $settings = app(PartnershipSettings::class);

        // Treat stop as an exclusive boundary when counting days.
        $stopExclusive = $stop->copy();
        if ($stopExclusive->lessThanOrEqualTo($start)) {
            $stopExclusive = $start->copy()->addMinute();
        }

        $requestedByYear = $this->countDaysByYear($start, $stopExclusive, $settings->holiday_dates, (bool) $settings->count_unique_days);

        $resultYears = [];
        $ok = true;

        foreach ($requestedByYear as $year => $requested) {
            $allowed = $this->allocateForUser($user, (int) $year);
            $used = $this->usedByUserForYear($user, (int) $year, $settings->holiday_dates, $excludeReservationId, (bool) $settings->count_unique_days);

            $remainingWeekend = max(0, $allowed['weekend'] - $used['weekend']);
            $remainingHoliday = max(0, $allowed['holiday'] - $used['holiday']);

            $yearOk = $requested['weekend'] <= $remainingWeekend && $requested['holiday'] <= $remainingHoliday;
            $ok = $ok && $yearOk;

            $resultYears[] = [
                'year' => (int) $year,
                'requested_weekend_days' => $requested['weekend'],
                'requested_holiday_days' => $requested['holiday'],
                'used_weekend_days' => $used['weekend'],
                'used_holiday_days' => $used['holiday'],
                'allowed_weekend_days' => $allowed['weekend'],
                'allowed_holiday_days' => $allowed['holiday'],
                'remaining_weekend_days' => $remainingWeekend,
                'remaining_holiday_days' => $remainingHoliday,
            ];
        }

        return [
            'ok' => $ok,
            'years' => $resultYears,
        ];
    }

    /**
     * Preview/sandbox evaluation using an explicit configuration array.
     *
     * This is intended for UI previews where you want to see the impact of
     * changing settings *before* saving them. It does not write any data.
     *
     * @param array{
     *   annual_weekend_days_pool?: int,
     *   annual_holiday_days_pool?: int,
     *   holiday_dates?: array<int, string>,
     *   count_unique_days?: bool,
     *   allocation_rounding?: 'floor'|'round'|'ceil'
     * } $config
     */
    public function evaluateWithConfig(
        User $user,
        Carbon $start,
        Carbon $stop,
        array $config,
        ?int $excludeReservationId = null
    ): array {
        $cfg = $this->normalizeConfig($config);

        $stopExclusive = $stop->copy();
        if ($stopExclusive->lessThanOrEqualTo($start)) {
            $stopExclusive = $start->copy()->addMinute();
        }

        $requestedByYear = $this->countDaysByYear(
            $start,
            $stopExclusive,
            $cfg['holiday_dates'],
            $cfg['count_unique_days'],
        );

        $resultYears = [];
        $ok = true;

        foreach ($requestedByYear as $year => $requested) {
            $allowed = $this->allocateForUserWithConfig($user, (int) $year, $cfg);
            $used = $this->usedByUserForYear(
                $user,
                (int) $year,
                $cfg['holiday_dates'],
                $excludeReservationId,
                $cfg['count_unique_days'],
            );

            $remainingWeekend = max(0, $allowed['weekend'] - $used['weekend']);
            $remainingHoliday = max(0, $allowed['holiday'] - $used['holiday']);

            $yearOk = $requested['weekend'] <= $remainingWeekend && $requested['holiday'] <= $remainingHoliday;
            $ok = $ok && $yearOk;

            $resultYears[] = [
                'year' => (int) $year,
                'requested_weekend_days' => $requested['weekend'],
                'requested_holiday_days' => $requested['holiday'],
                'used_weekend_days' => $used['weekend'],
                'used_holiday_days' => $used['holiday'],
                'allowed_weekend_days' => $allowed['weekend'],
                'allowed_holiday_days' => $allowed['holiday'],
                'remaining_weekend_days' => $remainingWeekend,
                'remaining_holiday_days' => $remainingHoliday,
            ];
        }

        return [
            'ok' => $ok,
            'years' => $resultYears,
        ];
    }

    /**
     * @return array{weekend:int, holiday:int}
     */
    private function allocateForUser(User $user, int $year): array
    {
        $settings = app(PartnershipSettings::class);

        $userShare = (float) ($user->ownership_shares ?? 0);
        if ($userShare <= 0) {
            return ['weekend' => 0, 'holiday' => 0];
        }

        $totalShares = (float) User::query()
            ->role(User::IS_MEMBER)
            ->whereNull('deleted_at')
            ->sum('ownership_shares');

        if ($totalShares <= 0) {
            return ['weekend' => 0, 'holiday' => 0];
        }

        $weekendRaw = ((int) $settings->annual_weekend_days_pool) * ($userShare / $totalShares);
        $holidayRaw = ((int) $settings->annual_holiday_days_pool) * ($userShare / $totalShares);

        $round = match ((string) $settings->allocation_rounding) {
            'ceil' => fn(float $v) => (int) ceil($v),
            'round' => fn(float $v) => (int) round($v),
            default => fn(float $v) => (int) floor($v),
        };

        return [
            'weekend' => max(0, $round($weekendRaw)),
            'holiday' => max(0, $round($holidayRaw)),
        ];
    }

    /**
     * @param array{
     *   annual_weekend_days_pool: int,
     *   annual_holiday_days_pool: int,
     *   holiday_dates: array<int, string>,
     *   count_unique_days: bool,
     *   allocation_rounding: 'floor'|'round'|'ceil'
     * } $cfg
     *
     * @return array{weekend:int, holiday:int}
     */
    private function allocateForUserWithConfig(User $user, int $year, array $cfg): array
    {
        $userShare = (float) ($user->ownership_shares ?? 0);
        if ($userShare <= 0) {
            return ['weekend' => 0, 'holiday' => 0];
        }

        $totalShares = (float) User::query()
            ->role(User::IS_MEMBER)
            ->whereNull('deleted_at')
            ->sum('ownership_shares');

        if ($totalShares <= 0) {
            return ['weekend' => 0, 'holiday' => 0];
        }

        $weekendRaw = ((int) $cfg['annual_weekend_days_pool']) * ($userShare / $totalShares);
        $holidayRaw = ((int) $cfg['annual_holiday_days_pool']) * ($userShare / $totalShares);

        $round = match ((string) $cfg['allocation_rounding']) {
            'ceil' => fn(float $v) => (int) ceil($v),
            'round' => fn(float $v) => (int) round($v),
            default => fn(float $v) => (int) floor($v),
        };

        return [
            'weekend' => max(0, $round($weekendRaw)),
            'holiday' => max(0, $round($holidayRaw)),
        ];
    }

    /**
     * @param array{
     *   annual_weekend_days_pool?: int,
     *   annual_holiday_days_pool?: int,
     *   holiday_dates?: array<int, string>,
     *   count_unique_days?: bool,
     *   allocation_rounding?: string
     * } $config
     *
     * @return array{
     *   annual_weekend_days_pool: int,
     *   annual_holiday_days_pool: int,
     *   holiday_dates: array<int, string>,
     *   count_unique_days: bool,
     *   allocation_rounding: 'floor'|'round'|'ceil'
     * }
     */
    private function normalizeConfig(array $config): array
    {
        $settings = app(PartnershipSettings::class);

        $rounding = (string)($config['allocation_rounding'] ?? $settings->allocation_rounding ?? 'floor');
        if (!in_array($rounding, ['floor', 'round', 'ceil'], true)) {
            $rounding = 'floor';
        }

        $holidayDates = $config['holiday_dates'] ?? $settings->holiday_dates ?? [];
        $holidayDates = array_values(array_unique(array_map('strval', $holidayDates)));

        return [
            'annual_weekend_days_pool' => (int)($config['annual_weekend_days_pool'] ?? $settings->annual_weekend_days_pool ?? 0),
            'annual_holiday_days_pool' => (int)($config['annual_holiday_days_pool'] ?? $settings->annual_holiday_days_pool ?? 0),
            'holiday_dates' => $holidayDates,
            'count_unique_days' => (bool)($config['count_unique_days'] ?? $settings->count_unique_days ?? true),
            'allocation_rounding' => $rounding,
        ];
    }

    /**
     * @param array<int, string> $holidayDates
     * @return array{weekend:int, holiday:int}
     */
    private function usedByUserForYear(
        User $user,
        int $year,
        array $holidayDates,
        ?int $excludeReservationId,
        bool $countUniqueDays
    ): array {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        $query = Reservation::query()
            ->whereHas('bookingUsers', fn($q) => $q->where('users.id', $user->id))
            ->where('reservation_start', '<=', $yearEnd)
            ->where('reservation_stop', '>=', $yearStart);

        if ($excludeReservationId !== null) {
            $query->where('id', '!=', $excludeReservationId);
        }

        $weekend = 0;
        $holiday = 0;

        $weekendDays = [];
        $holidayDays = [];

        foreach ($query->get(['reservation_start', 'reservation_stop']) as $reservation) {
            $start = Carbon::parse($reservation->reservation_start)->max($yearStart);
            $stopExclusive = Carbon::parse($reservation->reservation_stop)->min($yearEnd->copy()->addSecond());

            $counts = $this->countDays($start, $stopExclusive, $holidayDates, $countUniqueDays);
            if ($countUniqueDays) {
                foreach ($counts['weekend_days'] as $d) {
                    $weekendDays[$d] = true;
                }
                foreach ($counts['holiday_days'] as $d) {
                    $holidayDays[$d] = true;
                }
            } else {
                $weekend += $counts['weekend'];
                $holiday += $counts['holiday'];
            }
        }

        if ($countUniqueDays) {
            $holiday = count($holidayDays);
            // If a day is a holiday, it should not also count as weekend.
            foreach (array_keys($holidayDays) as $d) {
                unset($weekendDays[$d]);
            }
            $weekend = count($weekendDays);
        }

        return ['weekend' => $weekend, 'holiday' => $holiday];
    }

    /**
     * @param array<int, string> $holidayDates
     * @return array<int, array{weekend:int, holiday:int}>
     */
    private function countDaysByYear(Carbon $start, Carbon $stopExclusive, array $holidayDates, bool $countUniqueDays): array
    {
        $byYear = [];

        $startDay = $start->copy()->startOfDay();
        $endDay = $stopExclusive->copy()->subSecond()->startOfDay();

        foreach (CarbonPeriod::create($startDay, '1 day', $endDay) as $day) {
            /** @var Carbon $day */
            $year = (int) $day->year;

            $byYear[$year] ??= [
                'weekend' => 0,
                'holiday' => 0,
                'weekend_days' => [],
                'holiday_days' => [],
            ];

            $key = $day->toDateString();
            if (in_array($key, $holidayDates, true)) {
                if ($countUniqueDays) {
                    $byYear[$year]['holiday_days'][$key] = true;
                } else {
                    $byYear[$year]['holiday']++;
                }
                continue;
            }

            if ($day->isWeekend()) {
                if ($countUniqueDays) {
                    $byYear[$year]['weekend_days'][$key] = true;
                } else {
                    $byYear[$year]['weekend']++;
                }
            }
        }

        // Normalize unique-day sets into counts.
        foreach ($byYear as $year => $data) {
            if ($countUniqueDays) {
                $holidayDays = $data['holiday_days'];
                $weekendDays = $data['weekend_days'];
                foreach (array_keys($holidayDays) as $d) {
                    unset($weekendDays[$d]);
                }
                $byYear[$year]['holiday'] = count($holidayDays);
                $byYear[$year]['weekend'] = count($weekendDays);
            }
            unset($byYear[$year]['holiday_days'], $byYear[$year]['weekend_days']);
        }

        return $byYear;
    }

    /**
     * @param array<int, string> $holidayDates
     * @return array{weekend:int, holiday:int, weekend_days:array<int,string>, holiday_days:array<int,string>}
     */
    private function countDays(Carbon $start, Carbon $stopExclusive, array $holidayDates, bool $countUniqueDays): array
    {
        $weekend = 0;
        $holiday = 0;
        $weekendDays = [];
        $holidayDays = [];

        $startDay = $start->copy()->startOfDay();
        $endDay = $stopExclusive->copy()->subSecond()->startOfDay();

        foreach (CarbonPeriod::create($startDay, '1 day', $endDay) as $day) {
            /** @var Carbon $day */
            $key = $day->toDateString();

            if (in_array($key, $holidayDates, true)) {
                if ($countUniqueDays) {
                    $holidayDays[$key] = true;
                } else {
                    $holiday++;
                }
                continue;
            }

            if ($day->isWeekend()) {
                if ($countUniqueDays) {
                    $weekendDays[$key] = true;
                } else {
                    $weekend++;
                }
            }
        }

        if ($countUniqueDays) {
            foreach (array_keys($holidayDays) as $d) {
                unset($weekendDays[$d]);
            }
            $holiday = count($holidayDays);
            $weekend = count($weekendDays);
        }

        return [
            'weekend' => $weekend,
            'holiday' => $holiday,
            'weekend_days' => array_keys($weekendDays),
            'holiday_days' => array_keys($holidayDays),
        ];
    }
}

