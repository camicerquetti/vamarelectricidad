<?php
session_start();
require_once "config.php"; // conexión mysqli ($conn)

// Solo permitir empleados
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== "empleado") {
    header("Location: login.php");
    exit;
}

// Menú para empleados
$menu = [
  
    'ver lista de  Precios' => 'productosempleados.php',

    'Presupuestos' => 'presupuestoempleados.php',

     'Ventas' => 'ventasempleado.php',   // 👈 nueva sección

     'Cerrar Sesión' => 'login.php'
];

// ==========================
// Buscar producto
// ==========================
$busqueda = "";
if (!empty($_GET['q'])) {
    $busqueda = trim($_GET['q']);
}

// ==========================
// Obtener lista de productos
// ==========================
$sql = "SELECT * FROM productos";
if ($busqueda) {
    $sql .= " WHERE codigo LIKE ? OR nombre LIKE ?";
    $stmt = $conn->prepare($sql);
    $like = "%$busqueda%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Lista de Productos - Empleado</title>
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f4f6f9; }
nav {
    display: flex; justify-content: space-between; align-items: center;
    background: #6B73FF; padding: 10px 20px; color: white; box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
nav .logo { font-weight: bold; font-size: 20px; }
nav ul { list-style: none; display: flex; gap: 15px; }
nav ul li a { text-decoration: none; color: white; padding: 8px 12px; border-radius: 6px; transition: background 0.3s; }
nav ul li a:hover { background: rgba(255,255,255,0.2); }
.container { padding: 30px; background:#fff; margin:20px auto; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1); max-width:1200px; }
h1 { text-align:center; margin-bottom:20px; }
form.buscar { margin-bottom:20px; text-align:center; }
form.buscar input { padding:8px; width:250px; border:1px solid #ccc; border-radius:5px; }
form.buscar button { padding:8px 15px; background:#6B73FF; border:none; color:#fff; border-radius:5px; cursor:pointer; }
form.buscar button:hover { background:#000DFF; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
table th, table td { border:1px solid #ddd; padding:10px; text-align:center; }
table th { background:#6B73FF; color:white; }
</style>
</head>
<body>

<!-- Menú de empleado -->
<nav>
    <div class="logo">Sistema Vamar</div>
    <ul>
        <?php foreach($menu as $label => $link): ?>
            <li><a href="<?= $link ?>"><?= $label ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>

<div class="container">
    <h1>Lista de Productos</h1>

    <!-- Buscador -->
    <form method="GET" class="buscar">
        <input type="text" name="q" placeholder="Buscar por código o nombre..." value="<?php echo htmlspecialchars($busqueda); ?>">
        <button type="submit">Buscar</button>
    </form>

    <!-- Tabla de productos -->
    <table>
        <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Descripción</th>
            <th>Precio</th>
            <th>Stock</th>
        </tr>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                    <td>$<?php echo number_format($row['precio'], 2); ?></td>
                    <td><?php echo $row['stock']; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No se encontraron productos.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
