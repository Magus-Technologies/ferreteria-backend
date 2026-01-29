-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: localhost    Database: ferreteria2
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `_permissiontorole`
--

DROP TABLE IF EXISTS `_permissiontorole`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `_permissiontorole` (
  `A` int NOT NULL,
  `B` int NOT NULL,
  UNIQUE KEY `_PermissionToRole_AB_unique` (`A`,`B`) USING BTREE,
  KEY `_PermissionToRole_B_index` (`B`) USING BTREE,
  CONSTRAINT `_PermissionToRole_A_fkey` FOREIGN KEY (`A`) REFERENCES `permission` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `_PermissionToRole_B_fkey` FOREIGN KEY (`B`) REFERENCES `role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `_permissiontouser`
--

DROP TABLE IF EXISTS `_permissiontouser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `_permissiontouser` (
  `A` int NOT NULL,
  `B` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  UNIQUE KEY `_PermissionToUser_AB_unique` (`A`,`B`) USING BTREE,
  KEY `_PermissionToUser_B_index` (`B`) USING BTREE,
  CONSTRAINT `_PermissionToUser_A_fkey` FOREIGN KEY (`A`) REFERENCES `permission` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `_PermissionToUser_B_fkey` FOREIGN KEY (`B`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `_prisma_migrations`
--

DROP TABLE IF EXISTS `_prisma_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `_prisma_migrations` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `finished_at` datetime(3) DEFAULT NULL,
  `migration_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logs` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rolled_back_at` datetime(3) DEFAULT NULL,
  `started_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `applied_steps_count` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `_roletouser`
--

DROP TABLE IF EXISTS `_roletouser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `_roletouser` (
  `A` int NOT NULL,
  `B` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  UNIQUE KEY `_RoleToUser_AB_unique` (`A`,`B`) USING BTREE,
  KEY `_RoleToUser_B_index` (`B`) USING BTREE,
  CONSTRAINT `_RoleToUser_A_fkey` FOREIGN KEY (`A`) REFERENCES `role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `_RoleToUser_B_fkey` FOREIGN KEY (`B`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account`
--

DROP TABLE IF EXISTS `account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account` (
  `userId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `providerAccountId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `refresh_token` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_token` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` int DEFAULT NULL,
  `token_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_token` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_state` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`provider`,`providerAccountId`) USING BTREE,
  KEY `Account_userId_fkey` (`userId`) USING BTREE,
  CONSTRAINT `Account_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `almacen`
--

DROP TABLE IF EXISTS `almacen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `almacen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Almacen_name_key` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `apertura_cierre_caja`
--

DROP TABLE IF EXISTS `apertura_cierre_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apertura_cierre_caja` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caja_principal_id` int unsigned NOT NULL,
  `sub_caja_id` int unsigned NOT NULL COMMENT 'Siempre será la Caja Chica',
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que apertura',
  `monto_apertura` decimal(10,2) NOT NULL,
  `fecha_apertura` timestamp NOT NULL,
  `monto_cierre` decimal(10,2) DEFAULT NULL,
  `fecha_cierre` timestamp NULL DEFAULT NULL,
  `estado` enum('abierta','cerrada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierta',
  `monto_cierre_efectivo` decimal(10,2) DEFAULT NULL,
  `monto_cierre_cuentas` decimal(10,2) DEFAULT NULL,
  `conteo_billetes_monedas` json DEFAULT NULL,
  `conceptos_adicionales` json DEFAULT NULL,
  `comentarios` text COLLATE utf8mb4_unicode_ci,
  `supervisor_id` bigint unsigned DEFAULT NULL,
  `diferencia_efectivo` decimal(10,2) DEFAULT NULL,
  `diferencia_total` decimal(10,2) DEFAULT NULL,
  `forzar_cierre` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `apertura_cierre_caja_caja_principal_id_estado_index` (`caja_principal_id`,`estado`),
  KEY `apertura_cierre_caja_sub_caja_id_index` (`sub_caja_id`),
  KEY `apertura_cierre_caja_user_id_index` (`user_id`),
  KEY `apertura_cierre_caja_fecha_apertura_index` (`fecha_apertura`),
  KEY `apertura_cierre_caja_supervisor_id_foreign` (`supervisor_id`),
  CONSTRAINT `apertura_cierre_caja_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `aperturaycierrecaja`
--

DROP TABLE IF EXISTS `aperturaycierrecaja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aperturaycierrecaja` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_apertura` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `monto_apertura` decimal(9,2) NOT NULL DEFAULT '0.00',
  `fecha_cierre` datetime(3) DEFAULT NULL,
  `monto_cierre` decimal(9,2) DEFAULT NULL,
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `AperturaYCierreCaja_fecha_apertura_idx` (`fecha_apertura`) USING BTREE,
  KEY `AperturaYCierreCaja_fecha_cierre_idx` (`fecha_cierre`) USING BTREE,
  KEY `AperturaYCierreCaja_user_id_fkey` (`user_id`) USING BTREE,
  CONSTRAINT `AperturaYCierreCaja_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `authenticator`
--

DROP TABLE IF EXISTS `authenticator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `authenticator` (
  `credentialID` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `providerAccountId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `credentialPublicKey` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `counter` int NOT NULL,
  `credentialDeviceType` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `credentialBackedUp` tinyint(1) NOT NULL,
  `transports` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`userId`,`credentialID`) USING BTREE,
  UNIQUE KEY `Authenticator_credentialID_key` (`credentialID`) USING BTREE,
  CONSTRAINT `Authenticator_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cajas_principales`
--

DROP TABLE IF EXISTS `cajas_principales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cajas_principales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: V01, V02, V03',
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID del vendedor',
  `estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=Activa, 0=Inactiva',
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cajas_principales_codigo_unique` (`codigo`),
  KEY `cajas_principales_user_id_idx` (`user_id`),
  CONSTRAINT `cajas_principales_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cajas principales por vendedor';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `carro`
--

DROP TABLE IF EXISTS `carro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `placa` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `proveedor_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `Carro_proveedor_id_fkey` (`proveedor_id`) USING BTREE,
  CONSTRAINT `Carro_proveedor_id_fkey` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categoria`
--

DROP TABLE IF EXISTS `categoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categoria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Categoria_name_key` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chofer`
--

DROP TABLE IF EXISTS `chofer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chofer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dni` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `licencia` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `proveedor_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Chofer_dni_key` (`dni`) USING BTREE,
  KEY `Chofer_proveedor_id_fkey` (`proveedor_id`) USING BTREE,
  CONSTRAINT `Chofer_proveedor_id_fkey` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `choferes`
--

DROP TABLE IF EXISTS `choferes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `choferes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dni` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `licencia` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=Activo, 0=Inactivo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `dni` (`dni`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cierre_caja`
--

DROP TABLE IF EXISTS `cierre_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cierre_caja` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_caja_id` int NOT NULL,
  `fecha_cierre` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `saldo_sistema` decimal(12,2) NOT NULL COMMENT 'Saldo según el sistema',
  `saldo_fisico` decimal(12,2) NOT NULL COMMENT 'Saldo contado físicamente',
  `diferencia` decimal(12,2) NOT NULL COMMENT 'Diferencia entre sistema y físico',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `user_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `cierre_caja_sub_caja_id_idx` (`sub_caja_id`),
  KEY `cierre_caja_fecha_cierre_idx` (`fecha_cierre`),
  KEY `cierre_caja_user_id_idx` (`user_id`),
  CONSTRAINT `cierre_caja_sub_caja_id_fkey` FOREIGN KEY (`sub_caja_id`) REFERENCES `sub_cajas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `cierre_caja_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cierres de caja';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cliente`
--

DROP TABLE IF EXISTS `cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo_cliente` enum('p','e') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'p',
  `numero_documento` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apellidos` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `razon_social` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dirección principal',
  `direccion_2` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion_3` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion_4` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Cliente_numero_documento_key` (`numero_documento`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `compra`
--

DROP TABLE IF EXISTS `compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compra` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_documento` enum('01','03','nv','in','sa','rc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nv',
  `serie` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` int DEFAULT NULL,
  `descripcion` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `forma_de_pago` enum('co','cr') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'co',
  `tipo_moneda` enum('s','d') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 's',
  `tipo_de_cambio` decimal(9,4) NOT NULL DEFAULT '1.0000',
  `percepcion` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `numero_dias` int DEFAULT NULL,
  `fecha_vencimiento` datetime(3) DEFAULT NULL,
  `fecha` datetime(3) NOT NULL,
  `guia` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_de_compra` enum('cr','ee','an','pr') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cr',
  `egreso_dinero_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `despliegue_de_pago_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `almacen_id` int NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL,
  `proveedor_id` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Compra_serie_numero_proveedor_id_key` (`serie`,`numero`,`proveedor_id`) USING BTREE,
  KEY `Compra_fecha_idx` (`fecha`) USING BTREE,
  KEY `Compra_estado_de_compra_idx` (`estado_de_compra`) USING BTREE,
  KEY `Compra_proveedor_id_idx` (`proveedor_id`) USING BTREE,
  KEY `Compra_almacen_id_idx` (`almacen_id`) USING BTREE,
  KEY `Compra_user_id_idx` (`user_id`) USING BTREE,
  KEY `Compra_created_at_idx` (`created_at`) USING BTREE,
  KEY `Compra_despliegue_de_pago_id_fkey` (`despliegue_de_pago_id`) USING BTREE,
  KEY `Compra_egreso_dinero_id_fkey` (`egreso_dinero_id`) USING BTREE,
  CONSTRAINT `Compra_almacen_id_fkey` FOREIGN KEY (`almacen_id`) REFERENCES `almacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Compra_despliegue_de_pago_id_fkey` FOREIGN KEY (`despliegue_de_pago_id`) REFERENCES `desplieguedepago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `Compra_egreso_dinero_id_fkey` FOREIGN KEY (`egreso_dinero_id`) REFERENCES `egresodinero` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Compra_proveedor_id_fkey` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedor` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `Compra_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `configuracion_impresion`
--

DROP TABLE IF EXISTS `configuracion_impresion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_impresion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_documento` enum('ingreso_salida','venta','cotizacion','prestamo','recepcion_almacen','compra') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `campo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del campo (ej: cantidad, precio, total, codigo, descripcion, etc)',
  `font_family` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Arial',
  `font_size` int NOT NULL DEFAULT '10',
  `font_weight` enum('normal','bold') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_user_tipo_campo` (`user_id`,`tipo_documento`,`campo`) USING BTREE,
  KEY `idx_user_id` (`user_id`) USING BTREE,
  KEY `idx_tipo_documento` (`tipo_documento`) USING BTREE,
  KEY `idx_campo` (`campo`) USING BTREE,
  CONSTRAINT `fk_configuracion_impresion_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cotizacion`
--

DROP TABLE IF EXISTS `cotizacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cotizacion` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` datetime(3) NOT NULL,
  `fecha_proforma` datetime(3) DEFAULT NULL COMMENT 'Fecha de la proforma',
  `vigencia_dias` int NOT NULL DEFAULT '7',
  `fecha_vencimiento` datetime(3) NOT NULL,
  `tipo_moneda` enum('s','d') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 's',
  `tipo_de_cambio` decimal(9,4) NOT NULL DEFAULT '1.0000',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `estado_cotizacion` enum('pe','co','ve','ca') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pe',
  `reservar_stock` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indica si se debe reservar el stock de los productos',
  `cliente_id` int DEFAULT NULL,
  `ruc_dni` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'RUC o DNI del cliente',
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Teléfono del cliente',
  `direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Dirección del cliente',
  `tipo_documento` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tipo de documento (boleta, factura)',
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendedor` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre del vendedor',
  `forma_de_pago` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Forma de pago (contado, credito, etc)',
  `almacen_id` int NOT NULL,
  `venta_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Cotizacion_numero_key` (`numero`) USING BTREE,
  UNIQUE KEY `Cotizacion_venta_id_key` (`venta_id`) USING BTREE,
  KEY `Cotizacion_fecha_idx` (`fecha`) USING BTREE,
  KEY `Cotizacion_estado_cotizacion_idx` (`estado_cotizacion`) USING BTREE,
  KEY `Cotizacion_cliente_id_idx` (`cliente_id`) USING BTREE,
  KEY `Cotizacion_almacen_id_idx` (`almacen_id`) USING BTREE,
  KEY `Cotizacion_user_id_fkey` (`user_id`) USING BTREE,
  CONSTRAINT `Cotizacion_almacen_id_fkey` FOREIGN KEY (`almacen_id`) REFERENCES `almacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Cotizacion_cliente_id_fkey` FOREIGN KEY (`cliente_id`) REFERENCES `cliente` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `Cotizacion_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Cotizacion_venta_id_fkey` FOREIGN KEY (`venta_id`) REFERENCES `venta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `desplieguedepago`
--

DROP TABLE IF EXISTS `desplieguedepago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `desplieguedepago` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adicional` decimal(9,2) NOT NULL DEFAULT '0.00',
  `mostrar` tinyint(1) NOT NULL DEFAULT '1',
  `metodo_de_pago_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `DespliegueDePago_name_key` (`name`) USING BTREE,
  KEY `DespliegueDePago_metodo_de_pago_id_fkey` (`metodo_de_pago_id`) USING BTREE,
  CONSTRAINT `DespliegueDePago_metodo_de_pago_id_fkey` FOREIGN KEY (`metodo_de_pago_id`) REFERENCES `metododepago` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `desplieguedepagoventa`
--

DROP TABLE IF EXISTS `desplieguedepagoventa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `desplieguedepagoventa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `venta_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `despliegue_de_pago_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(9,4) NOT NULL,
  `referencia` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recibe_efectivo` decimal(9,4) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `DespliegueDePagoVenta_venta_id_despliegue_de_pago_id_key` (`venta_id`,`despliegue_de_pago_id`) USING BTREE,
  KEY `DespliegueDePagoVenta_despliegue_de_pago_id_fkey` (`despliegue_de_pago_id`) USING BTREE,
  CONSTRAINT `DespliegueDePagoVenta_despliegue_de_pago_id_fkey` FOREIGN KEY (`despliegue_de_pago_id`) REFERENCES `desplieguedepago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `DespliegueDePagoVenta_venta_id_fkey` FOREIGN KEY (`venta_id`) REFERENCES `venta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `detalleentregaproducto`
--

DROP TABLE IF EXISTS `detalleentregaproducto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalleentregaproducto` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entrega_producto_id` int NOT NULL,
  `unidad_derivada_venta_id` int NOT NULL,
  `cantidad_entregada` decimal(9,3) NOT NULL,
  `ubicacion` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `DetalleEntregaProducto_entrega_producto_id_unidad_derivada_vent` (`entrega_producto_id`,`unidad_derivada_venta_id`) USING BTREE,
  KEY `DetalleEntregaProducto_unidad_derivada_venta_id_fkey` (`unidad_derivada_venta_id`) USING BTREE,
  CONSTRAINT `DetalleEntregaProducto_entrega_producto_id_fkey` FOREIGN KEY (`entrega_producto_id`) REFERENCES `entregaproducto` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `DetalleEntregaProducto_unidad_derivada_venta_id_fkey` FOREIGN KEY (`unidad_derivada_venta_id`) REFERENCES `unidadderivadainmutableventa` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `egresodinero`
--

DROP TABLE IF EXISTS `egresodinero`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `egresodinero` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(9,2) NOT NULL DEFAULT '0.00',
  `vuelto` decimal(9,2) DEFAULT NULL,
  `observaciones` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `despliegue_de_pago_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `EgresoDinero_vuelto_idx` (`vuelto`) USING BTREE,
  KEY `EgresoDinero_createdAt_idx` (`createdAt`) USING BTREE,
  KEY `EgresoDinero_despliegue_de_pago_id_fkey` (`despliegue_de_pago_id`) USING BTREE,
  KEY `EgresoDinero_user_id_fkey` (`user_id`) USING BTREE,
  CONSTRAINT `EgresoDinero_despliegue_de_pago_id_fkey` FOREIGN KEY (`despliegue_de_pago_id`) REFERENCES `desplieguedepago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `EgresoDinero_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `empresa`
--

DROP TABLE IF EXISTS `empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `almacen_id` int NOT NULL,
  `marca_id` int NOT NULL,
  `serie_ingreso` int NOT NULL DEFAULT '1',
  `serie_salida` int NOT NULL DEFAULT '1',
  `serie_recepcion_almacen` int NOT NULL DEFAULT '1',
  `ruc` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon_social` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_comercial` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubigeo_id` int DEFAULT NULL,
  `departamento` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provincia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distrito` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regimen` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actividad_economica` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `celular` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_identificacion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'RUC',
  `logo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gerente_nombre` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gerente_email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gerente_celular` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facturacion_nombre` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facturacion_email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facturacion_celular` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contabilidad_nombre` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contabilidad_email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contabilidad_celular` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terminos_comprobantes_ventas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `terminos_letras_cambio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `terminos_guias_remision` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `terminos_cotizaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `terminos_ordenes_compras` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `imprimir_impuestos_boleta` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `Empresa_almacen_id_fkey` (`almacen_id`) USING BTREE,
  KEY `Empresa_marca_id_fkey` (`marca_id`) USING BTREE,
  KEY `idx_ubigeo_id` (`ubigeo_id`) USING BTREE,
  CONSTRAINT `Empresa_almacen_id_fkey` FOREIGN KEY (`almacen_id`) REFERENCES `almacen` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `Empresa_marca_id_fkey` FOREIGN KEY (`marca_id`) REFERENCES `marca` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `entregaproducto`
--

DROP TABLE IF EXISTS `entregaproducto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `entregaproducto` (
  `id` int NOT NULL AUTO_INCREMENT,
  `venta_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_entrega` enum('in','pr') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in',
  `tipo_despacho` enum('et','do') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'et',
  `estado_entrega` enum('pe','ec','en','ca') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pe',
  `fecha_entrega` datetime(3) NOT NULL,
  `fecha_programada` datetime(3) DEFAULT NULL,
  `hora_inicio` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hora_fin` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion_entrega` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `almacen_salida_id` int NOT NULL,
  `chofer_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `EntregaProducto_venta_id_idx` (`venta_id`) USING BTREE,
  KEY `EntregaProducto_fecha_entrega_idx` (`fecha_entrega`) USING BTREE,
  KEY `EntregaProducto_estado_entrega_idx` (`estado_entrega`) USING BTREE,
  KEY `EntregaProducto_almacen_salida_id_fkey` (`almacen_salida_id`) USING BTREE,
  KEY `EntregaProducto_chofer_id_fkey` (`chofer_id`) USING BTREE,
  KEY `EntregaProducto_user_id_fkey` (`user_id`) USING BTREE,
  CONSTRAINT `EntregaProducto_almacen_salida_id_fkey` FOREIGN KEY (`almacen_salida_id`) REFERENCES `almacen` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `EntregaProducto_chofer_id_fkey` FOREIGN KEY (`chofer_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `EntregaProducto_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `EntregaProducto_venta_id_fkey` FOREIGN KEY (`venta_id`) REFERENCES `venta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `historialunidadderivadainmutableingresosalida`
--

DROP TABLE IF EXISTS `historialunidadderivadainmutableingresosalida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historialunidadderivadainmutableingresosalida` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unidad_derivada_inmutable_ingreso_salida_id` int NOT NULL,
  `stock_anterior` decimal(9,3) NOT NULL,
  `stock_nuevo` decimal(9,3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `HistorialUnidadDerivadaInmutableIngresoSalida_unidad_deriva_fkey` (`unidad_derivada_inmutable_ingreso_salida_id`) USING BTREE,
  CONSTRAINT `HistorialUnidadDerivadaInmutableIngresoSalida_unidad_deriva_fkey` FOREIGN KEY (`unidad_derivada_inmutable_ingreso_salida_id`) REFERENCES `unidadderivadainmutableingresosalida` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `historialunidadderivadainmutablerecepcion`
--

DROP TABLE IF EXISTS `historialunidadderivadainmutablerecepcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historialunidadderivadainmutablerecepcion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unidad_derivada_inmutable_recepcion_id` int NOT NULL,
  `stock_anterior` decimal(9,3) NOT NULL,
  `stock_nuevo` decimal(9,3) NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `HistorialUnidadDerivadaInmutableRecepcion_created_at_idx` (`created_at`) USING BTREE,
  KEY `HistorialUnidadDerivadaInmutableRecepcion_unidad_derivada_i_fkey` (`unidad_derivada_inmutable_recepcion_id`) USING BTREE,
  CONSTRAINT `HistorialUnidadDerivadaInmutableRecepcion_unidad_derivada_i_fkey` FOREIGN KEY (`unidad_derivada_inmutable_recepcion_id`) REFERENCES `unidadderivadainmutablerecepcion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ingresodinero`
--

DROP TABLE IF EXISTS `ingresodinero`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ingresodinero` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(9,2) NOT NULL DEFAULT '0.00',
  `observaciones` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `despliegue_de_pago_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `IngresoDinero_despliegue_de_pago_id_fkey` (`despliegue_de_pago_id`) USING BTREE,
  KEY `IngresoDinero_user_id_fkey` (`user_id`) USING BTREE,
  CONSTRAINT `IngresoDinero_despliegue_de_pago_id_fkey` FOREIGN KEY (`despliegue_de_pago_id`) REFERENCES `desplieguedepago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `IngresoDinero_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ingresosalida`
--

DROP TABLE IF EXISTS `ingresosalida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ingresosalida` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `tipo_documento` enum('01','03','nv','in','sa','rc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `serie` int NOT NULL,
  `numero` int NOT NULL,
  `descripcion` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `almacen_id` int NOT NULL,
  `tipo_ingreso_id` int NOT NULL,
  `proveedor_id` int DEFAULT NULL,
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `IngresoSalida_almacen_id_fkey` (`almacen_id`) USING BTREE,
  KEY `IngresoSalida_proveedor_id_fkey` (`proveedor_id`) USING BTREE,
  KEY `IngresoSalida_tipo_ingreso_id_fkey` (`tipo_ingreso_id`) USING BTREE,
  KEY `IngresoSalida_user_id_fkey` (`user_id`) USING BTREE,
  KEY `idx_tipo_serie_numero` (`tipo_documento`,`serie`,`numero` DESC) USING BTREE,
  CONSTRAINT `IngresoSalida_almacen_id_fkey` FOREIGN KEY (`almacen_id`) REFERENCES `almacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `IngresoSalida_proveedor_id_fkey` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedor` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `IngresoSalida_tipo_ingreso_id_fkey` FOREIGN KEY (`tipo_ingreso_id`) REFERENCES `tipoingresosalida` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `IngresoSalida_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5198 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `jobs_queue_index` (`queue`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marca`
--

DROP TABLE IF EXISTS `marca`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marca` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Marca_name_key` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=190 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `metododepago`
--

DROP TABLE IF EXISTS `metododepago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metododepago` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuenta_bancaria` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` decimal(9,2) NOT NULL DEFAULT '0.00',
  `subcaja_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `MetodoDePago_name_key` (`name`) USING BTREE,
  KEY `MetodoDePago_subcaja_id_fkey` (`subcaja_id`) USING BTREE,
  CONSTRAINT `MetodoDePago_subcaja_id_fkey` FOREIGN KEY (`subcaja_id`) REFERENCES `subcaja` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`) USING BTREE,
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`) USING BTREE,
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`) USING BTREE,
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`) USING BTREE,
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `movimiento_caja`
--

DROP TABLE IF EXISTS `movimiento_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimiento_caja` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apertura_cierre_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID de la apertura/cierre de caja',
  `caja_principal_id` int unsigned NOT NULL,
  `sub_caja_id` int unsigned DEFAULT NULL,
  `cajero_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que realiza el movimiento',
  `fecha_hora` timestamp NOT NULL,
  `tipo_movimiento` enum('apertura','venta','gasto','ingreso','cobro','pago','transferencia','cierre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'venta',
  `concepto` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `saldo_inicial` decimal(10,2) NOT NULL DEFAULT '0.00',
  `ingreso` decimal(10,2) NOT NULL DEFAULT '0.00',
  `salida` decimal(10,2) NOT NULL DEFAULT '0.00',
  `saldo_final` decimal(10,2) NOT NULL DEFAULT '0.00',
  `registradora` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Punto de venta o caja registradora',
  `estado_caja` enum('abierta','cerrada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierta',
  `tipo_comprobante` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '01=Factura, 03=Boleta, nv=Nota Venta',
  `numero_comprobante` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo_pago_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID del método de pago usado',
  `referencia_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID de venta, gasto, etc.',
  `referencia_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'venta, gasto, ingreso, etc.',
  `caja_origen_id` int unsigned DEFAULT NULL,
  `caja_destino_id` int unsigned DEFAULT NULL,
  `monto_transferencia` decimal(10,2) DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `movimiento_caja_apertura_cierre_id_fecha_hora_index` (`apertura_cierre_id`,`fecha_hora`),
  KEY `movimiento_caja_caja_principal_id_index` (`caja_principal_id`),
  KEY `movimiento_caja_cajero_id_index` (`cajero_id`),
  KEY `movimiento_caja_tipo_movimiento_index` (`tipo_movimiento`),
  KEY `movimiento_caja_fecha_hora_index` (`fecha_hora`),
  CONSTRAINT `movimiento_caja_apertura_cierre_id_foreign` FOREIGN KEY (`apertura_cierre_id`) REFERENCES `apertura_cierre_caja` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `movimientos_internos`
--

DROP TABLE IF EXISTS `movimientos_internos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos_internos` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_caja_origen_id` int NOT NULL,
  `sub_caja_destino_id` int NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `despliegue_de_pago_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Método de pago usado (efectivo, transferencia, etc)',
  `justificacion` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Motivo del movimiento',
  `comprobante` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de voucher, depósito, etc',
  `user_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `movimientos_internos_sub_caja_origen_idx` (`sub_caja_origen_id`),
  KEY `movimientos_internos_sub_caja_destino_idx` (`sub_caja_destino_id`),
  KEY `movimientos_internos_fecha_idx` (`fecha`),
  KEY `movimientos_internos_user_id_idx` (`user_id`),
  CONSTRAINT `movimientos_internos_sub_caja_destino_fkey` FOREIGN KEY (`sub_caja_destino_id`) REFERENCES `sub_cajas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `movimientos_internos_sub_caja_origen_fkey` FOREIGN KEY (`sub_caja_origen_id`) REFERENCES `sub_cajas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `movimientos_internos_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Movimientos internos entre sub-cajas';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pagodecompra`
--

DROP TABLE IF EXISTS `pagodecompra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagodecompra` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `compra_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `despliegue_de_pago_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(9,2) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `PagoDeCompra_compra_id_fkey` (`compra_id`) USING BTREE,
  KEY `PagoDeCompra_despliegue_de_pago_id_fkey` (`despliegue_de_pago_id`) USING BTREE,
  CONSTRAINT `PagoDeCompra_compra_id_fkey` FOREIGN KEY (`compra_id`) REFERENCES `compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `PagoDeCompra_despliegue_de_pago_id_fkey` FOREIGN KEY (`despliegue_de_pago_id`) REFERENCES `desplieguedepago` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pagos_prestamos`
--

DROP TABLE IF EXISTS `pagos_prestamos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos_prestamos` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prestamo_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_pago` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `fecha_pago` datetime(3) NOT NULL,
  `metodo_pago` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_operacion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_pagos_numero` (`numero_pago`) USING BTREE,
  KEY `idx_pagos_prestamo` (`prestamo_id`) USING BTREE,
  KEY `idx_pagos_fecha` (`fecha_pago`) USING BTREE,
  KEY `idx_pagos_user` (`user_id`) USING BTREE,
  CONSTRAINT `fk_pagos_prestamo` FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pagos_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_pagos_monto` CHECK ((`monto` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_codes`
--

DROP TABLE IF EXISTS `password_reset_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código de 6 dígitos',
  `used` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si el código ya fue usado',
  `expires_at` timestamp NOT NULL COMMENT 'Fecha de expiración (15 minutos)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `password_reset_codes_email_index` (`email`) USING BTREE,
  KEY `password_reset_codes_code_index` (`code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permission`
--

DROP TABLE IF EXISTS `permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Permission_name_key` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`) USING BTREE,
  KEY `personal_access_tokens_expires_at_index` (`expires_at`) USING BTREE,
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `prestamos`
--

DROP TABLE IF EXISTS `prestamos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prestamos` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` datetime(3) NOT NULL,
  `fecha_vencimiento` datetime(3) NOT NULL,
  `tipo_operacion` enum('PRESTAR','PEDIR_PRESTADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_entidad` enum('CLIENTE','PROVEEDOR') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_id` int DEFAULT NULL,
  `proveedor_id` int DEFAULT NULL,
  `ruc_dni` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tipo_moneda` enum('s','d') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 's',
  `tipo_de_cambio` decimal(9,4) NOT NULL DEFAULT '1.0000',
  `monto_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `monto_pagado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `monto_pendiente` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tasa_interes` decimal(5,2) DEFAULT NULL,
  `tipo_interes` enum('SIMPLE','COMPUESTO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dias_gracia` int DEFAULT '0',
  `garantia` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `estado_prestamo` enum('pendiente','pagado_parcial','pagado_total','vencido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendedor` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `almacen_id` int NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `numero` (`numero`) USING BTREE,
  KEY `idx_prestamos_numero` (`numero`) USING BTREE,
  KEY `idx_prestamos_fecha` (`fecha`) USING BTREE,
  KEY `idx_prestamos_fecha_vencimiento` (`fecha_vencimiento`) USING BTREE,
  KEY `idx_prestamos_estado` (`estado_prestamo`) USING BTREE,
  KEY `idx_prestamos_tipo_operacion` (`tipo_operacion`) USING BTREE,
  KEY `idx_prestamos_tipo_entidad` (`tipo_entidad`) USING BTREE,
  KEY `idx_prestamos_cliente` (`cliente_id`) USING BTREE,
  KEY `idx_prestamos_proveedor` (`proveedor_id`) USING BTREE,
  KEY `idx_prestamos_user` (`user_id`) USING BTREE,
  KEY `idx_prestamos_almacen` (`almacen_id`) USING BTREE,
  KEY `idx_prestamos_estado_vencimiento` (`estado_prestamo`,`fecha_vencimiento`) USING BTREE,
  KEY `idx_prestamos_tipo_estado` (`tipo_operacion`,`estado_prestamo`) USING BTREE,
  CONSTRAINT `fk_prestamos_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacen` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prestamos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `cliente` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prestamos_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedor` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prestamos_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_prestamos_montos` CHECK (((`monto_total` >= 0) and (`monto_pagado` >= 0) and (`monto_pendiente` >= 0))),
  CONSTRAINT `chk_prestamos_tasa_interes` CHECK (((`tasa_interes` is null) or ((`tasa_interes` >= 0) and (`tasa_interes` <= 100))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `prestamos_entre_cajas`
--

DROP TABLE IF EXISTS `prestamos_entre_cajas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prestamos_entre_cajas` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_caja_origen_id` int NOT NULL,
  `sub_caja_destino_id` int NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `despliegue_de_pago_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Método de pago usado (efectivo, transferencia, etc)',
  `estado` enum('pendiente','devuelto','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `motivo` text COLLATE utf8mb4_unicode_ci,
  `user_presta_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que presta',
  `user_recibe_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que recibe',
  `fecha_prestamo` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `fecha_devolucion` datetime(3) DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `prestamos_sub_caja_origen_idx` (`sub_caja_origen_id`),
  KEY `prestamos_sub_caja_destino_idx` (`sub_caja_destino_id`),
  KEY `prestamos_estado_idx` (`estado`),
  KEY `prestamos_fecha_prestamo_idx` (`fecha_prestamo`),
  KEY `prestamos_user_presta_id_idx` (`user_presta_id`),
  KEY `prestamos_user_recibe_id_idx` (`user_recibe_id`),
  CONSTRAINT `prestamos_sub_caja_destino_fkey` FOREIGN KEY (`sub_caja_destino_id`) REFERENCES `sub_cajas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `prestamos_sub_caja_origen_fkey` FOREIGN KEY (`sub_caja_origen_id`) REFERENCES `sub_cajas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `prestamos_user_presta_fkey` FOREIGN KEY (`user_presta_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `prestamos_user_recibe_fkey` FOREIGN KEY (`user_recibe_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Préstamos entre cajas';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `producto`
--

DROP TABLE IF EXISTS `producto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cod_producto` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cod_barra` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ticket` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria_id` int NOT NULL,
  `marca_id` int NOT NULL,
  `unidad_medida_id` int NOT NULL,
  `accion_tecnica` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `img` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ficha_tecnica` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock_min` decimal(9,3) NOT NULL,
  `stock_max` int DEFAULT NULL,
  `unidades_contenidas` decimal(9,3) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `permitido` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Producto_cod_producto_key` (`cod_producto`) USING BTREE,
  UNIQUE KEY `Producto_name_key` (`name`) USING BTREE,
  UNIQUE KEY `Producto_cod_barra_key` (`cod_barra`) USING BTREE,
  KEY `Producto_name_idx` (`name`) USING BTREE,
  KEY `Producto_categoria_id_idx` (`categoria_id`) USING BTREE,
  KEY `Producto_marca_id_idx` (`marca_id`) USING BTREE,
  KEY `Producto_estado_idx` (`estado`) USING BTREE,
  KEY `Producto_created_at_idx` (`created_at`) USING BTREE,
  KEY `Producto_unidad_medida_id_fkey` (`unidad_medida_id`) USING BTREE,
  CONSTRAINT `Producto_categoria_id_fkey` FOREIGN KEY (`categoria_id`) REFERENCES `categoria` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `Producto_marca_id_fkey` FOREIGN KEY (`marca_id`) REFERENCES `marca` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `Producto_unidad_medida_id_fkey` FOREIGN KEY (`unidad_medida_id`) REFERENCES `unidadmedida` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5165 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `productoalmacen`
--

DROP TABLE IF EXISTS `productoalmacen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productoalmacen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `producto_id` int NOT NULL,
  `almacen_id` int NOT NULL,
  `stock_fraccion` decimal(9,3) NOT NULL DEFAULT '0.000',
  `costo` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `ubicacion_id` int NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `ProductoAlmacen_producto_id_almacen_id_key` (`producto_id`,`almacen_id`) USING BTREE,
  KEY `ProductoAlmacen_almacen_id_fkey` (`almacen_id`) USING BTREE,
  KEY `ProductoAlmacen_ubicacion_id_fkey` (`ubicacion_id`) USING BTREE,
  CONSTRAINT `ProductoAlmacen_almacen_id_fkey` FOREIGN KEY (`almacen_id`) REFERENCES `almacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ProductoAlmacen_producto_id_fkey` FOREIGN KEY (`producto_id`) REFERENCES `producto` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ProductoAlmacen_ubicacion_id_fkey` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicacion` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5165 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `productoalmacencompra`
--

DROP TABLE IF EXISTS `productoalmacencompra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productoalmacencompra` (
  `id` int NOT NULL AUTO_INCREMENT,
  `compra_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `costo` decimal(9,4) NOT NULL,
  `producto_almacen_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `ProductoAlmacenCompra_compra_id_producto_almacen_id_key` (`compra_id`,`producto_almacen_id`) USING BTREE,
  KEY `ProductoAlmacenCompra_producto_almacen_id_fkey` (`producto_almacen_id`) USING BTREE,
  CONSTRAINT `ProductoAlmacenCompra_compra_id_fkey` FOREIGN KEY (`compra_id`) REFERENCES `compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ProductoAlmacenCompra_producto_almacen_id_fkey` FOREIGN KEY (`producto_almacen_id`) REFERENCES `productoalmacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `productoalmacencotizacion`
--

DROP TABLE IF EXISTS `productoalmacencotizacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productoalmacencotizacion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cotizacion_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `costo` decimal(9,4) NOT NULL,
  `producto_almacen_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `ProductoAlmacenCotizacion_cotizacion_id_producto_almacen_id_key` (`cotizacion_id`,`producto_almacen_id`) USING BTREE,
  KEY `ProductoAlmacenCotizacion_producto_almacen_id_fkey` (`producto_almacen_id`) USING BTREE,
  CONSTRAINT `ProductoAlmacenCotizacion_cotizacion_id_fkey` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizacion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ProductoAlmacenCotizacion_producto_almacen_id_fkey` FOREIGN KEY (`producto_almacen_id`) REFERENCES `productoalmacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `productoalmaceningresosalida`
--

DROP TABLE IF EXISTS `productoalmaceningresosalida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productoalmaceningresosalida` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ingreso_id` int NOT NULL,
  `costo` decimal(9,4) NOT NULL,
  `producto_almacen_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `ProductoAlmacenIngresoSalida_ingreso_id_producto_almacen_id_key` (`ingreso_id`,`producto_almacen_id`) USING BTREE,
  KEY `ProductoAlmacenIngresoSalida_producto_almacen_id_fkey` (`producto_almacen_id`) USING BTREE,
  CONSTRAINT `ProductoAlmacenIngresoSalida_ingreso_id_fkey` FOREIGN KEY (`ingreso_id`) REFERENCES `ingresosalida` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ProductoAlmacenIngresoSalida_producto_almacen_id_fkey` FOREIGN KEY (`producto_almacen_id`) REFERENCES `productoalmacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67225 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `productoalmacenprestamo`
--

DROP TABLE IF EXISTS `productoalmacenprestamo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productoalmacenprestamo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prestamo_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `costo` decimal(9,4) NOT NULL,
  `producto_almacen_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_productoalmacenprestamo_prestamo` (`prestamo_id`) USING BTREE,
  KEY `idx_productoalmacenprestamo_producto` (`producto_almacen_id`) USING BTREE,
  CONSTRAINT `fk_productoalmacenprestamo_prestamo` FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_productoalmacenprestamo_producto` FOREIGN KEY (`producto_almacen_id`) REFERENCES `productoalmacen` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `productoalmacenrecepcion`
--

DROP TABLE IF EXISTS `productoalmacenrecepcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productoalmacenrecepcion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recepcion_id` int NOT NULL,
  `costo` decimal(9,4) NOT NULL,
  `producto_almacen_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `ProductoAlmacenRecepcion_recepcion_id_producto_almacen_id_key` (`recepcion_id`,`producto_almacen_id`) USING BTREE,
  KEY `ProductoAlmacenRecepcion_producto_almacen_id_fkey` (`producto_almacen_id`) USING BTREE,
  CONSTRAINT `ProductoAlmacenRecepcion_producto_almacen_id_fkey` FOREIGN KEY (`producto_almacen_id`) REFERENCES `productoalmacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ProductoAlmacenRecepcion_recepcion_id_fkey` FOREIGN KEY (`recepcion_id`) REFERENCES `recepcionalmacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `productoalmacenunidadderivada`
--

DROP TABLE IF EXISTS `productoalmacenunidadderivada`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productoalmacenunidadderivada` (
  `id` int NOT NULL AUTO_INCREMENT,
  `producto_almacen_id` int NOT NULL,
  `unidad_derivada_id` int NOT NULL,
  `factor` decimal(9,3) NOT NULL,
  `precio_publico` decimal(9,3) NOT NULL,
  `comision_publico` decimal(9,3) DEFAULT '0.000',
  `precio_especial` decimal(9,3) DEFAULT NULL,
  `comision_especial` decimal(9,3) DEFAULT '0.000',
  `activador_especial` decimal(9,3) DEFAULT NULL,
  `precio_minimo` decimal(9,3) DEFAULT NULL,
  `comision_minimo` decimal(9,3) DEFAULT '0.000',
  `activador_minimo` decimal(9,3) DEFAULT NULL,
  `precio_ultimo` decimal(9,3) DEFAULT NULL,
  `comision_ultimo` decimal(9,3) DEFAULT '0.000',
  `activador_ultimo` decimal(9,3) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `ProductoAlmacenUnidadDerivada_producto_almacen_id_unidad_der_key` (`producto_almacen_id`,`unidad_derivada_id`) USING BTREE,
  UNIQUE KEY `ProductoAlmacenUnidadDerivada_producto_almacen_id_factor_key` (`producto_almacen_id`,`factor`) USING BTREE,
  KEY `ProductoAlmacenUnidadDerivada_unidad_derivada_id_fkey` (`unidad_derivada_id`) USING BTREE,
  CONSTRAINT `ProductoAlmacenUnidadDerivada_producto_almacen_id_fkey` FOREIGN KEY (`producto_almacen_id`) REFERENCES `productoalmacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ProductoAlmacenUnidadDerivada_unidad_derivada_id_fkey` FOREIGN KEY (`unidad_derivada_id`) REFERENCES `unidadderivada` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6549 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `productoalmacenventa`
--

DROP TABLE IF EXISTS `productoalmacenventa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productoalmacenventa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `venta_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `costo` decimal(9,4) NOT NULL,
  `producto_almacen_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `ProductoAlmacenVenta_venta_id_producto_almacen_id_key` (`venta_id`,`producto_almacen_id`) USING BTREE,
  KEY `ProductoAlmacenVenta_producto_almacen_id_fkey` (`producto_almacen_id`) USING BTREE,
  CONSTRAINT `ProductoAlmacenVenta_producto_almacen_id_fkey` FOREIGN KEY (`producto_almacen_id`) REFERENCES `productoalmacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ProductoAlmacenVenta_venta_id_fkey` FOREIGN KEY (`venta_id`) REFERENCES `venta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `proveedor`
--

DROP TABLE IF EXISTS `proveedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proveedor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `razon_social` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruc` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Proveedor_razon_social_key` (`razon_social`) USING BTREE,
  UNIQUE KEY `Proveedor_ruc_key` (`ruc`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recepcionalmacen`
--

DROP TABLE IF EXISTS `recepcionalmacen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recepcionalmacen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero` int NOT NULL,
  `observaciones` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` datetime(3) NOT NULL,
  `transportista_razon_social` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transportista_ruc` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transportista_placa` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transportista_licencia` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transportista_dni` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transportista_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transportista_guia_remision` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `compra_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `RecepcionAlmacen_fecha_idx` (`fecha`) USING BTREE,
  KEY `RecepcionAlmacen_compra_id_fkey` (`compra_id`) USING BTREE,
  KEY `RecepcionAlmacen_user_id_fkey` (`user_id`) USING BTREE,
  CONSTRAINT `RecepcionAlmacen_compra_id_fkey` FOREIGN KEY (`compra_id`) REFERENCES `compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `RecepcionAlmacen_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role`
--

DROP TABLE IF EXISTS `role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Role_name_key` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`) USING BTREE,
  KEY `role_has_permissions_role_id_foreign` (`role_id`) USING BTREE,
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `seriedocumento`
--

DROP TABLE IF EXISTS `seriedocumento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seriedocumento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo_documento` enum('01','03','nv','in','sa','rc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `serie` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `correlativo` int NOT NULL DEFAULT '0',
  `almacen_id` int NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `SerieDocumento_tipo_documento_serie_almacen_id_key` (`tipo_documento`,`serie`,`almacen_id`) USING BTREE,
  KEY `SerieDocumento_almacen_id_tipo_documento_activo_idx` (`almacen_id`,`tipo_documento`,`activo`) USING BTREE,
  CONSTRAINT `SerieDocumento_almacen_id_fkey` FOREIGN KEY (`almacen_id`) REFERENCES `almacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `session`
--

DROP TABLE IF EXISTS `session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `session` (
  `sessionToken` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires` datetime(3) NOT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  UNIQUE KEY `Session_sessionToken_key` (`sessionToken`) USING BTREE,
  KEY `Session_userId_fkey` (`userId`) USING BTREE,
  CONSTRAINT `Session_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `sessions_user_id_index` (`user_id`) USING BTREE,
  KEY `sessions_last_activity_index` (`last_activity`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sub_cajas`
--

DROP TABLE IF EXISTS `sub_cajas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_cajas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: V01-001, V01-002',
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre descriptivo de la sub-caja',
  `caja_principal_id` int NOT NULL,
  `tipo_caja` enum('CC','SC') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SC' COMMENT 'CC=Caja Chica (automática), SC=Sub-Caja (manual)',
  `despliegues_pago_ids` json NOT NULL COMMENT 'Array de IDs de desplieguedepago: ["id1","id2"] o ["*"] para todos',
  `tipos_comprobante` json NOT NULL COMMENT 'Array de tipos: ["01","03"] o ["nv"] o ["01","03","nv"]',
  `saldo_actual` decimal(12,2) NOT NULL DEFAULT '0.00',
  `proposito` text COLLATE utf8mb4_unicode_ci COMMENT 'Descripción del propósito de la sub-caja',
  `estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=Activa, 0=Inactiva',
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sub_cajas_codigo_unique` (`codigo`),
  KEY `sub_cajas_caja_principal_id_idx` (`caja_principal_id`),
  KEY `sub_cajas_tipo_caja_idx` (`tipo_caja`),
  CONSTRAINT `sub_cajas_caja_principal_id_fkey` FOREIGN KEY (`caja_principal_id`) REFERENCES `cajas_principales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sub-cajas configurables con múltiples métodos de pago';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subcaja`
--

DROP TABLE IF EXISTS `subcaja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subcaja` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `SubCaja_name_key` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipoingresosalida`
--

DROP TABLE IF EXISTS `tipoingresosalida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipoingresosalida` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `TipoIngresoSalida_name_key` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transacciones_caja`
--

DROP TABLE IF EXISTS `transacciones_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transacciones_caja` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_caja_id` int NOT NULL,
  `tipo_transaccion` enum('ingreso','egreso','prestamo_enviado','prestamo_recibido','movimiento_interno_salida','movimiento_interno_entrada') COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `saldo_anterior` decimal(12,2) NOT NULL,
  `saldo_nuevo` decimal(12,2) NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `referencia_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID de venta, compra, préstamo, etc',
  `referencia_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'venta, compra, prestamo, movimiento_interno',
  `user_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que realizó la transacción',
  `fecha` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transacciones_caja_sub_caja_id_idx` (`sub_caja_id`),
  KEY `transacciones_caja_fecha_idx` (`fecha`),
  KEY `transacciones_caja_tipo_transaccion_idx` (`tipo_transaccion`),
  KEY `transacciones_caja_user_id_idx` (`user_id`),
  CONSTRAINT `transacciones_caja_sub_caja_id_fkey` FOREIGN KEY (`sub_caja_id`) REFERENCES `sub_cajas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `transacciones_caja_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de transacciones en sub-cajas';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ubicacion`
--

DROP TABLE IF EXISTS `ubicacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ubicacion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `almacen_id` int NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Ubicacion_almacen_id_name_key` (`almacen_id`,`name`) USING BTREE,
  KEY `Ubicacion_name_idx` (`name`) USING BTREE,
  CONSTRAINT `Ubicacion_almacen_id_fkey` FOREIGN KEY (`almacen_id`) REFERENCES `almacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ubigeo_inei`
--

DROP TABLE IF EXISTS `ubigeo_inei`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ubigeo_inei` (
  `id_ubigeo` int NOT NULL,
  `departamento` varchar(2) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `provincia` varchar(2) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `distrito` varchar(2) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `nombre` varchar(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`id_ubigeo`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `unidadderivada`
--

DROP TABLE IF EXISTS `unidadderivada`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidadderivada` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `UnidadDerivada_name_key` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `unidadderivadainmutable`
--

DROP TABLE IF EXISTS `unidadderivadainmutable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidadderivadainmutable` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `UnidadDerivadaInmutable_name_key` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `unidadderivadainmutablecompra`
--

DROP TABLE IF EXISTS `unidadderivadainmutablecompra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidadderivadainmutablecompra` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unidad_derivada_inmutable_id` int NOT NULL,
  `producto_almacen_compra_id` int NOT NULL,
  `factor` decimal(9,3) NOT NULL,
  `cantidad` decimal(9,3) NOT NULL,
  `cantidad_pendiente` decimal(9,3) NOT NULL,
  `lote` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vencimiento` datetime(3) DEFAULT NULL,
  `flete` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `bonificacion` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `UnidadDerivadaInmutableCompra_producto_almacen_compra_id_uni_key` (`producto_almacen_compra_id`,`unidad_derivada_inmutable_id`,`bonificacion`) USING BTREE,
  KEY `UnidadDerivadaInmutableCompra_cantidad_pendiente_idx` (`cantidad_pendiente`) USING BTREE,
  KEY `UnidadDerivadaInmutableCompra_unidad_derivada_inmutable_id_fkey` (`unidad_derivada_inmutable_id`) USING BTREE,
  CONSTRAINT `UnidadDerivadaInmutableCompra_producto_almacen_compra_id_fkey` FOREIGN KEY (`producto_almacen_compra_id`) REFERENCES `productoalmacencompra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `UnidadDerivadaInmutableCompra_unidad_derivada_inmutable_id_fkey` FOREIGN KEY (`unidad_derivada_inmutable_id`) REFERENCES `unidadderivadainmutable` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `unidadderivadainmutablecotizacion`
--

DROP TABLE IF EXISTS `unidadderivadainmutablecotizacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidadderivadainmutablecotizacion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unidad_derivada_inmutable_id` int NOT NULL,
  `producto_almacen_cotizacion_id` int NOT NULL,
  `factor` decimal(9,3) NOT NULL,
  `cantidad` decimal(9,3) NOT NULL,
  `precio` decimal(9,4) NOT NULL,
  `recargo` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `descuento_tipo` enum('%','m') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'm',
  `descuento` decimal(9,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `UnidadDerivadaInmutableCotizacion_producto_almacen_cotizacio_key` (`producto_almacen_cotizacion_id`,`unidad_derivada_inmutable_id`) USING BTREE,
  KEY `UnidadDerivadaInmutableCotizacion_unidad_derivada_inmutable_fkey` (`unidad_derivada_inmutable_id`) USING BTREE,
  CONSTRAINT `UnidadDerivadaInmutableCotizacion_producto_almacen_cotizaci_fkey` FOREIGN KEY (`producto_almacen_cotizacion_id`) REFERENCES `productoalmacencotizacion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `UnidadDerivadaInmutableCotizacion_unidad_derivada_inmutable_fkey` FOREIGN KEY (`unidad_derivada_inmutable_id`) REFERENCES `unidadderivadainmutable` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `unidadderivadainmutableingresosalida`
--

DROP TABLE IF EXISTS `unidadderivadainmutableingresosalida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidadderivadainmutableingresosalida` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unidad_derivada_inmutable_id` int NOT NULL,
  `producto_almacen_ingreso_salida_id` int NOT NULL,
  `factor` decimal(9,3) NOT NULL,
  `cantidad` decimal(9,3) NOT NULL,
  `cantidad_restante` decimal(9,3) NOT NULL,
  `lote` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vencimiento` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `UnidadDerivadaInmutableIngresoSalida_producto_almacen_ingre_fkey` (`producto_almacen_ingreso_salida_id`) USING BTREE,
  KEY `UnidadDerivadaInmutableIngresoSalida_unidad_derivada_inmuta_fkey` (`unidad_derivada_inmutable_id`) USING BTREE,
  CONSTRAINT `UnidadDerivadaInmutableIngresoSalida_producto_almacen_ingre_fkey` FOREIGN KEY (`producto_almacen_ingreso_salida_id`) REFERENCES `productoalmaceningresosalida` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `UnidadDerivadaInmutableIngresoSalida_unidad_derivada_inmuta_fkey` FOREIGN KEY (`unidad_derivada_inmutable_id`) REFERENCES `unidadderivadainmutable` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `unidadderivadainmutableprestamo`
--

DROP TABLE IF EXISTS `unidadderivadainmutableprestamo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidadderivadainmutableprestamo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `factor` decimal(9,4) NOT NULL,
  `cantidad` decimal(9,4) NOT NULL,
  `producto_almacen_prestamo_id` int NOT NULL,
  `unidad_derivada_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_unidadderivadainmutableprestamo_producto` (`producto_almacen_prestamo_id`) USING BTREE,
  KEY `idx_unidadderivadainmutableprestamo_unidad` (`unidad_derivada_id`) USING BTREE,
  CONSTRAINT `fk_unidadderivadainmutableprestamo_producto` FOREIGN KEY (`producto_almacen_prestamo_id`) REFERENCES `productoalmacenprestamo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_unidadderivadainmutableprestamo_unidad` FOREIGN KEY (`unidad_derivada_id`) REFERENCES `unidadderivada` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `unidadderivadainmutablerecepcion`
--

DROP TABLE IF EXISTS `unidadderivadainmutablerecepcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidadderivadainmutablerecepcion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unidad_derivada_inmutable_id` int NOT NULL,
  `producto_almacen_recepcion_id` int NOT NULL,
  `factor` decimal(9,3) NOT NULL,
  `cantidad` decimal(9,3) NOT NULL,
  `cantidad_restante` decimal(9,3) NOT NULL,
  `lote` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vencimiento` datetime(3) DEFAULT NULL,
  `flete` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `bonificacion` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `UnidadDerivadaInmutableRecepcion_producto_almacen_recepcion__key` (`producto_almacen_recepcion_id`,`unidad_derivada_inmutable_id`,`bonificacion`) USING BTREE,
  KEY `UnidadDerivadaInmutableRecepcion_unidad_derivada_inmutable__fkey` (`unidad_derivada_inmutable_id`) USING BTREE,
  CONSTRAINT `UnidadDerivadaInmutableRecepcion_producto_almacen_recepcion_fkey` FOREIGN KEY (`producto_almacen_recepcion_id`) REFERENCES `productoalmacenrecepcion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `UnidadDerivadaInmutableRecepcion_unidad_derivada_inmutable__fkey` FOREIGN KEY (`unidad_derivada_inmutable_id`) REFERENCES `unidadderivadainmutable` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `unidadderivadainmutableventa`
--

DROP TABLE IF EXISTS `unidadderivadainmutableventa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidadderivadainmutableventa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unidad_derivada_inmutable_id` int NOT NULL,
  `producto_almacen_venta_id` int NOT NULL,
  `factor` decimal(9,3) NOT NULL,
  `cantidad` decimal(9,3) NOT NULL,
  `cantidad_pendiente` decimal(9,3) NOT NULL,
  `precio` decimal(9,4) NOT NULL,
  `recargo` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `descuento_tipo` enum('%','m') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'm',
  `descuento` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `comision` decimal(9,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `UnidadDerivadaInmutableVenta_producto_almacen_venta_id_unida_key` (`producto_almacen_venta_id`,`unidad_derivada_inmutable_id`) USING BTREE,
  KEY `UnidadDerivadaInmutableVenta_unidad_derivada_inmutable_id_fkey` (`unidad_derivada_inmutable_id`) USING BTREE,
  CONSTRAINT `UnidadDerivadaInmutableVenta_producto_almacen_venta_id_fkey` FOREIGN KEY (`producto_almacen_venta_id`) REFERENCES `productoalmacenventa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `UnidadDerivadaInmutableVenta_unidad_derivada_inmutable_id_fkey` FOREIGN KEY (`unidad_derivada_inmutable_id`) REFERENCES `unidadderivadainmutable` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `unidadmedida`
--

DROP TABLE IF EXISTS `unidadmedida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidadmedida` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `UnidadMedida_name_key` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `emailVerified` datetime(3) DEFAULT NULL,
  `image` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa_id` int NOT NULL,
  `efectivo` decimal(9,2) NOT NULL DEFAULT '0.00',
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  `tipo_documento` enum('DNI','RUC','CE','PASAPORTE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'DNI' COMMENT 'Tipo de documento de identidad',
  `numero_documento` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de documento',
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Teléfono fijo',
  `celular` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Teléfono celular',
  `genero` enum('M','F','O') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Género: M=Masculino, F=Femenino, O=Otro',
  `estado_civil` enum('SOLTERO','CASADO','DIVORCIADO','VIUDO','CONVIVIENTE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estado civil',
  `email_corporativo` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email corporativo',
  `cargo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cargo u ocupación del empleado',
  `fecha_inicio` date DEFAULT NULL COMMENT 'Fecha de inicio de contrato',
  `fecha_baja` date DEFAULT NULL COMMENT 'Fecha de baja/término de contrato',
  `vacaciones_dias` int NOT NULL DEFAULT '15' COMMENT 'Días de vacaciones al año',
  `sueldo_boleta` decimal(10,2) DEFAULT NULL COMMENT 'Sueldo en boleta',
  `rol_sistema` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rol en el sistema (ADMIN, VENDEDOR, etc.)',
  `direccion_linea1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dirección línea 1',
  `direccion_linea2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dirección línea 2',
  `ciudad` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ciudad',
  `nacionalidad` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'PERUANA' COMMENT 'Nacionalidad',
  `fecha_nacimiento` date DEFAULT NULL COMMENT 'Fecha de nacimiento',
  `estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Estado activo/inactivo',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `User_email_key` (`email`) USING BTREE,
  KEY `User_empresa_id_fkey` (`empresa_id`) USING BTREE,
  KEY `idx_numero_documento` (`numero_documento`) USING BTREE,
  KEY `idx_email_corporativo` (`email_corporativo`) USING BTREE,
  CONSTRAINT `User_empresa_id_fkey` FOREIGN KEY (`empresa_id`) REFERENCES `empresa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `users_email_unique` (`email`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendedor`
--

DROP TABLE IF EXISTS `vendedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendedor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dni` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `cumple` datetime(3) DEFAULT NULL,
  `proveedor_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `Vendedor_dni_key` (`dni`) USING BTREE,
  KEY `Vendedor_proveedor_id_fkey` (`proveedor_id`) USING BTREE,
  CONSTRAINT `Vendedor_proveedor_id_fkey` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `venta`
--

DROP TABLE IF EXISTS `venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `venta` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_documento` enum('01','03','nv','in','sa','rc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nv',
  `serie` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` int DEFAULT NULL,
  `descripcion` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `forma_de_pago` enum('co','cr') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'co',
  `tipo_moneda` enum('s','d') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 's',
  `tipo_de_cambio` decimal(9,4) NOT NULL DEFAULT '1.0000',
  `fecha` datetime(3) NOT NULL,
  `estado_de_venta` enum('cr','ee','an','pr') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cr',
  `cliente_id` int DEFAULT NULL,
  `direccion_seleccionada` text COLLATE utf8mb4_unicode_ci,
  `recomendado_por_id` int DEFAULT NULL,
  `chofer_id` int DEFAULT NULL,
  `user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `almacen_id` int NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `Venta_fecha_idx` (`fecha`) USING BTREE,
  KEY `Venta_almacen_id_fkey` (`almacen_id`) USING BTREE,
  KEY `Venta_cliente_id_fkey` (`cliente_id`) USING BTREE,
  KEY `Venta_recomendado_por_id_fkey` (`recomendado_por_id`) USING BTREE,
  KEY `Venta_user_id_fkey` (`user_id`) USING BTREE,
  KEY `fk_venta_chofer` (`chofer_id`) USING BTREE,
  CONSTRAINT `fk_venta_chofer` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `Venta_almacen_id_fkey` FOREIGN KEY (`almacen_id`) REFERENCES `almacen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Venta_cliente_id_fkey` FOREIGN KEY (`cliente_id`) REFERENCES `cliente` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `Venta_recomendado_por_id_fkey` FOREIGN KEY (`recomendado_por_id`) REFERENCES `cliente` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `Venta_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `verificationtoken`
--

DROP TABLE IF EXISTS `verificationtoken`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verificationtoken` (
  `identifier` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires` datetime(3) NOT NULL,
  PRIMARY KEY (`identifier`,`token`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-20  9:05:36
