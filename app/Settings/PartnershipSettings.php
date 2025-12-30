<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PartnershipSettings extends Settings
{
    /**
     * ISO 4217 currency code used across the portal (e.g. USD).
     */
    public string $currency_code = 'USD';

    /**
     * Locale used for formatting money & dates (e.g. en_US).
     */
    public string $currency_locale = 'en_US';

    /**
     * If enabled, members cannot create/edit reservations that exceed their
     * share-based weekend/holiday day entitlement for the year.
     */
    public bool $enforce_share_entitlements = false;

    /**
     * Total weekend days available to the partnership per calendar year.
     */
    public int $annual_weekend_days_pool = 0;

    /**
     * Total holiday days available to the partnership per calendar year.
     */
    public int $annual_holiday_days_pool = 0;

    /**
     * List of holiday dates (Y-m-d) used for entitlement counting.
     *
     * @var array<int, string>
     */
    public array $holiday_dates = [];

    /**
     * Whether entitlement usage counts unique calendar days (recommended).
     * If false, multiple reservations on the same day may be counted multiple times.
     */
    public bool $count_unique_days = true;

    /**
     * How to round share-based allocations: floor | round | ceil.
     */
    public string $allocation_rounding = 'floor';

    public static function group(): string
    {
        return 'partnership';
    }
}

