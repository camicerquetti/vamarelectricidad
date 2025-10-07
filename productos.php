<?php
session_start();
require_once "config.php"; // conexión mysqli ($conn)

// Solo permitir admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== "admin") {
    header("Location: login.php");
    exit;
}

// Menú
$menu = [
    'Dashboard' => 'Dashboard.php',
    'Modificar Precios' => 'productos.php',
    'Manejar Proveedores' => 'proveedores.php',
    'Presupuestos' => 'presupuesto.php',
    'Cuentas Corrientes' => 'cuentas.php',
    'Ventas' => 'ventas.php',
    'Cerrar Sesión' => 'login.php'
];

$mensaje = "";

// ==========================
// Subida CSV optimizada
// ==========================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['csv'])) {
    $archivo = $_FILES['csv']['tmp_name'];

    if (!is_uploaded_file($archivo)) {
        $mensaje = "Error: no se leyó el archivo subido.";
    } else {
        if (($handle = fopen($archivo, "r")) !== FALSE) {
            $firstLine = fgets($handle);
            rewind($handle);

            // Detectar separador
            $delim = (substr_count($firstLine, "\t") > 0) ? "\t" : ",";
            $headers = fgetcsv($handle, 0, $delim);

            $filas = 0;
            $errores = [];

            // Insertar o actualizar según 'codigo' (asegúrate de que 'codigo' sea UNIQUE en la tabla)
            $sql = "INSERT INTO productos (codigo, nombre, proveedor, descripcion, precio, stock, cantidad, total)
                    VALUES (?, ?, ?, ?, ?, 0, 0, 0)
                    ON DUPLICATE KEY UPDATE
                    nombre=VALUES(nombre),
                    proveedor=VALUES(proveedor),
                    descripcion=VALUES(descripcion),
                    precio=VALUES(precio)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) die("Error prepare: " . $conn->error);

            while (($data = fgetcsv($handle, 0, $delim)) !== FALSE) {
                // Mapear columnas según tu CSV
                $nombre = isset($data[0]) ? trim($data[0]) : '';
                $proveedor = isset($data[1]) ? trim($data[1]) : '';
                $codigo = isset($data[2]) ? trim($data[2]) : '';
                $descripcion = isset($data[3]) ? trim($data[3]) : '';
                $precio = isset($data[4]) ? floatval(str_replace(',', '.', $data[4])) : 0;

                $stmt->bind_param("ssssd", $codigo, $nombre, $proveedor, $descripcion, $precio);
                if (!$stmt->execute()) $errores[] = $stmt->error;
                else $filas++;
            }

            fclose($handle);
            $stmt->close();

            if (empty($errores)) $mensaje = "CSV importado correctamente. Filas procesadas: $filas ✅";
            else $mensaje = "Importado con errores. Filas procesadas: $filas. Errores: " . implode(" | ", $errores);
        } else {
            $mensaje = "No se pudo abrir el archivo CSV.";
        }
    }
}

// ==========================
// Actualizar producto
// ==========================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $proveedor = trim($_POST['proveedor']); // <-- nuevo
    $precio = (float)$_POST['precio'];
    $stock = (int)$_POST['stock'];
    $cantidad = (int)$_POST['cantidad'];
    $total = $precio * $cantidad;

    $sql = "UPDATE productos 
            SET proveedor=?, precio=?, stock=?, cantidad=?, total=? 
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) $mensaje = "Error en prepare UPDATE: " . $conn->error;
    else {
        $stmt->bind_param("sdiiid", $proveedor, $precio, $stock, $cantidad, $total, $id);
        if ($stmt->execute()) $mensaje = "Producto actualizado correctamente ✅";
        else $mensaje = "Error al actualizar ❌: " . $stmt->error;
        $stmt->close();
    }
}


// ==========================
// Paginación y búsqueda
// ==========================
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$pagina = max($pagina, 1);
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;
$busqueda = !empty($_GET['q']) ? trim($_GET['q']) : "";

// Contar total productos
$total_sql = "SELECT COUNT(*) as total FROM productos";
$params = [];
$tipos = "";
if ($busqueda) {
    $total_sql .= " WHERE codigo LIKE ? OR nombre LIKE ?";
    $like = "%$busqueda%";
    $params = [$like, $like];
    $tipos = "ss";
}
$stmt_total = $conn->prepare($total_sql);
if (!$stmt_total) die("Error en SQL total: " . $conn->error);
if ($busqueda) $stmt_total->bind_param($tipos, ...$params);
$stmt_total->execute();
$total_productos = $stmt_total->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_productos / $por_pagina);
$stmt_total->close();

