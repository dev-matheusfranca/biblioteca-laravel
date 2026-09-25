<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livros', fn (Blueprint $table) => $table->index(['status', 'titulo', 'id'], 'livros_public_order_idx'));
    }

    public function down(): void
    {
        Schema::table('livros', fn (Blueprint $table) => $table->dropIndex('livros_public_order_idx'));
    }
};
