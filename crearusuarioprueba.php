<?php
require 'config.php'; // tu config.php con $conn

// Datos del usuario de prueba
$nombre = "Usuario Prueba";
$email  = "empleadosvamar2@gmail.com";
$password = "Emplead@s02"; // contraseña
$rol_id  = 2;         // 1 = admin, 2 = empleado

// Encriptar la contraseña
$pass_hashed = password_hash($password, PASSWORD_DEFAULT);

// Preparar la consulta
$stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol_id) VALUES (?, ?, ?, ?)");
$stmt->bind_param("sssi", $nombre, $email, $pass_hashed, $rol_id);

// Ejecutar
if ($stmt->execute()) {
    echo "Usuario de prueba creado correctamente.";
} else {
    echo "Error al crear usuario: " . $stmt->error;
}

// Cerrar statement y conexión
$stmt->close();
$conn->close();
?>
