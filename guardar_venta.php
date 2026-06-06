<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include 'db.php';

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['productos']) || !is_array($input['productos'])) {
    echo json_encode(['success' => false, 'message' => 'Datos invalidos']);
    exit;
}

try {
    $conexion->beginTransaction();

    $idClienteVenta = !empty($input['id_cliente']) ? (int)$input['id_cliente'] : null;
    $idClienteCredito = !empty($input['id_cliente_credito']) ? (int)$input['id_cliente_credito'] : null;
    $tipoPago = $input['tipo_pago'] ?? 'efectivo';

    // Validar cliente para reglas de mayoreo
    $cliente = null;
    $idClienteRef = $idClienteCredito ?? $idClienteVenta;
    if ($idClienteRef) {
        $stmtCliente = $conexion->prepare("SELECT id_usuario, nombre, tipo_cliente, limite_mayoreo FROM usuarios WHERE id_usuario = ?");
        $stmtCliente->execute([$idClienteRef]);
        $cliente = $stmtCliente->fetch();
    }

    // Si pago con credito, validar saldo ANTES de procesar
    $creditoRecord = null;
    if ($tipoPago === 'credito') {
        if (!$idClienteCredito) {
            throw new Exception('Debe seleccionar un cliente con credito para este tipo de pago.');
        }
        $stmtCred = $conexion->prepare("SELECT * FROM creditos WHERE id_usuario = ? AND estado = 'aprobado' FOR UPDATE");
        $stmtCred->execute([$idClienteCredito]);
        $creditoRecord = $stmtCred->fetch();
        if (!$creditoRecord) {
            throw new Exception('El cliente no tiene una linea de credito activa.');
        }
    }

    // Procesar productos
    $items = [];
    $total = 0.0;

    foreach ($input['productos'] as $prod) {
        $idProducto = (int)($prod['id'] ?? 0);
        $cantidad   = (int)($prod['cantidad'] ?? 0);
        $modalidadSolicitada = ($prod['modalidad'] ?? 'menudeo') === 'mayoreo' ? 'mayoreo' : 'menudeo';

        if ($idProducto <= 0 || $cantidad <= 0) throw new Exception('Producto o cantidad invalida');

        $stmtCheck = $conexion->prepare("SELECT * FROM productos WHERE id_producto = ? FOR UPDATE");
        $stmtCheck->execute([$idProducto]);
        $producto = $stmtCheck->fetch();

        if (!$producto) throw new Exception('Producto no encontrado');
        if ((int)$producto['stock'] < $cantidad) throw new Exception('Stock insuficiente para: ' . $producto['nombre']);
        if (!can_sell_product($producto)) throw new Exception(expiry_status($producto['fecha_caducidad'] ?? null)['message']);

        $modalidad = $modalidadSolicitada;
        $limiteMayoreo = $cliente ? (int)$cliente['limite_mayoreo'] : 50;
        if ($modalidad === 'mayoreo' && (!$cliente || $cliente['tipo_cliente'] !== 'mayorista' || $cantidad < $limiteMayoreo)) {
            $modalidad = 'menudeo';
        }

        $precioUnitario = $modalidad === 'mayoreo' ? (float)$producto['precio_mayoreo'] : (float)$producto['precio'];
        if ($precioUnitario <= 0) { $precioUnitario = (float)$producto['precio']; $modalidad = 'menudeo'; }

        $subtotal = $precioUnitario * $cantidad;
        $total += $subtotal;
        $items[] = ['producto' => $producto, 'cantidad' => $cantidad, 'precio_unitario' => $precioUnitario, 'modalidad' => $modalidad, 'subtotal' => $subtotal];
    }

    // Validar saldo de credito vs total real calculado
    if ($tipoPago === 'credito' && $creditoRecord) {
        if ((float)$creditoRecord['saldo_disponible'] < $total) {
            throw new Exception('Credito insuficiente. Saldo: $' . number_format((float)$creditoRecord['saldo_disponible'], 2) . ' — Total venta: $' . number_format($total, 2));
        }
    }

    // Insertar pedido
    $idCliente = $idClienteCredito ?? $idClienteVenta;
    $stmtPedido = $conexion->prepare("INSERT INTO pedidos (id_usuario, id_cliente, estado, tipo_pago, total, fecha) VALUES (?, ?, 'completado', ?, ?, NOW())");
    $stmtPedido->execute([$_SESSION['id_usuario'], $idCliente, $tipoPago, $total]);
    $idPedido = $conexion->lastInsertId();

    $stmtDetalle = $conexion->prepare("INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, subtotal, precio_unitario, modalidad) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtStock   = $conexion->prepare("UPDATE productos SET stock = stock - ? WHERE id_producto = ?");
    $stmtMov     = $conexion->prepare("INSERT INTO movimientos_inventario (id_producto, id_usuario, tipo_movimiento, cantidad, observaciones) VALUES (?, ?, 'salida', ?, ?)");

    foreach ($items as $item) {
        $prod = $item['producto'];
        $stmtDetalle->execute([$idPedido, $prod['id_producto'], $item['cantidad'], $item['subtotal'], $item['precio_unitario'], $item['modalidad']]);
        $stmtStock->execute([$item['cantidad'], $prod['id_producto']]);
        // Registrar movimiento de SALIDA por venta (para corte de caja y reportes)
        $stmtMov->execute([
            $prod['id_producto'],
            $_SESSION['id_usuario'],
            $item['cantidad'],
            'Venta POS Folio #' . str_pad($idPedido, 6, '0', STR_PAD_LEFT) . ' (' . $item['modalidad'] . ') — $' . number_format($item['precio_unitario'], 2) . ' c/u'
        ]);
    }

    // Descontar del credito si aplica
    if ($tipoPago === 'credito' && $creditoRecord) {
        $nuevoSaldo = (float)$creditoRecord['saldo_disponible'] - $total;
        $stmtUpdCred = $conexion->prepare("UPDATE creditos SET saldo_disponible = ? WHERE id_credito = ?");
        $stmtUpdCred->execute([$nuevoSaldo, $creditoRecord['id_credito']]);
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'id_pedido' => $idPedido, 'total' => $total]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
