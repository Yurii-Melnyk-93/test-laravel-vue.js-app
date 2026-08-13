<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();

            // Stored upper-cased so lookups are case-insensitive without
            // relying on the collation of whichever database is behind us.
            $table->string('code', 12)->unique();

            // Unsigned: a promo code can never carry a negative bonus.
            $table->unsignedBigInteger('bonus_amount_cents');

            // Null means the code never expires.
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
