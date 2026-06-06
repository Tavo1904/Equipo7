<?php
include 'db.php';
session_start();

if (!isset($_GET['id'])) die('Falta el ID del pedido.');
$idPedido = (int)$_GET['id'];

$stmt = $conexion->prepare("
    SELECT p.*, u.nombre AS vendedor, COALESCE(c.nombre, 'Venta de mostrador') AS cliente
    FROM pedidos p
    INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
    LEFT JOIN usuarios c ON p.id_cliente = c.id_usuario
    WHERE p.id_pedido = ?
");
$stmt->execute([$idPedido]);
$pedido = $stmt->fetch();
if (!$pedido) die('Pedido no encontrado.');

$stmtDet = $conexion->prepare("
    SELECT dp.*, prod.nombre, prod.numero_lote
    FROM detalle_pedido dp
    INNER JOIN productos prod ON dp.id_producto = prod.id_producto
    WHERE dp.id_pedido = ?
");
$stmtDet->execute([$idPedido]);
$detalles = $stmtDet->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?php echo str_pad($idPedido,6,'0',STR_PAD_LEFT); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: #f0f0f0;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 20px;
            min-height: 100vh;
        }
        .ticket {
            background: #fff;
            width: 320px;
            padding: 18px 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.15);
            border-radius: 4px;
        }
        .header { text-align: center; padding-bottom: 10px; border-bottom: 1px dashed #aaa; margin-bottom: 10px; }
        .header h2 { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .header p  { font-size: 11px; margin-top: 2px; color: #555; }
        .info-row  { display: flex; justify-content: space-between; margin-bottom: 3px; font-size: 11px; }
        .info-row .lbl { color: #555; }
        .info-row .val { font-weight: bold; text-align: right; }
        .sep { border: none; border-top: 1px dashed #aaa; margin: 8px 0; }
        .items-header { display: flex; font-size: 10px; font-weight: bold; color: #888; text-transform: uppercase; margin-bottom: 4px; }
        .items-header .c1 { width: 30px; }
        .items-header .c2 { flex: 1; }
        .items-header .c3 { width: 65px; text-align: right; }
        .item-row { display: flex; align-items: flex-start; padding: 4px 0; border-bottom: 1px dotted #eee; }
        .item-row .c1 { width: 30px; font-weight: bold; color: #333; }
        .item-row .c2 { flex: 1; }
        .item-row .c2 .nombre { font-weight: bold; line-height: 1.3; }
        .item-row .c2 .detalle { font-size: 10px; color: #888; margin-top: 1px; }
        .item-row .c3 { width: 65px; text-align: right; font-weight: bold; }
        .total-section { padding-top: 8px; }
        .total-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 3px; }
        .total-row.grande { font-size: 15px; font-weight: bold; margin-top: 6px; padding-top: 6px; border-top: 2px solid #000; }
        .footer { text-align: center; margin-top: 12px; padding-top: 10px; border-top: 1px dashed #aaa; font-size: 11px; color: #666; line-height: 1.6; }
        .no-print { margin-top: 16px; text-align: center; }
        .no-print button {
            padding: 7px 16px; margin: 3px;
            border: none; border-radius: 6px;
            cursor: pointer; font-size: 12px; font-weight: bold;
        }
        .btn-print { background: #0d6efd; color: #fff; }
        .btn-close-w { background: #e9ecef; color: #333; }
        @media print {
            body { background: #fff; padding: 0; }
            .ticket { box-shadow: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
<div class="ticket">
    <div class="header">
        <h2>FARMACIA PEÑALOZA</h2>
        <p>Ticket de Venta</p>
        <p><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></p>
    </div>

    <div class="info-row"><span class="lbl">Folio:</span><span class="val">#<?php echo str_pad($pedido['id_pedido'],6,'0',STR_PAD_LEFT); ?></span></div>
    <div class="info-row"><span class="lbl">Cliente:</span><span class="val"><?php echo h($pedido['cliente']); ?></span></div>
    <div class="info-row"><span class="lbl">Vendedor:</span><span class="val"><?php echo h($pedido['vendedor']); ?></span></div>
    <div class="info-row"><span class="lbl">Forma de Pago:</span><span class="val"><?php echo ucfirst(h($pedido['tipo_pago'])); ?></span></div>

    <hr class="sep">

    <div class="items-header">
        <div class="c1">Cant</div>
        <div class="c2">Producto</div>
        <div class="c3">Importe</div>
    </div>

    <?php foreach ($detalles as $d): ?>
    <div class="item-row">
        <div class="c1"><?php echo (int)$d['cantidad']; ?></div>
        <div class="c2">
            <div class="nombre"><?php echo h($d['nombre']); ?></div>
            <div class="detalle">Lote: <?php echo h($d['numero_lote']); ?> | <?php echo ucfirst(h($d['modalidad'])); ?> | $<?php echo number_format((float)$d['precio_unitario'],2); ?>/u</div>
        </div>
        <div class="c3">$<?php echo number_format((float)$d['subtotal'],2); ?></div>
    </div>
    <?php endforeach; ?>

    <div class="total-section">
        <div class="total-row grande">
            <span>TOTAL</span>
            <span>$<?php echo number_format((float)$pedido['total'],2); ?></span>
        </div>
    </div>

    <div class="footer">
        Gracias por su compra.<br>
        Conserve este ticket para aclaraciones.<br>
        Farmacia Peñaloza
    </div>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()"><i>🖨</i> Imprimir</button>
        <button class="btn-close-w" onclick="window.close()">Cerrar</button>
    </div>
</div>
</body>
</html>
