<?php
require_once "config.php";

if(!isset($_GET['compra_id'])) {
    echo json_encode([]);
    exit;
}

$compra_id = (int)$_GET['compra_id'];

$stmt = $conn->prepare("SELECT * FROM pagos_compras_negro WHERE compra_id=? ORDER BY fecha_pago ASC");
$stmt->bind_param("i", $compra_id);
$stmt->execute();
$result = $stmt->get_result();

$pagos = [];
while($row = $result->fetch_assoc()) {
    $pagos[] = $row;
}

header('Content-Type: application/json');
echo json_encode($pagos);
