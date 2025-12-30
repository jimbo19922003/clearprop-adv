<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\PartnershipEntitlementService;
use App\Settings\PartnershipSettings;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use Illuminate\Support\Facades\Gate;
use NumberFormatter;

class ManagePartnership extends SettingsPage
{
    protected static ?string $label = 'Partnership Settings';
    protected static ?string $title = 'Partnership Settings';
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $settings = PartnershipSettings::class;
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Partnership Settings';
    protected static ?int $navigationSort = 150;

    public static function shouldRegisterNavigation(): bool
    {
        return Gate::allows('viewSettings');
    }

    public static function canView(): bool
    {
        return Gate::allows('viewSettings');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Money & currency')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('currency_code')
                        ->label('Currency')
                        ->options([
                            'USD' => 'USD — US Dollar',
                            'EUR' => 'EUR — Euro',
                            'GBP' => 'GBP — British Pound',
                            'CAD' => 'CAD — Canadian Dollar',
                            'AUD' => 'AUD — Australian Dollar',
                        ])
                        ->searchable()
                        ->native(true)
                        ->reactive()
                        ->required(),

                    Forms\Components\Select::make('currency_locale')
                        ->label('Locale (formatting)')
                        ->options([
                            'en_US' => 'English (US)',
                            'en_CA' => 'English (Canada)',
                            'en_GB' => 'English (UK)',
                            'de_DE' => 'German (Germany)',
                            'it_IT' => 'Italian (Italy)',
                        ])
                        ->native(true)
                        ->reactive()
                        ->required(),

                    Forms\Components\Placeholder::make('currency_preview')
                        ->label('Preview (not saved)')
                        ->content(function (Forms\Get $get): string {
                            $currency = (string) ($get('currency_code') ?? 'USD');
                            $locale = (string) ($get('currency_locale') ?? 'en_US');
                            $value = 1234.56;

                            if (class_exists(NumberFormatter::class)) {
                                $fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);
                                $formatted = $fmt->formatCurrency($value, $currency);
                                if ($formatted !== false) {
                                    return $formatted;
                                }
                            }

                            return number_format($value, 2, '.', ',') . ' ' . $currency;
                        })
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Share-based weekend / holiday entitlements')
                ->description('Configure the annual pool. Each member gets an allocation proportional to their ownership shares.')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('enforce_share_entitlements')
                        ->label('Enforce entitlements on reservations')
                        ->helperText('When enabled, members will be blocked from reserving more weekend/holiday days than their share allows.')
                        ->default(false)
                        ->reactive()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('annual_weekend_days_pool')
                        ->label('Annual weekend days pool')
                        ->helperText('Total weekend days available to the partnership per year.')
                        ->numeric()
                        ->minValue(0)
                        ->reactive()
                        ->required(),

                    Forms\Components\TextInput::make('annual_holiday_days_pool')
                        ->label('Annual holiday days pool')
                        ->helperText('Total holiday days available to the partnership per year.')
                        ->numeric()
                        ->minValue(0)
                        ->reactive()
                        ->required(),

                    Forms\Components\Select::make('allocation_rounding')
                        ->label('Allocation rounding')
                        ->options([
                            'floor' => 'Floor (conservative)',
                            'round' => 'Round (nearest)',
                            'ceil' => 'Ceil (aggressive)',
                        ])
                        ->native(true)
                        ->reactive()
                        ->required(),

