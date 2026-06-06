<?php
include 'db.php';
include 'header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['id_usuario'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $rfc = trim($_POST['rfc'] ?? '');
    $tipo = $_POST['tipo_cliente'] === 'mayorista' ? 'mayorista' : 'minorista';
    $limite = max(1, (int)($_POST['limite_mayoreo'] ?? 50));

    if ($nombre === '' || $correo === '') {
        $error = 'Nombre y correo son obligatorios.';
    } else {
        try {
            if ($id === '') {
                $hash = password_hash('Cliente123', PASSWORD_DEFAULT);
                $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, correo, contraseña, rol, estado, rfc, tipo_cliente, limite_mayoreo) VALUES (?, ?, ?, 'cliente', 'activo', ?, ?, ?)");
                $stmt->execute([$nombre, $correo, $hash, $rfc, $tipo, $limite]);
                $success = 'Cliente registrado. Al seleccionarlo en POS se aplican sus reglas comerciales.';
            } else {
                $stmt = $conexion->prepare("UPDATE usuarios SET nombre=?, correo=?, rfc=?, tipo_cliente=?, limite_mayoreo=? WHERE id_usuario=?");
                $stmt->execute([$nombre, $correo, $rfc, $tipo, $limite, $id]);
                $success = 'Cliente actualizado.';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$clientes = $conexion->query("SELECT * FROM usuarios WHERE rol = 'cliente' OR tipo_cliente = 'mayorista' ORDER BY nombre ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark">Clientes Mayoristas</h3>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header bg-white"><h5 class="fw-bold mb-0" id="tituloCliente">Nuevo Cliente</h5></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="id_usuario" id="inputId">
                    <label class="small fw-bold text-muted">Nombre</label>
                    <input name="nombre" id="inputNombre" class="form-control mb-3" required>
                    <label class="small fw-bold text-muted">Correo</label>
                    <input type="email" name="correo" id="inputCorreo" class="form-control mb-3" required>
                    <label class="small fw-bold text-muted">RFC</label>
                    <input name="rfc" id="inputRfc" class="form-control mb-3">
                    <label class="small fw-bold text-muted">Tipo</label>
                    <select name="tipo_cliente" id="inputTipo" class="form-select mb-3">
                        <option value="mayorista">Mayorista</option>
                        <option value="minorista">Minorista</option>
                    </select>
                    <label class="small fw-bold text-muted">Minimo mayoreo</label>
                    <input type="number" name="limite_mayoreo" id="inputLimite" class="form-control mb-4" value="50" min="1">
                    <button class="btn btn-primary w-100 fw-bold">Guardar Cliente</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card card-custom border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Cliente</th><th>RFC</th><th>Perfil</th><th>Historial</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($clientes as $c):
                        $stmtHist = $conexion->prepare("SELECT COUNT(*) AS compras, COALESCE(SUM(total),0) AS total FROM pedidos WHERE id_cliente = ?");
                        $stmtHist->execute([$c['id_usuario']]);
                        $hist = $stmtHist->fetch();
                    ?>
                        <tr>
                            <td><div class="fw-bold"><?php echo h($c['nombre']); ?></div><small class="text-muted"><?php echo h($c['correo']); ?></small></td>
                            <td><?php echo h($c['rfc']); ?></td>
                            <td><span class="badge <?php echo $c['tipo_cliente'] === 'mayorista' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo h($c['tipo_cliente']); ?></span><div class="small text-muted">Min. <?php echo (int)$c['limite_mayoreo']; ?> uds</div></td>
                            <td><?php echo (int)$hist['compras']; ?> compras<br><small class="text-muted">$<?php echo number_format((float)$hist['total'], 2); ?></small></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="clientes.php?historial=<?php echo (int)$c['id_usuario']; ?>"><i class="bi bi-clock-history"></i></a>
                                <button class="btn btn-sm btn-warning" onclick='editarCliente(<?php echo json_encode($c); ?>)'><i class="bi bi-pencil"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (isset($_GET['historial'])):
            $idHist = (int)$_GET['historial'];
            $stmt = $conexion->prepare("
                SELECT p.id_pedido, p.fecha, pr.nombre AS producto, dp.cantidad, dp.precio_unitario, dp.modalidad, dp.subtotal, p.total
                FROM pedidos p
                INNER JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido
                INNER JOIN productos pr ON dp.id_producto = pr.id_producto
                WHERE p.id_cliente = ?
                ORDER BY p.fecha DESC
            ");
            $stmt->execute([$idHist]);
            $historial = $stmt->fetchAll();
        ?>
        <div class="card card-custom border-0 shadow-sm mt-4">
            <div class="card-header bg-white"><h5 class="fw-bold mb-0">Historial de compras</h5></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Folio</th><th>Fecha</th><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Modalidad</th><th>Total linea</th><th>Total compra</th></tr></thead>
                    <tbody>
                    <?php foreach ($historial as $h): ?>
                        <tr><td>#<?php echo str_pad((string)$h['id_pedido'], 6, '0', STR_PAD_LEFT); ?></td><td><?php echo h($h['fecha']); ?></td><td><?php echo h($h['producto']); ?></td><td><?php echo (int)$h['cantidad']; ?></td><td>$<?php echo number_format((float)$h['precio_unitario'], 2); ?></td><td><?php echo h($h['modalidad']); ?></td><td>$<?php echo number_format((float)$h['subtotal'], 2); ?></td><td class="fw-bold">$<?php echo number_format((float)$h['total'], 2); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editarCliente(c) {
    document.getElementById('tituloCliente').innerText = 'Editar Cliente';
    document.getElementById('inputId').value = c.id_usuario;
    document.getElementById('inputNombre').value = c.nombre || '';
    document.getElementById('inputCorreo').value = c.correo || '';
    document.getElementById('inputRfc').value = c.rfc || '';
    document.getElementById('inputTipo').value = c.tipo_cliente || 'minorista';
    document.getElementById('inputLimite').value = c.limite_mayoreo || 50;
}
</script>
</body></html>
