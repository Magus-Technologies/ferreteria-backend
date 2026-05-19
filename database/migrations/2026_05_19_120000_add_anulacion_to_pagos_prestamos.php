<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pagos_prestamos', function (Blueprint $table) {
            $table->boolean('estado')->default(true)->after('observaciones');
            $table->text('motivo_anulacion')->nullable()->after('estado');
            $table->dateTime('fecha_anulacion', 3)->nullable()->after('motivo_anulacion');
        });

        // Recrear triggers para que el monto_pagado sume SOLO los pagos
        // activos (estado = 1). Así un pago anulado no cuenta y el préstamo
        // se recalcula correctamente.
        DB::unprepared('DROP TRIGGER IF EXISTS trg_after_insert_pago_prestamo');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_after_delete_pago_prestamo');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_after_insert_pago_prestamo AFTER INSERT ON pagos_prestamos FOR EACH ROW
BEGIN
    DECLARE v_monto_total DECIMAL(12,2);
    DECLARE v_monto_pagado DECIMAL(12,2);
    DECLARE v_monto_pendiente DECIMAL(12,2);
    DECLARE v_nuevo_estado VARCHAR(20);
    DECLARE v_fecha_vencimiento DATETIME(3);
    DECLARE v_dias_gracia INT;

    SELECT monto_total, fecha_vencimiento, dias_gracia
    INTO v_monto_total, v_fecha_vencimiento, v_dias_gracia
    FROM prestamos
    WHERE id = NEW.prestamo_id;

    SELECT COALESCE(SUM(monto), 0)
    INTO v_monto_pagado
    FROM pagos_prestamos
    WHERE prestamo_id = NEW.prestamo_id AND estado = 1;

    SET v_monto_pendiente = v_monto_total - v_monto_pagado;

    IF v_monto_pendiente <= 0 THEN
        SET v_nuevo_estado = 'pagado_total';
    ELSEIF v_monto_pagado > 0 THEN
        SET v_nuevo_estado = 'pagado_parcial';
    ELSE
        IF DATE_ADD(v_fecha_vencimiento, INTERVAL COALESCE(v_dias_gracia, 0) DAY) < NOW() THEN
            SET v_nuevo_estado = 'vencido';
        ELSE
            SET v_nuevo_estado = 'pendiente';
        END IF;
    END IF;

    UPDATE prestamos
    SET monto_pagado = v_monto_pagado,
        monto_pendiente = v_monto_pendiente,
        estado_prestamo = v_nuevo_estado,
        updated_at = CURRENT_TIMESTAMP(3)
    WHERE id = NEW.prestamo_id;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_after_delete_pago_prestamo AFTER DELETE ON pagos_prestamos FOR EACH ROW
BEGIN
    DECLARE v_monto_total DECIMAL(12,2);
    DECLARE v_monto_pagado DECIMAL(12,2);
    DECLARE v_monto_pendiente DECIMAL(12,2);
    DECLARE v_nuevo_estado VARCHAR(20);
    DECLARE v_fecha_vencimiento DATETIME(3);
    DECLARE v_dias_gracia INT;

    SELECT monto_total, fecha_vencimiento, dias_gracia
    INTO v_monto_total, v_fecha_vencimiento, v_dias_gracia
    FROM prestamos
    WHERE id = OLD.prestamo_id;

    SELECT COALESCE(SUM(monto), 0)
    INTO v_monto_pagado
    FROM pagos_prestamos
    WHERE prestamo_id = OLD.prestamo_id AND estado = 1;

    SET v_monto_pendiente = v_monto_total - v_monto_pagado;

    IF v_monto_pendiente <= 0 THEN
        SET v_nuevo_estado = 'pagado_total';
    ELSEIF v_monto_pagado > 0 THEN
        SET v_nuevo_estado = 'pagado_parcial';
    ELSE
        IF DATE_ADD(v_fecha_vencimiento, INTERVAL COALESCE(v_dias_gracia, 0) DAY) < NOW() THEN
            SET v_nuevo_estado = 'vencido';
        ELSE
            SET v_nuevo_estado = 'pendiente';
        END IF;
    END IF;

    UPDATE prestamos
    SET monto_pagado = v_monto_pagado,
        monto_pendiente = v_monto_pendiente,
        estado_prestamo = v_nuevo_estado,
        updated_at = CURRENT_TIMESTAMP(3)
    WHERE id = OLD.prestamo_id;
