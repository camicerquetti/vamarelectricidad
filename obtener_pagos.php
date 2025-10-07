<?php
require_once "config.php";

if(!isset($_GET['compra_id'])) exit;

$compra_id = (int)$_GET['compra_id'];

$stmt = $conn->prepare("SELECT id, fecha_pago, monto FROM pagos_compras WHERE compra_id=? ORDER BY fecha_pago DESC");
$stmt->bind_param("i", $compra_id);
$stmt->execute();
$result = $stmt->get_result();

$pagos = [];
while($row = $result->fetch_assoc()){
    $pagos[] = $row;
}

header('Content-Type: application/json');
echo json_encode($pagos);
?>
