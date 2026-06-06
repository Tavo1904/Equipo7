<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'] ?? '', ['admin', 'vendedor'], true)) {
    header("Location: index.php");
    exit;
}

if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso']) > 900) {
    session_unset();
    session_destroy();
    header("Location: index.php?expirada=1");
    exit;
}
$_SESSION['ultimo_acceso'] = time();

$current = basename($_SERVER['PHP_SELF']);
$nombreSesion = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Farmaceutico - Populares Penaloza</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f5f7fb; font-family: 'Segoe UI', sans-serif; }
        .sidebar {
            min-height: 100vh; background-color: #0d6efd; color: white;
            position: fixed; width: 230px; top: 0; left: 0; z-index: 1000;
            display: flex; flex-direction: column; overflow-y: auto;
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 10px; }
        .main-content { margin-left: 230px; padding: 20px; width: calc(100% - 230px); }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.9); padding: 8px 15px; font-size: 0.95rem;
            border-radius: 6px; margin-bottom: 3px; transition: all 0.2s;
            display: flex; align-items: center;
        }
        .sidebar .nav-link i { font-size: 1.1rem; margin-right: 10px; }
        .sidebar .nav-link:hover { background-color: rgba(255,255,255,0.15); color: white; transform: translateX(3px); }
        .sidebar .nav-link.active { background-color: white; color: #0d6efd; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .card-custom { border: 1px solid #eaeaea; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); background: white; }
        .required-empty { border-color: #dc3545 !important; box-shadow: 0 0 0 .2rem rgba(220,53,69,.15); }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3">
        <div class="d-flex align-items-center justify-content-center border-bottom border-white-50 pb-3 mb-3">
            <i class="bi bi-capsule fs-3 me-2"></i>
            <span class="fw-bold fs-5">Penaloza</span>
        </div>

        <div class="d-flex align-items-center rounded p-2 mb-3 bg-white bg-opacity-10">
            <div class="bg-white rounded-circle p-1 d-flex justify-content-center align-items-center text-primary" style="width:35px;height:35px;">
                <i class="bi bi-person-fill fs-5"></i>
            </div>
            <div class="ms-2 lh-1 overflow-hidden">
                <div class="fw-bold text-truncate" style="font-size:.9rem;"><?php echo h($nombreSesion); ?></div>
                <div class="badge bg-warning text-dark p-1 mt-1" style="font-size:.65rem;"><?php echo strtoupper(h($_SESSION['rol'])); ?></div>
            </div>
        </div>

        <nav class="nav flex-column flex-grow-1">
            <a class="nav-link <?php echo $current == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link <?php echo $current == 'pos.php' ? 'active' : ''; ?>" href="pos.php"><i class="bi bi-shop"></i> Punto de Venta</a>
            <a class="nav-link <?php echo $current == 'inventario.php' ? 'active' : ''; ?>" href="inventario.php"><i class="bi bi-box"></i> Inventario</a>
            <a class="nav-link <?php echo $current == 'pedidos.php' ? 'active' : ''; ?>" href="pedidos.php"><i class="bi bi-list-check"></i> Pedidos Web</a>
            <a class="nav-link <?php echo $current == 'movimientos.php' ? 'active' : ''; ?>" href="movimientos.php"><i class="bi bi-arrow-left-right"></i> Movimientos</a>
            <a class="nav-link <?php echo $current == 'creditos.php' ? 'active' : ''; ?>" href="creditos.php"><i class="bi bi-credit-card"></i> Creditos</a>
            <a class="nav-link <?php echo $current == 'clientes.php' ? 'active' : ''; ?>" href="clientes.php"><i class="bi bi-person-vcard"></i> Clientes</a>

            <?php if ($_SESSION['rol'] === 'admin'): ?>
                <div class="mt-3 mb-1 px-2 text-uppercase small text-white-50 fw-bold border-top border-white-50 pt-2" style="font-size:.7rem;">Sistema</div>
                <a class="nav-link <?php echo $current == 'proveedores.php' ? 'active' : ''; ?>" href="proveedores.php"><i class="bi bi-truck"></i> Proveedores</a>
                <a class="nav-link <?php echo $current == 'reportes.php' ? 'active' : ''; ?>" href="reportes.php"><i class="bi bi-file-earmark-bar-graph"></i> Reportes</a>
                <a class="nav-link <?php echo $current == 'empleados.php' ? 'active' : ''; ?>" href="empleados.php"><i class="bi bi-people-fill"></i> Empleados</a>
                <a class="nav-link <?php echo $current == 'configuracion.php' ? 'active' : ''; ?>" href="configuracion.php"><i class="bi bi-gear-fill"></i> Configuracion</a>
            <?php endif; ?>
        </nav>

        <div class="mt-auto pt-3">
            <a class="nav-link bg-danger bg-opacity-75 text-white justify-content-center fw-bold shadow-sm" href="logout.php">
                <i class="bi bi-box-arrow-left"></i> Salir
            </a>
        </div>
    </div>
    <div class="main-content">
