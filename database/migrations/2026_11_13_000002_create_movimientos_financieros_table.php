<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('movimientos_financieros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
            $table->string('concepto', 255);
            $table->timestamps();
            $table->index(['user_id', 'fecha']);
        });
    }
    public function down(): void { Schema::dropIfExists('movimientos_financieros'); }
};
