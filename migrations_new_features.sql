-- ==================================================
-- MIGRACIONES PARA NUEVAS FUNCIONALIDADES
-- ==================================================

-- 1. Actualizar tabla PEDIDOS para agregar campos de estado de aprobación
-- Primero, verificar si existen las columnas
ALTER TABLE `pedidos` 
ADD COLUMN IF NOT EXISTS `estado_aprobacion` ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente' AFTER `estado`,
ADD COLUMN IF NOT EXISTS `razon_rechazo` TEXT DEFAULT NULL AFTER `estado_aprobacion`,
ADD COLUMN IF NOT EXISTS `hora_recogida_desde` TIME DEFAULT '09:00:00' AFTER `razon_rechazo`,
ADD COLUMN IF NOT EXISTS `hora_recogida_hasta` TIME DEFAULT '17:00:00' AFTER `hora_recogida_desde`,
ADD COLUMN IF NOT EXISTS `id_encargado_aprobacion` INT(11) DEFAULT NULL AFTER `hora_recogida_hasta`;

-- 2. Crear tabla SOLICITUDES_CREDITO para gestionar solicitudes de crédito de clientes
CREATE TABLE IF NOT EXISTS `solicitudes_credito` (
  `id_solicitud` INT(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` INT(11) NOT NULL,
  `monto_solicitado` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado` ENUM('pendiente', 'aprobado', 'rechazado') NOT NULL DEFAULT 'pendiente',
  `razon_rechazo` TEXT DEFAULT NULL,
  `fecha_solicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` DATETIME DEFAULT NULL,
  `id_encargado_resolucion` INT(11) DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  PRIMARY KEY (`id_solicitud`),
  KEY `idx_solicitud_usuario` (`id_usuario`),
  KEY `idx_solicitud_estado` (`estado`),
  KEY `idx_solicitud_fecha` (`fecha_solicitud`),
  CONSTRAINT `fk_solicitud_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_solicitud_encargado` FOREIGN KEY (`id_encargado_resolucion`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 3. Agregar columna de encargado de aprobación a la tabla pedidos
ALTER TABLE `pedidos`
ADD CONSTRAINT `fk_pedidos_encargado` FOREIGN KEY (`id_encargado_aprobacion`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 4. Crear tabla AUDITAR_CAMBIOS para registrar quién aprobó/rechazó
CREATE TABLE IF NOT EXISTS `auditar_cambios` (
  `id_auditoria` INT(11) NOT NULL AUTO_INCREMENT,
  `tipo_accion` ENUM('pedido_aprobado', 'pedido_rechazado', 'credito_aprobado', 'credito_rechazado') NOT NULL,
  `id_referencia` INT(11) NOT NULL COMMENT 'ID del pedido o solicitud',
  `id_usuario_cliente` INT(11) DEFAULT NULL,
  `id_usuario_encargado` INT(11) DEFAULT NULL,
  `fecha_accion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `observaciones` TEXT DEFAULT NULL,
  PRIMARY KEY (`id_auditoria`),
  KEY `idx_auditoria_tipo` (`tipo_accion`),
  KEY `idx_auditoria_fecha` (`fecha_accion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

