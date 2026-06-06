<?php 
include 'db.php'; include 'header.php'; 

// CAMBIAR ESTADO
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $estado = $_GET['accion'];
    
    $stmt = $conexion->prepare("UPDATE pedidos SET estado = ? WHERE id_pedido = ?");
    $stmt->execute([$estado, $id]);
    
    // SI SE ACEPTA -> ABRIR PDF
    if ($estado == 'aceptado') {
        echo "<script>window.open('ver_ticket.php?id=$id', '_blank'); window.location='pedidos.php';</script>";
    } else {
        echo "<script>window.location='pedidos.php';</script>";
    }
    exit;
}

$pedidos = $conexion->query("SELECT p.*, u.nombre as cliente FROM pedidos p INNER JOIN usuarios u ON p.id_usuario = u.id_usuario ORDER BY p.fecha DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<h3 class="fw-bold mb-4">Pedidos Web</h3>
<div class="card card-custom border-0 shadow-sm p-4">
    <table class="table table-hover align-middle">
        <thead class="table-light"><tr><th>Folio</th><th>Fecha</th><th>Cliente</th><th>Total</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
            <?php foreach($pedidos as $p): 
                $col = ($p['estado']=='pendiente')?'bg-warning text-dark':(($p['estado']=='aceptado')?'bg-success':'bg-secondary');
            ?>
            <tr>
                <td class="fw-bold">#<?php echo $p['id_pedido']; ?></td>
                <td><?php echo date("d/m H:i", strtotime($p['fecha'])); ?></td>
                <td><?php echo $p['cliente']; ?></td>
                <td class="fw-bold text-success">$<?php echo number_format($p['total'], 2); ?></td>
                <td><span class="badge <?php echo $col; ?>"><?php echo ucfirst($p['estado']); ?></span></td>
                <td class="text-end">
                    <a href="ver_ticket.php?id=<?php echo $p['id_pedido']; ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-eye"></i></a>
                    <?php if($p['estado']=='pendiente'): ?>
                        <a href="admin_pedidos.php?id=<?php echo $p['id_pedido']; ?>&accion=aceptado" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></a>
                        <a href="admin_pedidos.php?id=<?php echo $p['id_pedido']; ?>&accion=rechazado" class="btn btn-sm btn-danger"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>