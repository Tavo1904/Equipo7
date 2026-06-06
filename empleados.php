<?php 
include 'db.php'; 
include 'header.php'; 

// 1. SEGURIDAD (Solo Admin)
if ($_SESSION['rol'] != 'admin') {
    echo "<script>window.location='dashboard.php';</script>"; exit;
}

// 2. GUARDAR (CREAR O EDITAR)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id_usuario'] ?? '';
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $rol = $_POST['rol'];
    $pass = $_POST['password'];

    try {
        if (empty($id)) {
            // CREAR NUEVO
            $check = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
            $check->execute([$correo]);
            if ($check->rowCount() > 0) {
                $error = "El correo ya existe.";
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $sql = "INSERT INTO usuarios (nombre, correo, contraseña, rol, estado) VALUES (?, ?, ?, ?, 'activo')";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$nombre, $correo, $hash, $rol]);
                $success = "Usuario registrado.";
            }
        } else {
            // EDITAR
            if (!empty($pass)) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nombre=?, correo=?, rol=?, contraseña=? WHERE id_usuario=?";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$nombre, $correo, $rol, $hash, $id]);
            } else {
                $sql = "UPDATE usuarios SET nombre=?, correo=?, rol=? WHERE id_usuario=?";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$nombre, $correo, $rol, $id]);
            }
            $success = "Usuario actualizado.";
        }
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
}

// 3. CAMBIAR ESTADO (ACTIVAR / DESACTIVAR)
if (isset($_GET['cambiar_estado']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $nuevo_estado = $_GET['cambiar_estado']; // 'activo' o 'inactivo'

    if ($id != $_SESSION['id_usuario']) {
        $stmt = $conexion->prepare("UPDATE usuarios SET estado = ? WHERE id_usuario = ?");
        $stmt->execute([$nuevo_estado, $id]);
        echo "<script>window.location='empleados.php';</script>";
    } else {
        echo "<script>alert('No puedes cambiar tu propio estado.');</script>";
    }
}

// 4. CONSULTA: TRAER TODOS (Quitamos el filtro de 'activo' para ver a los inactivos también)
$usuarios = $conexion->query("SELECT * FROM usuarios WHERE rol != 'cliente' ORDER BY estado ASC, id_usuario DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm sticky-top" style="top: 20px;">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0" id="tituloForm">Nuevo Empleado</h5>
                <button type="button" class="btn btn-sm btn-light" onclick="limpiarForm()">Limpiar</button>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?><div class="alert alert-danger small py-2"><?php echo $error; ?></div><?php endif; ?>
                <?php if(isset($success)): ?><div class="alert alert-success small py-2"><?php echo $success; ?></div><?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="id_usuario" id="inputId">
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">NOMBRE COMPLETO</label>
                        <input type="text" name="nombre" id="inputNombre" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">CORREO</label>
                        <input type="email" name="correo" id="inputCorreo" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">CONTRASEÑA</label>
                        <input type="password" name="password" class="form-control" placeholder="Dejar vacío si no cambia">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-muted fw-bold">ROL</label>
                        <select name="rol" id="inputRol" class="form-select">
                            <option value="vendedor">Vendedor</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold" id="btnGuardar">
                        <i class="bi bi-save me-2"></i> Guardar Usuario
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="fw-bold mb-0">Personal del Sistema</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th> <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usuarios as $u): 
                            $esActivo = $u['estado'] === 'activo';
                            // Estilo visual para inactivos (fila grisácea)
                            $claseFila = $esActivo ? '' : 'bg-light text-muted';
                        ?>
                        <tr class="<?php echo $claseFila; ?>">
                            <td><small>#<?php echo $u['id_usuario']; ?></small></td>
                            <td>
                                <div class="fw-bold"><?php echo $u['nombre']; ?></div>
                                <small class="<?php echo $esActivo ? 'text-muted' : ''; ?>"><?php echo $u['correo']; ?></small>
                            </td>
                            <td>
                                <?php if($u['rol'] == 'admin'): ?>
                                    <span class="badge bg-dark">ADMIN</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">VENDEDOR</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if($esActivo): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactivo</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <button class="btn btn-sm btn-warning text-dark border-0 me-1" 
                                        onclick='editarUsuario(<?php echo json_encode($u); ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <?php if($u['id_usuario'] != $_SESSION['id_usuario']): ?>
                                    
                                    <?php if($esActivo): ?>
                                        <a href="empleados.php?id=<?php echo $u['id_usuario']; ?>&cambiar_estado=inactivo" 
                                           class="btn btn-sm btn-danger border-0"
                                           onclick="return confirm('¿Desactivar a este usuario? Ya no podrá entrar al sistema.');"
                                           title="Desactivar acceso">
                                            <i class="bi bi-person-x-fill"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="empleados.php?id=<?php echo $u['id_usuario']; ?>&cambiar_estado=activo" 
                                           class="btn btn-sm btn-success border-0"
                                           onclick="return confirm('¿Reactivar a este usuario? Podrá entrar nuevamente.');"
                                           title="Reactivar acceso">
                                            <i class="bi bi-check-lg"></i>
                                        </a>
                                    <?php endif; ?>

                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div></div></div>

<script>
    function editarUsuario(u) {
        document.getElementById('tituloForm').innerText = "Editar Usuario #" + u.id_usuario;
        document.getElementById('btnGuardar').innerHTML = "<i class='bi bi-pencil-square me-2'></i> Actualizar";
        document.getElementById('inputId').value = u.id_usuario;
        document.getElementById('inputNombre').value = u.nombre;
        document.getElementById('inputCorreo').value = u.correo;
        document.getElementById('inputRol').value = u.rol;
    }

    function limpiarForm() {
        document.getElementById('tituloForm').innerText = "Nuevo Empleado";
        document.getElementById('btnGuardar').innerHTML = "<i class='bi bi-person-plus-fill me-2'></i> Registrar";
        document.getElementById('inputId').value = "";
        document.getElementById('inputNombre').value = "";
        document.getElementById('inputCorreo').value = "";
        document.getElementById('inputRol').value = "vendedor";
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>