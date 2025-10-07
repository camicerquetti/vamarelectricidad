<?php
session_start();
require_once "config.php"; // conexión mysqli ($conn)

// Solo permitir admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== "admin") {
    header("Location: login.php");
    exit;
}

// ==========================
// Eliminar producto del presupuesto
// ==========================
if (isset($_GET['eliminar'])) {
    unset($_SESSION['presupuesto'][$_GET['eliminar']]);
    header("Location: presupuesto.php");
    exit;
}

// Menú para administradores
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

// Inicializar presupuesto en sesión
if (!isset($_SESSION['presupuesto'])) {
    $_SESSION['presupuesto'] = [];
}
if (!isset($_SESSION['cliente'])) {
    $_SESSION['cliente'] = "";
}

// ==========================
// Agregar o actualizar producto al presupuesto
// ==========================
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Guardar nombre cliente
    if (isset($_POST['cliente'])) {
        $_SESSION['cliente'] = trim($_POST['cliente']);
    }

    // Agregar producto
    if (isset($_POST['codigo']) && isset($_POST['cantidad'])) {
        $codigo = trim($_POST['codigo']);
        $cantidad = (int)$_POST['cantidad'];

        // Buscar por código o nombre
        $stmt = $conn->prepare("SELECT * FROM productos WHERE codigo = ? OR nombre = ? LIMIT 1");
        $stmt->bind_param("ss", $codigo, $codigo);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $producto = $res->fetch_assoc();

            if (isset($_SESSION['presupuesto'][$producto['codigo']])) {
                $_SESSION['presupuesto'][$producto['codigo']]['cantidad'] += $cantidad;
            } else {
                $producto['cantidad'] = $cantidad;
                $_SESSION['presupuesto'][$producto['codigo']] = $producto;
            }
        } else {
            $mensaje = "Producto no encontrado.";
        }
    }

    // Editar cantidades
    if (isset($_POST['editar'])) {
        foreach ($_POST['cantidades'] as $codigo => $cantidad) {
            if (isset($_SESSION['presupuesto'][$codigo])) {
                $_SESSION['presupuesto'][$codigo]['cantidad'] = (int)$cantidad;
            }
        }
        $mensaje = "Cantidades actualizadas correctamente ✅";
    }

    // Confirmar presupuesto
    if (isset($_POST['confirmar']) && isset($_POST['metodo_pago'])) {
        $metodo_pago = $_POST['metodo_pago'];
        $usuario_id = $_SESSION['usuario_id'];
        $fecha = date('Y-m-d H:i:s');
        $cliente = $_SESSION['cliente'];

        // Calcular total
        $total_presupuesto = 0;
        foreach ($_SESSION['presupuesto'] as $item) {
            $total_presupuesto += $item['cantidad'] * $item['precio'];
        }

        // Insertar presupuesto
        $stmt = $conn->prepare("INSERT INTO presupuestos (cliente, usuario_id, fecha, total, metodo_pago, confirmado) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sisss", $cliente, $usuario_id, $fecha, $total_presupuesto, $metodo_pago);
        $stmt->execute();
        $presupuesto_id = $stmt->insert_id;

        // Insertar productos detalle
        $stmt_detalle = $conn->prepare("INSERT INTO presupuesto_detalles (presupuesto_id, producto_id, cantidad, precio) VALUES (?, ?, ?, ?)");
        foreach ($_SESSION['presupuesto'] as $item) {
            $stmt_detalle->bind_param("iiid", $presupuesto_id, $item['id'], $item['cantidad'], $item['precio']);
            $stmt_detalle->execute();
        }

        // Limpiar sesión
        $_SESSION['presupuesto'] = [];
        $_SESSION['cliente'] = "";
        $mensaje = "Presupuesto confirmado y guardado ✅";
    }
}

