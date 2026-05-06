<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('profesion')) {
            Schema::create('profesion', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100)->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('cliente', 'profesion_id')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->unsignedBigInteger('profesion_id')->nullable()->after('telefono');
            });
        }

        if (Schema::hasColumn('cliente', 'profesion')) {
            $profesiones = DB::table('cliente')
                ->select('profesion')
                ->whereNotNull('profesion')
                ->where('profesion', '!=', '')
                ->distinct()
                ->pluck('profesion');

            foreach ($profesiones as $nombre) {
                DB::table('profesion')->updateOrInsert(
                    ['nombre' => $nombre],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }

            $catalogo = DB::table('profesion')->pluck('id', 'nombre');
            $clientes = DB::table('cliente')
                ->select('id', 'profesion')
                ->whereNotNull('profesion')
                ->where('profesion', '!=', '')
                ->get();

            foreach ($clientes as $cliente) {
                $profesionId = $catalogo[$cliente->profesion] ?? null;
                if ($profesionId) {
                    DB::table('cliente')
                        ->where('id', $cliente->id)
                        ->update(['profesion_id' => $profesionId]);
                }
            }
        }

        Schema::table('cliente', function (Blueprint $table) {
            $foreignKeys = collect(DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'cliente'
                  AND COLUMN_NAME = 'profesion_id'
                  AND REFERENCED_TABLE_NAME = 'profesion'
            "));

            if ($foreignKeys->isEmpty()) {
                $table->foreign('profesion_id')
                    ->references('id')
                    ->on('profesion')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('cliente', 'profesion')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->dropColumn('profesion');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('cliente', 'profesion')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->string('profesion')->nullable()->after('telefono');
            });
        }

        if (Schema::hasColumn('cliente', 'profesion_id')) {
            $catalogo = DB::table('profesion')->pluck('nombre', 'id');
            $clientes = DB::table('cliente')
                ->select('id', 'profesion_id')
                ->whereNotNull('profesion_id')
                ->get();

            foreach ($clientes as $cliente) {
                DB::table('cliente')
                    ->where('id', $cliente->id)
                    ->update(['profesion' => $catalogo[$cliente->profesion_id] ?? null]);
            }

            Schema::table('cliente', function (Blueprint $table) {
                $table->dropForeign(['profesion_id']);
                $table->dropColumn('profesion_id');
            });
        }

        Schema::dropIfExists('profesion');
    }
};
