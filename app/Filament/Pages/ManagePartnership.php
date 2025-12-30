<?php

namespace App\Filament\Pages;

use App\Settings\PartnershipSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use Illuminate\Support\Facades\Gate;

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
                        ->required(),
                ]),

            Forms\Components\Section::make('Share-based weekend / holiday entitlements')
                ->description('Configure the annual pool. Each member gets an allocation proportional to their ownership shares.')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('enforce_share_entitlements')
                        ->label('Enforce entitlements on reservations')
                        ->helperText('When enabled, members will be blocked from reserving more weekend/holiday days than their share allows.')
                        ->default(false)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('annual_weekend_days_pool')
                        ->label('Annual weekend days pool')
                        ->helperText('Total weekend days available to the partnership per year.')
                        ->numeric()
                        ->minValue(0)
                        ->required(),

                    Forms\Components\TextInput::make('annual_holiday_days_pool')
                        ->label('Annual holiday days pool')
                        ->helperText('Total holiday days available to the partnership per year.')
                        ->numeric()
                        ->minValue(0)
                        ->required(),

                    Forms\Components\Select::make('allocation_rounding')
                        ->label('Allocation rounding')
                        ->options([
                            'floor' => 'Floor (conservative)',
                            'round' => 'Round (nearest)',
                            'ceil' => 'Ceil (aggressive)',
                        ])
                        ->native(true)
                        ->required(),

                    Forms\Components\Toggle::make('count_unique_days')
                        ->label('Count unique days')
                        ->helperText('Recommended. Multiple reservations on the same calendar day count only once.')
                        ->default(true)
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('holiday_dates')
                        ->label('Holidays')
                        ->helperText('Dates treated as holidays for entitlement counting.')
                        ->schema([
                            Forms\Components\DatePicker::make('date')
                                ->label('Holiday date')
                                ->native(true)
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
                        }),
                ]),
        ]);
    }
}

