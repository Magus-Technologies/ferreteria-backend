-- ============================================
-- MÓDULO DE PRÉSTAMOS - FERRETERÍA 2
-- ============================================
-- Autor: Sistema
-- Fecha: 2025-12-27
-- Descripción: Tablas para el módulo de préstamos
--              Similar a cotizaciones pero con tracking de pagos
-- ============================================

USE ferreteria2;

-- ============================================
-- 1. TABLA PRINCIPAL: prestamos
-- ============================================

CREATE TABLE IF NOT EXISTS `prestamos` (
  `id` VARCHAR(191) NOT NULL PRIMARY KEY,
  `numero` VARCHAR(191) NOT NULL UNIQUE,
  `fecha` DATETIME(3) NOT NULL,
  `fecha_vencimiento` DATETIME(3) NOT NULL,
  `tipo_operacion` ENUM('PRESTAR', 'PEDIR_PRESTADO') NOT NULL,
  `tipo_entidad` ENUM('CLIENTE', 'PROVEEDOR') NOT NULL,
  `cliente_id` INT NULL,
  `proveedor_id` INT NULL,
  `ruc_dni` VARCHAR(20) NULL,
  `telefono` VARCHAR(20) NULL,
  `direccion` TEXT NULL,
  `tipo_moneda` ENUM('s', 'd') NOT NULL DEFAULT 's',
  `tipo_de_cambio` DECIMAL(9,4) NOT NULL DEFAULT 1.0000,
  `monto_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_pagado` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_pendiente` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tasa_interes` DECIMAL(5,2) NULL,
  `tipo_interes` ENUM('SIMPLE', 'COMPUESTO') NULL,
  `dias_gracia` INT NULL DEFAULT 0,
  `garantia` TEXT NULL,
  `estado_prestamo` ENUM('pendiente', 'pagado_parcial', 'pagado_total', 'vencido') NOT NULL DEFAULT 'pendiente',
  `observaciones` TEXT NULL,
  `user_id` VARCHAR(191) NOT NULL,
  `vendedor` VARCHAR(191) NULL,
  `almacen_id` INT NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),

  KEY `idx_prestamos_numero` (`numero`),
  KEY `idx_prestamos_fecha` (`fecha`),
  KEY `idx_prestamos_fecha_vencimiento` (`fecha_vencimiento`),
  KEY `idx_prestamos_estado` (`estado_prestamo`),
  KEY `idx_prestamos_tipo_operacion` (`tipo_operacion`),
  KEY `idx_prestamos_tipo_entidad` (`tipo_entidad`),
  KEY `idx_prestamos_cliente` (`cliente_id`),
  KEY `idx_prestamos_proveedor` (`proveedor_id`),
  KEY `idx_prestamos_user` (`user_id`),
  KEY `idx_prestamos_almacen` (`almacen_id`),

  CONSTRAINT `fk_prestamos_cliente`
    FOREIGN KEY (`cliente_id`) REFERENCES `cliente`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prestamos_proveedor`
    FOREIGN KEY (`proveedor_id`) REFERENCES `proveedor`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prestamos_user`
    FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prestamos_almacen`
    FOREIGN KEY (`almacen_id`) REFERENCES `almacen`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `chk_prestamos_montos`
    CHECK (monto_total >= 0 AND monto_pagado >= 0 AND monto_pendiente >= 0),
  CONSTRAINT `chk_prestamos_tasa_interes`
    CHECK (tasa_interes IS NULL OR (tasa_interes >= 0 AND tasa_interes <= 100))

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 2. TABLA DETALLE: productoalmacenprestamo
-- ============================================

CREATE TABLE IF NOT EXISTS `productoalmacenprestamo` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `prestamo_id` VARCHAR(191) NOT NULL,
  `costo` DECIMAL(9,4) NOT NULL,
  `producto_almacen_id` INT NOT NULL,

  KEY `idx_productoalmacenprestamo_prestamo` (`prestamo_id`),
  KEY `idx_productoalmacenprestamo_producto` (`producto_almacen_id`),

  CONSTRAINT `fk_productoalmacenprestamo_prestamo`
    FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_productoalmacenprestamo_producto`
    FOREIGN KEY (`producto_almacen_id`) REFERENCES `productoalmacen`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 3. TABLA UNIDADES INMUTABLES: unidadderivadainmutableprestamo
-- ============================================

CREATE TABLE IF NOT EXISTS `unidadderivadainmutableprestamo` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL,
  `factor` DECIMAL(9,4) NOT NULL,
  `cantidad` DECIMAL(9,4) NOT NULL,
  `producto_almacen_prestamo_id` INT NOT NULL,
  `unidad_derivada_id` INT NOT NULL,

  KEY `idx_unidadderivadainmutableprestamo_producto` (`producto_almacen_prestamo_id`),
  KEY `idx_unidadderivadainmutableprestamo_unidad` (`unidad_derivada_id`),

  CONSTRAINT `fk_unidadderivadainmutableprestamo_producto`
    FOREIGN KEY (`producto_almacen_prestamo_id`)
    REFERENCES `productoalmacenprestamo`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_unidadderivadainmutableprestamo_unidad`
    FOREIGN KEY (`unidad_derivada_id`)
    REFERENCES `unidadderivada`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 4. TABLA NUEVA: pagos_prestamos
-- ============================================

CREATE TABLE IF NOT EXISTS `pagos_prestamos` (
  `id` VARCHAR(191) NOT NULL PRIMARY KEY,
  `prestamo_id` VARCHAR(191) NOT NULL,
  `numero_pago` VARCHAR(191) NOT NULL,
  `monto` DECIMAL(12,2) NOT NULL,
  `fecha_pago` DATETIME(3) NOT NULL,
  `metodo_pago` VARCHAR(50) NOT NULL,
  `numero_operacion` VARCHAR(100) NULL,
  `observaciones` TEXT NULL,
  `user_id` VARCHAR(191) NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),

  KEY `idx_pagos_prestamo` (`prestamo_id`),
  KEY `idx_pagos_fecha` (`fecha_pago`),
  KEY `idx_pagos_user` (`user_id`),
  UNIQUE KEY `idx_pagos_numero` (`numero_pago`),

  CONSTRAINT `fk_pagos_prestamo`
    FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pagos_user`
    FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_pagos_monto` CHECK (monto > 0)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 5. TRIGGERS PARA CÁLCULOS AUTOMÁTICOS
-- ============================================

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_after_insert_pago_prestamo` $$
CREATE TRIGGER `trg_after_insert_pago_prestamo`
AFTER INSERT ON `pagos_prestamos`
FOR EACH ROW
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
END $$

DROP TRIGGER IF EXISTS `trg_after_delete_pago_prestamo` $$
CREATE TRIGGER `trg_after_delete_pago_prestamo`
AFTER DELETE ON `pagos_prestamos`
FOR EACH ROW
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
END $$

DELIMITER ;


-- ============================================
-- 6. ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ============================================

CREATE INDEX `idx_prestamos_estado_vencimiento`
  ON `prestamos` (`estado_prestamo`, `fecha_vencimiento`);

CREATE INDEX `idx_prestamos_tipo_estado`
  ON `prestamos` (`tipo_operacion`, `estado_prestamo`);


-- ============================================
-- FIN DEL SCRIPT
-- ============================================

SELECT 'Módulo de préstamos creado exitosamente!' AS mensaje;
