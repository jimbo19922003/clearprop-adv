<?php

namespace App\Support;

use App\Settings\PartnershipSettings;
use NumberFormatter;

class Money
{
    public static function currencyCode(): string
    {
        try {
            return app(PartnershipSettings::class)->currency_code ?: 'USD';
        } catch (\Throwable) {
            return 'USD';
        }
    }

    public static function locale(): string
    {
        try {
            return app(PartnershipSettings::class)->currency_locale ?: 'en_US';
        } catch (\Throwable) {
            return 'en_US';
        }
    }

    public static function format(float|int|null $amount): string
    {
        $value = (float)($amount ?? 0);
        $currency = self::currencyCode();
        $locale = self::locale();

        // Prefer PHP intl if available; fallback is safe but less locale-correct.
        if (class_exists(NumberFormatter::class)) {
            $fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $fmt->formatCurrency($value, $currency);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return number_format($value, 2, '.', ',') . ' ' . $currency;
    }
}

