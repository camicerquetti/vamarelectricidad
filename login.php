<?php
session_start();
require_once "config.php"; // conexión mysqli ($conn)

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        $sql = "SELECT u.id, u.nombre, u.email, u.password, r.nombre AS rol
                FROM usuarios u
                INNER JOIN roles r ON u.rol_id = r.id
                WHERE u.email = ?
                LIMIT 1";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['usuario_nombre'] = $user['nombre'];
                    $_SESSION['usuario_rol'] = $user['rol'];

                    if ($user['rol'] === "admin") {
                        header("Location: homeadmin.php");
                    } else {
                        header("Location: home.php");
                    }
                    exit;
                } else {
                    $error = "Contraseña incorrecta.";
                }
            } else {
                $error = "No se encontró un usuario con ese email.";
            }

            $stmt->close();
        } else {
            $error = "Error en la consulta: " . $conn->error;
        }
    } else {
        $error = "Por favor, complete todos los campos.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Iniciar Sesión</title>
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;}
body {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: linear-gradient(135deg,#6B73FF 0%,#000DFF 100%);
}
.login-container {
    background: #fff;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    width: 100%;
    max-width: 400px;
    text-align: center;
}
h2 {
    margin-bottom: 30px;
    color: #333;
}
.error {
    background: #ffe0e0;
    color: #a70000;
    border-left: 5px solid #ff5c5c;
    padding: 10px;
    margin-bottom: 20px;
    border-radius: 5px;
    text-align: left;
}
form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    text-align: left;
}
form input {
    width: 100%;
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    border: 1px solid #ccc;
    transition: 0.3s;
}
form input:focus {
    border-color: #6B73FF;
    outline: none;
    box-shadow: 0 0 8px rgba(107,115,255,0.4);
}
button {
    width: 100%;
    padding: 12px;
    background: #6B73FF;
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 16px;
    cursor: pointer;
    transition: 0.3s;
}
button:hover {
    background: #000DFF;
}
@media(max-width:450px){
    .login-container { padding: 30px 20px; }
}
</style>
</head>
<body>
<div class="login-container">
    <h2>Iniciar Sesión</h2>
    <?php if(!empty($error)) echo "<div class='error'>$error</div>"; ?>
    <form method="POST" action="">
        <label>Email:</label>
        <input type="email" name="email" placeholder="usuario@correo.com" required>

        <label>Contraseña:</label>
        <input type="password" name="password" placeholder="********" required>

        <button type="submit">Ingresar</button>
    </form>
</div>
</body>
</html>
