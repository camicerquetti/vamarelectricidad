<?php
session_start();
require_once "config.php";

// Solo permitir admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== "admin") {
    header("Location: login.php");
    exit;
}

// =====================
// Menú de admin
// =====================
$menu = [
    'Dashboard' => 'Dashboard.php',
    'Modificar Precios' => 'productos.php',
    'Manejar Proveedores' => 'proveedores.php',
    'Presupuestos' => 'presupuesto.php',
    'Cuentas Corrientes' => 'cuentas.php',
    'Ventas' => 'ventas.php',
    'Compras' => 'compras.php',
    'Compras x' => 'compras_negro.php',
    'Cerrar Sesión' => 'login.php'
];

// =====================
// Crear compra en negro
// =====================
if(isset($_POST['agregar_compra_negro'])){
    $proveedor_id = (int)$_POST['proveedor_id'];
    $fecha = $_POST['fecha'];
    $numero_factura = $_POST['numero_factura'];
    $subtotal = floatval($_POST['total']); // campo 'total' = subtotal
    $iva = floatval($_POST['iva']);
    $total = $subtotal + $iva;
    $estado = 'pendiente';

    $stmt = $conn->prepare("INSERT INTO compras_negro (proveedor_id, fecha, numero_factura, total, iva, estado) VALUES (?,?,?,?,?,?)");
    if(!$stmt){ die("Error en prepare: ".$conn->error); }
    $stmt->bind_param("issdds",$proveedor_id, $fecha, $numero_factura, $total, $iva, $estado);
    $stmt->execute();
    header("Location: compras_negro.php");
    exit;
}

// =====================
// Registrar pago en negro
// =====================
if(isset($_POST['registrar_pago_negro'])){
    $compra_id = (int)$_POST['compra_id'];
    $monto = floatval($_POST['monto']);
    $fecha_pago = $_POST['fecha_pago'];

    // Insertar pago
    $stmt = $conn->prepare("INSERT INTO pagos_compras_negro (compra_id, monto, fecha_pago) VALUES (?,?,?)");
    if(!$stmt){ die("Error en prepare pagos_compras_negro: ".$conn->error); }
    $stmt->bind_param("dds",$compra_id,$monto,$fecha_pago);
    $stmt->execute();

    // Actualizar estado
    $stmt2 = $conn->prepare("SELECT total, IFNULL((SELECT SUM(monto) FROM pagos_compras_negro WHERE compra_id=?),0) as pagado FROM compras_negro WHERE id=?");
    if(!$stmt2){ die("Error en prepare estado: ".$conn->error); }
    $stmt2->bind_param("ii",$compra_id,$compra_id);
    $stmt2->execute();
    $res = $stmt2->get_result()->fetch_assoc();
    $saldo = $res['total'] - $res['pagado'];
    $estado = $saldo <= 0 ? 'pagada' : 'pendiente';

    $stmt3 = $conn->prepare("UPDATE compras_negro SET estado=? WHERE id=?");
    if(!$stmt3){ die("Error en prepare update estado: ".$conn->error); }
    $stmt3->bind_param("si",$estado,$compra_id);
    $stmt3->execute();

    header("Location: compras_negro.php");
    exit;
}

