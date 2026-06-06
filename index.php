<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$info = isset($_GET['expirada']) ? 'Sesion expirada por inactividad.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($correo === '' || $password === '') {
        $error = 'Por favor ingrese correo y contrasena.';
    } else {
        try {
            $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE correo = ? AND rol IN ('admin','vendedor') LIMIT 1");
            $stmt->execute([$correo]);
            $usuario = $stmt->fetch();

            if (!$usuario) {
                $error = 'Credenciales incorrectas o no tienes permisos de acceso.';
            } elseif (!empty($usuario['bloqueado_hasta']) && strtotime($usuario['bloqueado_hasta']) > time()) {
                $error = 'Cuenta suspendida temporalmente. Contacta al Administrador.';
            } elseif (($usuario['estado'] ?? '') !== 'activo') {
                $error = 'Acceso denegado: usuario inactivo.';
            } else {
                $hashGuardado = $usuario['contraseña'];
                $passwordOk = password_verify($password, $hashGuardado) || hash('sha256', $password) === $hashGuardado;

                if ($passwordOk) {
                    $conexion->prepare("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?")->execute([$usuario['id_usuario']]);
                    $_SESSION['id_usuario'] = $usuario['id_usuario'];
                    $_SESSION['nombre'] = $usuario['nombre'];
                    $_SESSION['usuario'] = $usuario['nombre'];
                    $_SESSION['rol'] = $usuario['rol'];
                    $_SESSION['ultimo_acceso'] = time();
                    header("Location: dashboard.php");
                    exit;
                }

                $intentos = (int)$usuario['intentos_fallidos'] + 1;
                if ($intentos >= 5) {
                    $conexion->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id_usuario = ?")->execute([$intentos, $usuario['id_usuario']]);
                    $error = 'Cuenta suspendida temporalmente. Contacta al Administrador.';
                } else {
                    $conexion->prepare("UPDATE usuarios SET intentos_fallidos = ? WHERE id_usuario = ?")->execute([$intentos, $usuario['id_usuario']]);
                    $error = 'Credenciales incorrectas. Intento ' . $intentos . ' de 5.';
                }
            }
        } catch (Exception $e) {
            $error = 'Error de sistema: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Populares Penaloza</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color:#f0f2f5; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; font-family:'Segoe UI',system-ui,sans-serif; }
        .login-card { width:100%; max-width:400px; border:none; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,.05); background:white; overflow:hidden; }
        .login-header { background:#0d6efd; padding:30px; text-align:center; color:white; }
        .btn-login { background-color:#0d6efd; border:none; padding:12px; font-weight:700; border-radius:8px; transition:all .3s; }
        .btn-login:hover { background-color:#0b5ed7; transform:translateY(-1px); }
        .form-control { padding:12px; border-radius:8px; background-color:#f8f9fa; border:1px solid #dee2e6; }
        .form-control:focus { background-color:#fff; border-color:#0d6efd; box-shadow:0 0 0 4px rgba(13,110,253,.15); }
    </style>
</head>
<body>
    <div class="card login-card">
        <div class="login-header">
            <div class="mb-2"><i class="bi bi-capsule" style="font-size:3rem;"></i></div>
            <h4 class="mb-0 fw-bold">Populares Penaloza</h4>
            <small class="opacity-75">Sistema de Gestion Web</small>
        </div>
        <div class="card-body p-4 pt-5">
            <?php if ($info): ?><div class="alert alert-info"><?php echo h($info); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger d-flex align-items-center mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?php echo h($error); ?></div></div><?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">CORREO ELECTRONICO</label>
                    <div class="input-group"><span class="input-group-text bg-white border-end-0"><i class="bi bi-envelope text-muted"></i></span><input type="email" name="correo" class="form-control border-start-0" placeholder="admin@farmacia.com" required></div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small fw-bold">CONTRASENA</label>
                    <div class="input-group"><span class="input-group-text bg-white border-end-0"><i class="bi bi-lock text-muted"></i></span><input type="password" name="password" class="form-control border-start-0" required></div>
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-login mb-3">INGRESAR AL SISTEMA</button>
            </form>
        </div>
        <div class="card-footer text-center bg-white border-0 pb-4"><small class="text-muted">ERP Farmaceutico</small></div>
    </div>
</body>
</html>
