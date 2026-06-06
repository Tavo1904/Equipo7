<?php
include 'db.php';
include 'header.php';
require_admin('Permisos insuficientes');

$totalProductos = $conexion->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$totalUsuarios = $conexion->query("SELECT COUNT(*) FROM usuarios WHERE rol IN ('admin','vendedor')")->fetchColumn();
$totalClientes = $conexion->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente' OR tipo_cliente = 'mayorista'")->fetchColumn();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark">Configuracion</h3>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card card-custom border-0 p-4 h-100">
            <div class="small text-muted fw-bold">PRODUCTOS REGISTRADOS</div>
            <div class="fs-2 fw-bold text-primary"><?php echo (int)$totalProductos; ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom border-0 p-4 h-100">
            <div class="small text-muted fw-bold">USUARIOS INTERNOS</div>
            <div class="fs-2 fw-bold text-success"><?php echo (int)$totalUsuarios; ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom border-0 p-4 h-100">
            <div class="small text-muted fw-bold">CLIENTES</div>
            <div class="fs-2 fw-bold text-info"><?php echo (int)$totalClientes; ?></div>
        </div>
    </div>
</div>

<div class="card card-custom border-0 shadow-sm mt-4">
    <div class="card-header bg-white"><h5 class="fw-bold mb-0">Parametros del sistema</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="small fw-bold text-muted">Tiempo de inactividad</label>
                <input class="form-control" value="15 minutos" readonly>
            </div>
            <div class="col-md-4">
                <label class="small fw-bold text-muted">Intentos fallidos permitidos</label>
                <input class="form-control" value="5 intentos" readonly>
            </div>
            <div class="col-md-4">
                <label class="small fw-bold text-muted">Alerta de caducidad</label>
                <input class="form-control" value="90 dias antes" readonly>
            </div>
        </div>
    </div>
</div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
