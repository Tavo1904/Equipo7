<?php 
include 'db.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();

// Definimos la función de escape por si no existe en db.php o header.php
if (!function_exists('h')) {
    function h($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

$fecha  = $_GET['fecha']  ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

// 1. Consulta del Corte de Caja
$sqlCorte = "SELECT 
                COALESCE(SUM(total),0) as total_general,
                COALESCE(SUM(CASE WHEN tipo_pago='efectivo' THEN total ELSE 0 END),0) as total_efectivo,
                COALESCE(SUM(CASE WHEN tipo_pago='tarjeta'  THEN total ELSE 0 END),0) as total_tarjeta,
                COALESCE(SUM(CASE WHEN tipo_pago='credito'  THEN total ELSE 0 END),0) as total_credito,
                COUNT(*) as total_ventas
             FROM pedidos WHERE DATE(fecha)=? AND estado='completado'";
$stmtCorte = $conexion->prepare($sqlCorte);
$stmtCorte->execute([$fecha]);
$corte = $stmtCorte->fetch(PDO::FETCH_ASSOC);

// 2. Consulta de Movimientos de Inventario
$sqlMovs = "SELECT m.fecha, m.tipo_movimiento, m.cantidad, m.observaciones,
                    p.nombre as producto, u.nombre as usuario
            FROM movimientos_inventario m
            INNER JOIN productos p ON m.id_producto = p.id_producto
            INNER JOIN usuarios  u ON m.id_usuario  = u.id_usuario
            WHERE DATE(m.fecha)=?
            ORDER BY m.fecha DESC";
$stmtMovs = $conexion->prepare($sqlMovs);
$stmtMovs->execute([$fecha]);
$movimientos = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);

/* ── EXCEL ── */
if ($export === 'excel') {
    // Limpiamos cualquier buffer residual para evitar corrupción de bytes
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="corte_caja_' . $fecha . '.xlsx"');
    header('Cache-Control: max-age=0');

    $py = <<<PYTHON
import sys, json, io
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter

# Cargar datos de manera segura desde el archivo temporal JSON
with open(sys.argv[1], 'r', encoding='utf-8') as f:
    data = json.load(f)

corte = data['corte']; movs = data['movimientos']; fecha = data['fecha']

wb = Workbook(); ws = wb.active; ws.title = "Corte de Caja"
thin = Side(style='thin', color="D1D5DB")
border = Border(left=thin, right=thin, top=thin, bottom=thin)
def fill(c): return PatternFill("solid", fgColor=c)

# Título Principal
ws.merge_cells("A1:F1")
ws["A1"] = "FARMACIA PEÑALOZA — Corte de Caja"
ws["A1"].font = Font(bold=True, size=14, color="FFFFFF")
ws["A1"].fill = fill("1E3A5F")
ws["A1"].alignment = Alignment(horizontal="center", vertical="center")
ws.row_dimensions[1].height = 28

ws.merge_cells("A2:F2")
ws["A2"] = "Fecha del reporte: " + fecha
ws["A2"].font = Font(size=10, color="6B7280")
ws["A2"].alignment = Alignment(horizontal="center")

# Fila de KPIs Informativos
kpis = [
    ("TOTAL VENTAS", f"\${float(corte['total_general']):,.2f}", "1A56DB"),
    ("EFECTIVO",     f"\${float(corte['total_efectivo']):,.2f}", "065F46"),
    ("TARJETA",      f"\${float(corte['total_tarjeta']):,.2f}", "1D4ED8"),
    ("CREDITO",      f"\${float(corte['total_credito']):,.2f}", "92400E"),
    ("# TICKETS",    str(corte['total_ventas']),             "374151"),
]
for i, (lbl, val, clr) in enumerate(kpis):
    c = get_column_letter(i+1)
    ws[f"{c}4"] = lbl; ws[f"{c}4"].font = Font(bold=True, size=8, color=clr)
    ws[f"{c}4"].fill = fill("F9FAFB"); ws[f"{c}4"].alignment = Alignment(horizontal="center")
    ws[f"{c}5"] = val; ws[f"{c}5"].font = Font(bold=True, size=12, color=clr)
    ws[f"{c}5"].alignment = Alignment(horizontal="center")
    ws.row_dimensions[5].height = 22

# Encabezados de Tabla de Movimientos
hdrs = ["Hora", "Tipo", "Producto", "Cantidad", "Usuario", "Detalle"]
widths = [12, 12, 32, 10, 18, 50]
for i, (h, w) in enumerate(zip(hdrs, widths)):
    c = get_column_letter(i+1)
    ws[f"{c}7"] = h
    ws[f"{c}7"].font = Font(bold=True, size=9, color="FFFFFF")
    ws[f"{c}7"].fill = fill("111827")
    ws[f"{c}7"].alignment = Alignment(horizontal="center", vertical="center")
    ws[f"{c}7"].border = border
    ws.column_dimensions[c].width = w
ws.row_dimensions[7].height = 20

# Inserción Dinámica de Datos
for r, m in enumerate(movs):
    row = 8 + r
    es_entrada = m['tipo_movimiento'] == 'entrada'
    bg = "ECFDF5" if es_entrada else "FEF2F2"
    vals = [m['fecha'][11:16], m['tipo_movimiento'].upper(), m['producto'],
            ("+" if es_entrada else "-") + str(m['cantidad']), m['usuario'], m['observaciones'] or ""]
    for i, v in enumerate(vals):
        c = get_column_letter(i+1)
        ws[f"{c}{row}"] = v
        ws[f"{c}{row}"].fill = fill(bg if i in [0, 1] else ("F9FAFB" if r % 2 == 0 else "FFFFFF"))
        ws[f"{c}{row}"].border = border
        ws[f"{c}{row}"].alignment = Alignment(horizontal="center" if i in [0, 1, 3] else "left", wrap_text=(i == 5))
        if i == 1:
            clr = "065F46" if es_entrada else "991B1B"
            ws[f"{c}{row}"].font = Font(bold=True, color=clr)
    ws.row_dimensions[row].height = 16

buf = io.BytesIO()
wb.save(buf)
sys.stdout.buffer.write(buf.getvalue())
PYTHON;

    // Crear rutas temporales independientes
    $tmpPy = tempnam(sys_get_temp_dir(), 'py_excel_') . '.py';
    $tmpJson = tempnam(sys_get_temp_dir(), 'js_excel_') . '.json';
    
    file_put_contents($tmpPy, $py);
    file_put_contents($tmpJson, json_encode(['corte' => $corte, 'movimientos' => $movimientos, 'fecha' => $fecha], JSON_UNESCAPED_UNICODE));
    
    // Ejecución controlada pasando la ruta del JSON como parámetro
    $output = shell_exec("python3 " . escapeshellarg($tmpPy) . " " . escapeshellarg($tmpJson));
    echo $output;
    
    unlink($tmpPy);
    unlink($tmpJson);
    exit;
}

/* ── PDF ── */
if ($export === 'pdf') {
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="corte_caja_' . $fecha . '.pdf"');
    header('Cache-Control: max-age=0');

    $py = <<<PYTHON
import sys, json
from fpdf import FPDF

with open(sys.argv[1], 'r', encoding='utf-8') as f:
    data = json.load(f)
corte = data['corte']; movs = data['movimientos']; fecha = data['fecha']

class PDF(FPDF):
    def header(self):
        self.set_fill_color(30, 58, 95)
        self.rect(0, 0, 210, 22, 'F')
        self.set_text_color(255, 255, 255)
        self.set_font('Helvetica', 'B', 13)
        self.set_xy(10, 5)
        self.cell(0, 12, 'FARMACIA PENALOZA - Corte de Caja', ln=True)
        self.set_font('Helvetica', '', 9)
        self.set_xy(10, 15)
        self.cell(0, 6, f'Fecha: {fecha}', ln=True)
        self.set_text_color(0, 0, 0)
        
    def footer(self):
        self.set_y(-12)
        self.set_font('Helvetica', 'I', 8)
        self.set_text_color(150, 150, 150)
        self.cell(0, 8, f'Pagina {self.page_no()} | Farmacia Penaloza', align='C')

pdf = PDF()
pdf.add_page()
pdf.set_auto_page_break(auto=True, margin=15)
pdf.ln(6)

# Sección superior de indicadores económicos (KPIs)
kpis = [
    ('TOTAL', f"\${float(corte['total_general']):,.2f}", (26, 86, 219)),
    ('EFECTIVO', f"\${float(corte['total_efectivo']):,.2f}", (6, 95, 70)),
    ('TARJETA', f"\${float(corte['total_tarjeta']):,.2f}", (29, 78, 216)),
    ('CREDITO', f"\${float(corte['total_credito']):,.2f}", (146, 64, 14)),
    ('TICKETS', str(corte['total_ventas']), (55, 65, 81)),
]
bw = 36; gap = 1; x0 = 10
for i, (lbl, val, clr) in enumerate(kpis):
    x = x0 + i * (bw + gap)
    y = pdf.get_y()
    pdf.set_fill_color(249, 250, 251); pdf.set_draw_color(209, 213, 219)
    pdf.rect(x, y, bw, 14, 'FD')
    pdf.set_font('Helvetica', 'B', 7); pdf.set_text_color(*clr)
    pdf.set_xy(x, y + 2); pdf.cell(bw, 4, lbl, align='C')
    pdf.set_font('Helvetica', 'B', 11)
    pdf.set_xy(x, y + 7); pdf.cell(bw, 5, val, align='C')
pdf.ln(18); pdf.set_text_color(0, 0, 0)

# Renderizado de Tabla principal de movimientos
cols = ['Hora', 'Tipo', 'Producto', 'Cant', 'Usuario', 'Detalle']
widths = [16, 20, 50, 12, 26, 64]
pdf.set_fill_color(17, 24, 39); pdf.set_text_color(255, 255, 255)
pdf.set_font('Helvetica', 'B', 8)
for c, w in zip(cols, widths):
    pdf.cell(w, 8, c, border=1, align='C', fill=True)
pdf.ln()

pdf.set_font('Helvetica', '', 7.5)
for i, m in enumerate(movs):
    es = m['tipo_movimiento'] == 'entrada'
    bg = (236, 253, 245) if i % 2 == 0 else (249, 250, 251)
    pdf.set_fill_color(*bg); pdf.set_text_color(0, 0, 0)
    vals = [m['fecha'][11:16], m['tipo_movimiento'].upper(), m['producto'][:28],
          ('+' if es else '-') + str(m['cantidad']), m['usuario'][:14], (m['observaciones'] or '')[:40]]
    aligns = ['C', 'C', 'L', 'C', 'C', 'L']
    for v, w, a in zip(vals, widths, aligns):
        pdf.cell(w, 6.5, str(v), border=1, align=a, fill=True)
    pdf.ln()

# Manejo robusto para retornar el buffer según la versión instalada de fpdf/fpdf2
try:
    res = pdf.output(dest='S')
    if isinstance(res, str):
        res = res.encode('latin1')
    sys.stdout.buffer.write(res)
except:
    sys.stdout.buffer.write(pdf.output())
PYTHON;

    $tmpPy = tempnam(sys_get_temp_dir(), 'py_pdf_') . '.py';
    $tmpJson = tempnam(sys_get_temp_dir(), 'js_pdf_') . '.json';
    
    file_put_contents($tmpPy, $py);
    file_put_contents($tmpJson, json_encode(['corte' => $corte, 'movimientos' => $movimientos, 'fecha' => $fecha], JSON_UNESCAPED_UNICODE));
    
    $output = shell_exec("python3 " . escapeshellarg($tmpPy) . " " . escapeshellarg($tmpJson));
    echo $output;
    
    unlink($tmpPy);
    unlink($tmpJson);
    exit;
}

include 'header.php';
?>

<style>
@media print {
    .sidebar, .btn-no-print, form { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
    .titulo-impresion { display: block !important; text-align: center; margin-bottom: 20px; }
}
.titulo-impresion { display: none; }
</style>

<div class="titulo-impresion">
    <h2>CORTE DE CAJA - FARMACIA PEÑALOZA</h2>
    <p>Fecha de Corte: <?php echo date("d/m/Y", strtotime($fecha)); ?></p><hr>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 btn-no-print">
    <h3 class="fw-bold text-dark">Corte de Caja y Movimientos</h3>
    <div class="d-flex gap-2">
        <a href="movimientos.php?fecha=<?php echo h($fecha); ?>&export=pdf" class="btn btn-dark btn-sm"><i class="bi bi-filetype-pdf me-1"></i>PDF</a>
        <a href="movimientos.php?fecha=<?php echo h($fecha); ?>&export=excel" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</a>
        <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i>Imprimir</button>
    </div>
</div>

<div class="card card-custom p-3 mb-4 border-0 shadow-sm btn-no-print">
    <form action="" method="GET" class="row g-3 align-items-end">
        <div class="col-auto">
            <label class="fw-bold small text-muted">Seleccionar Día:</label>
            <input type="date" name="fecha" class="form-control" value="<?php echo h($fecha); ?>">
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-primary px-4"><i class="bi bi-search"></i> Ver Corte</button></div>
        <div class="col-auto"><a href="movimientos.php" class="btn btn-outline-secondary">Hoy</a></div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-custom bg-primary text-white border-0 p-3 h-100">
            <div class="small opacity-75 fw-bold">VENTA TOTAL</div>
            <div class="fs-2 fw-bold">$<?php echo number_format($corte['total_general'],2); ?></div>
            <div class="small"><?php echo (int)$corte['total_ventas']; ?> Tickets</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-custom border-0 p-3 h-100 border-start border-5 border-success">
            <div class="small text-muted fw-bold text-uppercase">Efectivo en Caja</div>
            <div class="fs-3 fw-bold text-success">$<?php echo number_format($corte['total_efectivo'],2); ?></div>
            <small class="text-muted">Dinero físico</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-custom border-0 p-3 h-100 border-start border-5 border-info">
            <div class="small text-muted fw-bold text-uppercase">Tarjetas / Bancos</div>
            <div class="fs-3 fw-bold text-info">$<?php echo number_format($corte['total_tarjeta'],2); ?></div>
            <small class="text-muted">Vouchers</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-custom border-0 p-3 h-100 border-start border-5 border-warning">
            <div class="small text-muted fw-bold text-uppercase">Créditos Otorgados</div>
            <div class="fs-3 fw-bold text-warning">$<?php echo number_format($corte['total_credito'],2); ?></div>
            <small class="text-muted">Por cobrar</small>
        </div>
    </div>
</div>

<div class="card card-custom p-4 border-0 shadow-sm">
    <h5 class="fw-bold mb-3 border-bottom pb-2">Detalle de Operaciones</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Hora</th><th>Tipo</th><th>Producto</th>
                    <th class="text-center">Cant</th><th>Usuario</th><th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($movimientos)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No hubo movimientos en este día.</td></tr>
                <?php else: ?>
                    <?php foreach($movimientos as $m):
                        $esEntrada = ($m['tipo_movimiento'] == 'entrada');
                        $badge = $esEntrada ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                        $icono = $esEntrada ? 'bi-box-arrow-in-down' : 'bi-box-arrow-up-right';
                        $signo = $esEntrada ? '+' : '-';
                    ?>
                    <tr>
                        <td class="small text-muted"><?php echo date("H:i", strtotime($m['fecha'])); ?></td>
                        <td><span class="badge <?php echo $badge; ?> border rounded-pill px-3">
                            <i class="bi <?php echo $icono; ?> me-1"></i><?php echo ucfirst($m['tipo_movimiento']); ?>
                        </span></td>
                        <td class="fw-bold text-dark"><?php echo h($m['producto']); ?></td>
                        <td class="text-center fw-bold fs-6"><?php echo $signo.(int)$m['cantidad']; ?></td>
                        <td class="small text-uppercase text-muted"><?php echo h($m['usuario']); ?></td>
                        <td class="small text-muted fst-italic"><?php echo h($m['observaciones']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>