// Obtener productos
$sql = "SELECT * FROM productos";
if ($busqueda) $sql .= " WHERE codigo LIKE ? OR nombre LIKE ?";
$sql .= " ORDER BY id ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Error en SQL productos: " . $conn->error);
if ($busqueda) $stmt->bind_param("ssii", $like, $like, $por_pagina, $offset);
else $stmt->bind_param("ii", $por_pagina, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestión de Productos</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Verdana,sans-serif;}
body{background:#f4f6f9;}
nav{display:flex;justify-content:space-between;align-items:center;background:#6B73FF;padding:10px 20px;color:white;}
nav .logo{font-weight:bold;font-size:20px;}
nav ul{list-style:none;display:flex;gap:15px;}
nav ul li a{text-decoration:none;color:white;padding:8px 12px;border-radius:6px;}
nav ul li a:hover{background:rgba(255,255,255,0.2);}
.container{padding:30px;background:#fff;margin:20px auto;border-radius:10px;max-width:1300px;box-shadow:0 4px 15px rgba(0,0,0,0.1);}
h1{text-align:center;margin-bottom:20px;}
.mensaje{background:#e0ffe0;color:#007700;padding:10px;margin-bottom:15px;border-radius:5px;}
form.buscar{margin-bottom:20px;text-align:center;}
form.buscar input{padding:8px;width:250px;border:1px solid #ccc;border-radius:5px;}
form.buscar button{padding:8px 15px;background:#6B73FF;border:none;color:#fff;border-radius:5px;cursor:pointer;}
form.buscar button:hover{background:#000DFF;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
table th,table td{border:1px solid #ddd;padding:10px;text-align:center;}
table th{background:#6B73FF;color:white;}
input[type="number"], input[type="text"]{width:90px;padding:5px;border-radius:4px;border:1px solid #ccc;}
button.actualizar{padding:5px 10px;background:#28a745;border:none;color:#fff;border-radius:5px;cursor:pointer;}
button.actualizar:hover{background:#218838;}
.subir-csv{margin-top:20px;text-align:center;}
.paginacion{text-align:center;margin-top:20px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;}
.paginacion a{display:inline-block;padding:8px 12px;text-decoration:none;border-radius:5px;background:#f0f0f0;color:#333;transition:0.2s;}
.paginacion a:hover{background:#6B73FF;color:#fff;}
.paginacion a.active{background:#6B73FF;color:#fff;font-weight:bold;}
</style>
</head>
<body>

<nav>
<div class="logo">Sistema Vamar</div>
<ul>
<?php foreach($menu as $label=>$link): ?>
<li><a href="<?= $link ?>"><?= $label ?></a></li>
<?php endforeach; ?>
</ul>
</nav>

<div class="container">
<h1>Gestión de Productos</h1>

<?php if(!empty($mensaje)) echo "<div class='mensaje'>".htmlspecialchars($mensaje)."</div>"; ?>

<form method="GET" class="buscar">
<input type="text" name="q" placeholder="Buscar por código o nombre..." value="<?= htmlspecialchars($busqueda) ?>">
<button type="submit">Buscar</button>
</form>

<table>
<tr>
<th>ID</th><th>Código</th><th>Nombre</th><th>Proveedor</th><th>Descripción</th><th>Precio</th><th>Stock</th><th>Cantidad</th><th>Total</th><th>Acción</th>
</tr>
<?php if($result && $result->num_rows>0): ?>
<?php while($row=$result->fetch_assoc()): ?>
<tr>
<form method="POST">
<td><?= $row['id'] ?></td>
<td><?= htmlspecialchars($row['codigo']) ?></td>
<td><?= htmlspecialchars($row['nombre']) ?></td>
<td><input type="text" name="proveedor" value="<?= htmlspecialchars($row['proveedor']) ?>"></td>
<td><?= htmlspecialchars($row['descripcion']) ?></td>
<td><input type="number" step="0.01" name="precio" value="<?= $row['precio'] ?>"></td>
<td><input type="number" name="stock" value="<?= $row['stock'] ?>"></td>
<td><input type="number" name="cantidad" value="<?= $row['cantidad'] ?>"></td>
<td><?= number_format($row['total'],2) ?></td>
<td><input type="hidden" name="id" value="<?= $row['id'] ?>">
<button type="submit" name="update" class="actualizar">Actualizar</button></td>
</form>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="10">No se encontraron productos.</td></tr>
<?php endif; ?>
</table>

<!-- Paginación -->
<div class="paginacion">
<?php if($pagina>1): ?><a href="?pagina=<?= $pagina-1 ?>&q=<?= urlencode($busqueda) ?>">« Anterior</a><?php endif; ?>
<?php
$start=max(1,$pagina-3);
$end=min($total_paginas,$pagina+3);
if($start>1) echo '<a href="?pagina=1&q='.urlencode($busqueda).'">1</a><span>...</span>';
for($i=$start;$i<=$end;$i++): ?>
<a class="<?= $i==$pagina?'active':'' ?>" href="?pagina=<?= $i ?>&q=<?= urlencode($busqueda) ?>"><?= $i ?></a>
<?php endfor;
if($end<$total_paginas) echo '<span>...</span><a href="?pagina='.$total_paginas.'&q='.urlencode($busqueda).'">'.$total_paginas.'</a>';
?>
<?php if($pagina<$total_paginas): ?><a href="?pagina=<?= $pagina+1 ?>&q=<?= urlencode($busqueda) ?>">Siguiente »</a><?php endif; ?>
</div>

<!-- Subida CSV -->
<div class="subir-csv">
<h3>Importar productos desde CSV</h3>
<form method="POST" enctype="multipart/form-data">
<input type="file" name="csv" accept=".csv" required>
<button type="submit">Cargar CSV</button>
</form>
<p><small>Formato CSV esperado: <strong>Nombre, proveedor, codigo, Descripcion, Precio</strong></small></p>
</div>

</div>
</body>
</html>
