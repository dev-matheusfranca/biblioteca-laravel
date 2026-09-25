<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livros', function (Blueprint $table) {
            $table->string('modo_acervo')->default('reconciliacao')->after('status');
        });

        Schema::create('exemplares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livro_id')->constrained('livros')->restrictOnDelete();
            $table->string('codigo_patrimonial')->unique();
            $table->string('condicao')->default('circulacao');
            $table->string('origem')->nullable();
            $table->boolean('identificacao_fisica')->default(false);
            $table->text('motivo_condicao')->nullable();
            $table->timestamps();
        });

        Schema::table('locacoes', function (Blueprint $table) {
            $table->foreignId('exemplar_id')->nullable()->after('livro_id')->constrained('exemplares')->restrictOnDelete();
            $table->foreignId('active_exemplar_id')->nullable()->unique()->after('exemplar_id')->constrained('exemplares')->restrictOnDelete();
            $table->timestamp('encerrado_em')->nullable()->after('data_devolvido');
            $table->string('encerramento_motivo')->nullable()->after('encerrado_em');
        });
    }

    public function down(): void
    {
        if (DB::table('exemplares')->exists()) {
            throw new RuntimeException('Inventário já utilizado. Preserve o schema e aplique uma correção compatível; restauração exige ensaio separado.');
        }
        Schema::table('locacoes', function (Blueprint $table) {
            $table->dropForeign(['exemplar_id']);
            $table->dropForeign(['active_exemplar_id']);
            $table->dropUnique(['active_exemplar_id']);
            $table->dropColumn(['exemplar_id', 'active_exemplar_id', 'encerrado_em', 'encerramento_motivo']);
        });
        Schema::dropIfExists('exemplares');
        Schema::table('livros', fn (Blueprint $table) => $table->dropColumn('modo_acervo'));
    }
};
