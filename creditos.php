<?php 
include 'db.php'; 
include 'header.php'; 

// --- 1. LÓGICA: ELIMINAR CRÉDITO (SOLO ADMIN) ---
if (isset($_GET['borrar'])) {
    // SEGURIDAD: Solo admin puede borrar
    if ($_SESSION['rol'] != 'admin') {
        echo "<script>alert('Acceso denegado: Solo administradores pueden eliminar créditos.'); window.location='creditos.php';</script>";
        exit;
    }

    $id_borrar = $_GET['borrar'];
    try {
        $stmt = $conexion->prepare("DELETE FROM creditos WHERE id_credito = ?");
        $stmt->execute([$id_borrar]);
        echo "<script>window.location='creditos.php';</script>";
        exit;
    } catch (Exception $e) {
        echo "<script>alert('No se puede eliminar: Tiene historial asociado.'); window.location='creditos.php';</script>";
    }
}

// --- 2. LÓGICA: REGISTRAR ABONO (PERMITIDO PARA TODOS) ---
// Los vendedores SÍ deben poder cobrar dinero.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'abonar') {
    $id_credito = $_POST['id_credito'];
    $monto_pago = $_POST['monto_pago'];
    $obs = "Abono recibido por: " . $_SESSION['nombre'];

    try {
        $conexion->beginTransaction();
        $stmtPago = $conexion->prepare("INSERT INTO pagos_credito (id_credito, monto_pagado, observaciones) VALUES (?, ?, ?)");
        $stmtPago->execute([$id_credito, $monto_pago, $obs]);

        $stmtUpd = $conexion->prepare("UPDATE creditos SET saldo_disponible = saldo_disponible + ?, fecha_ultimo_pago = NOW() WHERE id_credito = ?");
        $stmtUpd->execute([$monto_pago, $id_credito]);

        $conexion->commit();
        echo "<script>alert('Abono registrado exitosamente.'); window.location='creditos.php';</script>";
    } catch (Exception $e) {
        $conexion->rollBack();
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}

// --- 3. LÓGICA: CREAR NUEVA CUENTA (SOLO ADMIN) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'crear_credito') {
    
    // SEGURIDAD: Solo admin puede crear
    if ($_SESSION['rol'] != 'admin') {
        echo "<script>alert('Acceso denegado: Solo administradores pueden autorizar créditos.'); window.location='creditos.php';</script>";
        exit;
    }

    $id_usuario = $_POST['id_usuario'];
    $limite = $_POST['monto_total'];

    $check = $conexion->prepare("SELECT id_credito FROM creditos WHERE id_usuario = ?");
    $check->execute([$id_usuario]);
    
    if ($check->rowCount() > 0) {
        echo "<script>alert('Este usuario ya tiene un crédito activo.'); window.location='creditos.php';</script>";
    } else {
        $sql = "INSERT INTO creditos (id_usuario, monto_total, saldo_disponible, estado, fecha_autorizacion) VALUES (?, ?, ?, 'aprobado', NOW())";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$id_usuario, $limite, $limite]);
        echo "<script>alert('Crédito aperturado correctamente.'); window.location='creditos.php';</script>";
    }
}

// --- 4. CONSULTAS ---
$creditos = $conexion->query("SELECT c.*, u.nombre as cliente, u.correo FROM creditos c INNER JOIN usuarios u ON c.id_usuario = u.id_usuario ORDER BY c.id_credito DESC")->fetchAll(PDO::FETCH_ASSOC);
$clientes = $conexion->query("SELECT * FROM usuarios WHERE rol != 'admin'")->fetchAll(PDO::FETCH_ASSOC);

