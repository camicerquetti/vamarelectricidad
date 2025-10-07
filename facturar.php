<?php
require_once "config.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "ID inválido";
    exit;
}

$id = intval($_GET['id']);

// 1. Obtener datos de la venta (total y metodo_pago)
$sql_venta = "SELECT total, metodo_pago, estado FROM ventas WHERE id = $id";
$result = $conn->query($sql_venta);

if ($result->num_rows === 0) {
    echo "Venta no encontrada";
    exit;
}

$venta = $result->fetch_assoc();

if ($venta['estado'] === 'facturado') {
    echo "Venta ya está facturada";
    exit;
}

$total = $venta['total'];
$metodo_pago = $venta['metodo_pago'];

// 2. Actualizar estado a facturado
$sql_update = "UPDATE ventas SET estado = 'facturado' WHERE id = $id";
if ($conn->query($sql_update) !== TRUE) {
    echo "Error al actualizar estado";
    exit;
}

// 3. Verificar si ya existe fila para ese metodo_pago en cuentas
$sql_cuenta = "SELECT id, total FROM cuentas WHERE metodo_pago = '$metodo_pago'";
$result_cuenta = $conn->query($sql_cuenta);

if ($result_cuenta->num_rows > 0) {
    // 4. Sumar al total existente
    $cuenta = $result_cuenta->fetch_assoc();
    $nuevo_total = $cuenta['total'] + $total;

    $sql_update_cuenta = "UPDATE cuentas SET total = $nuevo_total WHERE id = " . $cuenta['id'];
    if ($conn->query($sql_update_cuenta) === TRUE) {
        echo "ok";
    } else {
        echo "Error al actualizar cuenta";
    }
} else {
    // 5. Insertar nueva fila si no existe
    $fecha = date('Y-m-d H:i:s');
    $sql_insert_cuenta = "INSERT INTO cuentas (fecha, total, metodo_pago) VALUES ('$fecha', $total, '$metodo_pago')";
    if ($conn->query($sql_insert_cuenta) === TRUE) {
        echo "ok";
    } else {
        echo "Error al insertar cuenta";
    }
}

?>
