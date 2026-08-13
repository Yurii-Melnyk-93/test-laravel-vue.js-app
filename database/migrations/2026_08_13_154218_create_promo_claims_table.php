<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Null when the player typed a code that does not exist — the
            // attempt still has to be recorded so it shows up in history.
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();

            // What the player actually typed. Kept even for successful claims
            // so history stays readable if a code is renamed or deleted.
            $table->string('code_attempted', 12);

            $table->string('status');
            $table->string('rejection_reason')->nullable();

            // Null for rejected attempts: nothing was credited.
            $table->unsignedBigInteger('amount_cents')->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // The real defence against double crediting. A check in PHP cannot
        // stop two concurrent requests that both pass validation before
        // either writes; a unique index makes the second insert fail.
        //
        // Rejected attempts are excluded: they never consumed the code, so a
        // player may retry. Revoked claims are NOT excluded — otherwise
        // claim -> revoke -> claim would be an endless bonus generator.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX promo_claims_one_per_player_per_code
            ON promo_claims (user_id, promo_code_id)
            WHERE status <> 'rejected'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_claims');
    }
};
