<?php

session_start();

require_once "conexion.php";

// Comprobar que el formulario se ha enviado mediante POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Método no permitido");
}

// Recoger los datos del formulario
$usuario = trim($_POST["usuario"] ?? "");
$password = $_POST["password"] ?? "";

// Comprobar que no haya campos vacíos
if ($usuario === "" || $password === "") {
    die("Todos los campos son obligatorios");
}

// Buscar el usuario en la base de datos
$consulta = $conexion->prepare(
    "SELECT id, usuario, email, password, foto_perfil
     FROM usuarios
     WHERE usuario = ?"
);

$consulta->execute([$usuario]);

$usuarioBD = $consulta->fetch(PDO::FETCH_ASSOC);

// Comprobar que el usuario existe
if (!$usuarioBD) {
    die("Usuario o contraseña incorrectos");
}

// Comprobar la contraseña
if (!password_verify($password, $usuarioBD["password"])) {
    die("Usuario o contraseña incorrectos");
}

// Regenerar el identificador de sesión
session_regenerate_id(true);

// Guardar los datos del usuario en la sesión
$_SESSION["usuario_id"] = $usuarioBD["id"];
$_SESSION["usuario"] = $usuarioBD["usuario"];
$_SESSION["email"] = $usuarioBD["email"];
$_SESSION["foto_perfil"] = $usuarioBD["foto_perfil"];
// Ir a la página de inicio
header("Location: ../Pagina/index.php");
exit;