END
SQL);

        // Trigger nuevo: al ANULAR (UPDATE estado 1 -> 0) recalcular también
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_after_update_pago_prestamo AFTER UPDATE ON pagos_prestamos FOR EACH ROW
BEGIN
    DECLARE v_monto_total DECIMAL(12,2);
    DECLARE v_monto_pagado DECIMAL(12,2);
    DECLARE v_monto_pendiente DECIMAL(12,2);
    DECLARE v_nuevo_estado VARCHAR(20);
    DECLARE v_fecha_vencimiento DATETIME(3);
    DECLARE v_dias_gracia INT;

    SELECT monto_total, fecha_vencimiento, dias_gracia
    INTO v_monto_total, v_fecha_vencimiento, v_dias_gracia
    FROM prestamos
    WHERE id = NEW.prestamo_id;

    SELECT COALESCE(SUM(monto), 0)
    INTO v_monto_pagado
    FROM pagos_prestamos
    WHERE prestamo_id = NEW.prestamo_id AND estado = 1;

    SET v_monto_pendiente = v_monto_total - v_monto_pagado;

    IF v_monto_pendiente <= 0 THEN
        SET v_nuevo_estado = 'pagado_total';
    ELSEIF v_monto_pagado > 0 THEN
        SET v_nuevo_estado = 'pagado_parcial';
    ELSE
        IF DATE_ADD(v_fecha_vencimiento, INTERVAL COALESCE(v_dias_gracia, 0) DAY) < NOW() THEN
            SET v_nuevo_estado = 'vencido';
        ELSE
            SET v_nuevo_estado = 'pendiente';
        END IF;
    END IF;

    UPDATE prestamos
    SET monto_pagado = v_monto_pagado,
        monto_pendiente = v_monto_pendiente,
        estado_prestamo = v_nuevo_estado,
        updated_at = CURRENT_TIMESTAMP(3)
    WHERE id = NEW.prestamo_id;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_after_update_pago_prestamo');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_after_insert_pago_prestamo');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_after_delete_pago_prestamo');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_after_insert_pago_prestamo AFTER INSERT ON pagos_prestamos FOR EACH ROW
BEGIN
    DECLARE v_monto_total DECIMAL(12,2);
    DECLARE v_monto_pagado DECIMAL(12,2);
    DECLARE v_monto_pendiente DECIMAL(12,2);
    DECLARE v_nuevo_estado VARCHAR(20);
    DECLARE v_fecha_vencimiento DATETIME(3);
    DECLARE v_dias_gracia INT;

    SELECT monto_total, fecha_vencimiento, dias_gracia
    INTO v_monto_total, v_fecha_vencimiento, v_dias_gracia
    FROM prestamos
    WHERE id = NEW.prestamo_id;

    SELECT COALESCE(SUM(monto), 0)
    INTO v_monto_pagado
    FROM pagos_prestamos
    WHERE prestamo_id = NEW.prestamo_id;

    SET v_monto_pendiente = v_monto_total - v_monto_pagado;

    IF v_monto_pendiente <= 0 THEN
        SET v_nuevo_estado = 'pagado_total';
    ELSEIF v_monto_pagado > 0 THEN
        SET v_nuevo_estado = 'pagado_parcial';
    ELSE
        IF DATE_ADD(v_fecha_vencimiento, INTERVAL COALESCE(v_dias_gracia, 0) DAY) < NOW() THEN
            SET v_nuevo_estado = 'vencido';
        ELSE
            SET v_nuevo_estado = 'pendiente';
        END IF;
    END IF;

    UPDATE prestamos
    SET monto_pagado = v_monto_pagado,
        monto_pendiente = v_monto_pendiente,
        estado_prestamo = v_nuevo_estado,
        updated_at = CURRENT_TIMESTAMP(3)
    WHERE id = NEW.prestamo_id;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_after_delete_pago_prestamo AFTER DELETE ON pagos_prestamos FOR EACH ROW
BEGIN
    DECLARE v_monto_total DECIMAL(12,2);
    DECLARE v_monto_pagado DECIMAL(12,2);
    DECLARE v_monto_pendiente DECIMAL(12,2);
    DECLARE v_nuevo_estado VARCHAR(20);
    DECLARE v_fecha_vencimiento DATETIME(3);
    DECLARE v_dias_gracia INT;

    SELECT monto_total, fecha_vencimiento, dias_gracia
    INTO v_monto_total, v_fecha_vencimiento, v_dias_gracia
    FROM prestamos
    WHERE id = OLD.prestamo_id;

    SELECT COALESCE(SUM(monto), 0)
    INTO v_monto_pagado
    FROM pagos_prestamos
    WHERE prestamo_id = OLD.prestamo_id;

    SET v_monto_pendiente = v_monto_total - v_monto_pagado;

    IF v_monto_pendiente <= 0 THEN
        SET v_nuevo_estado = 'pagado_total';
    ELSEIF v_monto_pagado > 0 THEN
        SET v_nuevo_estado = 'pagado_parcial';
    ELSE
        IF DATE_ADD(v_fecha_vencimiento, INTERVAL COALESCE(v_dias_gracia, 0) DAY) < NOW() THEN
            SET v_nuevo_estado = 'vencido';
        ELSE
            SET v_nuevo_estado = 'pendiente';
        END IF;
    END IF;

    UPDATE prestamos
    SET monto_pagado = v_monto_pagado,
        monto_pendiente = v_monto_pendiente,
        estado_prestamo = v_nuevo_estado,
        updated_at = CURRENT_TIMESTAMP(3)
    WHERE id = OLD.prestamo_id;
END
SQL);

        Schema::table('pagos_prestamos', function (Blueprint $table) {
            $table->dropColumn(['estado', 'motivo_anulacion', 'fecha_anulacion']);
        });
    }
};
