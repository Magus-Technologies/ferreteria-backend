<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar y eliminar foreign keys si existen
        $this->dropForeignKeyIfExists('comprobantes_electronicos', 'comprobantes_electronicos_user_id_foreign');
        $this->dropForeignKeyIfExists('comprobantes_electronicos', 'comprobantes_electronicos_user_envio_id_foreign');
        $this->dropForeignKeyIfExists('comprobantes_electronicos', 'comprobantes_electronicos_user_anulacion_id_foreign');
        
        // Cambiar tipo de columna a VARCHAR(191) para soportar ULIDs (igual que user.id)
        DB::statement('ALTER TABLE `comprobantes_electronicos` MODIFY `user_id` VARCHAR(191) NULL');
        DB::statement('ALTER TABLE `comprobantes_electronicos` MODIFY `user_envio_id` VARCHAR(191) NULL');
        DB::statement('ALTER TABLE `comprobantes_electronicos` MODIFY `user_anulacion_id` VARCHAR(191) NULL');
        
        // Recrear foreign keys apuntando a la tabla 'user' (no 'users')
        DB::statement('ALTER TABLE `comprobantes_electronicos` ADD CONSTRAINT `comprobantes_electronicos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        DB::statement('ALTER TABLE `comprobantes_electronicos` ADD CONSTRAINT `comprobantes_electronicos_user_envio_id_foreign` FOREIGN KEY (`user_envio_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        DB::statement('ALTER TABLE `comprobantes_electronicos` ADD CONSTRAINT `comprobantes_electronicos_user_anulacion_id_foreign` FOREIGN KEY (`user_anulacion_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        // Eliminar foreign keys
        $this->dropForeignKeyIfExists('comprobantes_electronicos', 'comprobantes_electronicos_user_id_foreign');
        $this->dropForeignKeyIfExists('comprobantes_electronicos', 'comprobantes_electronicos_user_envio_id_foreign');
        $this->dropForeignKeyIfExists('comprobantes_electronicos', 'comprobantes_electronicos_user_anulacion_id_foreign');
        
        // Volver a BIGINT UNSIGNED
        DB::statement('ALTER TABLE `comprobantes_electronicos` MODIFY `user_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `comprobantes_electronicos` MODIFY `user_envio_id` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `comprobantes_electronicos` MODIFY `user_anulacion_id` BIGINT UNSIGNED NULL');
        
        // Recrear foreign keys originales apuntando a 'users'
        DB::statement('ALTER TABLE `comprobantes_electronicos` ADD CONSTRAINT `comprobantes_electronicos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `comprobantes_electronicos` ADD CONSTRAINT `comprobantes_electronicos_user_envio_id_foreign` FOREIGN KEY (`user_envio_id`) REFERENCES `users` (`id`) ON DELETE SET NULL');
        DB::statement('ALTER TABLE `comprobantes_electronicos` ADD CONSTRAINT `comprobantes_electronicos_user_anulacion_id_foreign` FOREIGN KEY (`user_anulacion_id`) REFERENCES `users` (`id`) ON DELETE SET NULL');
    }

    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        $exists = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_NAME = ? 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table, $foreignKey]);

        if (!empty($exists)) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey}`");
        }
    }
};
