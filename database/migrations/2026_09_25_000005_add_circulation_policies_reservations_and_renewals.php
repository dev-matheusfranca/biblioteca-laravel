<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politicas_circulacao', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->unsignedSmallInteger('loan_days');
            $table->unsignedSmallInteger('max_open_loans');
            $table->unsignedSmallInteger('max_renewals');
            $table->unsignedSmallInteger('renewal_days');
            $table->unsignedSmallInteger('pickup_hours');
            $table->boolean('blocks_overdue')->default(true);
            $table->string('timezone', 64);
            $table->string('active_key', 16)->nullable()->unique();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_reason')->nullable();
            $table->timestamps();
        });

        DB::table('politicas_circulacao')->insert([
            'version' => 1,
            'loan_days' => 14,
            'max_open_loans' => 3,
            'max_renewals' => 1,
            'renewal_days' => 14,
            'pickup_hours' => 48,
            'blocks_overdue' => true,
            'timezone' => 'America/Sao_Paulo',
            'active_key' => 'current',
            'change_reason' => 'Parâmetros iniciais aprovados para a demonstração.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('livro_id')->constrained('livros')->restrictOnDelete();
            $table->string('status', 24)->default('aguardando');
            $table->string('active_key')->nullable()->unique();
            $table->foreignId('exemplar_id')->nullable()->constrained('exemplares')->restrictOnDelete();
            $table->foreignId('active_exemplar_id')->nullable()->unique()->constrained('exemplares')->restrictOnDelete();
            $table->foreignId('policy_id')->constrained('politicas_circulacao')->restrictOnDelete();
            $table->json('policy_snapshot');
            $table->timestamp('disponivel_em')->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('encerrada_em')->nullable();
            $table->text('encerramento_motivo')->nullable();
            $table->timestamps();
            $table->index(['livro_id', 'status', 'created_at', 'id']);
            $table->index(['usuario_id', 'status']);
        });

        Schema::table('locacoes', function (Blueprint $table) {
            $table->foreignId('policy_id')->nullable()->after('active_exemplar_id')->constrained('politicas_circulacao')->restrictOnDelete();
            $table->json('policy_snapshot')->nullable()->after('policy_id');
            $table->unsignedSmallInteger('renewal_count')->default(0)->after('policy_snapshot');
        });

        Schema::create('renovacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('locacao_id')->constrained('locacoes')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('policy_id')->constrained('politicas_circulacao')->restrictOnDelete();
            $table->date('previous_due_date');
            $table->date('new_due_date');
            $table->json('policy_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('reservas')->exists()
            || DB::table('renovacoes')->exists()
            || DB::table('locacoes')->whereNotNull('policy_id')->exists()
            || DB::table('locacoes')->where('renewal_count', '>', 0)->exists()) {
            throw new RuntimeException('A circulação F3 já possui histórico. Preserve o schema; restauração exige ensaio separado.');
        }

        Schema::dropIfExists('renovacoes');
        Schema::table('locacoes', function (Blueprint $table) {
            $table->dropForeign(['policy_id']);
            $table->dropColumn(['policy_id', 'policy_snapshot', 'renewal_count']);
        });
        Schema::dropIfExists('reservas');
        Schema::dropIfExists('politicas_circulacao');
    }
};