                    Forms\Components\Toggle::make('count_unique_days')
                        ->label('Count unique days')
                        ->helperText('Recommended. Multiple reservations on the same calendar day count only once.')
                        ->default(true)
                        ->reactive()
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('holiday_dates')
                        ->label('Holidays')
                        ->helperText('Dates treated as holidays for entitlement counting.')
                        ->schema([
                            Forms\Components\DatePicker::make('date')
                                ->label('Holiday date')
                                ->native(true)
                                ->reactive()
                                ->required(),
                        ])
                        ->default([])
                        ->columnSpanFull()
                        ->dehydrateStateUsing(function ($state) {
                            // Convert repeater rows [{date: ...}] into ["Y-m-d", ...] for settings storage.
                            $dates = [];
                            foreach ($state ?? [] as $row) {
                                if (!empty($row['date'])) {
                                    $dates[] = (string) $row['date'];
                                }
                            }
                            return array_values(array_unique($dates));
                        })
                        ->afterStateHydrated(function (Forms\Set $set, $state) {
                            // Convert stored ["Y-m-d", ...] into repeater rows [{date: ...}].
                            $rows = [];
                            foreach (($state ?? []) as $date) {
                                $rows[] = ['date' => $date];
                            }
                            $set('holiday_dates', $rows);
                        })
                        ->reactive(),

                    Forms\Components\Section::make('Entitlement sandbox (not saved)')
                        ->description('Use this to test changes live before saving or enabling enforcement. This does not create or modify any reservations.')
                        ->columns(2)
                        ->schema([
                            Forms\Components\Select::make('preview_user_id')
                                ->label('Member')
                                ->options(fn() => User::query()
                                    ->role(User::IS_MEMBER)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->searchable()
                                ->native(true)
                                ->reactive()
                                ->dehydrated(false),

                            Forms\Components\DatePicker::make('preview_start_date')
                                ->label('From (date)')
                                ->native(true)
                                ->reactive()
                                ->dehydrated(false),

                            Forms\Components\DatePicker::make('preview_stop_date')
                                ->label('To (date)')
                                ->native(true)
                                ->reactive()
                                ->dehydrated(false),

                            Forms\Components\Placeholder::make('preview_result')
                                ->label('Result')
                                ->content(function (Forms\Get $get): string {
                                    $userId = $get('preview_user_id');
                                    $startDate = $get('preview_start_date');
                                    $stopDate = $get('preview_stop_date');

                                    if (empty($userId) || empty($startDate) || empty($stopDate)) {
                                        return 'Select a member and date range.';
                                    }

                                    $user = User::find($userId);
                                    if (!$user) {
                                        return 'Member not found.';
                                    }

                                    $start = Carbon::parse((string) $startDate)->startOfDay();
                                    $stop = Carbon::parse((string) $stopDate)->endOfDay();

                                    // Translate current *form state* into config (works even before saving).
                                    $holidayState = $get('holiday_dates') ?? [];
                                    $holidayDates = [];
                                    foreach ($holidayState as $row) {
                                        if (is_array($row) && !empty($row['date'])) {
                                            $holidayDates[] = (string) $row['date'];
                                        } elseif (is_string($row)) {
                                            $holidayDates[] = $row;
                                        }
                                    }
                                    $holidayDates = array_values(array_unique($holidayDates));

                                    $config = [
                                        'annual_weekend_days_pool' => (int) ($get('annual_weekend_days_pool') ?? 0),
                                        'annual_holiday_days_pool' => (int) ($get('annual_holiday_days_pool') ?? 0),
                                        'holiday_dates' => $holidayDates,
                                        'count_unique_days' => (bool) ($get('count_unique_days') ?? true),
                                        'allocation_rounding' => (string) ($get('allocation_rounding') ?? 'floor'),
                                    ];

                                    $evaluation = app(PartnershipEntitlementService::class)
                                        ->evaluateWithConfig($user, $start, $stop, $config);

                                    $lines = [];
                                    $lines[] = $evaluation['ok'] ? 'OK — within share entitlement.' : 'BLOCK — exceeds share entitlement.';
                                    foreach ($evaluation['years'] as $y) {
                                        $lines[] = "{$y['year']}: requested W{$y['requested_weekend_days']}/H{$y['requested_holiday_days']}; remaining W{$y['remaining_weekend_days']}/H{$y['remaining_holiday_days']}.";
                                    }

                                    return implode("\n", $lines);
                                })
                                ->dehydrated(false)
                                ->columnSpanFull(),
                        ])
                        ->collapsed(),
                ]),
        ]);
    }
}

