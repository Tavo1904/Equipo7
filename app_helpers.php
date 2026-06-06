<?php
if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('column_exists')) {
    function column_exists(PDO $conexion, $table, $column) {
        $stmt = $conexion->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('table_exists')) {
    function table_exists(PDO $conexion, $table) {
        $stmt = $conexion->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('ensure_column')) {
    function ensure_column(PDO $conexion, $table, $column, $definition) {
        if (!column_exists($conexion, $table, $column)) {
            $conexion->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
}

if (!function_exists('index_exists')) {
    function index_exists(PDO $conexion, $table, $index) {
        $stmt = $conexion->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ");
        $stmt->execute([$table, $index]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('ensure_index')) {
    function ensure_index(PDO $conexion, $table, $index, $column) {
        if (!index_exists($conexion, $table, $index)) {
            $conexion->exec("ALTER TABLE `$table` ADD INDEX `$index` (`$column`)");
        }
    }
}

if (!function_exists('ensure_schema')) {
    function ensure_schema(PDO $conexion) {
        ensure_column($conexion, 'productos', 'compuesto', "VARCHAR(150) NULL");
        ensure_column($conexion, 'productos', 'numero_lote', "VARCHAR(60) NULL");
        ensure_column($conexion, 'productos', 'precio_mayoreo', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        ensure_column($conexion, 'productos', 'costo_adquisicion', "DECIMAL(10,2) NOT NULL DEFAULT 0");

        ensure_column($conexion, 'usuarios', 'intentos_fallidos', "INT NOT NULL DEFAULT 0");
        ensure_column($conexion, 'usuarios', 'bloqueado_hasta', "DATETIME NULL");
        ensure_column($conexion, 'usuarios', 'rfc', "VARCHAR(20) NULL");
        ensure_column($conexion, 'usuarios', 'tipo_cliente', "VARCHAR(20) NOT NULL DEFAULT 'minorista'");
        ensure_column($conexion, 'usuarios', 'limite_mayoreo', "INT NOT NULL DEFAULT 50");

        ensure_column($conexion, 'pedidos', 'id_cliente', "INT NULL");

        ensure_column($conexion, 'detalle_pedido', 'precio_unitario', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        ensure_column($conexion, 'detalle_pedido', 'modalidad', "VARCHAR(20) NOT NULL DEFAULT 'menudeo'");

        if (!table_exists($conexion, 'proveedores')) {
            $conexion->exec("
                CREATE TABLE proveedores (
                    id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
                    nombre VARCHAR(150) NOT NULL,
                    rfc VARCHAR(20) NOT NULL,
                    telefono VARCHAR(20) NOT NULL,
                    productos TEXT NULL,
                    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");
        }

        if (!table_exists($conexion, 'facturas_compra')) {
            $conexion->exec("
                CREATE TABLE facturas_compra (
                    id_factura INT AUTO_INCREMENT PRIMARY KEY,
                    id_proveedor INT NOT NULL,
                    id_producto INT NOT NULL,
                    cantidad INT NOT NULL,
                    costo_unitario DECIMAL(10,2) NOT NULL,
                    folio VARCHAR(80) NULL,
                    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");
        }

        if (!table_exists($conexion, 'historial_precios')) {
            $conexion->exec("
                CREATE TABLE historial_precios (
                    id_historial INT AUTO_INCREMENT PRIMARY KEY,
                    id_producto INT NOT NULL,
                    id_usuario INT NOT NULL,
                    precio_menudeo_anterior DECIMAL(10,2) NOT NULL DEFAULT 0,
                    precio_menudeo_nuevo DECIMAL(10,2) NOT NULL DEFAULT 0,
                    precio_mayoreo_anterior DECIMAL(10,2) NOT NULL DEFAULT 0,
                    precio_mayoreo_nuevo DECIMAL(10,2) NOT NULL DEFAULT 0,
                    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");
        }

        ensure_index($conexion, 'productos', 'idx_productos_nombre', 'nombre');
        ensure_index($conexion, 'productos', 'idx_productos_lote', 'numero_lote');
        ensure_index($conexion, 'productos', 'idx_productos_caducidad', 'fecha_caducidad');
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
    }
}

if (!function_exists('require_admin')) {
    function require_admin($message = 'Permisos insuficientes') {
        if (!is_admin()) {
            http_response_code(403);
            echo "<div class='alert alert-danger fw-bold'>$message</div>";
            echo "<a href='dashboard.php' class='btn btn-primary'>Volver al panel</a>";
            echo "</div></div></body></html>";
            exit;
        }
    }
}

if (!function_exists('normalize_text')) {
    function normalize_text($text) {
        $text = function_exists('mb_strtolower') ? mb_strtolower((string)$text, 'UTF-8') : strtolower((string)$text);

        $text = str_replace(
            ["\xC3\xA1", "\xC3\xA9", "\xC3\xAD", "\xC3\xB3", "\xC3\xBA", "\xC3\xBC", "\xC3\xB1"],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $text
        );

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return str_replace(["'", '`', '^', '~'], '', strtolower($converted));
            }
        }

        $from = ['á','é','í','ó','ú','ü','ñ','Á','É','Í','Ó','Ú','Ü','Ñ'];
        $to = ['a','e','i','o','u','u','n','a','e','i','o','u','u','n'];
        return str_replace(["'", '`', '^', '~'], '', str_replace($from, $to, $text));
    }
}

if (!function_exists('expiry_status')) {
    function expiry_status($fecha) {
        if (empty($fecha)) {
            return ['status' => 'ok', 'days' => null, 'message' => ''];
        }

        $today = new DateTimeImmutable(date('Y-m-d'));
        $expiry = new DateTimeImmutable($fecha);
        $days = (int)$today->diff($expiry)->format('%r%a');

        if ($days < 0) {
            return ['status' => 'expired', 'days' => $days, 'message' => 'Producto vencido - no se puede vender'];
        }
        if ($days === 0) {
            return ['status' => 'expired_today', 'days' => 0, 'message' => 'Producto vencido el dia de hoy - no es apto para comercializacion'];
        }
        if ($days <= 90) {
            return ['status' => 'warning', 'days' => $days, 'message' => 'Proximo a vencer - ' . $days . ' dias'];
        }
        return ['status' => 'ok', 'days' => $days, 'message' => ''];
    }
}

if (!function_exists('can_sell_product')) {
    function can_sell_product(array $producto) {
        $status = expiry_status($producto['fecha_caducidad'] ?? null);
        return $status['status'] !== 'expired' && $status['status'] !== 'expired_today';
    }
}
?>
