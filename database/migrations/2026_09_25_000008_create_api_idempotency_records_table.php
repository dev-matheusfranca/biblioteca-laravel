<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotency_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['usuario_id', 'key_hash'], 'api_idem_user_key_uq');
            $table->index('expires_at', 'api_idem_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_records');
    }
};
