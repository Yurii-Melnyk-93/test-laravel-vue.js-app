<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Money is stored in minor units (cents) as an integer.
            // Floats would accumulate rounding errors on a balance.
            $table->bigInteger('balance_cents')->default(0)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('balance_cents');
        });
    }
};
