<?php
session_start();
require_once "config.php";

// Solo permitir admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== "empleado") {
    header("Location: login.php");
    exit;
}

// =====================
// Menú de admin
// =====================
$menu = [
  
    'Modificar Precios' => 'productosempleados.php',

    'Presupuestos' => 'presupuestoempleados.php',

     'Ventas' => 'ventasempleado.php',   // 👈 nueva sección

     'Cerrar Sesión' => 'login.php'
];
// =====================
// Obtener ventas
// =====================
$ventas = [];
$result = $conn->query("SELECT * FROM ventas ORDER BY fecha DESC");
while($row = $result->fetch_assoc()){
    $ventas[] = $row;
}

// =====================
// Traer detalles de ventas
// =====================
$detalles_ventas = [];
foreach($ventas as $v){
    $venta_id = $v['id'];
    $detalles_ventas[$venta_id] = [];
    $res = $conn->query("SELECT vd.cantidad, vd.precio, p.nombre, p.codigo
                         FROM venta_detalles vd
                         JOIN productos p ON vd.producto_id = p.id
                         WHERE vd.venta_id = $venta_id");
    while($row = $res->fetch_assoc()){
        $detalles_ventas[$venta_id][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Lista de Ventas</title>
<style>
* { box-sizing:border-box; margin:0; padding:0; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f4f6f9; }
nav { display:flex; justify-content:space-between; align-items:center; background:#6B73FF; padding:10px 20px; color:white; box-shadow:0 2px 6px rgba(0,0,0,0.2); }
nav .logo { font-weight:bold; font-size:20px; }
nav ul { list-style:none; display:flex; gap:15px; }
nav ul li a { text-decoration:none; color:white; padding:8px 12px; border-radius:6px; }
nav ul li a:hover { background: rgba(255,255,255,0.2); }
.container { padding:30px; background:#fff; margin:20px auto; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1); max-width:1200px; }
h1 { text-align:center; margin-bottom:20px; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
table th, table td { border:1px solid #ddd; padding:10px; text-align:center; }
table th { background:#6B73FF; color:white; }
button { padding:8px 15px; border:none; border-radius:5px; cursor:pointer; background:#6B73FF; color:#fff; margin-right:5px; }
button:hover { background:#000DFF; }
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; }
.modal-content { background:#fff; padding:20px; border-radius:10px; width:80%; max-width:600px; position:relative; }
.close { position:absolute; top:10px; right:15px; font-size:20px; cursor:pointer; }
</style>
</head>
<body>

<nav>
    <div class="logo">Sistema Vamar</div>
    <ul>
        <?php foreach($menu as $label => $link): ?>
            <li><a href="<?= $link ?>"><?= $label ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>

<div class="container">
    <h1>Lista de Ventas</h1>
    <?php if(empty($ventas)): ?>
        <p>No hay ventas registradas.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>ID Venta</th>
                <th>Usuario</th>
                <th>Cliente</th> <!-- nueva columna -->
                <th>Fecha</th>
                <th>Total</th>
                <th>Método de Pago</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
            <?php foreach($ventas as $v): ?>
                <tr>
                    <td><?= $v['id'] ?></td>
                    <td><?= $v['usuario_id'] ?></td>
                    <td><?= htmlspecialchars($v['cliente']) ?: '---' ?></td> <!-- mostrar cliente -->
                    <td><?= $v['fecha'] ?></td>
                    <td>$<?= number_format($v['total'],2) ?></td>
                    <td><?= htmlspecialchars($v['metodo_pago']) ?></td>
                    <td><?= htmlspecialchars($v['estado']) ?></td>
                    <td>
                        <button onclick='abrirModal(<?= $v['id'] ?>, <?= htmlspecialchars(json_encode($detalles_ventas[$v['id']])) ?>)'>Ver detalle</button>
                        <?php if ($v['estado'] === 'pendiente'): ?>
                            <button onclick='abrirModal(<?= $v['id'] ?>, <?= htmlspecialchars(json_encode($detalles_ventas[$v['id']])) ?>, true)'>Facturar</button>
                        <?php else: ?>
                            <span style="color:green; font-weight:bold;">Facturado</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<!-- Modal de detalle de venta -->
<div class="modal" id="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h2>Detalle de la Venta</h2>
        <table id="detalleTabla">
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Cantidad</th>
                <th>Precio Unitario</th>
                <th>Total</th>
            </tr>
        </table>
    </div>
</div>

<script>
function abrirModal(id, detalle, imprimir=false){
    const modal = document.getElementById('modal');
    const tabla = document.getElementById('detalleTabla');
    tabla.innerHTML = `<tr>
        <th>Código</th><th>Nombre</th><th>Cantidad</th><th>Precio Unitario</th><th>Total</th>
    </tr>`; // reset

    let total = 0;

    detalle.forEach(item => {
        let row = tabla.insertRow();
        row.insertCell(0).innerText = item.codigo;
        row.insertCell(1).innerText = item.nombre;
        row.insertCell(2).innerText = item.cantidad;
        row.insertCell(3).innerText = parseFloat(item.precio).toFixed(2);
        let subtotal = item.precio * item.cantidad;
        row.insertCell(4).innerText = subtotal.toFixed(2);
        total += subtotal;
    });

    // fila de total general
    let rowTotal = tabla.insertRow();
    rowTotal.insertCell(0).colSpan = 4;
    rowTotal.insertCell(0).innerText = 'Total';
    rowTotal.insertCell(1).innerText = total.toFixed(2);

    modal.style.display = 'flex';

    if(imprimir){
        setTimeout(() => {
            const contenido = modal.querySelector('.modal-content').innerHTML;
            const ventana = window.open('', '', 'width=800,height=600');
            ventana.document.write('<html><head><title>Factura</title></head><body>');
            ventana.document.write(contenido);
            ventana.document.write('</body></html>');
            ventana.document.close();
            ventana.print();

            // Realizar petición AJAX para cambiar estado a facturado
            fetch('facturar.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === "ok") {
                        alert("Venta facturada con éxito.");
                        location.reload();
                    } else {
                        alert("Error al facturar: " + data);
                    }
                })
                .catch(err => {
                    alert("Error de red al facturar.");
                    console.error(err);
                });

        }, 200);
    }
}

function cerrarModal(){
    document.getElementById('modal').style.display = 'none';
}

window.onclick = function(event){
    const modal = document.getElementById('modal');
    if(event.target == modal){
        modal.style.display = 'none';
    }
}
</script>

</body>
</html>
