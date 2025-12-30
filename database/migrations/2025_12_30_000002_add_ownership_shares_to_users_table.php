<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users', 'ownership_shares')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('ownership_shares', 10, 4)->default(0)->after('params');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'ownership_shares')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ownership_shares');
        });
    }
};

