<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requerimiento_id')->index();
            $table->unsignedBigInteger('from_cargo_id')->nullable();
            $table->unsignedBigInteger('to_cargo_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('action', ['pasar','aprobar','rechazar']);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('requerimiento_id')->references('id')->on('requerimientos_internos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_history');
    }
};
