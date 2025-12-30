<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class SandboxTools extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationGroup = 'Sandbox';
    protected static ?string $navigationLabel = 'Sandbox Tools';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.sandbox-tools';

    public static function shouldRegisterNavigation(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'sandbox';
    }

    public static function canAccess(): bool
    {
        // Keep this conservative: only admins in sandbox.
        return Auth::check() && Auth::user()->is_admin;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset_sandbox')
                ->label('Reset & Reseed Sandbox')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reset sandbox?')
                ->modalDescription('This will wipe the sandbox database and recreate it with demo data.')
                ->action(function () {
                    Artisan::call('sandbox:reset', ['--seed' => true]);

                    Notification::make()
                        ->title('Sandbox reset complete')
                        ->body('Demo data has been recreated. Refresh any open tables/calendars.')
                        ->success()
                        ->send();
                }),
        ];
    }
}

