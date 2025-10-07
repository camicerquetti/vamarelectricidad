<?php
session_start();
require_once "config.php";

// Solo permitir admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== "admin") {
    header("Location: login.php");
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

$mensaje = "";

// ==========================
// Buscar producto
// ==========================
$busqueda = "";
if (!empty($_GET['q'])) {
    $busqueda = trim($_GET['q']);
}
// ==========================
// Subida CSV
// ==========================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['csv'])) {
    $archivo = $_FILES['csv']['tmp_name'];
    if (($handle = fopen($archivo, "r")) !== FALSE) {

        $filaActual = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $filaActual++;

            // Ignorar las dos primeras filas (cabecera + familia)
            if ($filaActual <= 2) continue;

            // Nos aseguramos que tenga al menos 4 columnas: código, descripción, precio, proveedor
            if (count($data) < 4) continue;

            $codigo      = isset($data[0]) ? trim($data[0]) : '';
            $producto    = isset($data[1]) ? trim($data[1]) : '';
            $descripcion = $producto; // descripción igual que producto
            $precio      = isset($data[2]) ? floatval(str_replace(",", ".", $data[2])) : 0;
            $proveedor   = isset($data[3]) ? trim($data[3]) : ''; // proveedor real

            if ($codigo === '' || $producto === '') continue; // evitar filas vacías

            $sql = "INSERT INTO proveedores (codigo, producto, descripcion, proveedor, precio)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        producto=VALUES(producto), 
                        descripcion=VALUES(descripcion), 
                        proveedor=VALUES(proveedor), 
                        precio=VALUES(precio)";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssd", $codigo, $producto, $descripcion, $proveedor, $precio);
                $stmt->execute();
                $stmt->close();
            }
        }
        fclose($handle);
        $mensaje = "CSV importado correctamente ✅";
    }
}

// ==========================
// Paginación
// ==========================
$limit = 10; // proveedores por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Contar total de proveedores
if ($busqueda) {
    $count_sql = "SELECT COUNT(*) as total FROM proveedores WHERE producto LIKE ?";
    $stmt = $conn->prepare($count_sql);
    $like = "%$busqueda%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $total_proveedores = $res['total'];
} else {
    $res = $conn->query("SELECT COUNT(*) as total FROM proveedores")->fetch_assoc();
    $total_proveedores = $res['total'];
}

$total_pages = ceil($total_proveedores / $limit);

// ==========================
// Obtener lista de proveedores
// ==========================
// Obtener lista de proveedores
// ==========================
if ($busqueda) {
    $sql = "SELECT id, codigo, producto, descripcion, proveedor, precio 
            FROM proveedores 
            WHERE producto LIKE ? OR codigo LIKE ? OR proveedor LIKE ? OR descripcion LIKE ?
            ORDER BY producto, precio ASC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssii", $like, $like, $like, $like, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT id, codigo, producto, descripcion, proveedor, precio 
            FROM proveedores 
            ORDER BY producto, precio ASC
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestión de Proveedores</title>
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f4f6f9; }
nav { display:flex; justify-content:space-between; align-items:center; background:#6B73FF; padding:10px 20px; color:white; box-shadow:0 2px 6px rgba(0,0,0,0.2);}
nav .logo { font-weight:bold; font-size:20px; }
nav ul { list-style:none; display:flex; gap:15px; }
nav ul li a { text-decoration:none; color:white; padding:8px 12px; border-radius:6px; }
nav ul li a:hover { background: rgba(255,255,255,0.2); }
.container { padding:30px; background:#fff; margin:20px auto; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1); max-width:1200px; }
h1 { text-align:center; margin-bottom:20px; }
.mensaje { background:#e0ffe0; color:#007700; padding:10px; margin-bottom:15px; border-radius:5px; }
form.buscar { margin-bottom:20px; text-align:center; }
form.buscar input { padding:8px; width:250px; border:1px solid #ccc; border-radius:5px; }
form.buscar button { padding:8px 15px; background:#6B73FF; border:none; color:#fff; border-radius:5px; cursor:pointer; }
form.buscar button:hover { background:#000DFF; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
table th, table td { border:1px solid #ddd; padding:10px; text-align:center; }
table th { background:#6B73FF; color:white; }
input[type="number"] { width:80px; padding:5px; }
button.actualizar { padding:5px 10px; background:#28a745; border:none; color:#fff; border-radius:5px; cursor:pointer; }
button.actualizar:hover { background:#218838; }
.subir-csv { margin-top:20px; text-align:center; }
.paginacion { text-align:center; margin-top:20px; }
.paginacion a { padding:5px 10px; margin:0 3px; background:#6B73FF; color:#fff; text-decoration:none; border-radius:5px; }
.paginacion a:hover { background:#000DFF; }
.paginacion .actual { background:#000DFF; }
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
    <h1>Gestión de Proveedores</h1>

    <?php if (!empty($mensaje)) echo "<div class='mensaje'>$mensaje</div>"; ?>

    <form method="GET" class="buscar">
        <input type="text" name="q" placeholder="Buscar producto..." value="<?= htmlspecialchars($busqueda); ?>">
        <button type="submit">Buscar</button>
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>Código</th>
            <th>Producto</th>
            <th>Descripcion</th>
            <th>Proveedor</th>
            <th>Precio</th>
        </tr>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['codigo']) ?></td>
                    <td><?= htmlspecialchars($row['producto']) ?></td>
                    <td><?= htmlspecialchars($row['descripcion']) ?></td>
                    <td><?= htmlspecialchars($row['proveedor']) ?></td>
                    <td>$<?= number_format($row['precio'],2) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No se encontraron proveedores.</td></tr>
        <?php endif; ?>
    </table>

    <!-- Paginación -->
    <div class="paginacion">
    <?php
    if($total_pages > 1){
        $max_links = 5;
        $start = max(1, $page - floor($max_links / 2));
        $end = min($total_pages, $start + $max_links - 1);

        if($page > 1){
            echo '<a href="?q='.urlencode($busqueda).'&page='.($page-1).'">&laquo; Anterior</a>';
        }

        for($i=$start; $i<=$end; $i++){
            echo '<a href="?q='.urlencode($busqueda).'&page='.$i.'" class="'.($i==$page?'actual':'').'">'.$i.'</a>';
        }

        if($end < $total_pages){
            echo '<a href="?q='.urlencode($busqueda).'&page='.($page+1).'">Siguiente &raquo;</a>';
        }
    }
    ?>
    </div>

    <div class="subir-csv">
        <h3>Importar proveedores desde CSV</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csv" accept=".csv" required>
            <button type="submit">Cargar CSV</button>
        </form>
        <p><small>Formato CSV: código;producto;descripcion, proveedor, precio</small></p>
    </div>
</div>
</body>
</html>
