-- ============================================
-- SISTEMA DE GESTIÓN DE CAJAS MULTIPROPÓSITO
-- Versión Final - Integrado con desplieguedepago existente
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Tabla: cajas_principales
-- Caja principal asignada a cada vendedor
-- ----------------------------
DROP TABLE IF EXISTS `cajas_principales`;
CREATE TABLE `cajas_principales` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(20) NOT NULL COMMENT 'Ej: V01, V02, V03',
  `nombre` VARCHAR(255) NOT NULL,
  `user_id` VARCHAR(191) NOT NULL COMMENT 'ID del vendedor',
  `estado` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Activa, 0=Inactiva',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `cajas_principales_codigo_unique` (`codigo` ASC),
  INDEX `cajas_principales_user_id_idx` (`user_id` ASC),
  CONSTRAINT `cajas_principales_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Cajas principales por vendedor';

-- ----------------------------
-- Tabla: sub_cajas
-- Sub-cajas configurables dentro de cada caja principal
-- Soporta múltiples métodos de pago (desplieguedepago)
-- ----------------------------
DROP TABLE IF EXISTS `sub_cajas`;
CREATE TABLE `sub_cajas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(20) NOT NULL COMMENT 'Ej: V01-001, V01-002',
  `nombre` VARCHAR(255) NOT NULL COMMENT 'Nombre descriptivo de la sub-caja',
  `caja_principal_id` INT NOT NULL,
  `tipo_caja` ENUM('CC', 'SC') NOT NULL DEFAULT 'SC' COMMENT 'CC=Caja Chica (automática), SC=Sub-Caja (manual)',
  `despliegues_pago_ids` JSON NOT NULL COMMENT 'Array de IDs de desplieguedepago: ["id1","id2"] o ["*"] para todos',
  `tipos_comprobante` JSON NOT NULL COMMENT 'Array de tipos: ["01","03"] o ["nv"] o ["01","03","nv"]',
  `saldo_actual` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `proposito` TEXT NULL COMMENT 'Descripción del propósito de la sub-caja',
  `estado` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Activa, 0=Inactiva',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `sub_cajas_codigo_unique` (`codigo` ASC),
  INDEX `sub_cajas_caja_principal_id_idx` (`caja_principal_id` ASC),
  INDEX `sub_cajas_tipo_caja_idx` (`tipo_caja` ASC),
  CONSTRAINT `sub_cajas_caja_principal_id_fkey` FOREIGN KEY (`caja_principal_id`) REFERENCES `cajas_principales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Sub-cajas configurables con múltiples métodos de pago';

-- ----------------------------
-- Tabla: transacciones_caja
-- Registro de todas las transacciones en las sub-cajas
-- ----------------------------
DROP TABLE IF EXISTS `transacciones_caja`;
CREATE TABLE `transacciones_caja` (
  `id` VARCHAR(191) NOT NULL,
  `sub_caja_id` INT NOT NULL,
  `tipo_transaccion` ENUM('ingreso', 'egreso', 'prestamo_enviado', 'prestamo_recibido', 'movimiento_interno_salida', 'movimiento_interno_entrada') NOT NULL,
  `monto` DECIMAL(12, 2) NOT NULL,
  `saldo_anterior` DECIMAL(12, 2) NOT NULL,
  `saldo_nuevo` DECIMAL(12, 2) NOT NULL,
  `descripcion` TEXT NULL,
  `referencia_id` VARCHAR(191) NULL COMMENT 'ID de venta, compra, préstamo, etc',
  `referencia_tipo` VARCHAR(50) NULL COMMENT 'venta, compra, prestamo, movimiento_interno',
  `user_id` VARCHAR(191) NOT NULL COMMENT 'Usuario que realizó la transacción',
  `fecha` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  INDEX `transacciones_caja_sub_caja_id_idx` (`sub_caja_id` ASC),
  INDEX `transacciones_caja_fecha_idx` (`fecha` ASC),
  INDEX `transacciones_caja_tipo_transaccion_idx` (`tipo_transaccion` ASC),
  INDEX `transacciones_caja_user_id_idx` (`user_id` ASC),
  CONSTRAINT `transacciones_caja_sub_caja_id_fkey` FOREIGN KEY (`sub_caja_id`) REFERENCES `sub_cajas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `transacciones_caja_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Registro de transacciones en sub-cajas';

-- ----------------------------
-- Tabla: prestamos_entre_cajas
-- Préstamos de efectivo entre cajas
-- ----------------------------
DROP TABLE IF EXISTS `prestamos_entre_cajas`;
CREATE TABLE `prestamos_entre_cajas` (
  `id` VARCHAR(191) NOT NULL,
  `sub_caja_origen_id` INT NOT NULL,
  `sub_caja_destino_id` INT NOT NULL,
  `monto` DECIMAL(12, 2) NOT NULL,
  `despliegue_de_pago_id` VARCHAR(191) NULL COMMENT 'Método de pago usado (efectivo, transferencia, etc)',
  `estado` ENUM('pendiente', 'devuelto', 'cancelado') NOT NULL DEFAULT 'pendiente',
  `motivo` TEXT NULL,
  `user_presta_id` VARCHAR(191) NOT NULL COMMENT 'Usuario que presta',
  `user_recibe_id` VARCHAR(191) NOT NULL COMMENT 'Usuario que recibe',
  `fecha_prestamo` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `fecha_devolucion` DATETIME(3) NULL,
  `observaciones` TEXT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  INDEX `prestamos_sub_caja_origen_idx` (`sub_caja_origen_id` ASC),
  INDEX `prestamos_sub_caja_destino_idx` (`sub_caja_destino_id` ASC),
  INDEX `prestamos_estado_idx` (`estado` ASC),
  INDEX `prestamos_fecha_prestamo_idx` (`fecha_prestamo` ASC),
  INDEX `prestamos_user_presta_id_idx` (`user_presta_id` ASC),
  INDEX `prestamos_user_recibe_id_idx` (`user_recibe_id` ASC),
  CONSTRAINT `prestamos_sub_caja_origen_fkey` FOREIGN KEY (`sub_caja_origen_id`) REFERENCES `sub_cajas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `prestamos_sub_caja_destino_fkey` FOREIGN KEY (`sub_caja_destino_id`) REFERENCES `sub_cajas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `prestamos_user_presta_fkey` FOREIGN KEY (`user_presta_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `prestamos_user_recibe_fkey` FOREIGN KEY (`user_recibe_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Préstamos entre cajas';

-- ----------------------------
-- Tabla: movimientos_internos
-- Movimientos de fondos entre sub-cajas del mismo vendedor
-- ----------------------------
DROP TABLE IF EXISTS `movimientos_internos`;
CREATE TABLE `movimientos_internos` (
  `id` VARCHAR(191) NOT NULL,
  `sub_caja_origen_id` INT NOT NULL,
  `sub_caja_destino_id` INT NOT NULL,
  `monto` DECIMAL(12, 2) NOT NULL,
  `despliegue_de_pago_id` VARCHAR(191) NULL COMMENT 'Método de pago usado (efectivo, transferencia, etc)',
  `justificacion` TEXT NOT NULL COMMENT 'Motivo del movimiento',
  `comprobante` VARCHAR(255) NULL COMMENT 'Número de voucher, depósito, etc',
  `user_id` VARCHAR(191) NOT NULL,
  `fecha` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  INDEX `movimientos_internos_sub_caja_origen_idx` (`sub_caja_origen_id` ASC),
  INDEX `movimientos_internos_sub_caja_destino_idx` (`sub_caja_destino_id` ASC),
  INDEX `movimientos_internos_fecha_idx` (`fecha` ASC),
  INDEX `movimientos_internos_user_id_idx` (`user_id` ASC),
  CONSTRAINT `movimientos_internos_sub_caja_origen_fkey` FOREIGN KEY (`sub_caja_origen_id`) REFERENCES `sub_cajas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `movimientos_internos_sub_caja_destino_fkey` FOREIGN KEY (`sub_caja_destino_id`) REFERENCES `sub_cajas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `movimientos_internos_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Movimientos internos entre sub-cajas';

-- ----------------------------
-- Tabla: cierre_caja
-- Registro de cierres de caja diarios/semanales
-- ----------------------------
DROP TABLE IF EXISTS `cierre_caja`;
CREATE TABLE `cierre_caja` (
  `id` VARCHAR(191) NOT NULL,
  `sub_caja_id` INT NOT NULL,
  `fecha_cierre` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `saldo_sistema` DECIMAL(12, 2) NOT NULL COMMENT 'Saldo según el sistema',
  `saldo_fisico` DECIMAL(12, 2) NOT NULL COMMENT 'Saldo contado físicamente',
  `diferencia` DECIMAL(12, 2) NOT NULL COMMENT 'Diferencia entre sistema y físico',
  `observaciones` TEXT NULL,
  `user_id` VARCHAR(191) NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  INDEX `cierre_caja_sub_caja_id_idx` (`sub_caja_id` ASC),
  INDEX `cierre_caja_fecha_cierre_idx` (`fecha_cierre` ASC),
  INDEX `cierre_caja_user_id_idx` (`user_id` ASC),
  CONSTRAINT `cierre_caja_sub_caja_id_fkey` FOREIGN KEY (`sub_caja_id`) REFERENCES `sub_cajas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `cierre_caja_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Cierres de caja';

-- ----------------------------
-- Tabla: movimiento_caja
-- Registro detallado de todos los movimientos de caja
-- ----------------------------
DROP TABLE IF EXISTS `movimiento_caja`;
CREATE TABLE `movimiento_caja` (
  `id` VARCHAR(255) NOT NULL,
  `apertura_cierre_id` VARCHAR(255) NOT NULL COMMENT 'ID de la apertura/cierre de caja',
  `caja_principal_id` INT NOT NULL,
  `sub_caja_id` INT NULL,
  `cajero_id` VARCHAR(255) NOT NULL COMMENT 'Usuario que realiza el movimiento',
  `fecha_hora` TIMESTAMP NOT NULL,
  `tipo_movimiento` ENUM('apertura', 'venta', 'gasto', 'ingreso', 'cobro', 'pago', 'transferencia', 'cierre') NOT NULL DEFAULT 'venta',
  `concepto` VARCHAR(500) NOT NULL,
  `saldo_inicial` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `ingreso` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `salida` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `saldo_final` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `registradora` VARCHAR(100) NULL COMMENT 'Punto de venta o caja registradora',
  `estado_caja` ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
  -- Campos adicionales para detalles
  `tipo_comprobante` VARCHAR(10) NULL COMMENT '01=Factura, 03=Boleta, nv=Nota Venta',
  `numero_comprobante` VARCHAR(50) NULL,
  `metodo_pago_id` VARCHAR(255) NULL COMMENT 'ID del método de pago usado',
  `referencia_id` VARCHAR(255) NULL COMMENT 'ID de venta, gasto, etc.',
  `referencia_tipo` VARCHAR(50) NULL COMMENT 'venta, gasto, ingreso, etc.',
  -- Campos para transferencias entre cajas
  `caja_origen_id` INT NULL,
  `caja_destino_id` INT NULL,
  `monto_transferencia` DECIMAL(10, 2) NULL,
  `observaciones` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  INDEX `movimiento_caja_apertura_cierre_id_fecha_hora_idx` (`apertura_cierre_id` ASC, `fecha_hora` ASC),
  INDEX `movimiento_caja_caja_principal_id_idx` (`caja_principal_id` ASC),
  INDEX `movimiento_caja_cajero_id_idx` (`cajero_id` ASC),
  INDEX `movimiento_caja_tipo_movimiento_idx` (`tipo_movimiento` ASC),
  INDEX `movimiento_caja_fecha_hora_idx` (`fecha_hora` ASC),
  CONSTRAINT `movimiento_caja_apertura_cierre_id_fkey` FOREIGN KEY (`apertura_cierre_id`) REFERENCES `apertura_cierre_caja` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Registro detallado de movimientos de caja';

-- ----------------------------
-- Tabla: movimiento_caja
-- Registro detallado de todos los movimientos de caja
-- ----------------------------
DROP TABLE IF EXISTS `movimiento_caja`;
CREATE TABLE `movimiento_caja` (
  `id` VARCHAR(255) NOT NULL,
  `apertura_cierre_id` VARCHAR(255) NOT NULL COMMENT 'ID de la apertura/cierre de caja',
  `caja_principal_id` INT NOT NULL,
  `sub_caja_id` INT NULL,
  `cajero_id` VARCHAR(255) NOT NULL COMMENT 'Usuario que realiza el movimiento',
  `fecha_hora` TIMESTAMP NOT NULL,
  `tipo_movimiento` ENUM('apertura', 'venta', 'gasto', 'ingreso', 'cobro', 'pago', 'transferencia', 'cierre') NOT NULL DEFAULT 'venta',
  `concepto` VARCHAR(500) NOT NULL,
  `saldo_inicial` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `ingreso` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `salida` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `saldo_final` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `registradora` VARCHAR(100) NULL COMMENT 'Punto de venta o caja registradora',
  `estado_caja` ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
  -- Campos adicionales para detalles
  `tipo_comprobante` VARCHAR(10) NULL COMMENT '01=Factura, 03=Boleta, nv=Nota Venta',
  `numero_comprobante` VARCHAR(50) NULL,
  `metodo_pago_id` VARCHAR(255) NULL COMMENT 'ID del método de pago usado',
  `referencia_id` VARCHAR(255) NULL COMMENT 'ID de venta, gasto, etc.',
  `referencia_tipo` VARCHAR(50) NULL COMMENT 'venta, gasto, ingreso, etc.',
  -- Campos para transferencias entre cajas
  `caja_origen_id` INT NULL,
  `caja_destino_id` INT NULL,
  `monto_transferencia` DECIMAL(10, 2) NULL,
  `observaciones` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  INDEX `movimiento_caja_apertura_cierre_id_fecha_hora_idx` (`apertura_cierre_id` ASC, `fecha_hora` ASC),
  INDEX `movimiento_caja_caja_principal_id_idx` (`caja_principal_id` ASC),
  INDEX `movimiento_caja_cajero_id_idx` (`cajero_id` ASC),
  INDEX `movimiento_caja_tipo_movimiento_idx` (`tipo_movimiento` ASC),
  INDEX `movimiento_caja_fecha_hora_idx` (`fecha_hora` ASC),
  CONSTRAINT `movimiento_caja_apertura_cierre_id_fkey` FOREIGN KEY (`apertura_cierre_id`) REFERENCES `apertura_cierre_caja` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Registro detallado de movimientos de caja';


-- ============================================
-- NOTAS IMPORTANTES:
-- ============================================
-- 1. despliegues_pago_ids es un JSON array:
--    - ["id1"] para un solo método
--    - ["id1", "id2", "id3"] para múltiples métodos específicos
--    - ["*"] para TODOS los métodos de pago
--
-- 2. tipos_comprobante es un JSON array:
--    - ["01"] para solo facturas
--    - ["03"] para solo boletas
--    - ["nv"] para solo notas de venta
--    - ["01", "03"] para facturas y boletas
--    - ["01", "03", "nv"] para todos
--
-- 3. La Caja Chica se crea automáticamente con:
--    - despliegues_pago_ids: IDs de efectivo de desplieguedepago
--    - tipos_comprobante: ["01", "03", "nv"] (todos los comprobantes)
--
-- 4. Apertura de Caja:
--    - Se apertura diariamente con un monto inicial
--    - El monto va directo a la Caja Chica (efectivo)
--    - Solo puede haber una apertura activa por caja
--    - El admin apertura las cajas de los vendedores cada día
--
-- 5. Cierre de Caja:
--    - Se registra el conteo de efectivo y pagos digitales
--    - Se calculan diferencias automáticamente
--    - Si hay diferencias > 10 soles, requiere supervisor
--    - Se puede forzar el cierre con autorización de supervisor
--    - Campos JSON:
--      * conteo_billetes_monedas: {"billete_200": 1, "billete_100": 2, ...}
--      * conceptos_adicionales: [{"concepto": "...", "numero": "...", "cantidad": 50.00}]
--
-- 6. Validación de Supervisor:
--    - El supervisor debe tener rol 'admin' o 'supervisor'
--    - Se valida con email y password
--    - Se registra el supervisor_id en el cierre
--
-- 7. Movimientos de Caja (movimiento_caja):
--    - Registra TODOS los movimientos durante la apertura
--    - Tipos: apertura, venta, gasto, ingreso, cobro, pago, transferencia, cierre
--    - Mantiene saldo_inicial, ingreso, salida, saldo_final
--    - Vinculado a apertura_cierre_caja mediante apertura_cierre_id
--    - Permite rastrear cada transacción con referencia_id y referencia_tipo
--    - Soporta transferencias entre cajas con caja_origen_id y caja_destino_id
-- ============================================