// ==========================
// Calcular totales (sin IVA)
// ==========================
$subtotal = 0;
foreach ($_SESSION['presupuesto'] as $item) {
    $subtotal += $item['cantidad'] * $item['precio'];
}
$total = $subtotal;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Presupuestos</title>
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f4f6f9; }
nav { display: flex; justify-content: space-between; align-items: center; background: #6B73FF; padding: 10px 20px; color: white; box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
nav .logo { font-weight: bold; font-size: 20px; }
nav ul { list-style: none; display: flex; gap: 15px; }
nav ul li a { text-decoration: none; color: white; padding: 8px 12px; border-radius: 6px; transition: background 0.3s; }
nav ul li a:hover { background: rgba(255,255,255,0.2); }
.container { padding: 30px; background:#fff; margin:20px auto; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1); max-width:1200px; }
h1 { text-align:center; margin-bottom:20px; }
form.buscar { text-align:center; margin-bottom:20px; }
form.buscar input { padding:5px; margin-right:5px; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
table th, table td { border:1px solid #ddd; padding:10px; text-align:center; }
table th { background:#6B73FF; color:white; }
button { padding:10px 20px; margin:5px; border:none; border-radius:5px; cursor:pointer; color:#fff; }
button.efectivo { background:#28a745; }
button.tarjeta { background:#007bff; }
button.transferencia { background:#ffc107; color:#000; }
form.buscar button, form table button[type="submit"] { background-color: #6B73FF; color: #fff; border: none; border-radius: 5px; cursor: pointer; padding: 8px 15px; }
form.buscar button:hover, form table button[type="submit"]:hover { background-color: #000DFF; }
.mensaje { background:#e0ffe0; color:#007700; padding:10px; margin-bottom:15px; border-radius:5px; }
@media print {
    body * { visibility: hidden; }
    #presupuesto, #presupuesto * { visibility: visible; }
    #presupuesto { position: absolute; top: 0; left: 0; width: 100%; }
}
</style>
</head>
<body>

<!-- Menú -->
<nav>
    <div class="logo">Sistema Vamar</div>
    <ul>
        <?php foreach($menu as $label => $link): ?>
            <li><a href="<?= $link ?>"><?= $label ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>

<div class="container">
    <h1>Presupuesto</h1>
<div style="text-align:center; margin-top:20px;">
    <form action="lista_presupesto.php" method="GET" style="display:inline;">
        <button type="submit" style="background:#17a2b8;">Ver Lista de Presupuestos</button>
    </form>
    <button onclick="window.print()" style="background:#6B73FF;">🖨 Imprimir Presupuesto</button>
</div>

<?php if(!empty($mensaje)) echo "<div class='mensaje'>$mensaje</div>"; ?>

<!-- Form cliente y producto -->
<form method="POST" class="buscar">
    <input type="text" name="cliente" placeholder="Nombre del cliente" value="<?= htmlspecialchars($_SESSION['cliente']) ?>" required>
    <input type="text" name="codigo" placeholder="Código o nombre del producto" required>
    <input type="number" name="cantidad" placeholder="Cantidad" min="1" required>
    <button type="submit">Agregar al presupuesto</button>
</form>

<?php if(!empty($_SESSION['presupuesto'])): ?>
    <form method="POST">
        <div id="presupuesto">
        <p><strong>Cliente:</strong> <?= htmlspecialchars($_SESSION['cliente']) ?></p>
        <table>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Cantidad</th>
                <th>Precio Unitario</th>
                <th>Total</th>
                <th>Acción</th>
            </tr>
            <?php foreach($_SESSION['presupuesto'] as $codigo => $item): 
                $total_item = $item['cantidad'] * $item['precio']; ?>
                <tr>
                    <td><?= htmlspecialchars($item['codigo']) ?></td>
                    <td><?= htmlspecialchars($item['nombre']) ?></td>
                    <td><input type="number" name="cantidades[<?= $codigo ?>]" value="<?= $item['cantidad'] ?>" min="1"></td>
                    <td><?= number_format($item['precio'],2) ?></td>
                    <td><?= number_format($total_item,2) ?></td>
                    <td><a href="presupuesto.php?eliminar=<?= $codigo ?>" style="color:red;">Eliminar</a></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th colspan="4" style="text-align:right;">Total:</th>
                <td><?= number_format($total,2) ?></td>
                <td></td>
            </tr>
        </table>
        </div>
        <div style="text-align:center; margin-top:10px;">
            <button type="submit" name="editar">Actualizar cantidades</button>
        </div>
    </form>

    <div style="text-align:center; margin-top:20px;">
        <form method="POST" style="display:inline;" onsubmit="return confirmarVenta(this);">
            <input type="hidden" name="metodo_pago" value="efectivo">
            <button type="submit" name="confirmar" class="efectivo">Confirmar presupuesto en efectivo</button>
        </form>
        <form method="POST" style="display:inline;" onsubmit="return confirmarVenta(this);">
            <input type="hidden" name="metodo_pago" value="tarjeta">
            <button type="submit" name="confirmar" class="tarjeta">Confirmar presupuesto tarjeta</button>
        </form>
        <form method="POST" style="display:inline;" onsubmit="return confirmarVenta(this);">
            <input type="hidden" name="metodo_pago" value="transferencia">
            <button type="submit" name="confirmar" class="transferencia">Confirmar presupuesto transferencia</button>
        </form>
    </div>
<?php else: ?>
    <p>No hay productos en el presupuesto.</p>
<?php endif; ?>
</div>

<script>
function confirmarVenta(form) {
    return confirm("Recuerda confirmar la venta y facturar si corresponde. ¿Deseas continuar?");
}
</script>

</body>
</html>
