<x-filament::page>
    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">
                Sandbox mode
            </x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                You are in the <strong>Sandbox</strong> panel. All changes are written to a separate SQLite database and will not affect your real data.
            </p>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                Use the action above to wipe & recreate demo data whenever you want a clean slate.
            </p>
        </x-filament::section>
    </div>
</x-filament::page>

