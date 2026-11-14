<?php

require_once __DIR__ . "/autenticacion.php";
iniciarSesionSegura();
require_once __DIR__ . "/conexion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die("Método no permitido.");
}

$usuario = $_POST["usuario"] ?? "";
$password = $_POST["password"] ?? "";
if (!is_string($usuario) || !is_string($password) || trim($usuario) === "" || $password === "") {
    http_response_code(400);
    die("Todos los campos son obligatorios.");
}

$consulta = $conexion->prepare(
    "SELECT id, username, email, password_hash, foto_perfil FROM users WHERE username = ?"
);
$consulta->execute([trim($usuario)]);
$usuarioBD = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$usuarioBD || !password_verify($password, $usuarioBD["password_hash"])) {
    http_response_code(401);
    die("Usuario o contraseña incorrectos.");
}

autenticarUsuario($usuarioBD);
header("Location: ../Pagina/index.php");
exit;
