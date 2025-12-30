<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        // Money / currency defaults (USD-focused owner partnership)
        $this->migrator->add('partnership.currency_code', 'USD');
        $this->migrator->add('partnership.currency_locale', 'en_US');

        // Share-based entitlement controls
        $this->migrator->add('partnership.enforce_share_entitlements', false);
        $this->migrator->add('partnership.annual_weekend_days_pool', 0);
        $this->migrator->add('partnership.annual_holiday_days_pool', 0);
        $this->migrator->add('partnership.holiday_dates', []);
        $this->migrator->add('partnership.count_unique_days', true);
        $this->migrator->add('partnership.allocation_rounding', 'floor');
    }

    public function down(): void
    {
        $this->migrator->delete('partnership.currency_code');
        $this->migrator->delete('partnership.currency_locale');
        $this->migrator->delete('partnership.enforce_share_entitlements');
        $this->migrator->delete('partnership.annual_weekend_days_pool');
        $this->migrator->delete('partnership.annual_holiday_days_pool');
        $this->migrator->delete('partnership.holiday_dates');
        $this->migrator->delete('partnership.count_unique_days');
        $this->migrator->delete('partnership.allocation_rounding');
    }
};

