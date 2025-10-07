<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$nombre = $_SESSION['usuario_nombre'];
$rol = $_SESSION['usuario_rol'];

// Menú para administradores
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Home - Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f4f6f9; }
        nav {
            display: flex; justify-content: space-between; align-items: center;
            background: #6B73FF; padding: 10px 20px; color: white; box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        nav .logo { font-weight: bold; font-size: 20px; }
        nav ul { list-style: none; display: flex; gap: 15px; }
        nav ul li a { text-decoration: none; color: white; padding: 8px 12px; border-radius: 6px; transition: background 0.3s; }
        nav ul li a:hover { background: rgba(255,255,255,0.2); }
        .content { padding: 30px; }
        h1 { margin-bottom: 20px; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
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

    <div class="content">
        <h1>Bienvenido, <?= htmlspecialchars($nombre) ?>!</h1>
        <div class="card">
            <h2>Panel Administrador</h2>
            <p>Desde aquí puedes modificar precios, manejar proveedores, ver presupuestos y cuentas corrientes.</p>
        </div>
    </div>
</body>
</html>