$total_deuda = 0;
$total_limites = 0; 
foreach($creditos as $c) {
    $total_deuda += ($c['monto_total'] - $c['saldo_disponible']);
    $total_limites += $c['monto_total']; 
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark">Cartera de Clientes</h3>
    
    <?php if($_SESSION['rol'] == 'admin'): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoCredito">
            <i class="bi bi-person-plus-fill me-2"></i> Aperturar Crédito
        </button>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-custom p-3 border-0 border-start border-4 border-warning shadow-sm">
            <div class="text-muted small fw-bold text-uppercase">TOTAL POR COBRAR</div>
            <div class="fs-2 fw-bold text-warning">$<?php echo number_format($total_deuda, 2); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom p-3 border-0 border-start border-4 border-primary shadow-sm">
            <div class="text-muted small fw-bold text-uppercase">TOTAL CRÉDITOS DADOS</div>
            <div class="fs-2 fw-bold text-primary">$<?php echo number_format($total_limites, 2); ?></div>
        </div>
    </div>
</div>

<div class="card card-custom border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Cliente</th>
                    <th>Límite</th>
                    <th>Disponible</th>
                    <th style="width: 30%;">Estado Deuda</th>
                    <th class="text-end" style="min-width: 180px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($creditos)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">No hay créditos activos.</td></tr>
                <?php else: ?>
                    <?php foreach($creditos as $c): 
                        $deuda = $c['monto_total'] - $c['saldo_disponible'];
                        $pct = ($c['monto_total'] > 0) ? ($deuda / $c['monto_total']) * 100 : 0;
                        $color = ($pct > 80) ? 'bg-danger' : (($pct > 40) ? 'bg-warning' : 'bg-success');
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?php echo $c['cliente']; ?></div>
                            <small class="text-muted"><?php echo $c['correo']; ?></small>
                        </td>
                        <td class="fw-bold text-secondary">$<?php echo number_format($c['monto_total'], 2); ?></td>
                        <td class="fw-bold text-success">$<?php echo number_format($c['saldo_disponible'], 2); ?></td>
                        <td>
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-danger fw-bold">$<?php echo number_format($deuda, 2); ?></span>
                                <span class="text-muted"><?php echo round($pct); ?>%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?php echo $color; ?>" style="width: <?php echo $pct; ?>%"></div>
                            </div>
                        </td>
                        <td class="text-end">
                            <?php if($deuda > 0.01): ?>
                                <button class="btn btn-sm btn-outline-success fw-bold me-1" 
                                        onclick='abrirModalAbono(<?php echo json_encode($c); ?>, <?php echo $deuda; ?>)'>
                                    <i class="bi bi-cash"></i> Abonar
                                </button>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border me-1">Al día</span>
                            <?php endif; ?>

                            <?php if($_SESSION['rol'] == 'admin'): ?>
                                <a href="creditos.php?borrar=<?php echo $c['id_credito']; ?>" 
                                   class="btn btn-sm btn-danger border-0"
                                   onclick="return confirm('¿Seguro que deseas eliminar la cuenta de crédito de <?php echo $c['cliente']; ?>?');"
                                   title="Eliminar Cuenta">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div> <div class="modal fade" id="modalNuevoCredito" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Nuevo Crédito</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="crear_credito">
                    <div class="mb-3">
                        <label class="fw-bold small text-muted">CLIENTE</label>
                        <select name="id_usuario" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($clientes as $cli): ?>
                                <option value="<?php echo $cli['id_usuario']; ?>"><?php echo $cli['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold small text-muted">LÍMITE ($)</label>
                        <input type="number" name="monto_total" class="form-control form-control-lg fw-bold text-primary" placeholder="Ej. 1000" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">Guardar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAbono" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title fw-bold">Abonar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="abonar">
                    <input type="hidden" name="id_credito" id="idCreditoAbono">
                    <div class="text-center mb-3">
                        <h5 class="fw-bold" id="clienteAbono">Client</h5>
                        <div class="badge bg-danger-subtle text-danger border">Debe: $<span id="deudaAbono"></span></div>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small text-muted">MONTO A PAGAR</label>
                        <input type="number" name="monto_pago" class="form-control form-control-lg text-center fw-bold text-success" step="0.01" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold">Confirmar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function abrirModalAbono(credito, deuda) {
        document.getElementById('idCreditoAbono').value = credito.id_credito;
        document.getElementById('clienteAbono').innerText = credito.cliente;
        document.getElementById('deudaAbono').innerText = parseFloat(deuda).toFixed(2);
        new bootstrap.Modal(document.getElementById('modalAbono')).show();
    }
</script>
</body>
</html>