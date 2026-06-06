<?php
include 'db.php';
include 'header.php';
require_admin('Permisos insuficientes');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_proveedor') {
    try {
        $stmt = $conexion->prepare("INSERT INTO proveedores (nombre, rfc, telefono, productos) VALUES (?, ?, ?, ?)");
        $stmt->execute([trim($_POST['nombre']), trim($_POST['rfc']), trim($_POST['telefono']), trim($_POST['productos'])]);
        $success = 'Proveedor guardado en DB. Solo visible para Administrador.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cargar_factura') {
    $idProveedor = (int)$_POST['id_proveedor'];
    $idProducto = (int)$_POST['id_producto'];
    $cantidad = max(1, (int)$_POST['cantidad']);
    $costo = (float)$_POST['costo_unitario'];
    $folio = trim($_POST['folio'] ?? '');

    try {
        $conexion->beginTransaction();
        $stmt = $conexion->prepare("INSERT INTO facturas_compra (id_proveedor, id_producto, cantidad, costo_unitario, folio) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$idProveedor, $idProducto, $cantidad, $costo, $folio]);

        $upd = $conexion->prepare("UPDATE productos SET stock = stock + ?, costo_adquisicion = ? WHERE id_producto = ?");
        $upd->execute([$cantidad, $costo, $idProducto]);

        $mov = $conexion->prepare("INSERT INTO movimientos_inventario (id_producto, id_usuario, tipo_movimiento, cantidad, observaciones) VALUES (?, ?, 'entrada', ?, ?)");
        $mov->execute([$idProducto, $_SESSION['id_usuario'], $cantidad, 'Factura de compra ' . ($folio ?: 'sin folio')]);

        $conexion->commit();
        $success = 'Factura registrada. Stock y costo de adquisicion actualizados sin modificar precio de venta.';
    } catch (Exception $e) {
        $conexion->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}

$proveedores = $conexion->query("SELECT * FROM proveedores ORDER BY nombre ASC")->fetchAll();
$productos = $conexion->query("SELECT id_producto, nombre, numero_lote, stock, costo_adquisicion, precio FROM productos ORDER BY nombre ASC")->fetchAll();
$facturas = $conexion->query("
    SELECT f.*, pr.nombre AS producto, p.nombre AS proveedor
    FROM facturas_compra f
    INNER JOIN proveedores p ON f.id_proveedor = p.id_proveedor
    INNER JOIN productos pr ON f.id_producto = pr.id_producto
    ORDER BY f.fecha DESC
    LIMIT 50
")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark">Proveedores y Abastecimiento</h3>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h5 class="fw-bold mb-0">Nuevo Proveedor</h5></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar_proveedor">
                    <label class="small fw-bold text-muted">Nombre</label>
                    <input name="nombre" class="form-control mb-3" required>
                    <label class="small fw-bold text-muted">RFC</label>
                    <input name="rfc" class="form-control mb-3" required>
                    <label class="small fw-bold text-muted">Telefono</label>
                    <input name="telefono" class="form-control mb-3" required>
                    <label class="small fw-bold text-muted">Productos</label>
                    <input name="productos" class="form-control mb-4" placeholder="Analgesicos, antibioticos">
                    <button class="btn btn-primary w-100 fw-bold">Guardar Proveedor</button>
                </form>
            </div>
        </div>

        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header bg-white"><h5 class="fw-bold mb-0">Cargar Factura</h5></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="cargar_factura">
                    <label class="small fw-bold text-muted">Proveedor</label>
                    <select name="id_proveedor" class="form-select mb-3" required>
                        <?php foreach ($proveedores as $p): ?><option value="<?php echo (int)$p['id_proveedor']; ?>"><?php echo h($p['nombre']); ?></option><?php endforeach; ?>
                    </select>
                    <label class="small fw-bold text-muted">Producto</label>
                    <select name="id_producto" class="form-select mb-3" required>
                        <?php foreach ($productos as $p): ?><option value="<?php echo (int)$p['id_producto']; ?>"><?php echo h($p['nombre']); ?> / Lote <?php echo h($p['numero_lote']); ?> / Stock <?php echo (int)$p['stock']; ?></option><?php endforeach; ?>
                    </select>
                    <label class="small fw-bold text-muted">Folio</label>
                    <input name="folio" class="form-control mb-3">
                    <label class="small fw-bold text-muted">Cantidad</label>
                    <input type="number" name="cantidad" min="1" class="form-control mb-3" required>
                    <label class="small fw-bold text-muted">Costo unitario</label>
                    <input type="number" step="0.01" name="costo_unitario" min="0" class="form-control mb-4" required>
                    <button class="btn btn-success w-100 fw-bold">Registrar Factura</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-custom border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h5 class="fw-bold mb-0">Lista de Proveedores</h5></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0"><thead class="table-light"><tr><th>Nombre</th><th>RFC</th><th>Telefono</th><th>Productos</th></tr></thead><tbody>
                    <?php foreach ($proveedores as $p): ?><tr><td class="fw-bold"><?php echo h($p['nombre']); ?></td><td><?php echo h($p['rfc']); ?></td><td><?php echo h($p['telefono']); ?></td><td><?php echo h($p['productos']); ?></td></tr><?php endforeach; ?>
                </tbody></table>
            </div>
        </div>

        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header bg-white"><h5 class="fw-bold mb-0">Historial de Facturas</h5></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0"><thead class="table-light"><tr><th>Fecha</th><th>Folio</th><th>Proveedor</th><th>Producto</th><th>Cantidad</th><th>Costo</th></tr></thead><tbody>
                    <?php foreach ($facturas as $f): ?><tr><td><?php echo h($f['fecha']); ?></td><td><?php echo h($f['folio']); ?></td><td><?php echo h($f['proveedor']); ?></td><td><?php echo h($f['producto']); ?></td><td><?php echo (int)$f['cantidad']; ?></td><td>$<?php echo number_format((float)$f['costo_unitario'], 2); ?></td></tr><?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
