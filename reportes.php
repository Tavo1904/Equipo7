<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403); echo 'Funcion exclusiva del Administrador'; exit;
}

// Aseguramos la existencia de la función de escape
if (!function_exists('h')) {
    function h($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

$fecha  = $_GET['fecha'] ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

// 1. Obtener Corte de Caja
$stmtCorte = $conexion->prepare("
    SELECT COALESCE(SUM(total),0) AS total_general,
           COALESCE(SUM(CASE WHEN tipo_pago='efectivo' THEN total ELSE 0 END),0) AS total_efectivo,
           COALESCE(SUM(CASE WHEN tipo_pago='tarjeta'  THEN total ELSE 0 END),0) AS total_tarjeta,
           COALESCE(SUM(CASE WHEN tipo_pago='credito'  THEN total ELSE 0 END),0) AS total_credito,
           COUNT(*) AS total_ventas
    FROM pedidos WHERE DATE(fecha)=? AND estado='completado'
");
$stmtCorte->execute([$fecha]);
$corte = $stmtCorte->fetch();

// 2. Obtener Desglose de Productos
$stmtDetalle = $conexion->prepare("
    SELECT pr.nombre, dp.modalidad,
           SUM(dp.cantidad) AS cantidad,
           SUM(dp.subtotal) AS ingreso,
           SUM(dp.cantidad * pr.costo_adquisicion) AS costo
    FROM detalle_pedido dp
    INNER JOIN pedidos p  ON dp.id_pedido  = p.id_pedido
    INNER JOIN productos pr ON dp.id_producto = pr.id_producto
    WHERE DATE(p.fecha)=? AND p.estado='completado'
    GROUP BY pr.id_producto, dp.modalidad ORDER BY pr.nombre ASC
");
$stmtDetalle->execute([$fecha]);
$lineas = $stmtDetalle->fetchAll();

// 3. Cálculos Financieros
$costoTotal = 0; $ingresoTotal = 0;
foreach ($lineas as $l) { 
    $costoTotal += (float)$l['costo']; 
    $ingresoTotal += (float)$l['ingreso']; 
}
$utilidad = $ingresoTotal - $costoTotal;
$margen   = $ingresoTotal > 0 ? ($utilidad / $ingresoTotal) * 100 : 0;

/* ── EXCEL ── */
if ($export === 'excel') {
    if (ob_get_length()) ob_end_clean(); // Evita corrupción de bytes por buffers previos

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="reporte_' . $fecha . '.xlsx"');
    header('Cache-Control: max-age=0');

    $py = <<<PYTHON
import sys, json
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter
import io

# Carga de datos segura mediante archivo JSON temporal
with open(sys.argv[1], 'r', encoding='utf-8') as f:
    data = json.load(f)

corte  = data['corte']
lineas = data['lineas']
fecha  = data['fecha']

wb = Workbook()
ws = wb.active
ws.title = "Reporte Diario"

AZUL  = "1A56DB"
VERDE = "0E9F6E"
GRIS  = "F3F4F6"
NEGRO = "111827"

thin = Side(style='thin', color="D1D5DB")
border = Border(left=thin, right=thin, top=thin, bottom=thin)

def hdr_fill(color): return PatternFill("solid", fgColor=color)
def bold(size=11, color="111827", white=False):
    return Font(bold=True, size=size, color="FFFFFF" if white else color)

# Título Principal
ws.merge_cells("A1:G1")
ws["A1"] = "FARMACIA PEÑALOZA — Reporte Financiero Diario"
ws["A1"].font = bold(14, white=True)
ws["A1"].fill = hdr_fill(AZUL)
ws["A1"].alignment = Alignment(horizontal="center", vertical="center")
ws.row_dimensions[1].height = 30

ws.merge_cells("A2:G2")
ws["A2"] = "Fecha: " + fecha
ws["A2"].font = Font(size=10, color="6B7280")
ws["A2"].alignment = Alignment(horizontal="center")
ws.row_dimensions[2].height = 18

# Bloque de KPIs
kpi_row = 4
kpis = [
    ("VENTA TOTAL", f"\${float(corte['total_general']):,.2f}", AZUL),
    ("COSTO TOTAL", f"\${data['costo_total']:,.2f}", "6B7280"),
    ("UTILIDAD BRUTA", f"\${data['utilidad']:,.2f}", VERDE),
    ("MARGEN", f"{data['margen']:.1f}%", "7C3AED"),
    ("TICKETS", str(corte['total_ventas']), "374151"),
    ("EFECTIVO", f"\${float(corte['total_efectivo']):,.2f}", "065F46"),
    ("CREDITO", f"\${float(corte['total_credito']):,.2f}", "92400E"),
]
for i, (lbl, val, color) in enumerate(kpis):
    col = i + 1
    lc = get_column_letter(col)
    ws[f"{lc}{kpi_row}"] = lbl
    ws[f"{lc}{kpi_row}"].font = Font(bold=True, size=8, color=color)
    ws[f"{lc}{kpi_row}"].fill = hdr_fill("F9FAFB")
    ws[f"{lc}{kpi_row}"].alignment = Alignment(horizontal="center")
    ws[f"{lc}{kpi_row+1}"] = val
    ws[f"{lc}{kpi_row+1}"].font = Font(bold=True, size=13, color=color)
    ws[f"{lc}{kpi_row+1}"].alignment = Alignment(horizontal="center")
    ws.row_dimensions[kpi_row+1].height = 24

# Encabezados de Tabla
tbl_row = kpi_row + 4
headers = ["Producto", "Modalidad", "Cantidad", "Ingreso", "Costo", "Utilidad", "% Margen"]
for i, h in enumerate(headers):
    col = i + 1
    lc = get_column_letter(col)
    ws[f"{lc}{tbl_row}"] = h
    ws[f"{lc}{tbl_row}"].font = bold(10, white=True)
    ws[f"{lc}{tbl_row}"].fill = hdr_fill(NEGRO)
    ws[f"{lc}{tbl_row}"].alignment = Alignment(horizontal="center", vertical="center")
    ws[f"{lc}{tbl_row}"].border = border
ws.row_dimensions[tbl_row].height = 22

# Contenido de Tabla
for r, l in enumerate(lineas):
    row = tbl_row + 1 + r
    ing = float(l['ingreso']); cos = float(l['costo'])
    util = ing - cos
    marg = (util / ing * 100) if ing > 0 else 0
    vals = [l['nombre'], l['modalidad'], int(l['cantidad']),
            f"\${ing:,.2f}", f"\${cos:,.2f}", f"\${util:,.2f}", f"{marg:.1f}%"]
    fill_c = "ECFDF5" if r % 2 == 0 else "F9FAFB"
    for i, v in enumerate(vals):
        lc = get_column_letter(i + 1)
        ws[f"{lc}{row}"] = v
        ws[f"{lc}{row}"].fill = hdr_fill(fill_c)
        ws[f"{lc}{row}"].border = border
        ws[f"{lc}{row}"].alignment = Alignment(horizontal="center" if i > 1 else "left")
        if i == 5 and util < 0:
            ws[f"{lc}{row}"].font = Font(color="DC2626", bold=True)
        elif i == 5:
            ws[f"{lc}{row}"].font = Font(color="065F46", bold=True)
    ws.row_dimensions[row].height = 18

# Ancho Adaptado de Columnas
for i, w in enumerate([32, 12, 10, 14, 14, 14, 12]):
    ws.column_dimensions[get_column_letter(i+1)].width = w

buf = io.BytesIO()
wb.save(buf)
sys.stdout.buffer.write(buf.getvalue())
PYTHON;

    $tmpPy = tempnam(sys_get_temp_dir(), 'rpt_py_') . '.py';
    $tmpJson = tempnam(sys_get_temp_dir(), 'rpt_js_') . '.json';
    
    file_put_contents($tmpPy, $py);
    file_put_contents($tmpJson, json_encode([
        'corte' => $corte, 'lineas' => $lineas, 'fecha' => $fecha,
        'costo_total' => $costoTotal, 'utilidad' => $utilidad, 'margen' => $margen
    ], JSON_UNESCAPED_UNICODE));
    
    $xlsxData = shell_exec("python3 " . escapeshellarg($tmpPy) . " " . escapeshellarg($tmpJson));
    if ($xlsxData) { echo $xlsxData; } else { echo "Error generando Excel"; }
    
    unlink($tmpPy); unlink($tmpJson);
    exit;
}

/* ── PDF ── */
if ($export === 'pdf') {
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="reporte_' . $fecha . '.pdf"');
    header('Cache-Control: max-age=0');

    $py = <<<PYTHON
import sys, json
from fpdf import FPDF
import io

with open(sys.argv[1], 'r', encoding='utf-8') as f:
    data = json.load(f)

corte = data['corte']
lineas= data['lineas']
fecha = data['fecha']
costo_total = data['costo_total']
utilidad    = data['utilidad']
margen      = data['margen']

class PDF(FPDF):
    def header(self):
        self.set_fill_color(26, 86, 219)
        self.rect(0, 0, 210, 22, 'F')
        self.set_text_color(255, 255, 255)
        self.set_font('Helvetica', 'B', 14)
        self.set_xy(10, 5)
        self.cell(0, 12, 'FARMACIA PENALOZA - Reporte Financiero Diario', ln=True)
        self.set_font('Helvetica', '', 9)
        self.set_xy(10, 15)
        self.cell(0, 6, f'Fecha: {fecha}', ln=True)
        self.set_text_color(0,0,0)

    def footer(self):
        self.set_y(-12)
        self.set_font('Helvetica', 'I', 8)
        self.set_text_color(150,150,150)
        self.cell(0, 8, f'Pagina {self.page_no()} | Farmacia Penaloza', align='C')

pdf = PDF()
pdf.add_page()
pdf.set_auto_page_break(auto=True, margin=15)

# KPIs Dimensionados a la hoja A4 (Suma total exacta: 190mm)
pdf.ln(8)
kpis = [
    ('VENTA TOTAL', f"\${float(corte['total_general']):,.2f}", (26,86,219)),
    ('COSTO TOTAL', f"\${costo_total:,.2f}", (107,114,128)),
    ('UTILIDAD BRUTA', f"\${utilidad:,.2f}", (14,159,110)),
    ('MARGEN', f"{margen:.1f}%", (124,58,237)),
    ('TICKETS', str(corte['total_ventas']), (55,65,81)),
    ('EFECTIVO', f"\${float(corte['total_efectivo']):,.2f}", (6,95,70)),
]
x0 = 10; box_w = 30; box_h = 14; gap = 2
y_kpi = pdf.get_y()

for i,(lbl,val,clr) in enumerate(kpis):
    x = x0 + i*(box_w+gap)
    pdf.set_fill_color(249,250,251)
    pdf.set_draw_color(209,213,219)
    pdf.rect(x, y_kpi, box_w, box_h, 'FD')
    
    pdf.set_font('Helvetica','B',6)
    pdf.set_text_color(*clr)
    pdf.set_xy(x, y_kpi+2)
    pdf.cell(box_w, 4, lbl, align='C')
    
    pdf.set_font('Helvetica','B',9)
    pdf.set_xy(x, y_kpi+7)
    pdf.cell(box_w, 5, val, align='C')

pdf.set_xy(x0, y_kpi + box_h + 6)
pdf.set_text_color(0,0,0)

# Encabezados de Tabla (Suma exacta: 190mm)
cols = ['Producto','Modalidad','Cant','Ingreso','Costo','Utilidad','Margen%']
widths= [68, 22, 15, 24, 24, 24, 13]
pdf.set_fill_color(17,24,39)
pdf.set_text_color(255,255,255)
pdf.set_font('Helvetica','B',8)
for c,w in zip(cols,widths):
    pdf.cell(w, 8, c, border=1, align='C', fill=True)
pdf.ln()

# Filas de la Tabla
pdf.set_font('Helvetica','',8)
for i,l in enumerate(lineas):
    ing = float(l['ingreso']); cos = float(l['costo'])
    util = ing - cos
    marg = (util/ing*100) if ing>0 else 0
    fill = (236,253,245) if i%2==0 else (249,250,251)
    pdf.set_fill_color(*fill)
    pdf.set_text_color(0,0,0)
    row_vals = [l['nombre'], l['modalidad'], str(int(l['cantidad'])),
                f"\${ing:,.2f}", f"\${cos:,.2f}", f"\${util:,.2f}", f"{marg:.1f}%"]
    aligns = ['L','C','C','R','R','R','C']
    for v,w,a in zip(row_vals,widths,aligns):
        val_str = str(v)[:32] if w > 50 else str(v)
        pdf.cell(w, 6.5, val_str, border=1, align=a, fill=True)
    pdf.ln()

# Fila de Totales Generales
pdf.set_fill_color(243,244,246)
pdf.set_font('Helvetica','B',8)
pdf.set_text_color(0,0,0)
totals = ['TOTALES','',str(sum(int(l['cantidad']) for l in lineas)),
          f"\${sum(float(l['ingreso']) for l in lineas):,.2f}",
          f"\${costo_total:,.2f}",f"\${utilidad:,.2f}",f"{margen:.1f}%"]
for v,w,a in zip(totals,widths,['L','C','C','R','R','R','C']):
    pdf.cell(w,8,v,border=1,align=a,fill=True)

try:
    out_bytes = pdf.output(dest='S')
    if isinstance(out_bytes, str): out_bytes = out_bytes.encode('latin1')
    sys.stdout.buffer.write(out_bytes)
except Exception:
    sys.stdout.buffer.write(pdf.output(''))
PYTHON;

    $tmpPy = tempnam(sys_get_temp_dir(), 'rpt_py_') . '.py';
    $tmpJson = tempnam(sys_get_temp_dir(), 'rpt_js_') . '.json';
    
    file_put_contents($tmpPy, $py);
    file_put_contents($tmpJson, json_encode([
        'corte'=>$corte,'lineas'=>$lineas,'fecha'=>$fecha,
        'costo_total'=>$costoTotal,'utilidad'=>$utilidad,'margen'=>$margen
    ], JSON_UNESCAPED_UNICODE));
    
    $pdfData = shell_exec("python3 " . escapeshellarg($tmpPy) . " " . escapeshellarg($tmpJson));
    
    unlink($tmpPy); unlink($tmpJson);

    if (!empty($pdfData)) {
        echo $pdfData;
    } else {
        http_response_code(500);
        echo "Error interno: El generador de PDF no devolvió datos.";
    }
    exit;
}

include 'header.php';
?>

<style>
@media print {
    .sidebar, .no-print { display:none !important; }
    .main-content { margin-left:0 !important; width:100% !important; }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark">Reportes Financieros</h3>
    <div class="no-print d-flex gap-2">
        <a href="reportes.php?fecha=<?php echo h($fecha); ?>&export=pdf" class="btn btn-dark"><i class="bi bi-filetype-pdf me-1"></i>PDF</a>
        <a href="reportes.php?fecha=<?php echo h($fecha); ?>&export=excel" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</a>
    </div>
</div>

<div class="card card-custom p-3 mb-4 no-print">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-auto"><label class="small fw-bold text-muted">Fecha</label><input type="date" name="fecha" class="form-control" value="<?php echo h($fecha); ?>"></div>
        <div class="col-auto"><button class="btn btn-primary">Ver Reporte</button></div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card card-custom bg-primary text-white border-0 p-3"><div class="small opacity-75 fw-bold">VENTA TOTAL</div><div class="fs-2 fw-bold">$<?php echo number_format((float)$corte['total_general'],2); ?></div><div class="small"><?php echo (int)$corte['total_ventas']; ?> tickets</div></div></div>
    <div class="col-md-3"><div class="card card-custom border-0 p-3"><div class="small text-muted fw-bold">COSTO TOTAL</div><div class="fs-3 fw-bold text-secondary">$<?php echo number_format($costoTotal,2); ?></div></div></div>
    <div class="col-md-3"><div class="card card-custom border-0 p-3"><div class="small text-muted fw-bold">UTILIDAD BRUTA</div><div class="fs-3 fw-bold text-success">$<?php echo number_format($utilidad,2); ?></div></div></div>
    <div class="col-md-3"><div class="card card-custom border-0 p-3"><div class="small text-muted fw-bold">MARGEN</div><div class="fs-3 fw-bold text-info"><?php echo number_format($margen,2); ?>%</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card card-custom border-0 p-3"><div class="small text-muted fw-bold">EFECTIVO</div><div class="fs-4 fw-bold">$<?php echo number_format((float)$corte['total_efectivo'],2); ?></div></div></div>
    <div class="col-md-4"><div class="card card-custom border-0 p-3"><div class="small text-muted fw-bold">TARJETA</div><div class="fs-4 fw-bold">$<?php echo number_format((float)$corte['total_tarjeta'],2); ?></div></div></div>
    <div class="col-md-4"><div class="card card-custom border-0 p-3"><div class="small text-muted fw-bold">CREDITO</div><div class="fs-4 fw-bold">$<?php echo number_format((float)$corte['total_credito'],2); ?></div></div></div>
</div>

<div class="card card-custom border-0 shadow-sm">
    <div class="card-header bg-white"><h5 class="fw-bold mb-0">Desglose por Producto y Modalidad</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Producto</th><th>Modalidad</th><th>Cantidad</th><th>Ingreso</th><th>Costo</th><th>Utilidad</th></tr></thead>
            <tbody>
            <?php foreach ($lineas as $l): ?>
                <tr>
                    <td class="fw-bold"><?php echo h($l['nombre']); ?></td>
                    <td><?php echo h($l['modalidad']); ?></td>
                    <td><?php echo (int)$l['cantidad']; ?></td>
                    <td>$<?php echo number_format((float)$l['ingreso'],2); ?></td>
                    <td>$<?php echo number_format((float)$l['costo'],2); ?></td>
                    <td class="fw-bold text-success">$<?php echo number_format((float)$l['ingreso']-(float)$l['costo'],2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
