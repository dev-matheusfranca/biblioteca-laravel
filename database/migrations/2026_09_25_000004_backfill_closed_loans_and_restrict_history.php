<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('locacoes')->where('status', 'devolvida')->whereNull('encerrado_em')->update([
            'encerrado_em' => DB::raw('COALESCE(data_devolvido, updated_at, created_at, data_devolucao)'),
            'encerramento_motivo' => 'devolucao',
        ]);

        Schema::table('locacoes', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->dropForeign(['livro_id']);
            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('livro_id')->references('id')->on('livros')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('exemplares')->exists() || DB::table('locacoes')->whereNotNull('exemplar_id')->exists()) {
            throw new RuntimeException('O rollback de F2 exige uma reconciliação manual antes de remover dados de inventário.');
        }
        Schema::table('locacoes', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->dropForeign(['livro_id']);
            $table->foreign('usuario_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('livro_id')->references('id')->on('livros')->cascadeOnDelete();
        });
    }
};