// =====================
// Obtener compras en negro
// =====================
$compras_negro = $conn->query("
    SELECT c.*, p.nombre as proveedor_nombre,
    IFNULL((SELECT SUM(monto) FROM pagos_compras_negro pc WHERE pc.compra_id=c.id),0) as pagado,
    (c.total - IFNULL((SELECT SUM(monto) FROM pagos_compras_negro pc WHERE pc.compra_id=c.id),0)) as saldo
    FROM compras_negro c
    JOIN proveedores p ON c.proveedor_id=p.id
    ORDER BY c.fecha DESC
");

// =====================
// Obtener proveedores
// =====================
$proveedores = $conn->query("SELECT * FROM proveedores ORDER BY nombre ASC");

// =====================
// Totales mes actual compras en negro
// =====================
$mes_actual = date('m');
$anio_actual = date('Y');
$stmt = $conn->prepare("SELECT IFNULL(SUM(total),0) as total_mes, IFNULL(SUM(iva),0) as iva_mes FROM compras_negro WHERE MONTH(fecha)=? AND YEAR(fecha)=?");
$stmt->bind_param("ii",$mes_actual,$anio_actual);
$stmt->execute();
$totales_mes = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Compras en Negro</title>
<style>
/* Misma estética que compras normales */
* { box-sizing:border-box; margin:0; padding:0; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f4f6f9; }
nav { display:flex; justify-content:space-between; align-items:center; background:#6B73FF; padding:10px 20px; color:white; box-shadow:0 2px 6px rgba(0,0,0,0.2); }
nav .logo { font-weight:bold; font-size:20px; }
nav ul { list-style:none; display:flex; gap:15px; }
nav ul li a { text-decoration:none; color:white; padding:8px 12px; border-radius:6px; }
nav ul li a:hover { background: rgba(255,255,255,0.2); }
.container { padding:30px; background:#fff; margin:20px auto; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1); max-width:1200px; }
h1 { text-align:center; margin-bottom:20px; }
.cards { display:flex; gap:20px; justify-content:center; flex-wrap:wrap; margin-top:30px; }
.card { background:#6B73FF; color:white; padding:20px; border-radius:10px; flex:1 1 250px; text-align:center; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
.card h2 { font-size:32px; margin-bottom:10px; }
.card p { font-size:18px; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
table th, table td { border:1px solid #ddd; padding:10px; text-align:center; }
table th { background:#6B73FF; color:white; }
button, input[type=submit] { padding:8px 12px; border:none; border-radius:5px; cursor:pointer; background:#6B73FF; color:white; }
button:hover, input[type=submit]:hover { background:#000DFF; }
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; }
.modal-content { background:#fff; padding:20px; border-radius:10px; width:80%; max-width:600px; position:relative; }
.close { position:absolute; top:10px; right:15px; font-size:20px; cursor:pointer; }
.pendiente { background:#fff; color:#000; }
.pagada { background:#000; color:#fff; }
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
    <h1>Compras en Negro</h1>

    <div class="cards">
        <div class="card">
            <h2>$<?= number_format($totales_mes['total_mes'],2) ?></h2>
            <p>Total compras mes <?= date('F') ?></p>
        </div>
        <div class="card">
            <h2>$<?= number_format($totales_mes['iva_mes'],2) ?></h2>
            <p>Total IVA mes <?= date('F') ?></p>
        </div>
    </div>

    <!-- Formulario agregar compra en negro -->
    <h2>Agregar Compra en Negro</h2>
    <form method="post" style="margin-bottom:20px;">
        <select name="proveedor_id" required>
            <option value="">Seleccionar proveedor</option>
            <?php while($prov = $proveedores->fetch_assoc()): ?>
                <option value="<?= $prov['id'] ?>"><?= $prov['nombre'] ?></option>
            <?php endwhile; ?>
        </select>
        <input type="date" name="fecha" required>
        <input type="text" name="numero_factura" placeholder="Número de Factura" required>
        <input type="number" step="0.01" name="total" placeholder="Subtotal" required>
        <input type="number" step="0.01" name="iva" placeholder="IVA" required>
        <input type="submit" name="agregar_compra_negro" value="Agregar Compra">
    </form>

    <!-- Tabla de compras en negro -->
    <h2>Listado de Compras en Negro</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Proveedor</th>
            <th>Fecha</th>
            <th>Factura</th>
            <th>Total</th>
            <th>IVA</th>
            <th>Pagado</th>
            <th>Saldo</th>
            <th>Estado</th>
            <th>Acción</th>
        </tr>
        <?php while($c = $compras_negro->fetch_assoc()): ?>
            <tr class="<?= $c['estado'] ?>">
                <td><?= $c['id'] ?></td>
                <td><?= $c['proveedor_nombre'] ?></td>
                <td><?= $c['fecha'] ?></td>
                <td><?= $c['numero_factura'] ?></td>
                <td>$<?= number_format($c['total'],2) ?></td>
                <td>$<?= number_format($c['iva'],2) ?></td>
                <td>$<?= number_format($c['pagado'],2) ?></td>
                <td>$<?= number_format($c['saldo'],2) ?></td>
                <td><?= ucfirst($c['estado']) ?></td>
                <td>
                    <button onclick="abrirModalNegro(<?= $c['id'] ?>, '<?= $c['proveedor_nombre'] ?>', <?= $c['saldo'] ?>)">Registrar Pago</button>
                    <button onclick="abrirModalPagosNegro(<?= $c['id'] ?>)">Ver Pagos</button>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<!-- Modal registrar pago negro -->
<div class="modal" id="modal_negro">
    <div class="modal-content">
        <span class="close" onclick="cerrarModalNegro()">&times;</span>
        <h2>Registrar Pago Compra en Negro</h2>
        <form method="post">
            <input type="hidden" name="compra_id" id="compra_id_negro">
            <p>Proveedor: <span id="proveedor_nombre_negro"></span></p>
            <p>Saldo pendiente: $<span id="saldo_negro"></span></p>
            <input type="number" step="0.01" name="monto" placeholder="Monto a pagar" required>
            <input type="date" name="fecha_pago" required>
            <input type="submit" name="registrar_pago_negro" value="Registrar Pago">
        </form>
    </div>
</div>

<!-- Modal ver pagos negro -->
<div class="modal" id="modal_pagos_negro">
    <div class="modal-content">
        <span class="close" onclick="cerrarModalPagosNegro()">&times;</span>
        <h2>Pagos Registrados</h2>
        <table id="tabla_pagos_negro">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha Pago</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                <!-- Se llena dinámicamente -->
            </tbody>
        </table>
    </div>
</div>

<script>
function abrirModalNegro(id, proveedor, saldo){
    document.getElementById('compra_id_negro').value = id;
    document.getElementById('proveedor_nombre_negro').innerText = proveedor;
    document.getElementById('saldo_negro').innerText = saldo.toFixed(2);
    document.getElementById('modal_negro').style.display = 'flex';
}
function cerrarModalNegro(){
    document.getElementById('modal_negro').style.display = 'none';
}

// Modal pagos negro
function abrirModalPagosNegro(compra_id){
    const tbody = document.querySelector("#tabla_pagos_negro tbody");
    tbody.innerHTML = "";

    fetch("obtener_pagos_negro.php?compra_id=" + compra_id)
    .then(response => response.json())
    .then(data => {
        if(data.length === 0){
            tbody.innerHTML = "<tr><td colspan='3'>No hay pagos registrados</td></tr>";
        } else {
            data.forEach(pago => {
                tbody.innerHTML += `<tr>
                    <td>${pago.id}</td>
                    <td>${pago.fecha_pago}</td>
                    <td>$${parseFloat(pago.monto).toFixed(2)}</td>
                </tr>`;
            });
        }
        document.getElementById('modal_pagos_negro').style.display = 'flex';
    })
    .catch(err => console.error(err));
}
function cerrarModalPagosNegro(){
    document.getElementById('modal_pagos_negro').style.display = 'none';
}

window.onclick = function(event){
    if(event.target == document.getElementById('modal_negro')){
        cerrarModalNegro();
    }
    if(event.target == document.getElementById('modal_pagos_negro')){
        cerrarModalPagosNegro();
    }
}
</script>

</body>
</html>
