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
     'Ventas' => 'ventas.php',   // 👈 nueva sección
    'Compras x' => 'compras_negro.php',
     'Compras' => 'compras.php',
     'Cerrar Sesión' => 'login.php'
];
// =====================
// Obtener cuentas (ventas confirmadas)
// =====================
$cuentas = [];
$result = $conn->query("SELECT * FROM cuentas ORDER BY fecha DESC");
while($row = $result->fetch_assoc()){
    $cuentas[] = $row;
}

// =====================
// Calcular totales por método de pago
// =====================
$totales = ['efectivo'=>0, 'tarjeta'=>0, 'transferencia'=>0];
foreach($cuentas as $c){
    if(isset($totales[$c['metodo_pago']])){
        $totales[$c['metodo_pago']] += $c['total'];
    }
}
$total_general = array_sum($totales);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cuentas Corrientes</title>
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
button { padding:8px 15px; border:none; border-radius:5px; cursor:pointer; background:#6B73FF; color:#fff; }
button:hover { background:#000DFF; }
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; }
.modal-content { background:#fff; padding:20px; border-radius:10px; width:80%; max-width:600px; position:relative; }
.close { position:absolute; top:10px; right:15px; font-size:20px; cursor:pointer; }
.totales { display:flex; justify-content:space-around; margin-bottom:20px; }
.totales div { background:#eee; padding:20px; border-radius:10px; width:30%; text-align:center; font-weight:bold; }
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
    <h1>Cuentas Corrientes</h1>

    <div class="totales">
        <div>Efectivo: $<?= number_format($totales['efectivo'],2) ?></div>
        <div>Tarjeta: $<?= number_format($totales['tarjeta'],2) ?></div>
        <div>Transferencia: $<?= number_format($totales['transferencia'],2) ?></div>
        <div>Total: $<?= number_format($total_general,2) ?></div>
    </div>

    <?php if(empty($cuentas)): ?>
        <p>No hay cuentas registradas.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Total</th>
                <th>Método de Pago</th>
            </tr>
            <?php foreach($cuentas as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td><?= htmlspecialchars($c['cliente']) ?></td>
                    <td><?= $c['fecha'] ?></td>
                    <td>$<?= number_format($c['total'],2) ?></td>
                    <td><?= htmlspecialchars($c['metodo_pago']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
