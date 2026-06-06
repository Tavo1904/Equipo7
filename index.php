<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$info = isset($_GET['expirada']) ? 'Sesion expirada por inactividad.' : '';
$tab_activo = $_GET['tab'] ?? 'login'; // login o registro

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    // LÓGICA DE LOGIN
    if ($accion === 'login') {
        $correo = trim($_POST['correo'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($correo === '' || $password === '') {
            $error = 'Por favor ingrese correo y contrasena.';
        } else {
            try {
                $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE correo = ? AND rol IN ('admin','empleado') LIMIT 1");
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
    
    // LÓGICA DE REGISTRO CLIENTE
    elseif ($accion === 'registro') {
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $password_confirm = trim($_POST['password_confirm'] ?? '');
        $rfc = trim($_POST['rfc'] ?? '');

        if ($nombre === '' || $correo === '' || $password === '') {
            $error = 'Todos los campos son obligatorios.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo electrónico no es válido.';
        } elseif ($password !== $password_confirm) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } else {
            try {
                // Verificar si el correo ya existe
                $checkStmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE correo = ? LIMIT 1");
                $checkStmt->execute([$correo]);
                if ($checkStmt->rowCount() > 0) {
                    $error = 'Este correo electrónico ya está registrado.';
                } else {
                    // Crear nuevo usuario cliente
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, correo, contraseña, rol, estado, rfc, tipo_cliente, limite_mayoreo) VALUES (?, ?, ?, 'cliente', 'activo', ?, 'minorista', 50)");
                    $stmt->execute([$nombre, $correo, $hash, $rfc]);
                    
                    // Redirigir a login con mensaje
                    header("Location: index.php?tab=login&registro=exito");
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Error al registrar: ' . $e->getMessage();
            }
        }
    }
}

$registro_exito = isset($_GET['registro']) && $_GET['registro'] === 'exito';
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
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; font-family:'Segoe UI',system-ui,sans-serif; }
        .login-container { width:100%; max-width:450px; }
        .login-card { border:none; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.2); background:white; overflow:hidden; }
        .login-header { background:linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); padding:35px; text-align:center; color:white; }
        .login-header h4 { margin-bottom:5px; font-weight:700; font-size:1.5rem; }
        .nav-tabs { border:none; margin:0; background-color:#f8f9fa; }
        .nav-link { border:none; color:#6c757d; font-weight:600; padding:1rem; border-radius:0; }
        .nav-link.active { background-color:white; color:#0d6efd; border-bottom:3px solid #0d6efd; }
        .form-control { padding:12px; border-radius:8px; background-color:#f8f9fa; border:1px solid #dee2e6; }
        .form-control:focus { background-color:#fff; border-color:#0d6efd; box-shadow:0 0 0 4px rgba(13,110,253,.15); }
        .btn-login { background-color:#0d6efd; border:none; padding:12px; font-weight:700; border-radius:8px; transition:all .3s; }
        .btn-login:hover { background-color:#0b5ed7; transform:translateY(-1px); }
        .tab-content { padding:2rem; }
        .success-alert { display:none; }
        .success-alert.show { display:block; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card login-card">
            <div class="login-header">
                <div class="mb-2"><i class="bi bi-capsule" style="font-size:3rem;"></i></div>
                <h4>Populares Penaloza</h4>
                <small class="opacity-75">Sistema de Gestion Web</small>
            </div>
            
            <!-- Tabs -->
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo ($tab_activo === 'login' || !$registro_exito) ? 'active' : ''; ?>" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane" type="button" role="tab">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $tab_activo === 'registro' ? 'active' : ''; ?>" id="registro-tab" data-bs-toggle="tab" data-bs-target="#registro-pane" type="button" role="tab">
                        <i class="bi bi-person-plus me-2"></i>Registrarse
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- LOGIN TAB -->
                <div class="tab-pane fade <?php echo ($tab_activo === 'login' || !$registro_exito) ? 'show active' : ''; ?>" id="login-pane" role="tabpanel">
                    <?php if ($registro_exito): ?>
                        <div class="alert alert-success mb-4"><i class="bi bi-check-circle me-2"></i><strong>¡Registro exitoso!</strong> Por favor inicia sesión con tus credenciales.</div>
                    <?php endif; ?>
                    <?php if ($error && $accion === 'login'): ?><div class="alert alert-danger d-flex align-items-center mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?php echo htmlspecialchars($error); ?></div></div><?php endif; ?>
                    <?php if ($info): ?><div class="alert alert-info"><?php echo htmlspecialchars($info); ?></div><?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="login">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">CORREO ELECTRONICO</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" name="correo" class="form-control" placeholder="tu@email.com" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">CONTRASEÑA</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="••••••" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-login mb-3">INGRESAR AL SISTEMA</button>
                    </form>
                </div>

                <!-- REGISTRO TAB -->
                <div class="tab-pane fade <?php echo $tab_activo === 'registro' ? 'show active' : ''; ?>" id="registro-pane" role="tabpanel">
                    <?php if ($error && $accion === 'registro'): ?><div class="alert alert-danger d-flex align-items-center mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?php echo htmlspecialchars($error); ?></div></div><?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="registro">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NOMBRE COMPLETO</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Tu nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">CORREO ELECTRONICO</label>
                            <input type="email" name="correo" class="form-control" placeholder="tu@email.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">RFC (Opcional)</label>
                            <input type="text" name="rfc" class="form-control" placeholder="RFC">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">CONTRASEÑA</label>
                            <input type="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">CONFIRMAR CONTRASEÑA</label>
                            <input type="password" name="password_confirm" class="form-control" placeholder="Confirma tu contraseña" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-login mb-3">CREAR MI CUENTA</button>
                    </form>
                    <small class="text-muted text-center d-block">Al registrarte, aceptas nuestros términos y condiciones.</small>
                </div>
            </div>
        </div>
        <div class="text-center mt-3"><small class="text-white opacity-75">ERP Farmacéutico © 2026</small></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
