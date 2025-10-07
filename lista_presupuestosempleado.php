<?php
session_start();
require_once "config.php";

// Solo permitir admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== "empleado") {
    header("Location: login.php");
    exit;
}

// =====================
// Eliminar presupuesto
// =====================
if (isset($_GET['eliminar'])) {
    $presupuesto_id = (int)$_GET['eliminar'];

    // Primero eliminar detalles
    $stmt = $conn->prepare("DELETE FROM presupuesto_detalles WHERE presupuesto_id=?");
    $stmt->bind_param("i", $presupuesto_id);
    $stmt->execute();

    // Luego eliminar presupuesto
    $stmt = $conn->prepare("DELETE FROM presupuestos WHERE id=?");
    $stmt->bind_param("i", $presupuesto_id);
    $stmt->execute();

    echo "<script>alert('Presupuesto eliminado correctamente'); window.location='".$_SERVER['PHP_SELF']."';</script>";
    exit;
}

// =====================
// Confirmar venta
// =====================
if(isset($_GET['confirmar_venta'])){
    $presupuesto_id = (int)$_GET['confirmar_venta'];

    // Obtener presupuesto
    $stmt = $conn->prepare("SELECT * FROM presupuestos WHERE id=?");
    $stmt->bind_param("i",$presupuesto_id);
    $stmt->execute();
    $presupuesto = $stmt->get_result()->fetch_assoc();

    if($presupuesto){
        // Insertar en ventas incluyendo cliente
        $stmt = $conn->prepare("INSERT INTO ventas (usuario_id, cliente, fecha, total, metodo_pago) VALUES (?,?,?,?,?)");
        $stmt->bind_param(
            "issds",
            $presupuesto['usuario_id'],
            $presupuesto['cliente'],  // <-- cliente agregado
            $presupuesto['fecha'],
            $presupuesto['total'],
            $presupuesto['metodo_pago']
        );
        $stmt->execute();
        $venta_id = $conn->insert_id;

        // Obtener detalles del presupuesto
        $detalles = $conn->query("SELECT * FROM presupuesto_detalles WHERE presupuesto_id=".$presupuesto['id']);
        while($d = $detalles->fetch_assoc()){
            $stmt = $conn->prepare("INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio) VALUES (?,?,?,?)");
            $stmt->bind_param("iiid",$venta_id,$d['producto_id'],$d['cantidad'],$d['precio']);
            $stmt->execute();
        }

        // Eliminar presupuesto y sus detalles
        $stmt = $conn->prepare("DELETE FROM presupuesto_detalles WHERE presupuesto_id=?");
        $stmt->bind_param("i", $presupuesto_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM presupuestos WHERE id=?");
        $stmt->bind_param("i", $presupuesto_id);
        $stmt->execute();

        echo "<script>alert('Venta confirmada y presupuesto eliminado'); window.location='".$_SERVER['PHP_SELF']."';</script>";
        exit;
    }
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
// Obtener presupuestos
// =====================
$presupuestos = [];
$result = $conn->query("SELECT * FROM presupuestos ORDER BY fecha DESC");
while($row = $result->fetch_assoc()){
    $presupuestos[] = $row;
}

// =====================
// Traer detalles
// =====================
$detalles_presupuestos = [];
foreach($presupuestos as $p){
    $id = $p['id'];
    $detalles_presupuestos[$id] = [];
    $res = $conn->query("SELECT pd.cantidad, pd.precio, p.nombre, p.codigo 
                         FROM presupuesto_detalles pd 
                         JOIN productos p ON pd.producto_id = p.id 
                         WHERE pd.presupuesto_id = $id");
    while($row = $res->fetch_assoc()){
        $detalles_presupuestos[$id][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Lista de Presupuestos</title>
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
a.confirmar { background:#28a745; padding:8px 12px; border-radius:5px; color:white; text-decoration:none; margin-left:5px; }
a.confirmar:hover { background:#218838; }
a.eliminar { background:#dc3545; padding:8px 12px; border-radius:5px; color:white; text-decoration:none; margin-left:5px; }
a.eliminar:hover { background:#c82333; }
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
    <h1>Lista de Presupuestos</h1>
    <?php if(empty($presupuestos)): ?>
        <p>No hay presupuestos registrados.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Total</th>
                <th>Método de Pago</th>
                <th>Acción</th>
            </tr>
            <?php foreach($presupuestos as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= $p['usuario_id'] ?></td>
                    <td><?= $p['cliente'] ?: '---' ?></td>
                    <td><?= $p['fecha'] ?></td>
                    <td>$<?= number_format($p['total'],2) ?></td>
                    <td><?= htmlspecialchars($p['metodo_pago']) ?></td>
                    <td>
                        <button onclick='abrirModal(<?= $p['id'] ?>, <?= htmlspecialchars(json_encode($detalles_presupuestos[$p['id']])) ?>)'>Ver detalle</button>
                        <a href="?confirmar_venta=<?= $p['id'] ?>" class="confirmar" onclick="return confirm('¿Confirmar venta y mover a ventas?')">Confirmar venta</a>
                        <a href="?eliminar=<?= $p['id'] ?>" class="eliminar" onclick="return confirm('¿Seguro que quieres eliminar este presupuesto?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<!-- Modal de detalle -->
<div class="modal" id="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h2>Detalle del Presupuesto</h2>
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
function abrirModal(id, detalle){
    const modal = document.getElementById('modal');
    const tabla = document.getElementById('detalleTabla');
    tabla.innerHTML = `<tr>
        <th>Código</th><th>Nombre</th><th>Cantidad</th><th>Precio Unitario</th><th>Total</th>
    </tr>`; // reset

    detalle.forEach(item => {
        let row = tabla.insertRow();
        row.insertCell(0).innerText = item.codigo;
        row.insertCell(1).innerText = item.nombre;
        row.insertCell(2).innerText = item.cantidad;
        row.insertCell(3).innerText = parseFloat(item.precio).toFixed(2);
        row.insertCell(4).innerText = (item.precio * item.cantidad).toFixed(2);
    });

    modal.style.display = 'flex';
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
