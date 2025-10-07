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
    'Compras x' => 'compras_negro.php',
    'Compras' => 'compras.php',
    'Cerrar Sesión' => 'login.php'
];

// =====================
// Fecha actual
// =====================
$mes_actual = date('m');
$anio_actual = date('Y');

// =====================
// Ventas confirmadas este mes
// =====================
$stmt = $conn->prepare("SELECT COUNT(*) as total, IFNULL(SUM(total),0) as total_monto FROM ventas WHERE MONTH(fecha)=? AND YEAR(fecha)=?");
$stmt->bind_param("ii", $mes_actual, $anio_actual);
$stmt->execute();
$result = $stmt->get_result();
$ventas_data = $result->fetch_assoc();
$ventas_mes = $ventas_data['total'];
$ventas_total = $ventas_data['total_monto'];

// =====================
// Presupuestos creados este mes
// =====================
$stmt = $conn->prepare("SELECT COUNT(*) as total, IFNULL(SUM(total),0) as total_monto FROM presupuestos WHERE MONTH(fecha)=? AND YEAR(fecha)=?");
$stmt->bind_param("ii", $mes_actual, $anio_actual);
$stmt->execute();
$result = $stmt->get_result();
$presupuestos_data = $result->fetch_assoc();
$presupuestos_mes = $presupuestos_data['total'];
$presupuestos_total = $presupuestos_data['total_monto'];

// =====================
// Ventas por método de pago
// =====================
$metodos = ['Efectivo', 'Tarjeta', 'Transferencia'];
$ventas_metodo = [];
foreach($metodos as $m){
    $stmt = $conn->prepare("SELECT COUNT(*) as total, IFNULL(SUM(total),0) as total_monto FROM ventas WHERE MONTH(fecha)=? AND YEAR(fecha)=? AND metodo_pago=?");
    $stmt->bind_param("iis", $mes_actual, $anio_actual, $m);
    $stmt->execute();
    $result = $stmt->get_result();
    $ventas_metodo[$m] = $result->fetch_assoc();
}

// =====================
// Compras vs Compras x
// =====================
$stmt = $conn->prepare("SELECT IFNULL(SUM(total),0) as total, IFNULL(SUM(iva),0) as iva FROM compras WHERE MONTH(fecha)=? AND YEAR(fecha)=?");
$stmt->bind_param("ii", $mes_actual, $anio_actual);
$stmt->execute();
$result = $stmt->get_result();
$compras_data = $result->fetch_assoc();

$stmt = $conn->prepare("SELECT IFNULL(SUM(total),0) as total, IFNULL(SUM(iva),0) as iva FROM compras_negro WHERE MONTH(fecha)=? AND YEAR(fecha)=?");
$stmt->bind_param("ii", $mes_actual, $anio_actual);
$stmt->execute();
$result = $stmt->get_result();
$compras_negro_data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>
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
.cards { display:flex; gap:20px; justify-content:center; flex-wrap:wrap; margin-top:30px; }
.card { background:#6B73FF; color:white; padding:20px; border-radius:10px; flex:1 1 250px; text-align:center; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
.card h2 { font-size:32px; margin-bottom:10px; }
.card p { font-size:18px; }
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
    <h1>Dashboard - <?= date('F Y') ?></h1>
    <div class="cards">
        <!-- Ventas -->
        <div class="card">
            <h2><?= $ventas_mes ?></h2>
            <p>Ventas confirmadas</p>
            <p>Total: $<?= number_format($ventas_total,2) ?></p>
        </div>
        <div class="card">
            <h2><?= $presupuestos_mes ?></h2>
            <p>Presupuestos creados</p>
            <p>Total: $<?= number_format($presupuestos_total,2) ?></p>
        </div>
        <?php foreach($ventas_metodo as $metodo => $data): ?>
            <div class="card">
                <h2><?= $data['total'] ?></h2>
                <p>Ventas en <?= $metodo ?></p>
                <p>Total: $<?= number_format($data['total_monto'],2) ?></p>
            </div>
        <?php endforeach; ?>
        <div class="card" style="background:#ff6b6b;">
            <h2><?= number_format($ventas_total - $presupuestos_total,2) ?></h2>
            <p>Diferencia Ventas vs Presupuestos</p>
        </div>

        <!-- Comparativa Compras -->
        <div class="card" style="background:#28a745;">
            <h2><?= number_format($compras_data['total'],2) ?></h2>
            <p>Compras</p>
            <p>IVA: $<?= number_format($compras_data['iva'],2) ?></p>
        </div>
        <div class="card" style="background:#ffc107; color:#000;">
            <h2><?= number_format($compras_negro_data['total'],2) ?></h2>
            <p>Compras X</p>
            <p>IVA: $<?= number_format($compras_negro_data['iva'],2) ?></p>
        </div>
    </div>
</div>

</body>
</html>
