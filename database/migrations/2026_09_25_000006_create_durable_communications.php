<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->unsignedInteger('availability_version')->default(0)->after('policy_snapshot');
        });

        Schema::create('communication_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('type', 64);
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('locacao_id')->nullable()->constrained('locacoes')->restrictOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->restrictOnDelete();
            $table->json('payload');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->uuid('correlation_id')->index();
            $table->uuid('lease_token')->nullable()->index();
            $table->timestamp('leased_at')->nullable();
            $table->timestamp('last_enqueued_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('portal_completed_at')->nullable();
            $table->timestamp('mail_completed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at', 'last_enqueued_at'], 'outbox_delivery_due_idx');
            $table->index(['type', 'created_at']);
        });

        Schema::create('portal_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbox_id')->unique()->constrained('communication_outbox')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 64);
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['usuario_id', 'read_at', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('communication_outbox')->exists()
            || DB::table('portal_notices')->exists()
            || DB::table('reservas')->where('availability_version', '>', 0)->exists()) {
            throw new RuntimeException('A comunicação F4 já possui histórico. Preserve o schema; restauração exige ensaio separado.');
        }

        Schema::dropIfExists('portal_notices');
        Schema::dropIfExists('communication_outbox');
        Schema::table('reservas', fn (Blueprint $table) => $table->dropColumn('availability_version'));
    }
};
